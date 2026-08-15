<?php

namespace App\Services;

use App\Repositories\StoreOrderTasksRepository;
use App\Repositories\StoreOrderWorkflowRepository;
use App\Repositories\StoreOrdersRepository;

class StoreLogisticsWorkflowService
{
    private StoreOrdersRepository $ordersRepo;
    private StoreOrderTasksRepository $tasksRepo;
    private StoreOrderWorkflowRepository $workflowRepo;

    public function __construct()
    {
        $this->ordersRepo = new StoreOrdersRepository();
        $this->tasksRepo = new StoreOrderTasksRepository();
        $this->workflowRepo = new StoreOrderWorkflowRepository();
    }

    public function assignOperations(
        int $ownerId,
        int $orderId,
        ?int $preparationUserId,
        ?int $deliveryUserId,
        bool $allowClose,
        bool $allowChat,
        ?int $assignedBy = null
    ): bool {
        $ok = $this->workflowRepo->upsertOperations(
            $ownerId,
            $orderId,
            $preparationUserId,
            $deliveryUserId,
            $allowClose,
            $allowChat
        );

        if (!$ok) {
            return false;
        }

        $prepOk = $this->tasksRepo->replaceAssignmentByType(
            $ownerId,
            $orderId,
            $preparationUserId,
            StoreOrderTasksRepository::TYPE_PREPARATION,
            'Prepare and verify Store order',
            false,
            $assignedBy
        );

        $deliveryOk = $this->tasksRepo->replaceAssignmentByType(
            $ownerId,
            $orderId,
            $deliveryUserId,
            StoreOrderTasksRepository::TYPE_DELIVERY,
            'Deliver order and confirm receipt',
            true,
            $assignedBy
        );

        return $prepOk && $deliveryOk;
    }

    public function startPreparation(int $ownerId, int $orderId, int $taskId): bool
    {
        $task = $this->tasksRepo->getOneForOwner($taskId, $ownerId);
        if (!$task || (int)$task->id_store_order !== $orderId || (string)$task->task_type !== StoreOrderTasksRepository::TYPE_PREPARATION) {
            return false;
        }

        $order = $this->ordersRepo->getById($orderId);
        if (!$order || (int)$order->id_owner !== $ownerId) {
            return false;
        }

        $ok = $this->tasksRepo->updateTaskStatus($taskId, StoreOrderTasksRepository::STATUS_IN_PROGRESS);
        if ($ok && in_array((string)$order->status, [
            StoreOrdersRepository::STATUS_NEW,
            StoreOrdersRepository::STATUS_CONFIRMED,
            StoreOrdersRepository::STATUS_PROCESSING,
            StoreOrdersRepository::STATUS_READY,
        ], true)) {
            $this->ordersRepo->updateStatus($orderId, StoreOrdersRepository::STATUS_IN_PREPARATION);
        }

        return $ok;
    }

    public function completePreparation(int $ownerId, int $orderId, int $taskId, int $completedBy, string $notes = ''): bool
    {
        $task = $this->tasksRepo->getOneForOwner($taskId, $ownerId);
        if (!$task || (int)$task->id_store_order !== $orderId || (string)$task->task_type !== StoreOrderTasksRepository::TYPE_PREPARATION) {
            return false;
        }

        $order = $this->ordersRepo->getById($orderId);
        if (!$order || (int)$order->id_owner !== $ownerId) {
            return false;
        }

        $ok = $this->tasksRepo->updateTaskStatus($taskId, StoreOrderTasksRepository::STATUS_COMPLETED, $completedBy, $notes);
        if (!$ok) {
            return false;
        }

        $workflow = $this->workflowRepo->getByOrder($orderId);
        $hasDeliveryAssignee = $workflow && (int)($workflow->delivery_user_id ?? 0) > 0;
        $nextStatus = $hasDeliveryAssignee
            ? StoreOrdersRepository::STATUS_READY_FOR_DELIVERY
            : StoreOrdersRepository::STATUS_READY;

        return $this->ordersRepo->updateStatus($orderId, $nextStatus);
    }

    public function canStartDelivery(int $ownerId, int $orderId): bool
    {
        $order = $this->ordersRepo->getById($orderId);
        if (!$order || (int)$order->id_owner !== $ownerId) {
            return false;
        }

        if ((string)$order->status === StoreOrdersRepository::STATUS_READY_FOR_DELIVERY) {
            return true;
        }

        return $this->tasksRepo->hasCompletedTaskType($ownerId, $orderId, StoreOrderTasksRepository::TYPE_PREPARATION)
            && in_array((string)$order->status, [
                StoreOrdersRepository::STATUS_READY,
                StoreOrdersRepository::STATUS_REDELIVERY_SCHEDULED,
            ], true);
    }

    public function markDeliveryAttempted(int $ownerId, int $orderId, int $taskId, int $userId, string $notes = ''): bool
    {
        $task = $this->tasksRepo->getOneForOwner($taskId, $ownerId);
        if (!$task || (int)$task->id_store_order !== $orderId || (string)$task->task_type !== StoreOrderTasksRepository::TYPE_DELIVERY) {
            return false;
        }

        $order = $this->ordersRepo->getById($orderId);
        if (!$order || (int)$order->id_owner !== $ownerId || (string)$order->status !== StoreOrdersRepository::STATUS_OUT_FOR_DELIVERY || trim($notes) === '') {
            return false;
        }
        $ok = $this->tasksRepo->updateTaskStatus($taskId, StoreOrderTasksRepository::STATUS_WAITING_REVIEW, $userId, $notes);
        return $ok && $this->ordersRepo->updateStatus($orderId, StoreOrdersRepository::STATUS_DELIVERY_ATTEMPTED);
    }

    public function markReturnedToBusiness(int $ownerId, int $orderId, int $taskId, int $userId, string $notes = ''): bool
    {
        $task = $this->tasksRepo->getOneForOwner($taskId, $ownerId);
        if (!$task || (int)$task->id_store_order !== $orderId || (string)$task->task_type !== StoreOrderTasksRepository::TYPE_DELIVERY) {
            return false;
        }

        $order = $this->ordersRepo->getById($orderId);
        if (!$order || (int)$order->id_owner !== $ownerId || (string)$order->status !== StoreOrdersRepository::STATUS_OUT_FOR_DELIVERY || trim($notes) === '') {
            return false;
        }
        $ok = $this->tasksRepo->updateTaskStatus($taskId, StoreOrderTasksRepository::STATUS_WAITING_REVIEW, $userId, $notes);
        return $ok && $this->ordersRepo->updateStatus($orderId, StoreOrdersRepository::STATUS_RETURNED_TO_BUSINESS);
    }
}
