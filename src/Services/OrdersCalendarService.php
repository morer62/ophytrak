<?php

namespace App\Services;

use App\Utils\LocationUtils;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

class OrdersCalendarService
{
    public function buildWeek(
        array $orders,
        array $clients,
        ?string $weekDate,
        ?string $statusFilter = null,
        string $detailRoutePrefix = 'order-access?token=',
        string $staffRoutePrefix = 'panel/planner-hub/management/orders/orders/team_comunication/?id='
    ): array {
        [$weekStart, $weekEnd] = $this->getWeekBounds($weekDate);
        $clientMap = $this->buildClientMap($clients);

        $days = [];
        for ($i = 0; $i < 7; $i++) {
            $date = $weekStart->modify("+{$i} days");
            $key = $date->format('Y-m-d');
            $days[$key] = [
                'date' => $key,
                'day_name' => $date->format('D'),
                'day_number' => $date->format('j'),
                'month' => $date->format('M'),
                'is_today' => $key === date('Y-m-d'),
                'orders' => [],
                'no_time_orders' => [],
            ];
        }

        $visibleStatuses = [];
        foreach ($orders as $order) {
            $status = $this->stringValue($order, 'status_workflow', 'Unscheduled');
            $visibleStatuses[$status] = $this->formatStatus($status);

            if ($statusFilter && $statusFilter !== 'all' && $status !== $statusFilter) {
                continue;
            }

            $date = $this->stringValue($order, 'event_date', '');
            if (!$date || !isset($days[$date])) {
                continue;
            }

            $entry = $this->normalizeOrder($order, $clientMap, $detailRoutePrefix, $staffRoutePrefix);
            if ($entry['has_time']) {
                $days[$date]['orders'][] = $entry;
            } else {
                $days[$date]['no_time_orders'][] = $entry;
            }
        }

        foreach ($days as &$day) {
            usort($day['orders'], fn ($a, $b) => strcmp($a['sort_time'], $b['sort_time']));
            usort($day['no_time_orders'], fn ($a, $b) => strcmp($a['title'], $b['title']));
        }
        unset($day);

        asort($visibleStatuses);

        return [
            'week_start' => $weekStart->format('Y-m-d'),
            'week_end' => $weekEnd->format('Y-m-d'),
            'range_label' => $weekStart->format('M j') . ' - ' . $weekEnd->format('M j, Y'),
            'previous_week' => $weekStart->modify('-7 days')->format('Y-m-d'),
            'next_week' => $weekStart->modify('+7 days')->format('Y-m-d'),
            'today_week' => (new DateTimeImmutable('today'))->format('Y-m-d'),
            'days' => array_values($days),
            'statuses' => $visibleStatuses,
            'status_filter' => $statusFilter ?: 'all',
            'total_orders' => $this->countOrders($days),
            'no_time_total' => $this->countNoTimeOrders($days),
        ];
    }

    public function getWeekBounds(?string $weekDate): array
    {
        $timezone = new DateTimeZone(date_default_timezone_get());
        $base = $weekDate && preg_match('/^\d{4}-\d{2}-\d{2}$/', $weekDate)
            ? new DateTimeImmutable($weekDate, $timezone)
            : new DateTimeImmutable('today', $timezone);

        $weekStart = $base->modify('monday this week')->setTime(0, 0, 0);
        $weekEnd = $weekStart->modify('+6 days')->setTime(23, 59, 59);

        return [$weekStart, $weekEnd];
    }

    private function normalizeOrder(
        object $order,
        array $clientMap,
        string $detailRoutePrefix,
        string $staffRoutePrefix
    ): array {
        $id = (int)$this->stringValue($order, 'id', 0);
        $clientId = (int)$this->stringValue($order, 'id_client', 0);
        $client = $clientMap[$clientId] ?? null;
        $clientName = $client ? trim(($client->name ?? '') . ' ' . ($client->lastname ?? '')) : '';
        $status = $this->stringValue($order, 'status_workflow', 'Unscheduled');
        $startTime = $this->stringValue($order, 'start_time', '');
        $endTime = $this->stringValue($order, 'end_time', '');
        $primaryTime = $this->pickPrimaryTime($order);
        $hasTime = $this->isUsableTime($primaryTime);

        return [
            'id' => $id,
            'code' => 'vnv-341' . $id,
            'title' => $this->buildTitle($order, $clientName),
            'client_name' => $clientName ?: 'Unknown client',
            'client_email' => $client->email ?? '',
            'address' => $this->stringValue($order, 'address', ''),
            'status' => $status,
            'status_label' => $this->formatStatus($status),
            'status_class' => $this->statusClass($status),
            'event_date' => $this->stringValue($order, 'event_date', ''),
            'start_time' => $this->formatTimeOrEmpty($startTime),
            'end_time' => $this->formatTimeOrEmpty($endTime),
            'time_label' => $hasTime ? $this->formatTime($primaryTime) : 'No time assigned',
            'range_label' => $this->formatRange($startTime, $endTime),
            'sort_time' => $hasTime ? $primaryTime : '99:99:99',
            'has_time' => $hasTime,
            'detail_url' => LocationUtils::pathFor($detailRoutePrefix . urlencode($this->buildOrderAccessToken($id, $clientId))),
            'edit_url' => 'panel/planner-hub/management/orders/orders/edit?id=' . $id,
            'staff_url' => $staffRoutePrefix . $id,
            'payments_url' => 'panel/planner-hub/management/orders/orders/payments?id=' . $id,
            'status_url' => 'panel/planner-hub/management/orders/orders/status?id=' . $id,
        ];
    }

    private function buildOrderAccessToken(int $orderId, int $clientId): string
    {
        $secret = $_ENV["VNV_SECRET_KEY"] ?? "mySuperSecretKey";
        $payload = [
            'order_id' => $orderId,
            'user_id' => $clientId,
            'exp' => time() + 60 * 60 * 24,
        ];
        $payload['hash'] = hash_hmac('sha256', json_encode([
            'order_id' => $payload['order_id'],
            'user_id' => $payload['user_id'],
            'exp' => $payload['exp'],
        ]), $secret);

        return base64_encode(json_encode($payload));
    }

    private function buildClientMap(array $clients): array
    {
        $map = [];
        foreach ($clients as $client) {
            if (isset($client->id)) {
                $map[(int)$client->id] = $client;
            }
        }

        return $map;
    }

    private function pickPrimaryTime(object $order): string
    {
        foreach (['execution_time', 'install_time', 'start_time', 'event_time'] as $field) {
            $value = $this->stringValue($order, $field, '');
            if ($this->isUsableTime($value)) {
                return $value;
            }
        }

        return '';
    }

    private function buildTitle(object $order, string $clientName): string
    {
        foreach (['event_name', 'title', 'name'] as $field) {
            $value = trim($this->stringValue($order, $field, ''));
            if ($value !== '') {
                return $value;
            }
        }

        return $clientName !== '' ? $clientName : 'Order ' . $this->stringValue($order, 'id', '');
    }

    private function formatRange(string $startTime, string $endTime): string
    {
        if ($this->isUsableTime($startTime) && $this->isUsableTime($endTime)) {
            return $this->formatTime($startTime) . ' - ' . $this->formatTime($endTime);
        }

        if ($this->isUsableTime($startTime)) {
            return $this->formatTime($startTime);
        }

        return 'No time assigned';
    }

    private function formatTime(string $time): string
    {
        $parsed = date_create($time);
        return $parsed instanceof DateTimeInterface ? $parsed->format('g:i A') : $time;
    }

    private function formatTimeOrEmpty(string $time): string
    {
        return $this->isUsableTime($time) ? $this->formatTime($time) : '';
    }

    private function isUsableTime(string $time): bool
    {
        $time = trim($time);
        return $time !== '' && $time !== '00:00:00' && $time !== '00:00';
    }

    private function formatStatus(string $status): string
    {
        $labels = [
            'INVOICE_DRAFT' => 'Signature pending',
            'INVOICE_READY' => 'Signed, payment pending',
            'INVOICE_PARTIAL' => 'First payment completed',
            'INVOICE_PAID' => 'Fully paid',
            'INVOICE_EXPIRED' => 'Expired',
        ];

        return $labels[$status] ?? ucwords(strtolower(str_replace('_', ' ', $status)));
    }

    private function statusClass(string $status): string
    {
        return match ($status) {
            'INVOICE_PAID' => 'is-paid',
            'INVOICE_PARTIAL' => 'is-partial',
            'INVOICE_READY' => 'is-ready',
            'INVOICE_DRAFT' => 'is-draft',
            'INVOICE_EXPIRED' => 'is-expired',
            default => 'is-neutral',
        };
    }

    private function countOrders(array $days): int
    {
        return array_reduce($days, fn ($carry, $day) => $carry + count($day['orders']) + count($day['no_time_orders']), 0);
    }

    private function countNoTimeOrders(array $days): int
    {
        return array_reduce($days, fn ($carry, $day) => $carry + count($day['no_time_orders']), 0);
    }

    private function stringValue(object $object, string $field, mixed $default): string
    {
        if (!isset($object->{$field}) || $object->{$field} === null) {
            return (string)$default;
        }

        return (string)$object->{$field};
    }
}
