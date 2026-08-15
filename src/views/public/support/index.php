<?php
use App\Utils\Router;
use App\Utils\TemplateResponse;
use App\Services\OphyraSeoService;

$router = new Router();

$router->get(function () {
    $appUrl = rtrim($_ENV['APP_URL'] ?? 'https://ophyra.com', '/');
    $seoService = new OphyraSeoService();
    $seo = $seoService->seoForRoute('support', $appUrl);

    return TemplateResponse::render(__DIR__ . "/index.twig", [
        "sent" => false,
        "error" => false,
        "seo" => $seo,
        "schemaJsonList" => $seoService->schemaJsonListForRoute('support', $appUrl, $seo),
    ]);
});

$router->post(function () {
    $name = trim($_POST["name"] ?? '');
    $email = trim($_POST["email"] ?? '');
    $message = trim($_POST["message"] ?? '');

    if (!$name || !filter_var($email, FILTER_VALIDATE_EMAIL) || !$message) {
        $appUrl = rtrim($_ENV['APP_URL'] ?? 'https://ophyra.com', '/');
        $seoService = new OphyraSeoService();
        $seo = $seoService->seoForRoute('support', $appUrl);
        return TemplateResponse::render(__DIR__ . "/index.twig", [
            "sent" => false,
            "error" => true,
            "seo" => $seo,
            "schemaJsonList" => $seoService->schemaJsonListForRoute('support', $appUrl, $seo),
        ]);
    }

    $to = "support@vnvevents.com";
    $subject = "Support Request from Planner Hub";
    $headers = "From: $email\r\nReply-To: $email\r\nContent-Type: text/plain; charset=utf-8";
    $body = "Name: $name\nEmail: $email\n\nMessage:\n$message\n";

    @mail($to, $subject, $body, $headers);

    $appUrl = rtrim($_ENV['APP_URL'] ?? 'https://ophyra.com', '/');
    $seoService = new OphyraSeoService();
    $seo = $seoService->seoForRoute('support', $appUrl);

    return TemplateResponse::render(__DIR__ . "/index.twig", [
        "sent" => true,
        "error" => false,
        "seo" => $seo,
        "schemaJsonList" => $seoService->schemaJsonListForRoute('support', $appUrl, $seo),
    ]);
});

try {
    $router->run();
} catch (Exception $e) {
    echo $e->getMessage();
}
