<?php

use App\Services\TicketSalesService;
use App\Services\TranslationService;
use App\Services\StripeServiceV2;
use App\Repositories\StripeAccountsRepository;
use App\Repositories\PaymentProvidersRepository;
use App\Services\Payment\PaymentProviderFactory;
use App\Utils\Response;
use App\Utils\LocationUtils;
use App\Utils\TemplateResponse;
use App\Utils\Router;

$router = new Router();

$router->post(function () {
    $eventId = $_POST['event_id'] ?? null;
    $tickets = json_decode($_POST['tickets'] ?? '{}', true);
    $buyerInfo = json_decode($_POST['buyer_info'] ?? '{}', true);
    $cardToken = $_POST['customer_token'] ?? null;
    $customerName = trim($buyerInfo['name'] ?? "");
    $customerEmail = strtolower(trim($buyerInfo['email'] ?? ""));
    
    if (!$eventId || empty($tickets) || !$cardToken || !$customerEmail) {
        return TemplateResponse::render(__DIR__ . "/error.twig", [
            "error" => "Missing payment data"
        ]);
    }

    $user = \App\Services\LoginService::getSession();
    if (!$user) {
        LocationUtils::redirectInternal("/login");
    }

    try {
        $venueEventsRepo = new \App\Repositories\VenueEventsRepository();
        $event = $venueEventsRepo->getOne(['id' => $eventId]);
        
        if (!$event) {
            return TemplateResponse::render(__DIR__ . "/error.twig", [
                "error" => "Event not found"
            ]);
        }

        $venueRepo = new \App\Repositories\VenueRepository();
        $venue = $venueRepo->getFullVenueDetails($event->venue_id);
        
        if (!$venue) {
            return TemplateResponse::render(__DIR__ . "/error.twig", [
                "error" => "Venue not found"
            ]);
        }

        $providerCredentials = (new PaymentProvidersRepository())->getActiveProviderForOwner((int)$venue->user_id);
        if (!$providerCredentials) {
            return TemplateResponse::render(__DIR__ . "/error.twig", [
                "error" => TranslationService::trans("tickets_rsvp_setup.owner_payment_not_ready")
            ]);
        }

        // Validar disponibilidad de tickets ANTES del pago
        $ticketInventoryRepo = new \App\Repositories\TicketInventoryRepository();
        $ticketTypesRepo = new \App\Repositories\TicketTypesRepository();
        
        foreach ($tickets as $ticketTypeId => $quantity) {
            if ($quantity > 0) {
                $available = $ticketInventoryRepo->getAvailableQuantity($ticketTypeId);
                if ($available < $quantity) {
                    $ticketType = $ticketTypesRepo->getOne(['id' => $ticketTypeId]);
                    $ticketName = $ticketType ? $ticketType->name : 'this ticket type';
                    
                    return TemplateResponse::render(__DIR__ . "/error.twig", [
                        "error" => "Not enough tickets available for '{$ticketName}'. Only {$available} tickets left, but you're trying to buy {$quantity}."
                    ]);
                }
            }
        }

        $totalAmount = 0;
        foreach ($tickets as $ticketTypeId => $quantity) {
            if ($quantity > 0) {
                $ticketTypeRepo = new \App\Repositories\TicketTypesRepository();
                $ticketType = $ticketTypeRepo->getOne(['id' => $ticketTypeId]);
                if ($ticketType) {
                    $totalAmount += $ticketType->price * $quantity;
                }
            }
        }

        $provider = PaymentProviderFactory::create($providerCredentials);
        $charge = $provider->chargeCustomer((string)$cardToken, (float)$totalAmount, [
            'description' => 'Tickets for ' . (string)$event->name,
            'customer_email' => $customerEmail,
            'event_id' => (string)$eventId,
            'buyer_name' => $customerName,
        ]);

        if (!$charge) {
            return TemplateResponse::render(__DIR__ . "/error.twig", [
                "error" => "Failed to create charge"
            ]);
        }

        $_SESSION['current_event_id'] = $eventId;
        $_SESSION['selected_tickets'] = $tickets;
        
        $ticketSalesService = new TicketSalesService();
        $result = $ticketSalesService->confirmTicketPurchase((string)$charge->id, $buyerInfo);

        if ($result['success']) {
            $_SESSION['ticket_codes'] = $result['data']['ticket_codes'] ?? [];
            $_SESSION['ticket_total'] = $totalAmount;
            $_SESSION['event_name'] = $event->name ?? 'Event';
            $_SESSION['venue_name'] = $venue->name ?? 'Unknown Venue';
            $_SESSION['current_stage'] = $result['data']['current_stage'] ?? null;
            
            LocationUtils::redirectInternal("tickets/success");
        } else {
            return TemplateResponse::render(__DIR__ . "/error.twig", [
                "error" => $result['message']
            ]);
        }

    } catch (Exception $e) {
        return TemplateResponse::render(__DIR__ . "/error.twig", [
            "error" => "An error occurred while processing your purchase"
        ]);
    }
});

$router->run();
