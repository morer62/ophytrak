<?php

namespace App\Services;

use App\Repositories\UserAiUsageRepository;

class TokenUsageService
{
    private static ?UserAiUsageRepository $repo = null;

    public static function getCurrentPeriod(): string
    {
        return date('Y-m');
    }

    public static function getDefaultLimit(): int
    {
        $limit = (int) ($_ENV['AI_TOKEN_LIMIT_PER_MONTH'] ?? 100000);
        return $limit > 0 ? $limit : 100000;
    }

    public static function getUsage(int $userId): array
    {
        $period = self::getCurrentPeriod();
        $repo = self::getRepo();
        $row = $repo->getOrCreate($userId, $period);
        return [
            'used' => (int) ($row->tokens_used ?? 0),
            'limit' => self::getDefaultLimit(),
            'period' => $period,
        ];
    }

    public static function canUse(int $userId, int $estimatedTokens = 0): bool
    {
        $u = self::getUsage($userId);
        return ($u['used'] + $estimatedTokens) <= $u['limit'];
    }

    public static function addUsage(int $userId, int $tokens): void
    {
        if ($tokens <= 0) {
            return;
        }
        $period = self::getCurrentPeriod();
        $repo = self::getRepo();
        $repo->addTokens($userId, $period, $tokens);
    }

    private static function getRepo(): UserAiUsageRepository
    {
        if (self::$repo === null) {
            self::$repo = new UserAiUsageRepository();
        }
        return self::$repo;
    }
}
