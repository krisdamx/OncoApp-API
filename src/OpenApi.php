<?php
namespace App;

use OpenApi\Attributes as OA;

#[OA\Info(
    title: "OncoApp API",
    version: "1.0.0",
    description: "API REST de solo lectura para demo oncológica (Slim 4 + PostgreSQL)"
)]
#[OA\Server(
    url: "/",
    description: "Servidor local (Docker)"
)]
class OpenApi {}
