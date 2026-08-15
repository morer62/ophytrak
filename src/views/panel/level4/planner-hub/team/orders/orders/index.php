<?php

use App\Services\LoginService;
use App\Services\TranslationService;
use App\Utils\Router;
use App\Utils\TemplateResponse;
use App\Repositories\OrdersRepository;
use App\Repositories\UserRepository;
use App\Repositories\OrdersStaffInvitesRepository;
use App\Repositories\OrdersTeamOrderPhotosRepository;
use App\Repositories\InstitutionProfileRepository;
use App\Services\UserWorkspaceContextService;
use App\Utils\LocationUtils;
use App\Utils\MessageUtil;
use App\Utils\FileUtils;

$router = new Router();

// GET - Mostrar órdenes invitadas
$router->get(callback: function () {
    $user = LoginService::getSession();

    $repo = new OrdersRepository();
    $clientRepo = new UserRepository();
    $photosRepo = new OrdersTeamOrderPhotosRepository();
    $workspaceContextService = new UserWorkspaceContextService();

    $teamContext = $workspaceContextService->getTeamContext($user);
    $selectedOwnerId = (int)($teamContext["selectedOwnerId"] ?? 0);

    $orders = $selectedOwnerId > 0
        ? $repo->getOrdersByInvitationForOwner($user->getId(), $selectedOwnerId)
        : $repo->getOrdersByInvitation($user->getId());
    $clients = $clientRepo->getAllBy(["level" => 5]);

    // Añadir fotos a cada orden aceptada (solo para el usuario actual)
    foreach ($orders as $order) {
        $order->photos = ($order->is_confirmed == 1)
            ? $photosRepo->getByOrderAndUser((int) $order->id, (int) $user->getId())
            : [];
    }

    return TemplateResponse::render(__DIR__ . "/index.twig", [
        "orders" => $orders,
        "clients" => $clients,
        "teamContext" => $teamContext
    ]);
});

// POST - Confirmar, rechazar, subir fotos o eliminar fotos
$router->post(callback: function () {
    TranslationService::detectLocale();
    $user = LoginService::getSession();
    $inviteRepo = new OrdersStaffInvitesRepository();
    $photosRepo = new OrdersTeamOrderPhotosRepository();

    $formType = $_POST['form_type'] ?? null;

    // Subir fotos (solo si el usuario aceptó la orden)
    if ($formType === 'upload_photo') {
        $idOrder = (int) ($_POST['id_order'] ?? 0);
        $invite = $inviteRepo->getInvite($idOrder, (int) $user->getId());
        if ($invite && $invite->is_confirmed == 1 && isset($_FILES['photos']) && $_FILES['photos']['error'][0] != UPLOAD_ERR_NO_FILE) {
            $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
            $maxFileSize = 5 * 1024 * 1024; // 5MB
            $files = $_FILES['photos'];
            $fileCount = count($files['name']);

            for ($i = 0; $i < $fileCount; $i++) {
                if ($files['error'][$i] !== UPLOAD_ERR_OK) continue;
                if (!in_array($files['type'][$i], $allowedTypes)) continue;
                if ($files['size'][$i] > $maxFileSize) continue;

                $fileArray = [
                    'name' => $files['name'][$i],
                    'type' => $files['type'][$i],
                    'tmp_name' => $files['tmp_name'][$i],
                    'error' => $files['error'][$i],
                    'size' => $files['size'][$i]
                ];

                try {
                    $photoUrl = FileUtils::saveFile($fileArray, 'team-order-photos');
                    $photosRepo->add([
                        'id_order' => $idOrder,
                        'id_user' => $user->getId(),
                        'photo_url' => $photoUrl
                    ]);
                } catch (\Exception $e) {}
            }
        }
        header('Location: ' . $_SERVER['HTTP_REFERER']);
        exit;
    }

    // Eliminar foto
    if ($formType === 'delete_photo') {
        $photoId = (int) ($_POST['photo_id'] ?? 0);
        if ($photoId > 0) {
            $photo = $photosRepo->getOne(['id' => $photoId]);
            if ($photo && $photo->id_user == $user->getId()) {
                try {
                    FileUtils::removeFile($photo->photo_url);
                } catch (\Exception $e) {}
                $photosRepo->delete(['id' => $photoId]);
            }
        }
        header('Location: ' . $_SERVER['HTTP_REFERER']);
        exit;
    }

    $idOrder = $_POST['id_order'] ?? null;
    $isConfirmed = $_POST['is_confirmed'] ?? null;

    if ($idOrder !== null && $isConfirmed !== null) {
        $orderRepo = new OrdersRepository();
        $remainingSlots = $orderRepo->getRemainingTeamSlots($idOrder);

        if ((int)$isConfirmed === 1) {
            if ($remainingSlots == 0) {
                MessageUtil::setMessage("⚠️ " . TranslationService::trans('planner_hub.team_quota_reached'));
                LocationUtils::reload();
            }
        }

        $inviteRepo->confirmInvitation(
            id_order: (int)$idOrder,
            id_user: $user->getId(),
            is_confirmed: (int)$isConfirmed
        );
    }

    header("Location: " . $_SERVER['HTTP_REFERER']);
    exit;
});

$router->run();


 
