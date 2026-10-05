<?php
namespace App\Commands;

use App\Repositories\Connection;
use App\Services\MarketplaceSyncService;
use App\Services\Marketplace\MarketplaceTokenRefreshService;

final class MarketplaceSync
{
    public function run(): void
    {
        $db=new Connection();
        $db->query("SELECT id_owner,provider FROM marketplace_connectors WHERE status='ACTIVE' AND refresh_token_encrypted IS NOT NULL AND refresh_token_encrypted<>'' AND token_expires_at IS NOT NULL AND token_expires_at<=DATE_ADD(NOW(),INTERVAL 30 MINUTE) ORDER BY token_expires_at LIMIT 50");
        $expiring=$db->fetchAll();$refresher=new MarketplaceTokenRefreshService();
        foreach($expiring as$row){try{$refresher->refresh((int)$row->id_owner,(string)$row->provider);echo sprintf("[%s] owner=%d provider=%s token renewed\n",date('c'),$row->id_owner,$row->provider);}catch(\Throwable $e){$db->query("UPDATE marketplace_connectors SET last_error=:error,sync_status='FAILED',updated_at=NOW() WHERE id_owner=:owner AND provider=:provider");$db->bind(':error','Token renewal: '.$e->getMessage());$db->bind(':owner',(int)$row->id_owner);$db->bind(':provider',(string)$row->provider);$db->execute();}}
        $db->query("SELECT e.id,e.provider,e.external_shop_id,e.attempts,c.id_owner FROM marketplace_webhook_events e LEFT JOIN marketplace_connectors c ON c.provider=e.provider AND (c.external_shop_id=e.external_shop_id OR c.account_id=e.external_shop_id) WHERE e.status IN ('RECEIVED','RETRY') AND e.available_at<=NOW() ORDER BY e.id LIMIT 100");
        $events=$db->fetchAll();$eventService=new MarketplaceSyncService();
        foreach($events as$event){if(!(int)($event->id_owner??0)){$db->query("UPDATE marketplace_webhook_events SET status='IGNORED',last_error='No authorized shop matches this event',processed_at=NOW() WHERE id=:id");$db->bind(':id',(int)$event->id);$db->execute();continue;}$result=$eventService->sync((int)$event->id_owner,(string)$event->provider);$ok=(bool)($result['success']??false);$attempts=(int)($event->attempts??0)+1;$status=$ok?'PROCESSED':($attempts>=5?'FAILED':'RETRY');$db->query("UPDATE marketplace_webhook_events SET status=:status,attempts=attempts+1,processed_at=:processed,last_error=:error,available_at=DATE_ADD(NOW(),INTERVAL 15 MINUTE) WHERE id=:id");$db->bind(':status',$status);$db->bind(':processed',($ok||$status==='FAILED')?date('Y-m-d H:i:s'):null);$db->bind(':error',$ok?null:(string)($result['message']??'Synchronization failed'));$db->bind(':id',(int)$event->id);$db->execute();}
        $db->query("SELECT id_owner,provider FROM marketplace_connectors WHERE status='ACTIVE' AND (next_sync_at IS NULL OR next_sync_at<=NOW()) ORDER BY last_sync_at IS NULL DESC,last_sync_at ASC LIMIT 50");
        $rows=$db->fetchAll();$service=new MarketplaceSyncService();$failed=0;
        foreach($rows as$row){$result=$service->sync((int)$row->id_owner,(string)$row->provider);$ok=(bool)($result['success']??false);if(!$ok)$failed++;echo sprintf("[%s] owner=%d provider=%s %s\n",date('c'),$row->id_owner,$row->provider,$result['message']??($ok?'OK':'FAILED'));$db->query('UPDATE marketplace_connectors SET next_sync_at=DATE_ADD(NOW(),INTERVAL 1 HOUR) WHERE id_owner=:owner AND provider=:provider');$db->bind(':owner',(int)$row->id_owner);$db->bind(':provider',(string)$row->provider);$db->execute();}
        echo sprintf("Processed %d connector(s); %d failed.\n",count($rows),$failed);
        if($failed>0)exit(2);
    }
}
