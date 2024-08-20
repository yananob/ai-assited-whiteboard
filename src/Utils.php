<?php

declare(strict_types=1);

namespace MyApp;

class Utils
{
    public static function checkResponse(
        \Psr\Http\Message\ResponseInterface $response,
        array $allowedCode
    ): void {
        if (!in_array($response->getStatusCode(), $allowedCode, true)) {
            throw new \Exception("Request error: [{$response->getStatusCode()} {$response->getReasonPhrase()}");
        }
    }
}
