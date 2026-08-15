<?php

use App\Utils\Router;
use App\Utils\TemplateResponse;
use App\Services\LoginService;
use App\Repositories\TicketSalesRepository;
use App\Repositories\VenueEventsRepository;
use App\Utils\LocationUtils;
use App\Utils\MessageUtil;

$router = new Router();

    $router->get(function () {
        $user = LoginService::getSession();
        
        if (!$user) {
            LocationUtils::redirectInternal("login");
        }

        $ticketSalesRepo = new TicketSalesRepository();
        $venueEventsRepo = new VenueEventsRepository();

        $userTickets = $ticketSalesRepo->getByBuyerEmail($user->getEmail());

        $enrichedTickets = [];
        foreach ($userTickets as $ticket) {
            $event = $venueEventsRepo->getOne(['id' => $ticket->venue_event_id]);
            $ticket->event_name = $event ? $event->name : 'Event not found';
            $ticket->event_date = $event ? $event->start_date : null;
            $ticket->event_location = $event ? ($event->location ?? $event->venue_location ?? null) : null;
            
            if ($ticket->ticket_codes) {
                $ticket->ticket_codes = json_decode($ticket->ticket_codes, true);
            }
            if ($ticket->qr_codes) {
                $ticket->qr_codes = json_decode($ticket->qr_codes, true);
            }
            
            $enrichedTickets[] = $ticket;
        }

        $ticketsByEvent = [];
        $activeTickets = [];
        $usedTickets = [];
        $activeCount = 0;
        $usedCount = 0;
        $checkinDb = new \App\Repositories\Connection();
        
        foreach ($enrichedTickets as $ticket) {
            $eventId = $ticket->venue_event_id;
            if (!isset($ticketsByEvent[$eventId])) {
                $ticketsByEvent[$eventId] = [
                    'event' => [
                        'id' => $eventId,
                        'name' => $ticket->event_name,
                        'date' => $ticket->event_date,
                        'location' => $ticket->event_location
                    ],
                    'tickets' => []
                ];
            }
            $ticketsByEvent[$eventId]['tickets'][] = $ticket;
            
            $checkinDb->query('SELECT ticket_code,checked_in_at FROM ticket_checkins WHERE id_ticket_sale=:sale');
            $checkinDb->bind(':sale',(int)$ticket->id);
            $checkedRows=$checkinDb->fetchAll();$checked=[];
            foreach($checkedRows as $row){$checked[(string)$row->ticket_code]=(string)$row->checked_in_at;}
            $activeCodes=[];$usedCodes=[];
            foreach((array)$ticket->ticket_codes as $code){if(isset($checked[(string)$code]))$usedCodes[]=$code;else $activeCodes[]=$code;}
            if($activeCodes){$active=clone $ticket;$active->ticket_codes=$activeCodes;$activeTickets[]=$active;$activeCount+=count($activeCodes);}
            if($usedCodes){$used=clone $ticket;$used->ticket_codes=$usedCodes;$used->updated_at=max(array_intersect_key($checked,array_flip($usedCodes)));$usedTickets[]=$used;$usedCount+=count($usedCodes);}
        }

        $templateData = [
            "user" => $user,
            "ticketsByEvent" => $ticketsByEvent,
            "activeTickets" => $activeTickets,
            "usedTickets" => $usedTickets,
            "totalTickets" => $activeCount + $usedCount,
            "activeCount" => $activeCount,
            "usedCount" => $usedCount
        ];
        
        return TemplateResponse::render(__DIR__ . "/index.twig", $templateData);
    });

$router->run();
