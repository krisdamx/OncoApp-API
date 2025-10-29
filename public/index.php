<?php
require __DIR__ . '/../vendor/autoload.php';

use DI\ContainerBuilder;
use Slim\Factory\AppFactory;
use Dotenv\Dotenv;
use App\Middleware\CorsMiddleware;

// Cargar variables de entorno
$dotenv = Dotenv::createImmutable(dirname(__DIR__));
$dotenv->load();

// Configurar contenedor de dependencias
$containerBuilder = new ContainerBuilder();
AppFactory::setContainer($containerBuilder->build());

// Crear la app con la implementación PSR-7 detectada automáticamente (Nyholm\Psr7)
AppFactory::setResponseFactory(new \Nyholm\Psr7\Factory\Psr17Factory());
$app = AppFactory::create();

// Middlewares
$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();

$app->add(new CorsMiddleware());
$app->options('/{routes:.+}', function ($request, $response) {
    return $response;
});

// Registrar rutas
(require __DIR__ . '/../src/routes.php')($app);

// Ejecutar la app
$app->run();
