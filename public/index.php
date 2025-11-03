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

$app->add(function ($request, $handler) {
    $response = $handler->handle($request);
    $path = $request->getUri()->getPath();

    if (str_starts_with($path, '/api/')) {
        $ct = $response->getHeaderLine('Content-Type');
        if ($ct === '' || stripos($ct, 'application/json') === false) {
            $response = $response->withHeader('Content-Type', 'application/json; charset=utf-8');
        }
    }
    return $response;
});


// Middlewares
$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();

$app->add(new CorsMiddleware());
$app->options('/{routes:.+}', function ($request, $response) {
    return $response;
});

$errorMiddleware = $app->addErrorMiddleware(true, true, true);

$customErrorHandler = function ($request, $exception, $displayErrorDetails) use ($app) {
    $status = 500;
    $title  = 'Internal Server Error';
    if ($exception instanceof \Slim\Exception\HttpException) {
        $status = $exception->getCode() ?: 500;
        $title  = $exception->getTitle();
    }

    $payload = [
        'error' => [
            'status' => $status,
            'title'  => $title,
            'detail' => $displayErrorDetails ? $exception->getMessage() : null,
            'path'   => (string)$request->getUri()->getPath(),
        ]
    ];

    $response = $app->getResponseFactory()->createResponse($status);
    $response->getBody()->write(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

    return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
};

$errorMiddleware->setDefaultErrorHandler($customErrorHandler);
// (displayErrorDetails, logErrors, logErrorDetails)
// pon true solo en desarrollo si de verdad quieres stacktraces en pantalla


// Registrar rutas
(require __DIR__ . '/../src/routes.php')($app);

// Ejecutar la app
$app->run();
