<?php

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Repositories\UserModulesRepository;
use App\Services\AddonBillingService;

$today = new DateTimeImmutable('2026-10-28');
$cases = [
    'active_without_expiration' => [(object)['status' => 'ACTIVE', 'renewal_at' => null], true],
    'active_through_today' => [(object)['status' => 'ACTIVE', 'renewal_at' => '2026-10-28'], true],
    'active_future_period' => [(object)['status' => 'ACTIVE', 'renewal_at' => '2026-11-28'], true],
    'stale_active_period_must_reenter_checkout' => [(object)['status' => 'ACTIVE', 'renewal_at' => '2026-10-24'], false],
    'inactive_future_period' => [(object)['status' => 'INACTIVE', 'renewal_at' => '2026-11-28'], false],
    'invalid_renewal_date' => [(object)['status' => 'ACTIVE', 'renewal_at' => 'not-a-date'], false],
];

$failed = [];
foreach ($cases as $name => [$record, $expected]) {
    $actual = UserModulesRepository::isRecordCurrentlyActive($record, $today);
    if ($actual !== $expected) {
        $failed[] = sprintf('%s expected %s, got %s', $name, $expected ? 'true' : 'false', $actual ? 'true' : 'false');
    }
}

if ($failed) {
    fwrite(STDERR, "OPHYTRACK membership renewal QA failed:\n- " . implode("\n- ", $failed) . "\n");
    exit(1);
}

$renewalCases = [
    'future_shared_cycle_is_preserved' => ['2026-11-15', '2026-11-15'],
    'expired_cycle_starts_a_new_month' => ['2026-10-24', '2026-11-28'],
    'same_day_cycle_starts_a_new_month' => ['2026-10-28', '2026-11-28'],
    'missing_cycle_starts_a_new_month' => [null, '2026-11-28'],
    'invalid_cycle_starts_a_new_month' => ['not-a-date', '2026-11-28'],
];

foreach ($renewalCases as $name => [$membershipDueDate, $expected]) {
    $actual = AddonBillingService::resolveRenewalDate($membershipDueDate, $today);
    if ($actual !== $expected) {
        $failed[] = sprintf('%s expected %s, got %s', $name, $expected, $actual);
    }
}

if ($failed) {
    fwrite(STDERR, "OPHYTRACK membership renewal QA failed:\n- " . implode("\n- ", $failed) . "\n");
    exit(1);
}

echo "OPHYTRACK membership renewal QA passed (" . (count($cases) + count($renewalCases)) . " cases).\n";
