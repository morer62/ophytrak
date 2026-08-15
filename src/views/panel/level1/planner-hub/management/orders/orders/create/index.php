<?php

use App\Services\LoginService;
use App\Repositories\OrdersServiceRepository;
use App\Repositories\OrdersContractRepository;
use App\Repositories\UserRepository;
use App\Repositories\OrdersRepository;
use App\Repositories\OrdersServicesAssignedRepository;
use App\Repositories\OrdersSuborderRepository;
use App\Repositories\OrderSuborderServicesAssignedRepository;
use App\Utils\LocationUtils;
use App\Utils\TemplateResponse;
use App\Utils\MessageUtil;
use App\Utils\Router;
use App\Repositories\OrdersStatusHistoryRepository;
use App\Services\NotificationService;
use App\Services\EmailService;
use App\Services\TranslationService;
use App\Repositories\NotificationsRepository;
use App\Repositories\TipsRepository;

$router = new Router();

$router->get(function () {
    $serviceRepo = new OrdersServiceRepository();
    $contractRepo = new OrdersContractRepository();
    $userRepo = new UserRepository();
    $tipsRepo = new TipsRepository();
    $user = LoginService::getSession();
    $ownerId = in_array($user->getLevel(), [1, 2, 3], true) ? (int)$user->getId() : $user->getOwner();

    if ($user->getLevel() === 4) {
        $currentInstitutionId = $_SESSION['current_institution_id'] ?? null;
        if ($currentInstitutionId) {
            $institutionRepo = new \App\Repositories\InstitutionProfileRepository();
            $institution = $institutionRepo->getById($currentInstitutionId);
            if ($institution && $institution->id_owner) {
                $services = $serviceRepo->getAllByInstitutionOwner($institution->id_owner, 0);
                $contracts = $contractRepo->getAllByInstitutionOwner($institution->id_owner);
                // Para nivel 4: obtener todos los clientes del id_owner de la institución, no solo los asociados
                $clients = $userRepo->getAllBy([
                    "level" => 5,
                    "id_owner" => $institution->id_owner,
                ]);
            } else {
                $services = [];
                $contracts = [];
                $clients = [];
            }
        } else {
            $services = [];
            $contracts = [];
            $clients = [];
        }
    } else {
        $services = $serviceRepo->getAllBy([
            "id_owner" => $ownerId,
            "is_archived" => 0,
        ]);

        $contracts = $contractRepo->getAllBy([
            "id_owner" => $ownerId,
        ]);

        $clients = $userRepo->getAllBy([
            "level" => 5,
            "id_owner" => $ownerId,
        ]);
    }

    $tips = $tipsRepo->getActiveTips();

    $parentOrderId = $_GET["parent_order"] ?? null;
    $parentOrder = null;
    $isSubOrder = false;

    if ($parentOrderId) {
        $orderRepo = new OrdersRepository();
        
        if ($user->getLevel() === 4) {
            $currentInstitutionId = $_SESSION['current_institution_id'] ?? null;
            if ($currentInstitutionId) {
                $institutionRepo = new \App\Repositories\InstitutionProfileRepository();
                $institution = $institutionRepo->getById($currentInstitutionId);
                if ($institution && $institution->id_owner) {
                    $parentOrder = $orderRepo->getOneByIdAndOwner($parentOrderId, $institution->id_owner);
                    if ($parentOrder) {
                        $isSubOrder = true;
                    }
                }
            }
        } else {
            $parentOrder = $orderRepo->getOne(["id" => $parentOrderId]);
            if ($parentOrder && $parentOrder->id_owner == $ownerId) {
                $isSubOrder = true;
            }
        }
    }

    $prefillEmail = $_GET['client_email'] ?? null;
    $prefillClientId = (int)($_GET['client_id'] ?? 0);
    $prefillClient = null;
    if ($prefillClientId > 0) {
        foreach ($clients as $c) {
            if ((int)($c->id ?? 0) === $prefillClientId) {
                $prefillClient = $c;
                break;
            }
        }
    }

    return TemplateResponse::render(__DIR__ . "/index.twig", [
        "services" => $services,
        "contracts" => $contracts,
        "clients" => $clients,
        "statuses" => OrdersRepository::PAYMENT_STATUSES,
        'base_url' => $_ENV["APP_URL"],
        "parentOrder" => $parentOrder,
        "isSubOrder" => $isSubOrder,
        "prefillEmail" => $prefillEmail,
        "prefillClient" => $prefillClient,
        "tips" => $tips
    ]);
});

$router->post(function () {
    $user = LoginService::getSession();
    $ownerId = in_array($user->getLevel(), [1, 2, 3], true) ? (int)$user->getId() : $user->getOwner();
    $orderRepo = new OrdersRepository();
    $assignedRepo = new OrdersServicesAssignedRepository();
    $suborderRepo = new OrdersSuborderRepository();
    $suborderServicesRepo = new OrderSuborderServicesAssignedRepository();

    $parentOrderId = $_POST["parent_order_id"] ?? null;
    $isSubOrder = !empty($parentOrderId);

    if ($isSubOrder) {
        if ($user->getLevel() === 4) {
            $currentInstitutionId = $_SESSION['current_institution_id'] ?? null;
            if ($currentInstitutionId) {
                $institutionRepo = new \App\Repositories\InstitutionProfileRepository();
                $institution = $institutionRepo->getById($currentInstitutionId);
                if ($institution && $institution->id_owner) {
                    $parentOrder = $orderRepo->getOneByIdAndOwner($parentOrderId, $institution->id_owner);
                } else {
                    $parentOrder = null;
                }
            } else {
                $parentOrder = null;
            }
        } else {
            $parentOrder = $orderRepo->getOne(["id" => $parentOrderId]);
            if ($parentOrder && $parentOrder->id_owner != $ownerId) {
                $parentOrder = null;
            }
        }
        
        if (!$parentOrder) {
            MessageUtil::setMessage("Parent order not found or access denied.");
            LocationUtils::reload();
        }

        $client = $parentOrder->id_client;
        $date = $parentOrder->event_date;
        $address = $parentOrder->address;
        $hourStart = $parentOrder->start_time;
        $hourEnd = $parentOrder->end_time;
        $id_contract = $parentOrder->id_contract;
        $totalTeamNeeded = $parentOrder->total_team_needed;
    } else {
        $client = $_POST["id_client"] ?? null;
        $date = $_POST["event_date"] ?? null;
        $today = date("Y-m-d");
        if ($date < $today) {
            MessageUtil::setMessage("❌ Event date cannot be in the past.");
            LocationUtils::reload();
        }

        $address = $_POST["address"] ?? "";
        $hourStart = $_POST["hour_start"] ?? "";
        $hourEnd = $_POST["hour_end"] ?? "";
        $id_contract = $_POST["id_contract"] ?? null;
        $totalTeamNeeded = isset($_POST["total_team_needed"]) ? (int) $_POST["total_team_needed"] : 0;
    }

    $discount_type = $_POST["discount_type"] ?? "amount";
    $discount_value = $_POST["discount_value"] ?? 0;
    $tax_percentage = $_POST["tax_percentage"] ?? 0;
    $id_tip = !empty($_POST["id_tip"]) ? $_POST["id_tip"] : null;
    $notes = $_POST["notes"] ?? "";
    $services = $_POST["selectedServices"] ?? "";
    $userLocalTimestamp = $_POST["user_local_timestamp"] ?? null;

    $services = json_decode($services, true);

    if (!$client || !$date || !$address || !$hourStart || !$hourEnd || !$id_contract || empty($services)) {
        MessageUtil::setMessage("Missing required fields.");
        LocationUtils::reload();
    }

    $subtotal = 0;
    foreach ($services as $service) {
        $subtotal += (float)$service['subtotal'];
    }

    $actual_discount_value = $discount_value;
    if ($discount_type === 'percent') {
        $actual_discount_value = $subtotal * ($discount_value / 100);
    }
    
    $db_discount_type = ($discount_type === 'percent') ? 'percentage' : 'amount';

    $orderId = null;
    $suborderId = null;
    $message = "";
    $notificationMessage = "";
    $creationSuccess = false;

    if ($isSubOrder) {
        $suborderData = [
            'tax_percentage' => $tax_percentage,
            'payment_split_type' => $_POST["payment_split_type"] ?? 2,
            'payment_split_percent_1' => $_POST["payment_split_percent_1"] ?? 50,
            'payment_split_percent_2' => $_POST["payment_split_percent_2"] ?? 50,
            'discount_type' => $db_discount_type,
            'discount_value' => $actual_discount_value
        ];

        try {
            $suborderId = $suborderRepo->createSuborder($parentOrderId, $suborderData);
            
            $suborderOwnerId = in_array($user->getLevel(), [1, 2, 3], true) ? (int)$user->getId() : $user->getOwner();
            
            if ($user->getLevel() === 4) {
                $currentInstitutionId = $_SESSION['current_institution_id'] ?? null;
                if ($currentInstitutionId) {
                    $institutionRepo = new \App\Repositories\InstitutionProfileRepository();
                    $institution = $institutionRepo->getById($currentInstitutionId);
                    if ($institution && $institution->id_owner) {
                        $suborderOwnerId = $institution->id_owner;
                    }
                }
            }
            
            foreach ($services as $item) {
                // Obtener el precio y descripción del servicio para guardarlo como histórico
                $serviceRepo = new OrdersServiceRepository();
                $service = $serviceRepo->getByIdWithoutOwnershipCheck($item["service"]);
                $unitPrice = ($item["is_variable"] ?? "NO") === "YES" && isset($item["variable_price"]) 
                    ? $item["variable_price"] 
                    : ($service ? $service->price : 0);
                $description = $service ? ($service->description ?? null) : null;
                
                $suborderServicesRepo->add([
                    "id_suborder" => $suborderId,
                    "id_service" => $item["service"],
                    "quantity" => $item["qty"],
                    "unit_price" => $unitPrice,
                    "description" => $description,
                    "subtotal" => $item["subtotal"],
                    "id_owner" => $suborderOwnerId,
                    "is_variable" => $item["is_variable"] ?? "NO",
                    "variable_price" => $item["variable_price"] ?? null
                ]);
            }
            
            $orderId = $parentOrderId;
            $message = "Sub-order created successfully for order VNV 341{$parentOrderId}!";
            $notificationMessage = "Sub-order has been created for order VNV 341{$parentOrderId}. Please log in to your account to view the details.";
            $creationSuccess = true;
            
        } catch (\Throwable $e) {
            MessageUtil::setMessage("Error creating suborder: " . $e->getMessage());
            LocationUtils::reload();
        }
    } else {
        $ownerData = LoginService::getOwnerAsArray();
        $userIdData = LoginService::getUserIdAsArray(true);
        
        // Nivel 1, 2 y 3: la orden usa su propio proveedor de pago (id_owner = usuario actual)
        if (in_array($user->getLevel(), [1, 2, 3], true)) {
            $ownerData = ['id_owner' => (int) $user->getId()];
        }
        
        if ($user->getLevel() === 4) {
            $currentInstitutionId = $_SESSION['current_institution_id'] ?? null;
            
            if ($currentInstitutionId) {
                $institutionRepo = new \App\Repositories\InstitutionProfileRepository();
                $institution = $institutionRepo->getById($currentInstitutionId);
                
                if ($institution && $institution->id_owner) {
                    $ownerData = ['id_owner' => $institution->id_owner];
                    $userIdData = ['id_user' => $institution->id_owner];
                }
            }
        }
        
        $orderData = [
            ...$ownerData,
            ...$userIdData,
            "id_client" => $client,
            "event_date" => $date,
            "address" => $address,
            "start_time" => $hourStart,
            "end_time" => $hourEnd,
            "id_contract" => $id_contract,
            "payment_status" => "paid_full",
            "discount_type" => $discount_type,
            "discount_value" => $actual_discount_value,
            "tax_percentage" => $tax_percentage,
            "id_tip" => $id_tip,
            "notes" => $notes,
            "payment_split_type" => $_POST["payment_split_type"] ?? 2,
            "payment_split_percent_1" => $_POST["payment_split_percent_1"] ?? 50,
            "payment_split_percent_2" => $_POST["payment_split_percent_2"] ?? 50,
            "total_team_needed" => $totalTeamNeeded
        ];

        if ($userLocalTimestamp) {
            $orderData["created_at"] = $userLocalTimestamp;
        }

        try {
            if ($user->getLevel() === 4 && isset($ownerData['id_owner']) && $ownerData['id_owner'] != $user->getOwner()) {
                $orderId = $orderRepo->addWithExplicitOwner($orderData);
            } else {
                $orderRepo->add($orderData);
                $orderId = $orderRepo->getLastId();
            }

            $serviceOwnerId = $ownerData['id_owner'] ?? $user->getOwner();
            
            foreach ($services as $item) {
                // Obtener el precio y descripción del servicio para guardarlo como histórico
                $serviceRepo = new OrdersServiceRepository();
                $service = $serviceRepo->getByIdWithoutOwnershipCheck($item["service"]);
                $unitPrice = ($item["is_variable"] ?? "NO") === "YES" && isset($item["variable_price"]) 
                    ? $item["variable_price"] 
                    : ($service ? $service->price : 0);
                $description = $service ? ($service->description ?? null) : null;
                
                $assignedRepo->add([
                    "id_order" => $orderId,
                    "id_service" => $item["service"],
                    "quantity" => $item["qty"],
                    "unit_price" => $unitPrice,
                    "description" => $description,
                    "subtotal" => $item["subtotal"],
                    "id_owner" => $serviceOwnerId,
                    "is_variable" => $item["is_variable"] ?? "NO",
                    "variable_price" => $item["variable_price"] ?? null
                ]);
            }

            $statusWorkflow = "INVOICE_DRAFT"; 

            $historyRepo = new OrdersStatusHistoryRepository();

            $historyRepo->add([
                "id_order" => $orderId,
                "status" => $statusWorkflow,
                "action_type" => "manual_change",
                "note" => "Invoice approval requires client signature.",
                "created_by" => $user->getId()
            ]);

            $orderRepo->update([
                "status_workflow" => $statusWorkflow
            ], ["id" => $orderId]);

            $message = "Order created successfully!";
            $notificationMessage = "New order VNV 341{$orderId} has been created. Please log in to your account to view the details.";
            $creationSuccess = true;

            $shouldAddCalendar = isset($_POST['add_to_calendar']) && $_POST['add_to_calendar'] == '1';
            if ($shouldAddCalendar) {
                MessageUtil::setMessage($message);
                $clientId = $client;
                LocationUtils::redirectInternal("panel/pland-hub/management/orders/orders/?add_calendar=1&order_id=" . urlencode((string)$orderId) . "&client_id=" . urlencode((string)$clientId));
            }
            
        } catch (\Throwable $e) {
            MessageUtil::setMessage("Error creating order: " . $e->getMessage());
            LocationUtils::reload();
        }
    }

    if ($creationSuccess && $orderId) {
        $secret = $_ENV["VNV_SECRET_KEY"] ?? "mySuperSecretKey";

        // Para órdenes principales: token de /order-access
        if (!$isSubOrder) {
            $payload = [
                "order_id" => (int)$orderId,
                "user_id" => (int)$client,
                "exp" => time() + 60 * 60 * 24 * 30,
            ];
            $payload["hash"] = hash_hmac("sha256", json_encode([
                "order_id" => $payload["order_id"],
                "user_id" => $payload["user_id"],
                "exp" => $payload["exp"]
            ]), $secret);
            $orderToken = base64_encode(json_encode($payload));
            
            $clientOrderUrl = \App\Utils\LocationUtils::pathFor("/order-access?token=" . urlencode($orderToken));
            // Para el owner también apuntamos al detalle público de la orden
            $ownerOrderUrl = $clientOrderUrl;
        } else {
            // Para subórdenes: token de /order-access/suborder
            $subPayload = [
                "suborder_id" => (int)$suborderId,
                "user_id" => (int)$client,
                "exp" => time() + 60 * 60 * 24 * 30,
            ];
            $subPayload["hash"] = hash_hmac("sha256", json_encode([
                "suborder_id" => $subPayload["suborder_id"],
                "user_id" => $subPayload["user_id"],
                "exp" => $subPayload["exp"]
            ]), $secret);
            $suborderToken = base64_encode(json_encode($subPayload));

            $clientOrderUrl = \App\Utils\LocationUtils::pathFor("/order-access/suborder?token=" . urlencode($suborderToken));
            $ownerOrderUrl = $clientOrderUrl;
        }

        $notificationsRepo = new NotificationsRepository();
        
        $clientNotificationResult = $notificationsRepo->add([
            "id_user" => $client,
            "mensaje" => $notificationMessage,
            "link" => $clientOrderUrl,
            "leido" => 0
        ]);
        
        $ownerNotificationResult = $notificationsRepo->add([
            "id_user" => $user->getOwner(),
            "mensaje" => $notificationMessage,
            "link" => $ownerOrderUrl,
            "leido" => 0
        ]);
    }

    if ($creationSuccess && $orderId) {
        try {
            // Obtener la orden creada para usar su id_owner para las credenciales SMTP
            $orderRepo = new OrdersRepository();
            $createdOrder = $orderRepo->getByIdWithoutOwnershipCheck($orderId);
            $orderOwnerId = $createdOrder && isset($createdOrder['id_owner']) ? (int)$createdOrder['id_owner'] : null;
            
            // Fallback si no se puede obtener de la orden
            if (!$orderOwnerId) {
                $orderOwnerId = $serviceOwnerId ?? $user->getOwner();
                // Para nivel 4: asegurar que se use el id_owner de la institución activa
                if ($user->getLevel() === 4) {
                    $currentInstitutionId = $_SESSION['current_institution_id'] ?? null;
                    if ($currentInstitutionId) {
                        $institutionRepo = new \App\Repositories\InstitutionProfileRepository();
                        $institution = $institutionRepo->getById($currentInstitutionId);
                        if ($institution && $institution->id_owner) {
                            $orderOwnerId = $institution->id_owner;
                        }
                    }
                }
            }
            
            // Usar el id_owner de la orden para las credenciales SMTP del owner (panel/planner-hub/settings/smtp)
            // New-order notices are Ophyra platform transactional email.
            $emailService = new EmailService(null);
            
            $userRepo = new UserRepository();
            // Usar getOneWithoutOwnership para evitar filtros de ownership
            $clientInfo = $userRepo->getOneWithoutOwnership(["id" => $client]);
            
            // Obtener el idioma del sistema del owner para el correo
            $owner = $userRepo->getOneWithoutOwnership(["id" => $orderOwnerId]);
            // Acceder directamente a la propiedad system_language del objeto de BD
            $systemLanguage = ($owner && isset($owner->system_language) && !empty($owner->system_language)) ? $owner->system_language : 'en';
            
            // Establecer el locale para el correo según el system_language del owner
            TranslationService::setLocale($systemLanguage);
            
            if ($clientInfo && $clientInfo->email) {
                if ($isSubOrder) {
                    $subject = "📝 " . TranslationService::trans('planner_hub.email_new_suborder_created', ['suborder_id' => $suborderId, 'order_id' => 'VNV341' . $parentOrderId]);
                } else {
                    $subject = "📝 " . TranslationService::trans('planner_hub.email_new_order_created', ['order_id' => 'VNV341' . $orderId]);
                }
                
                if ($isSubOrder) {
                $assignedServices = $suborderServicesRepo->getServicesWithDetails($suborderId);
                $servicesForEmail = [];
                
                foreach ($assignedServices as $assigned) {
                    $unitPrice = ($assigned->is_variable === 'YES' && $assigned->variable_price !== null) 
                        ? $assigned->variable_price 
                        : $assigned->service_price;
                    
                    $servicesForEmail[] = [
                        'name' => $assigned->service_name,
                        'quantity' => $assigned->quantity,
                        'unit_price' => $unitPrice,
                        'subtotal' => $assigned->quantity * $unitPrice
                    ];
                }
            } else {
                $assignedServices = $assignedRepo->getAllBy(["id_order" => $orderId]);
                $servicesForEmail = [];
                $serviceRepo = new OrdersServiceRepository();
                
                foreach ($assignedServices as $assigned) {
                    $service = $serviceRepo->getOne(["id" => $assigned->id_service]);
                    if ($service) {
                        // Usar el precio histórico almacenado (unit_price) si existe
                        if (isset($assigned->unit_price) && $assigned->unit_price > 0) {
                            $unitPrice = $assigned->unit_price;
                        } else {
                            // Fallback para órdenes antiguas
                            $unitPrice = ($assigned->is_variable === 'YES' && $assigned->variable_price !== null) 
                                ? $assigned->variable_price 
                                : $service->price;
                        }
                        
                        $servicesForEmail[] = [
                            'name' => $service->name,
                            'quantity' => $assigned->quantity,
                            'unit_price' => $unitPrice,
                            'subtotal' => $assigned->quantity * $unitPrice
                        ];
                    }
                }
            }
            
            $subtotal = 0;
            foreach ($servicesForEmail as $service) {
                $subtotal += $service['subtotal'];
            }
            
            if ($isSubOrder) {
                $discountType = $db_discount_type;
                $discountValue = $actual_discount_value;
                $actualDiscount = ($discountType === 'percentage') ? $subtotal * ($discountValue / 100) : $discountValue;
                
                $base = max($subtotal - $actualDiscount, 0);
                $taxRate = $tax_percentage;
                $tax = $base * ($taxRate / 100);
                $totalAmount = $base + $tax;
            } else {
                $discountType = $db_discount_type;
                $discountValue = $actual_discount_value;
                $actualDiscount = ($discountType === 'percentage') ? $subtotal * ($discountValue / 100) : $discountValue;
                
                $base = max($subtotal - $actualDiscount, 0);
                $taxRate = $tax_percentage;
                $tax = $base * ($taxRate / 100);
                $totalAmount = $base + $tax;
            }
            
            $secret = $_ENV["VNV_SECRET_KEY"] ?? "mySuperSecretKey";
            if ($isSubOrder) {
                $payload = [
                    "order_id" => $parentOrderId,
                    "user_id" => $client,
                    "exp" => time() + 60 * 60 * 24 * 30,
                ];
            } else {
                $payload = [
                    "order_id" => $orderId,
                    "user_id" => $client,
                    "exp" => time() + 60 * 60 * 24 * 30,
                ];
            }
            $payload["hash"] = hash_hmac("sha256", json_encode([
                "order_id" => $payload["order_id"],
                "user_id" => $payload["user_id"],
                "exp" => $payload["exp"]
            ]), $secret);
            $orderToken = base64_encode(json_encode($payload));
            
            if ($isSubOrder) {
                $templateData = [
                    'orderId' => $parentOrderId,
                    'subOrderId' => $suborderId,
                    'eventDate' => date("F j, Y", strtotime($date)),
                    'eventTime' => date("g:i A", strtotime($hourStart)) . ' ' . TranslationService::trans('planner_hub.to') . ' ' . date("g:i A", strtotime($hourEnd)),
                    'location' => $address,
                    'totalAmount' => $totalAmount,
                    'services' => $servicesForEmail,
                    'orderUrl' => $clientOrderUrl,
                    'isSubOrder' => true,
                    'locale' => $systemLanguage
                ];
            } else {
                $templateData = [
                    'orderId' => $orderId,
                    'eventDate' => date("F j, Y", strtotime($date)),
                    'eventTime' => date("g:i A", strtotime($hourStart)) . ' ' . TranslationService::trans('planner_hub.to') . ' ' . date("g:i A", strtotime($hourEnd)),
                    'location' => $address,
                    'totalAmount' => $totalAmount,
                    'services' => $servicesForEmail,
                    'orderUrl' => $clientOrderUrl,
                    'isSubOrder' => false,
                    'locale' => $systemLanguage
                ];
            }
            
            $templatePath = \App\Utils\LocationUtils::getTemplatePath("emails/new_order.php");
            
            $emailService->sendTemplateEmail(
                $clientInfo->email,
                $subject,
                $templatePath,
                $templateData
            );
            }
        } catch (Exception $e) {
        }
    }

    if ($creationSuccess) {
        MessageUtil::setMessage($message);
        
        if ($isSubOrder) {
            LocationUtils::redirectInternal("panel/planner-hub/management/orders/orders/suborders/?id={$parentOrderId}");
        } elseif (isset($statusWorkflow) && $statusWorkflow === "INVOICE_DRAFT") {
            LocationUtils::redirectInternal("panel/planner-hub/management/orders/orders/?tab=estimates");
        } else {
            LocationUtils::redirectInternal("panel/planner-hub/management/orders/orders/");
        }
    }
});

$router->run();
