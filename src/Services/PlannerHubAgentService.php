<?php

namespace App\Services;

use App\Entity\User;
use App\Repositories\OrdersRepository;
use App\Repositories\OrdersPaymentsRepository;
use App\Repositories\UserRepository;
use App\Repositories\InstitutionProfileRepository;
use App\Services\TokenUsageService;
use App\Services\UserInstitutionService;
use Exception;

class PlannerHubAgentService
{
    private const INTENT_NEXT_EVENT = 'next_event';
    private const INTENT_EARNINGS_MONTH = 'earnings_month';
    private const INTENT_EARNINGS_MULTIPLE_MONTHS = 'earnings_multiple_months';
    private const INTENT_EARNINGS_YEAR = 'earnings_year';
    private const INTENT_UPCOMING_COUNT = 'upcoming_count';
    private const INTENT_CLIENT_COUNT = 'client_count';
    private const INTENT_BUSINESS_ADVICE = 'business_advice';
    private const INTENT_GREETING = 'greeting';
    private const INTENT_UNKNOWN = 'unknown';

    private OrdersRepository $ordersRepo;
    private OrdersPaymentsRepository $paymentsRepo;
    private UserRepository $userRepo;

    public function __construct()
    {
        $this->ordersRepo = new OrdersRepository();
        $this->paymentsRepo = new OrdersPaymentsRepository();
        $this->userRepo = new UserRepository();
    }

    public function chat(User $user, string $message, array $history = []): array
    {
        $message = trim($message);
        if ($message === '') {
            return ['success' => true, 'reply' => 'Type your question. E.g. "What is my next event?" or "What were earnings for December, January and February by month?"'];
        }

        try {
            $apiKey = $_ENV['OPENAI_TOKEN'] ?? '';
            $usageInfo = TokenUsageService::getUsage($user->getId());
            if ($apiKey !== '') {
                if (!TokenUsageService::canUse($user->getId(), 0)) {
                    return [
                        'success' => false,
                        'reply' => 'Has alcanzado tu límite de tokens para este periodo (' . $usageInfo['period'] . '). Consumo: ' . number_format($usageInfo['used']) . ' / ' . number_format($usageInfo['limit']) . '. Contacta al administrador para ampliar tu cupo.',
                        'usage' => $usageInfo,
                    ];
                }
                $result = $this->chatWithOpenAITools($user, $message, $history);
                if ($result !== null) {
                    return ['success' => true, 'reply' => $result, 'usage' => TokenUsageService::getUsage($user->getId())];
                }
            }
            $context = $this->buildContext($user);
            $intent = $this->detectIntent($message);
            $data = $this->fetchDataForIntent($intent, $context, $message, $history);
            $reply = $this->formatReplyWithOpenAI($user, $message, $intent, $data);
            return ['success' => true, 'reply' => $reply, 'usage' => TokenUsageService::getUsage($user->getId())];
        } catch (Exception $e) {
            return [
                'success' => false,
                'reply' => 'Could not process your question. Please try again.',
                'error' => $e->getMessage(),
                'usage' => TokenUsageService::getUsage($user->getId()),
            ];
        }
    }

    private function getToolsDefinition(): array
    {
        return [
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_next_event',
                    'description' => 'Get the user\'s next event (date, place, time). Use when they ask for next/closest/upcoming event.',
                    'parameters' => ['type' => 'object', 'properties' => [], 'required' => []],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_earnings_for_month',
                    'description' => 'Earnings (paid amount) for one month. Use when they ask for a specific month. If no year given, infer (e.g. Dec=previous year, Jan/Feb=current year). Month: 1=Jan, 12=Dec.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'year' => ['type' => 'integer', 'description' => 'Year (e.g. 2025, 2026)'],
                            'month' => ['type' => 'integer', 'description' => 'Month 1-12'],
                        ],
                        'required' => ['year', 'month'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_earnings_for_months',
                    'description' => 'Earnings for multiple months (list with total and per month). Use when they ask for total/sum of several months or breakdown by month. Pass months array; December before January = previous year.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'months' => [
                                'type' => 'array',
                                'description' => 'List of {year, month}. E.g. [{"year":2025,"month":12},{"year":2026,"month":1},{"year":2026,"month":2}]',
                                'items' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'year' => ['type' => 'integer'],
                                        'month' => ['type' => 'integer'],
                                    ],
                                    'required' => ['year', 'month'],
                                ],
                            ],
                        ],
                        'required' => ['months'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_earnings_for_year',
                    'description' => 'Total earnings for a year.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => ['year' => ['type' => 'integer', 'description' => 'Year (e.g. 2026)']],
                        'required' => ['year'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_upcoming_events_count',
                    'description' => 'Number of upcoming events/orders. Use when they ask how many events or orders they have.',
                    'parameters' => ['type' => 'object', 'properties' => [], 'required' => []],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_client_count',
                    'description' => 'Number of clients associated with the business. Use when they ask how many clients they have.',
                    'parameters' => ['type' => 'object', 'properties' => [], 'required' => []],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_business_stats',
                    'description' => 'Business stats: clients, earnings this/last month, orders, upcoming events. Call this for advice or trends questions (tendencias, qué aprovechar, industry).',
                    'parameters' => ['type' => 'object', 'properties' => [], 'required' => []],
                ],
            ],
        ];
    }

    private function runTool(string $name, array $args, array $context): array
    {
        $level = (int) $context['level'];
        $effectiveOwnerId = (int) $context['effective_owner_id'];

        switch ($name) {
            case 'get_next_event':
                $order = $this->getNextUpcomingOrder($context);
                return [
                    'event' => $order ? [
                        'event_date' => $order->event_date,
                        'address' => $order->address ?? '',
                        'start_time' => $order->start_time ?? '',
                    ] : null,
                ];

            case 'get_earnings_for_month':
                $year = (int) ($args['year'] ?? date('Y'));
                $month = (int) ($args['month'] ?? date('n'));
                $total = $this->getEarningsForPeriod($effectiveOwnerId, $year, $month);
                return [
                    'year' => $year,
                    'month' => $month,
                    'month_name' => $this->monthName($month),
                    'total' => round($total, 2),
                ];

            case 'get_earnings_for_months':
                $months = $args['months'] ?? [];
                $result = ['by_month' => [], 'total' => 0];
                foreach ($months as $m) {
                    $y = (int) ($m['year'] ?? date('Y'));
                    $mo = (int) ($m['month'] ?? 1);
                    $total = $this->getEarningsForPeriod($effectiveOwnerId, $y, $mo);
                    $result['by_month'][] = [
                        'year' => $y,
                        'month' => $mo,
                        'month_name' => $this->monthName($mo),
                        'total' => round($total, 2),
                    ];
                    $result['total'] += $total;
                }
                $result['total'] = round($result['total'], 2);
                return $result;

            case 'get_earnings_for_year':
                $year = (int) ($args['year'] ?? date('Y'));
                $total = $this->getEarningsForYear($effectiveOwnerId, $year);
                return ['year' => $year, 'total' => round($total, 2)];

            case 'get_upcoming_events_count':
                $count = $this->getUpcomingOrdersCount($context);
                return ['count' => $count];

            case 'get_client_count':
                if ($level === 5) {
                    return ['clients_count' => 0, 'note' => 'Only administrators can see total clients.'];
                }
                $count = $this->userRepo->getAssociatedClientsCount($effectiveOwnerId);
                return ['clients_count' => $count];

            case 'get_business_stats':
                if ($level === 5) {
                    return ['message' => 'unknown', 'note' => 'Contact the administrator.'];
                }
                $now = getdate();
                $thisMonth = $this->getEarningsForPeriod($effectiveOwnerId, $now['year'], $now['mon']);
                $lastMonth = $now['mon'] === 1
                    ? $this->getEarningsForPeriod($effectiveOwnerId, $now['year'] - 1, 12)
                    : $this->getEarningsForPeriod($effectiveOwnerId, $now['year'], $now['mon'] - 1);
                $ordersThisMonth = $this->ordersRepo->getOrdersCountByOwnerInMonth($effectiveOwnerId, $now['year'], $now['mon']);
                $ordersLastMonth = $now['mon'] === 1
                    ? $this->ordersRepo->getOrdersCountByOwnerInMonth($effectiveOwnerId, $now['year'] - 1, 12)
                    : $this->ordersRepo->getOrdersCountByOwnerInMonth($effectiveOwnerId, $now['year'], $now['mon'] - 1);
                $clientsCount = $this->userRepo->getAssociatedClientsCount($effectiveOwnerId);
                $upcomingCount = $this->getUpcomingOrdersCount($context);
                $profileType = $context['profile_type'] ?? 'planner';
                return [
                    'profile_type' => $profileType,
                    'clients_count' => $clientsCount,
                    'earnings_this_month' => round($thisMonth, 2),
                    'earnings_last_month' => round($lastMonth, 2),
                    'orders_this_month' => $ordersThisMonth,
                    'orders_last_month' => $ordersLastMonth,
                    'upcoming_events_count' => $upcomingCount,
                    'month_name' => $this->monthName($now['mon']),
                    'year' => $now['year'],
                ];

            default:
                return ['error' => 'Unknown tool: ' . $name];
        }
    }

    private function chatWithOpenAITools(User $user, string $message, array $history = []): ?string
    {
        $apiKey = trim($_ENV['OPENAI_TOKEN'] ?? '');
        if ($apiKey === '') {
            return null;
        }

        $context = $this->buildContext($user);
        $today = date('Y-m-d');
        $currentYear = (int) date('Y');
        $currentMonth = (int) date('n');

        $profileType = $context['profile_type'] ?? 'planner';
        $profileLabel = match ($profileType) {
            'venue' => 'venue (event spaces / espacios para eventos)',
            'vendor' => 'vendor (service provider / proveedor de servicios)',
            'client' => 'client',
            default => 'planner (event/order management / gestión de eventos y órdenes)',
        };
        $systemPrompt = "You are the Ophyra app panel assistant. Today: {$today} (year {$currentYear}, month {$currentMonth}). "
            . "The user's profile in this app is: {$profileLabel}. Use this when answering about their industry or sector. "
            . "You have tools that query the database. For earnings, events, client count, advice, or industry trends (tendencias, qué aprovechar), call the right tool. NEVER say you don't have access without calling the tool first. "
            . "For short follow-ups (e.g. 'and January?', 'y de enero?') use the same tool (get_earnings_for_month). "
            . "For TOTAL of several months use get_earnings_for_months with those months (e.g. [{\"year\":2025,\"month\":12},{\"year\":2026,\"month\":1},{\"year\":2026,\"month\":2}] for Dec/Jan/Feb). For two months in one phrase use get_earnings_for_months. Reply in the user's language. Be brief. Use only tool results; if total is 0, say so. "
            . "SCOPE: Only talk about the business/company profile and its industry (events, clients, earnings, operations). Do NOT give personal life advice. For industry trends / tendencias / qué actividades aprovechar: call get_business_stats, then with the data (profile_type, clients, orders, earnings, upcoming_events_count) answer with 2-4 recommendations. IMPORTANT: Vary your suggestions every time. Use your knowledge as an industry expert: suggest different angles (sustainability, technology, experiential offers, niches, seasonal opportunities, partnerships, digital presence, loyalty programs, upselling, etc.). Do NOT always repeat the same points (packages, follow-up, marketing). Adapt to their profile: venue = spaces/experiences; vendor = services/catering; planner = coordination/events. Reply in the user's language.";

        $messages = [['role' => 'system', 'content' => $systemPrompt]];

        foreach (array_slice($history, -10) as $h) {
            $u = isset($h['user']) ? trim((string) $h['user']) : '';
            $r = isset($h['reply']) ? trim((string) $h['reply']) : '';
            if ($u !== '') {
                $messages[] = ['role' => 'user', 'content' => $u];
            }
            if ($r !== '') {
                $messages[] = ['role' => 'assistant', 'content' => $r];
            }
        }

        $messages[] = ['role' => 'user', 'content' => $message];

        $payload = [
            'model' => 'gpt-4o-mini',
            'messages' => $messages,
            'tools' => $this->getToolsDefinition(),
            'tool_choice' => 'auto',
            'max_tokens' => 600,
            'temperature' => 0.3,
        ];

        $response = $this->callOpenAI($apiKey, $payload);
        if ($response === null) {
            return null;
        }
        $tokens = (int) ($response['usage']['total_tokens'] ?? 0);
        if ($tokens > 0) {
            TokenUsageService::addUsage($user->getId(), $tokens);
        }

        $choice = $response['choices'][0] ?? null;
        $msg = $choice['message'] ?? null;
        if (!$msg) {
            return null;
        }

        $toolCalls = $msg['tool_calls'] ?? null;
        if (empty($toolCalls)) {
            $content = $msg['content'] ?? null;
            $reply = $content !== null && trim($content) !== '' ? trim($content) : null;
            if ($reply && $this->looksLikeRefusal($reply) && $this->userAskedForData($message)) {
                return null;
            }
            return $reply;
        }

        $messages[] = $msg;

        foreach ($toolCalls as $tc) {
            $id = $tc['id'] ?? '';
            $fn = $tc['function'] ?? [];
            $name = $fn['name'] ?? '';
            $argumentsJson = $fn['arguments'] ?? '{}';
            $args = json_decode($argumentsJson, true) ?? [];
            $result = $this->runTool($name, $args, $context);
            $messages[] = [
                'role' => 'tool',
                'tool_call_id' => $id,
                'content' => json_encode($result, JSON_UNESCAPED_UNICODE),
            ];
        }

        $payload2 = [
            'model' => 'gpt-4o-mini',
            'messages' => $messages,
            'max_tokens' => 600,
            'temperature' => 0.3,
        ];

        $response2 = $this->callOpenAI($apiKey, $payload2);
        if ($response2 === null) {
            return null;
        }
        $tokens2 = (int) ($response2['usage']['total_tokens'] ?? 0);
        if ($tokens2 > 0) {
            TokenUsageService::addUsage($user->getId(), $tokens2);
        }

        $msg2 = $response2['choices'][0]['message'] ?? null;
        $content = $msg2['content'] ?? null;
        return $content !== null && trim($content) !== '' ? trim($content) : null;
    }

    private function looksLikeRefusal(string $reply): bool
    {
        $r = mb_strtolower($reply);
        return preg_match('/\b(no tengo|no puedo|no dispongo|no tengo acceso|no tengo la información|lo siento|sorry|no (puedo|tengo) (proporcionar|dar|decir|informar|acceso)|i don\'t have|i cannot)\b/ui', $r) === 1;
    }

    private function inferMonthsFromHistory(array $history): array
    {
        $nowYear = (int) date('Y');
        $monthNames = ['diciembre' => 12, 'enero' => 1, 'febrero' => 2, 'marzo' => 3, 'abril' => 4, 'mayo' => 5, 'junio' => 6, 'julio' => 7, 'agosto' => 8, 'septiembre' => 9, 'octubre' => 10, 'noviembre' => 11];
        $seen = [];
        foreach (array_reverse($history) as $h) {
            $reply = isset($h['reply']) ? mb_strtolower((string) $h['reply']) : '';
            foreach ($monthNames as $name => $month) {
                if (strpos($reply, $name) === false) {
                    continue;
                }
                $key = $month;
                if (isset($seen[$key])) {
                    continue;
                }
                $year = $nowYear;
                if ($month === 12) {
                    $year = $nowYear - 1;
                }
                if (preg_match('/\b(20\d{2})\b/', $reply, $m)) {
                    $year = (int) $m[1];
                }
                $seen[$key] = ['year' => $year, 'month' => $month];
            }
        }
        usort($seen, function ($a, $b) {
            if ($a['year'] !== $b['year']) {
                return $a['year'] <=> $b['year'];
            }
            return $a['month'] <=> $b['month'];
        });
        return $seen;
    }

    private function countMonthNamesInMessage(string $messageLower): int
    {
        $monthNames = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
        $count = 0;
        foreach ($monthNames as $name) {
            if (preg_match('/\b' . preg_quote($name, '/') . '\b/ui', $messageLower)) {
                $count++;
            }
        }
        return $count;
    }

    private function parseMultipleMonthsFromMessage(string $message): array
    {
        $m = mb_strtolower($message);
        $order = ['diciembre' => 12, 'enero' => 1, 'febrero' => 2, 'marzo' => 3, 'abril' => 4, 'mayo' => 5, 'junio' => 6, 'julio' => 7, 'agosto' => 8, 'septiembre' => 9, 'octubre' => 10, 'noviembre' => 11];
        $nowYear = (int) date('Y');
        $nowMonth = (int) date('n');
        $hasDecember = preg_match('/\bdiciembre\b/ui', $m);
        $out = [];
        foreach ($order as $name => $month) {
            if (!preg_match('/\b' . preg_quote($name, '/') . '\b/ui', $m)) {
                continue;
            }
            $year = $nowYear;
            if ($month === 12) {
                $year = ($nowMonth <= 2 || $hasDecember) ? $nowYear - 1 : $nowYear;
            }
            $out[] = ['year' => $year, 'month' => $month];
        }
        return count($out) >= 2 ? $out : [];
    }

    /**
     * Detecta si el mensaje es solo un mes o un seguimiento ("y de enero?", "las de febrero").
     */
    private function messageIsOnlyMonthOrFollowUp(string $messageLower): bool
    {
        $m = preg_replace('/\s+/', ' ', trim($messageLower));
        if ($m === '') {
            return false;
        }
        $monthNames = 'enero|febrero|marzo|abril|mayo|junio|julio|agosto|septiembre|octubre|noviembre|diciembre';
        $monthNamesEn = 'january|february|march|april|may|june|july|august|september|october|november|december';
        $pattern = '/^(y\s+(de\s+)?|las\s+de\s+|e\s+)?(' . $monthNames . '|' . $monthNamesEn . ')(\s+\d{4})?\s*[?.!]?$/ui';
        if (preg_match($pattern, $m)) {
            return true;
        }
        if (preg_match('/^(' . $monthNames . '|' . $monthNamesEn . ')(\s+\d{4})?\s*[?.!]?$/ui', $m)) {
            return true;
        }
        return false;
    }

    /**
     * Parsea mes y año del mensaje (ej. "diciembre", "enero 2026", "las de febrero").
     * @return array{year: int, month: int}|null
     */
    private function parseMonthYearFromMessage(string $message): ?array
    {
        $m = mb_strtolower($message);
        $monthNames = [
            'enero' => 1, 'january' => 1, 'janvier' => 1, 'janeiro' => 1,
            'febrero' => 2, 'february' => 2, 'février' => 2, 'fevereiro' => 2,
            'marzo' => 3, 'march' => 3, 'mars' => 3, 'março' => 3,
            'abril' => 4, 'april' => 4, 'avril' => 4,
            'mayo' => 5, 'may' => 5, 'mai' => 5,
            'junio' => 6, 'june' => 6, 'juin' => 6, 'junho' => 6,
            'julio' => 7, 'july' => 7, 'juillet' => 7, 'julho' => 7,
            'agosto' => 8, 'august' => 8, 'août' => 8, 'agosto' => 8,
            'septiembre' => 9, 'september' => 9, 'septembre' => 9, 'setembro' => 9,
            'octubre' => 10, 'october' => 10, 'octobre' => 10, 'outubro' => 10,
            'noviembre' => 11, 'november' => 11, 'novembre' => 11, 'novembro' => 11,
            'diciembre' => 12, 'december' => 12, 'décembre' => 12, 'dezembro' => 12,
        ];
        $month = null;
        foreach ($monthNames as $name => $num) {
            if (preg_match('/\b' . preg_quote($name, '/') . '\b/ui', $m)) {
                $month = $num;
                break;
            }
        }
        if ($month === null) {
            return null;
        }
        $year = null;
        if (preg_match('/\b(20\d{2})\b/', $m, $match)) {
            $year = (int) $match[1];
        }
        $nowYear = (int) date('Y');
        $nowMonth = (int) date('n');
        if ($year === null) {
            if ($month > $nowMonth) {
                $year = $nowYear - 1;
            } else {
                $year = $nowYear;
            }
        }
        return ['year' => $year, 'month' => $month];
    }

    private function userAskedForData(string $message): bool
    {
        $m = mb_strtolower($message);
        if (preg_match('/\b(ganancia|ganancias|ingreso|ingresos|revenue|earnings)\b/ui', $m)) {
            return true;
        }
        if (preg_match('/\b(próximo|evento|eventos|diciembre|enero|febrero|marzo|abril|mayo|junio|julio|agosto|septiembre|octubre|noviembre)\b/ui', $m)) {
            return true;
        }
        if (preg_match('/\b(clientes?|cuántos)\b/ui', $m)) {
            return true;
        }
        if (preg_match('/\b(suma|total)\s+(de\s+)?(eso|esos|estos|los)/ui', $m) || preg_match('/\bsumas?\b/ui', $m) || preg_match('/por\s+que\s+no\s+lo\s+sumas?/ui', $m)) {
            return true;
        }
        if (preg_match('/\b(tendencias?|industria|industry|trends|sector|recomend|advice|consejos?)\b/ui', $m)) {
            return true;
        }
        return false;
    }

    private function callOpenAI(string $apiKey, array $payload): ?array
    {
        $ch = curl_init('https://api.openai.com/v1/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json',
            ],
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($httpCode !== 200 || !$response) {
            return null;
        }
        $decoded = json_decode($response, true);
        return is_array($decoded) ? $decoded : null;
    }

    private function buildContext(User $user): array
    {
        $level = (int) $user->getLevel();
        $userId = (int) $user->getId();
        $idOwner = $user->getOwner();

        // Nivel 1 (planner): su empresa = id_owner o userId
        // Niveles 2 y 3 (venue/vendor): su empresa = la empresa de ESE usuario → siempre userId (son dueños de su negocio)
        // Nivel 4 (miembro): su empresa = id_owner de la institución en sesión
        $effectiveOwnerId = $userId;

        if ($level === 1) {
            $effectiveOwnerId = $idOwner !== null ? (int) $idOwner : $userId;
        } elseif (in_array($level, [2, 3], true)) {
            // Venue y vendor: hablar de la empresa de ese usuario = su userId (su negocio)
            $effectiveOwnerId = $userId;
        } elseif ($level === 4) {
            // Miembro: hablar del id_owner de su institución en sesión
            $institutionId = $_SESSION['current_institution_id'] ?? null;
            if ($institutionId === null || $institutionId === '') {
                $userInstService = new UserInstitutionService();
                $primary = $userInstService->getUserPrimaryInstitution($userId);
                $institutionId = $primary ? $primary->institution_id : null;
            }
            if ($institutionId !== null && $institutionId !== '') {
                $instRepo = new InstitutionProfileRepository();
                $inst = $instRepo->getById((int) $institutionId);
                if ($inst && isset($inst->id_owner) && $inst->id_owner !== null) {
                    $effectiveOwnerId = (int) $inst->id_owner;
                }
            }
        }

        $profileType = match ($level) {
            2 => 'venue',
            3 => 'vendor',
            4 => 'planner',
            5 => 'client',
            default => 'planner',
        };

        return [
            'level' => $level,
            'user_id' => $userId,
            'id_owner' => $idOwner,
            'effective_owner_id' => $effectiveOwnerId,
            'user_name' => $user->getName() . ' ' . $user->getLastname(),
            'profile_type' => $profileType,
        ];
    }

    private function detectIntent(string $message): string
    {
        $m = mb_strtolower($message);
        if (preg_match('/\b(hola|hey|buenas|qué tal|ayuda|hi|hello|help)\b/ui', $m) ||
            preg_match('/\b(bonjour|salut|allo|aide|bonsoir)\b/ui', $m) ||
            preg_match('/\b(olá|oi|ajuda)\b/ui', $m)) {
            return self::INTENT_GREETING;
        }
        if (preg_match('/\b(próximo|próximos|cercano|cercana|siguiente|evento|eventos|cual es el evento)\b/ui', $m) ||
            preg_match('/\b(next|upcoming|closest|nearest)\s*(event)?/ui', $m) ||
            preg_match('/\b(prochain|prochaine|prochains|événement|événements|event)\b/ui', $m) ||
            preg_match('/\b(quel est le prochain|prochain événement)\b/ui', $m) ||
            preg_match('/\b(próximo|próxima|evento|eventos|qual é o próximo)\b/ui', $m)) {
            return self::INTENT_NEXT_EVENT;
        }
        if ($this->countMonthNamesInMessage($m) >= 2 && !preg_match('/\b(cuántos?|how many)\s*(eventos?|events?)\b/ui', $m)) {
            return self::INTENT_EARNINGS_MULTIPLE_MONTHS;
        }
        if (preg_match('/\b(total|suma)\s*(de\s+)?(esos|estos|eso|los)\s*(\d|tres|three)?\s*meses?/ui', $m)) {
            return self::INTENT_EARNINGS_MULTIPLE_MONTHS;
        }
        if (preg_match('/\btotal\s+de\s+los\s+(entre\s+)?los\s*(\d\s*)?meses?/ui', $m) || preg_match('/\btotal\s+entre\s+los\s*(\d\s*)?meses?/ui', $m)) {
            return self::INTENT_EARNINGS_MULTIPLE_MONTHS;
        }
        if (preg_match('/cuanto\s+es\s+(el\s+)?(la\s+)?(total|suma)\s+(de\s+)?(eso|esos|estos)/ui', $m)) {
            return self::INTENT_EARNINGS_MULTIPLE_MONTHS;
        }
        if (preg_match('/\b(sumas?|súmalos?|sumar)\b/ui', $m) || preg_match('/por\s+que\s+no\s+lo\s+sumas?/ui', $m)) {
            return self::INTENT_EARNINGS_MULTIPLE_MONTHS;
        }
        if (preg_match('/cuanto\s+es\s+el\s+total\s+de\s+(esos|estos)/ui', $m)) {
            return self::INTENT_EARNINGS_MULTIPLE_MONTHS;
        }
        if (preg_match('/\b(revisa?|cuanto|cuánto)\b/ui', $m) && $this->countMonthNamesInMessage($m) === 1) {
            return self::INTENT_EARNINGS_MONTH;
        }
        // Ganancias / earnings (ES, EN, FR, PR)
        if (preg_match('/\b(ganancia|ganancias|ingreso|ingresos|venta|ventas|factur|recaud)\b/ui', $m) ||
            preg_match('/\b(earnings|revenue|income|sales)\b/ui', $m) ||
            preg_match('/\b(gains|revenus|recettes|ventes|ce mois|ce mois-ci)\b/ui', $m) ||
            preg_match('/\b(ganhos|receita|vendas|faturamento)\b/ui', $m)) {
            if (preg_match('/\b(año|year|anual|année|an)\b/ui', $m)) {
                return self::INTENT_EARNINGS_YEAR;
            }
            if ($this->countMonthNamesInMessage($m) >= 2) {
                return self::INTENT_EARNINGS_MULTIPLE_MONTHS;
            }
            return self::INTENT_EARNINGS_MONTH;
        }
        if ($this->messageIsOnlyMonthOrFollowUp($m)) {
            return self::INTENT_EARNINGS_MONTH;
        }
        if (preg_match('/\b(cuántos|cuantas|cuántas)\s*(evento|orden)\b/ui', $m) ||
            preg_match('/\b(how many)\s*(event|order)\b/ui', $m) ||
            preg_match('/\b(combien d\'?événements?|nombre d\'?événements?|combien d\'?événement)\b/ui', $m) ||
            preg_match('/\b(quantos?\s*eventos?|quantas?\s*ordens?)\b/ui', $m)) {
            return self::INTENT_UPCOMING_COUNT;
        }

        // Cuántos clientes / client count (ES, EN, FR, PR)
        if (preg_match('/\b(clientes?|client)\b/ui', $m) &&
            (preg_match('/\b(cuántos|cuantas|cuántas|disponibles?|tengo|tienes|total)\b/ui', $m) ||
             preg_match('/\b(how many|count|total)\b/ui', $m) ||
             preg_match('/\b(combien de clients?|nombre de clients?|clients? disponibles?)\b/ui', $m) ||
             preg_match('/\b(quantos?\s*clientes?|clientes?\s*disponíveis?)\b/ui', $m))) {
            return self::INTENT_CLIENT_COUNT;
        }
        if (preg_match('/\b(mejorar|perdiendo|perder|flujo|consejos?|recomend|ayudar)\s*(clientes?|empresa)?\b/ui', $m) ||
            preg_match('/\b(improve|advice|recommend|losing clients)\b/ui', $m) ||
            preg_match('/\b(améliorer|perdre des clients?|conseils?|recommandations?)\b/ui', $m) ||
            preg_match('/\b(melhorar|perder clientes?|conselhos?|recomendações?)\b/ui', $m)) {
            return self::INTENT_BUSINESS_ADVICE;
        }
        if (preg_match('/\b(tendencias?|tendências?|industria|industry|sector|trends|enfoque|focus)\b/ui', $m) ||
            preg_match('/\b(cuáles?|cuáles?|quais?|what are)\s*(las?|as?)?\s*(tendencias?|trends)\b/ui', $m)) {
            return self::INTENT_BUSINESS_ADVICE;
        }

        return self::INTENT_UNKNOWN;
    }

    /**
     * Obtiene datos según la intención y el contexto (solo datos permitidos para ese usuario).
     * @param string $userMessage Mensaje del usuario (opcional, para extraer mes/año en ganancias).
     * @param array $history Historial reciente para inferir meses cuando el mensaje dice "total de esos 3 meses".
     */
    private function fetchDataForIntent(string $intent, array $context, string $userMessage = '', array $history = []): array
    {
        $level = $context['level'];
        $effectiveOwnerId = (int) $context['effective_owner_id'];
        $userId = $context['user_id'];

        switch ($intent) {
            case self::INTENT_GREETING:
                return [
                    'user_name' => $context['user_name'],
                    'message' => 'greeting',
                ];

            case self::INTENT_NEXT_EVENT:
                $order = $this->getNextUpcomingOrder($context);
                return [
                    'event' => $order ? [
                        'id' => $order->id,
                        'event_date' => $order->event_date,
                        'address' => $order->address ?? '',
                        'start_time' => $order->start_time ?? '',
                    ] : null,
                ];

            case self::INTENT_UPCOMING_COUNT:
                $count = $this->getUpcomingOrdersCount($context);
                return ['count' => $count];

            case self::INTENT_EARNINGS_MONTH:
                $now = getdate();
                $year = $now['year'];
                $month = $now['mon'];
                $parsed = $this->parseMonthYearFromMessage($userMessage);
                if ($parsed !== null) {
                    $year = $parsed['year'];
                    $month = $parsed['month'];
                }
                $total = $this->getEarningsForPeriod($effectiveOwnerId, $year, $month);
                return [
                    'total' => round($total, 2),
                    'period' => 'month',
                    'year' => $year,
                    'month' => $month,
                ];

            case self::INTENT_EARNINGS_MULTIPLE_MONTHS:
                $months = $this->parseMultipleMonthsFromMessage($userMessage);
                if ($months === [] && !empty($history)) {
                    $months = $this->inferMonthsFromHistory($history);
                }
                if ($months === []) {
                    return ['message' => 'unknown'];
                }
                $byMonth = [];
                $grandTotal = 0;
                foreach ($months as $item) {
                    $y = (int) $item['year'];
                    $mo = (int) $item['month'];
                    $tot = $this->getEarningsForPeriod($effectiveOwnerId, $y, $mo);
                    $byMonth[] = ['year' => $y, 'month' => $mo, 'month_name' => $this->monthName($mo), 'total' => round($tot, 2)];
                    $grandTotal += $tot;
                }
                return ['by_month' => $byMonth, 'total' => round($grandTotal, 2)];

            case self::INTENT_EARNINGS_YEAR:
                $year = (int) date('Y');
                $total = $this->getEarningsForYear($effectiveOwnerId, $year);
                return [
                    'total' => round($total, 2),
                    'period' => 'year',
                    'year' => $year,
                ];

            case self::INTENT_CLIENT_COUNT:
                if ($level === 5) {
                    return ['clients_count' => 0, 'note' => 'Only administrators can see total clients.'];
                }
                $count = $this->userRepo->getAssociatedClientsCount($effectiveOwnerId);
                return ['clients_count' => $count];

            case self::INTENT_BUSINESS_ADVICE:
                if ($level === 5) {
                    return ['message' => 'unknown', 'note' => 'Contact the administrator.'];
                }
                $now = getdate();
                $thisMonth = $this->getEarningsForPeriod($effectiveOwnerId, $now['year'], $now['mon']);
                $lastMonth = $now['mon'] === 1
                    ? $this->getEarningsForPeriod($effectiveOwnerId, $now['year'] - 1, 12)
                    : $this->getEarningsForPeriod($effectiveOwnerId, $now['year'], $now['mon'] - 1);
                $ordersThisMonth = $this->ordersRepo->getOrdersCountByOwnerInMonth($effectiveOwnerId, $now['year'], $now['mon']);
                $ordersLastMonth = $now['mon'] === 1
                    ? $this->ordersRepo->getOrdersCountByOwnerInMonth($effectiveOwnerId, $now['year'] - 1, 12)
                    : $this->ordersRepo->getOrdersCountByOwnerInMonth($effectiveOwnerId, $now['year'], $now['mon'] - 1);
                $clientsCount = $this->userRepo->getAssociatedClientsCount($effectiveOwnerId);
                $upcomingCount = $this->getUpcomingOrdersCount($context);
                $profileType = $context['profile_type'] ?? 'planner';
                return [
                    'profile_type' => $profileType,
                    'clients_count' => $clientsCount,
                    'earnings_this_month' => round($thisMonth, 2),
                    'earnings_last_month' => round($lastMonth, 2),
                    'orders_this_month' => $ordersThisMonth,
                    'orders_last_month' => $ordersLastMonth,
                    'upcoming_events_count' => $upcomingCount,
                    'month_name' => $this->monthName($now['mon']),
                    'year' => $now['year'],
                ];

            default:
                return ['message' => 'unknown'];
        }
    }

    private function getNextUpcomingOrder(array $context): ?object
    {
        $level = $context['level'];
        $effectiveOwnerId = (int) $context['effective_owner_id'];
        $userId = $context['user_id'];

        if ($level === 5) {
            return $this->ordersRepo->getNextUpcomingByClient($userId);
        }
        return $this->ordersRepo->getNextUpcomingByOwner($effectiveOwnerId);
    }

    private function getUpcomingOrdersCount(array $context): int
    {
        $level = $context['level'];
        $effectiveOwnerId = (int) $context['effective_owner_id'];
        $userId = $context['user_id'];

        if ($level === 5) {
            return $this->ordersRepo->getUpcomingCountByClient($userId);
        }
        return $this->ordersRepo->getUpcomingCountByOwner($effectiveOwnerId);
    }

    private function getEarningsForPeriod(int $ownerId, int $year, int $month): float
    {
        $payments = $this->paymentsRepo->getPaidByOwnerInMonth($ownerId, $year, $month);
        $total = 0;
        foreach ($payments as $p) {
            $amount = $p->amount ?? $p->paid_amount ?? 0;
            $refunded = $p->refunded_amount ?? 0;
            $total += (float) $amount - (float) $refunded;
        }
        return $total;
    }

    private function getEarningsForYear(int $ownerId, int $year): float
    {
        $payments = $this->paymentsRepo->getPaidByOwnerInYear($ownerId, $year);
        $total = 0;
        foreach ($payments as $p) {
            $amount = $p->amount ?? $p->paid_amount ?? 0;
            $refunded = $p->refunded_amount ?? 0;
            $total += (float) $amount - (float) $refunded;
        }
        return $total;
    }

    private function formatReplyWithOpenAI(User $user, string $userMessage, string $intent, array $data): string
    {
        $apiKey = $_ENV['OPENAI_TOKEN'] ?? '';
        if ($apiKey === '') {
            return $this->formatReplyFallback($intent, $data);
        }
        $dataJson = json_encode($data, JSON_UNESCAPED_UNICODE);
        $systemPrompt = "You are the Ophyra app panel assistant. The user asked a question and we have the data (including profile_type: venue/vendor/planner). Reply in the user's language. Be brief. For business_advice or industry trends: use profile_type and the stats to give 2-4 concrete recommendations. Vary your angles: use your knowledge (sustainability, technology, experiences, niches, seasonal, partnerships, digital, loyalty, etc.). Do NOT always say the same (packages, follow-up, marketing). Adapt to profile: venue=spaces/experiences, vendor=services, planner=events/coordination. Do NOT give personal life advice.";
        $userPrompt = "User question: \"{$userMessage}\"\n\nData (JSON): {$dataJson}\n\nReply only with the answer to the user, no extra explanation.";

        $payload = [
            'model' => 'gpt-4o-mini',
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userPrompt],
            ],
            'max_tokens' => 400,
            'temperature' => 0.3,
        ];

        $ch = curl_init('https://api.openai.com/v1/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . trim($apiKey),
                'Content-Type: application/json',
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || !$response) {
            return $this->formatReplyFallback($intent, $data);
        }

        $decoded = json_decode($response, true);
        $usageTotal = (int) ($decoded['usage']['total_tokens'] ?? 0);
        if ($usageTotal > 0) {
            TokenUsageService::addUsage($user->getId(), $usageTotal);
        }
        $content = $decoded['choices'][0]['message']['content'] ?? null;
        if ($content !== null && trim($content) !== '') {
            return trim($content);
        }

        return $this->formatReplyFallback($intent, $data);
    }

    private function formatReplyFallback(string $intent, array $data): string
    {
        switch ($intent) {
            case 'greeting':
                $name = $data['user_name'] ?? 'User';
                return "Hi {$name}. You can ask about your next event, monthly earnings, or how many upcoming events you have.";
            case self::INTENT_NEXT_EVENT:
                $event = $data['event'] ?? null;
                if (!$event) {
                    return 'You have no upcoming events.';
                }
                return "Your next event is on {$event['event_date']}" . ($event['address'] ? " at {$event['address']}" : '') . ".";
            case self::INTENT_UPCOMING_COUNT:
                $count = $data['count'] ?? 0;
                return "You have {$count} upcoming event(s).";
            case self::INTENT_EARNINGS_MONTH:
                $total = $data['total'] ?? 0;
                $month = $data['month'] ?? date('n');
                $year = $data['year'] ?? date('Y');
                return "Earnings for " . $this->monthName($month) . " {$year}: \${$total}.";
            case self::INTENT_EARNINGS_MULTIPLE_MONTHS:
                $byMonth = $data['by_month'] ?? [];
                $grandTotal = $data['total'] ?? 0;
                $parts = [];
                foreach ($byMonth as $row) {
                    $parts[] = $row['month_name'] . ' ' . $row['year'] . ': $' . $row['total'];
                }
                return "Total: $" . $grandTotal . ". " . implode('. ', $parts) . ".";
            case self::INTENT_EARNINGS_YEAR:
                $total = $data['total'] ?? 0;
                $year = $data['year'] ?? date('Y');
                return "Earnings for {$year}: \${$total}.";
            case self::INTENT_CLIENT_COUNT:
                $count = $data['clients_count'] ?? 0;
                return "You have {$count} client(s) associated with your business.";
            case self::INTENT_BUSINESS_ADVICE:
                $c = $data['clients_count'] ?? 0;
                $e1 = $data['earnings_this_month'] ?? 0;
                $e0 = $data['earnings_last_month'] ?? 0;
                $o1 = $data['orders_this_month'] ?? 0;
                $o0 = $data['orders_last_month'] ?? 0;
                $up = $data['upcoming_events_count'] ?? 0;
                $profile = $data['profile_type'] ?? 'planner';
                $profileHint = $profile === 'venue' ? ' (venue/espacios)' : ($profile === 'vendor' ? ' (vendor/servicios)' : ' (planner/eventos)');
                return "You have {$c} clients, {$o1} orders this month, {$o0} last month, \${$e1} earnings this month, and {$up} upcoming events. Trends for your profile{$profileHint}: focus on client retention, seasonal bookings, and visibility in your sector. Review the CRM and orders panel.";
            default:
                return "I can help with: next event, earnings by month or year, event or client count, and business advice. Please rephrase your question.";
        }
    }

    private function monthName(int $month): string
    {
        $months = [1 => 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
        return $months[$month] ?? (string) $month;
    }
}
