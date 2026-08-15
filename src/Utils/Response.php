<?php

namespace App\Utils;

class Response
{

    private string $data;

    public function __construct($data)
    {
        $this->data = $data;
    }

    public static function createResponse(string $data): Response
    {
        return new Response($data);
    }

    public static function redirect(string $url, int $statusCode = 302): Response
    {
        header('Location: ' . $url, true, $statusCode);
        exit;
    }

    public function handle(): void {
        echo $this->data;
    }
}
