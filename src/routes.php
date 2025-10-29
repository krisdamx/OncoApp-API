<?php
use Slim\App;
use OpenApi\Generator;
use App\Controllers\{
    PacienteController,
    DiagnosticoController,
    TratamientoController,
    ReporteController,
    CatalogoController
};

return function (App $app) {

    // ========== ENDPOINTS PRINCIPALES ==========
    // Pacientes
    $app->get('/api/v1/pacientes', [PacienteController::class, 'getAll']);
    $app->get('/api/v1/pacientes/{id}', [PacienteController::class, 'getById']);
    $app->get('/api/v1/pacientes/{id}/diagnosticos', [PacienteController::class, 'getDiagnosticos']);

    // Diagnósticos / Tratamientos
    $app->get('/api/v1/diagnosticos/{id}/tratamientos', [DiagnosticoController::class, 'getTratamientos']);
    $app->get('/api/v1/tratamientos/{id}/prescripciones', [TratamientoController::class, 'getPrescripciones']);
    $app->get('/api/v1/tratamientos/{id}/actividades', [\App\Controllers\TratamientoController::class, 'getActividades']);


    // Reportes
    $app->get('/api/v1/reportes/resumen', [ReporteController::class, 'getResumen']);
    $app->get('/api/v1/reportes/cancer-tipos', [ReporteController::class, 'getCancerTipos']);
    $app->get('/api/v1/reportes/costos-tratamiento', [ReporteController::class, 'getCostosTratamiento']);
    $app->get('/api/v1/reportes/inventario', [ReporteController::class, 'getInventario']);
    $app->get('/api/v1/reportes/edad-por-cancer', [ReporteController::class, 'getEdadPorCancer']);

    // Catálogos
    $app->get('/api/v1/catalogos/tipos-cancer', [CatalogoController::class, 'getTiposCancer']);
    $app->get('/api/v1/catalogos/estados-enfermedad', [CatalogoController::class, 'getEstados']);
    $app->get('/api/v1/catalogos/tipos-tratamiento', [CatalogoController::class, 'getTiposTratamiento']);
    $app->get('/api/v1/medicamentos', [CatalogoController::class, 'getMedicamentos']);
    $app->get('/api/v1/unidades', [CatalogoController::class, 'getUnidades']);
    $app->get('/api/v1/instituciones', [CatalogoController::class, 'getInstituciones']);

    // ========== SWAGGER ==========
// Generar el JSON OpenAPI con manejo de errores y sin HTML
    $app->get('/openapi.json', function ($request, $response) {
        try {
            // Escanea TODO src (clase OpenApi + Controllers con atributos)
            $openapi = \OpenApi\Generator::scan([__DIR__]);
            $json = $openapi->toJson();

            // Validación mínima: debe contener el campo "openapi"
            if (strpos($json, '"openapi"') === false) {
                throw new \RuntimeException('Spec generado sin campo "openapi" (posible error de anotaciones)');
            }

            $response->getBody()->write($json);
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withHeader('Cache-Control', 'no-store');
        } catch (\Throwable $e) {
            $payload = json_encode([
                'error' => 'openapi_generation_failed',
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            $response->getBody()->write($payload);
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    });

    // Swagger UI (usa los assets de swagger-api/swagger-ui)
    $app->get('/docs', function ($request, $response) {
        $html = file_get_contents(__DIR__ . '/../public/swagger/index.html');
        $response->getBody()->write($html);
        return $response->withHeader('Content-Type', 'text/html');
    });

    // Redirect raíz a Swagger
    $app->get('/', function ($req, $res) {
        return $res->withHeader('Location', '/docs')->withStatus(302);
    });

    // (opcional) healthcheck simple
    $app->get('/health', fn($req,$res) => $res->withHeader('Content-Type','application/json')
        ->withBody(\Slim\Psr7\Stream::create(json_encode(['status' => 'ok']))));


};
