<?php

namespace App\Services;

use App\Repositories\StoreOrdersRepository;

final class StoreOrderLifecycleService
{
    public const TAB_ACTIVE = 'active';
    public const TAB_INCIDENTS = 'incidents';
    public const TAB_CLOSED = 'closed';

    public static function tabFor(object $order): string
    {
        $status = strtoupper((string)($order->status ?? ''));
        $cancellation = strtoupper((string)($order->cancellation_status ?? 'NONE'));
        if ($cancellation === 'REQUESTED' || in_array($status, [
            StoreOrdersRepository::STATUS_DELIVERY_ATTEMPTED,
            StoreOrdersRepository::STATUS_RETURNED_TO_BUSINESS,
            StoreOrdersRepository::STATUS_RETURN_REQUESTED,
            StoreOrdersRepository::STATUS_RETURN_APPROVED,
        ], true)) return self::TAB_INCIDENTS;
        if (in_array($status, [
            StoreOrdersRepository::STATUS_DELIVERED,
            StoreOrdersRepository::STATUS_COMPLETED,
            StoreOrdersRepository::STATUS_CANCELLED,
            StoreOrdersRepository::STATUS_RETURNED,
            StoreOrdersRepository::STATUS_RETURN_REJECTED,
            StoreOrdersRepository::STATUS_CLOSED,
        ], true)) return self::TAB_CLOSED;
        return self::TAB_ACTIVE;
    }

    public static function canClientCancel(object $order): bool
    {
        return strtoupper((string)($order->cancellation_status ?? 'NONE')) !== 'REQUESTED'
            && in_array(strtoupper((string)($order->status ?? '')), [
                StoreOrdersRepository::STATUS_NEW,
                StoreOrdersRepository::STATUS_CONFIRMED,
                StoreOrdersRepository::STATUS_PROCESSING,
                StoreOrdersRepository::STATUS_IN_PREPARATION,
                StoreOrdersRepository::STATUS_READY,
                StoreOrdersRepository::STATUS_READY_FOR_DELIVERY,
            ], true);
    }

    public static function canClientRequestReturn(object $order): bool
    {
        return in_array(strtoupper((string)($order->status ?? '')), [
            StoreOrdersRepository::STATUS_DELIVERED,
            StoreOrdersRepository::STATUS_COMPLETED,
            StoreOrdersRepository::STATUS_RETURN_REJECTED,
        ], true);
    }
}
