<?php
namespace App\Services;
use App\Repositories\Connection;
class StoreCustomerService {
    private static array $created=[];
    public static function findOrCreateLevel5User(int $owner,string $name,string $email,?string $phone=null): ?object {
        $email=strtolower(trim($email));if($email==='')return null;$db=new Connection();$db->query("SELECT * FROM users WHERE LOWER(email)=:email LIMIT 1");$db->bind(':email',$email);$user=$db->fetchOne();
        if(!$user){$parts=preg_split('/\\s+/',trim($name),2);$password=bin2hex(random_bytes(5));$db->query("INSERT INTO users (name,lastname,email,password,level,phone,id_owner,is_active) VALUES (:name,:lastname,:email,:password,'5',:phone,:owner,1)");$db->bind(':name',(string)($parts[0]??'Customer'));$db->bind(':lastname',(string)($parts[1]??''));$db->bind(':email',$email);$db->bind(':password',password_hash($password,PASSWORD_DEFAULT));$db->bind(':phone',$phone);$db->bind(':owner',$owner);$db->execute();$id=(int)$db->lastId();$db->query("SELECT * FROM users WHERE id=:id");$db->bind(':id',$id);$user=$db->fetchOne();if($user){$user->temporary_password_plain=$password;self::$created[$id]=true;}}
        if($user){$db->query("INSERT IGNORE INTO clients_users (client_id,id_owner_asociated) VALUES (:client,:owner)");$db->bind(':client',(int)$user->id);$db->bind(':owner',$owner);$db->execute();}
        return $user?:null;
    }
    public static function wasJustCreated(object $user): bool { return !empty(self::$created[(int)($user->id??0)]); }
}
