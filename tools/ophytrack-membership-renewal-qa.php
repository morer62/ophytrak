<?php

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Repositories\UserModulesRepository;

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

echo "OPHYTRACK membership renewal QA passed (" . count($cases) . " cases).\n";
