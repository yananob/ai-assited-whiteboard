<?php

declare(strict_types=1);

namespace MyApp;

class Utils
{
    public static function checkResponse(
        \Psr\Http\Message\ResponseInterface $response,
        array $allowedCodes
    ): void {
        if (!in_array($response->getStatusCode(), $allowedCodes, true)) {
            throw new \Exception("Request error: [{$response->getStatusCode()} {$response->getReasonPhrase()}");
        }
    }
}
