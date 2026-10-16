<?php

use App\Services\EmailServiceFactory;
use App\Services\ProductProfileService;
use App\Utils\Router;
use App\Utils\TemplateResponse;

$router = new Router();

$render = static function (bool $sent = false, bool $error = false) {
    return TemplateResponse::render(__DIR__ . '/index.twig', [
        'sent' => $sent,
        'error' => $error,
        'csrf' => $_SESSION['ophytrack_demo_csrf'] ??= bin2hex(random_bytes(24)),
    ]);
};

$router->get(fn () => $render());

$router->post(function () use ($render) {
    if (!ProductProfileService::isOphytrack()) {
        header('Location: /support');
        exit;
    }

    $token = (string)($_POST['csrf'] ?? '');
    $honeypot = trim((string)($_POST['website'] ?? ''));
    $name = trim((string)($_POST['name'] ?? ''));
    $company = trim((string)($_POST['company'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $phone = trim((string)($_POST['phone'] ?? ''));
    $volume = trim((string)($_POST['monthly_volume'] ?? ''));
    $operation = trim((string)($_POST['operation'] ?? ''));
    $message = trim((string)($_POST['message'] ?? ''));

    if ($honeypot !== '' || !hash_equals((string)($_SESSION['ophytrack_demo_csrf'] ?? ''), $token)
        || $name === '' || $company === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return $render(false, true);
    }

    $escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    $body = '<h2>Nova solicitação de demonstração OPHYTRACK</h2>'
        . '<p><strong>Nome:</strong> ' . $escape($name) . '</p>'
        . '<p><strong>Empresa:</strong> ' . $escape($company) . '</p>'
        . '<p><strong>E-mail:</strong> ' . $escape($email) . '</p>'
        . '<p><strong>Telefone:</strong> ' . $escape($phone) . '</p>'
        . '<p><strong>Pacotes/mês:</strong> ' . $escape($volume) . '</p>'
        . '<p><strong>Operação:</strong> ' . $escape($operation) . '</p>'
        . '<p><strong>Contexto:</strong><br>' . nl2br($escape($message)) . '</p>';

    try {
        $recipient = trim((string)($_ENV['OPHYTRACK_SALES_EMAIL'] ?? 'jonny.dev2020@gmail.com'));
        $sent = EmailServiceFactory::createDefault()->sendSimpleEmail(
            $recipient,
            'OPHYTRACK | Solicitação de demo: ' . $company,
            $body,
            true
        );
        if (!$sent) {
            error_log('OPHYTRACK demo request email was not sent for ' . $email);
        }
    } catch (Throwable $exception) {
        error_log('OPHYTRACK demo request error: ' . $exception->getMessage());
        return $render(false, true);
    }

    unset($_SESSION['ophytrack_demo_csrf']);
    return $render(true, false);
});

try {
    $router->run();
} catch (Throwable $exception) {
    error_log('OPHYTRACK request access route error: ' . $exception->getMessage());
    echo $render(false, true);
}
