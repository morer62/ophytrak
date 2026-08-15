<?php

namespace App\Services\Serp;

interface SERPProviderInterface
{
    public function createTask(array $input): array;

    public function fetchTask(string $taskId): array;
}
