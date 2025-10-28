<?php

namespace App\Helpers;

use Psr\Http\Message\ResponseInterface as Response;

class ResponseHelper
{
    public static function json(Response $response, $data, int $status = 200): Response
    {
        $payload = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $response->getBody()->write($payload);
        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }

    public static function error(Response $response, string $message, int $status = 400): Response
    {
        $payload = json_encode(['error' => $message]);
        $response->getBody()->write($payload);
        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }
}
