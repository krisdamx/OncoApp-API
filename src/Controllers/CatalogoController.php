<?php
namespace App\Controllers;

use App\Database;
use App\Helpers\ResponseHelper;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use OpenApi\Attributes as OA;

#[OA\Tag(name: "Catálogos", description: "Catálogos para etiquetas y selects")]
class CatalogoController
{
    #[OA\Get(
        path: "/api/v1/catalogos/tipos-cancer",
        summary: "Catálogo de tipos de cáncer (distinct desde diagnósticos)",
        tags: ["Catálogos"],
        responses: [
            new OA\Response(
                response: 200,
                description: "OK",
                content: new OA\JsonContent(
                    type: "array",
                    items: new OA\Items(
                        type: "object",
                        properties: [
                            new OA\Property(property: "tipo_cancer", type: "string")
                        ]
                    )
                )
            )
        ]
    )]
    public function getTiposCancer(Request $request, Response $response): Response
    {
        $db = Database::getConnection();
        $sql = "
            SELECT DISTINCT TRIM(tipo) AS tipo_cancer
            FROM public.tipo_cancer_cat
            WHERE tipo IS NOT NULL AND TRIM(tipo) <> ''
            ORDER BY 1 ASC
        ";
        $rows = $db->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
        return ResponseHelper::json($response, $rows);
    }

    #[OA\Get(
        path: "/api/v1/catalogos/estados-enfermedad",
        summary: "Catálogo de estados/estadios de enfermedad",
        tags: ["Catálogos"],
        responses: [
            new OA\Response(
                response: 200,
                description: "OK",
                content: new OA\JsonContent(
                    type: "array",
                    items: new OA\Items(
                        type: "object",
                        properties: [
                            new OA\Property(property: "estado", type: "string")
                        ]
                    )
                )
            )
        ]
    )]
    public function getEstados(Request $request, Response $response): Response
    {
        $db = Database::getConnection();
        $sql = "
            SELECT DISTINCT TRIM(estadio_enfermedad) AS estado
            FROM public.diagnostico
            WHERE estadio_enfermedad IS NOT NULL AND TRIM(estadio_enfermedad) <> ''
            ORDER BY 1 ASC
        ";
        $rows = $db->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
        return ResponseHelper::json($response, $rows);
    }

    #[OA\Get(
        path: "/api/v1/catalogos/tipos-tratamiento",
        summary: "Catálogo de tipos de tratamiento",
        tags: ["Catálogos"],
        responses: [
            new OA\Response(
                response: 200,
                description: "OK",
                content: new OA\JsonContent(
                    type: "array",
                    items: new OA\Items(
                        type: "object",
                        properties: [
                            new OA\Property(property: "tipo_tratamiento", type: "string")
                        ]
                    )
                )
            )
        ]
    )]
    public function getTiposTratamiento(Request $request, Response $response): Response
    {
        $db = Database::getConnection();
        $sql = "
            SELECT DISTINCT TRIM(tipo_tratamiento) AS tipo_tratamiento
            FROM public.tratamiento
            WHERE tipo_tratamiento IS NOT NULL AND TRIM(tipo_tratamiento) <> ''
            ORDER BY 1 ASC
        ";
        $rows = $db->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
        return ResponseHelper::json($response, $rows);
    }

    #[OA\Get(
        path: "/api/v1/medicamentos",
        summary: "Catálogo de medicamentos",
        tags: ["Catálogos"],
        responses: [
            new OA\Response(
                response: 200,
                description: "OK",
                content: new OA\JsonContent(
                    type: "array",
                    items: new OA\Items(
                        type: "object",
                        properties: [
                            new OA\Property(property: "id_medicamento", type: "integer"),
                            new OA\Property(property: "nombre_medicamento", type: "string")
                        ]
                    )
                )
            )
        ]
    )]
    public function getMedicamentos(Request $request, Response $response): Response
    {
        $db = Database::getConnection();
        $sql = "SELECT id_medicamento, nombre_medicamento FROM medicamento ORDER BY nombre_medicamento";
        return ResponseHelper::json($response, $db->query($sql)->fetchAll());
    }

    #[OA\Get(
        path: "/api/v1/unidades",
        summary: "Catálogo de unidades de atención",
        tags: ["Catálogos"],
        responses: [
            new OA\Response(
                response: 200,
                description: "OK",
                content: new OA\JsonContent(
                    type: "array",
                    items: new OA\Items(
                        type: "object",
                        properties: [
                            new OA\Property(property: "id_unidad", type: "integer"),
                            new OA\Property(property: "nombre_unidad", type: "string")
                        ]
                    )
                )
            )
        ]
    )]
    public function getUnidades(Request $request, Response $response, array $args): Response
    {
        $db = Database::getConnection();

        // No dependemos de la vista; hacemos el JOIN directo.
        $sql = "
        SELECT
          u.id_unidad,
          u.nombre_unidad,
          COALESCE(i.ciudad, '')            AS ciudad,
          COALESCE(i.nivel_complejidad, '') AS nivel_complejidad
        FROM public.unidad_atencion u
        LEFT JOIN public.institucion i ON i.id_institucion = u.id_institucion
        ORDER BY u.nombre_unidad
    ";

        try {
            $rows = $db->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
            return ResponseHelper::json($response, $rows);
        } catch (\Throwable $e) {
            return ResponseHelper::error($response, 500, 'Internal Server Error', $e->getMessage(), $request->getUri()->getPath());
        }
    }


    #[OA\Get(
        path: "/api/v1/instituciones",
        summary: "Catálogo de instituciones",
        tags: ["Catálogos"],
        responses: [
            new OA\Response(
                response: 200,
                description: "OK",
                content: new OA\JsonContent(
                    type: "array",
                    items: new OA\Items(
                        type: "object",
                        properties: [
                            new OA\Property(property: "id_institucion", type: "integer"),
                            new OA\Property(property: "nombre_institucion", type: "string"),
                            new OA\Property(property: "nivel_complejidad", type: "string", nullable: true),
                            new OA\Property(property: "ciudad", type: "string", nullable: true)
                        ]
                    )
                )
            )
        ]
    )]
    public function getInstituciones(Request $request, Response $response): Response
    {
        $db = Database::getConnection();
        $rows = $db->query("
            SELECT
                id_institucion,
                nombre_institucion,
                COALESCE(nivel_complejidad, '') AS nivel_complejidad,
                COALESCE(ciudad, '')            AS ciudad
            FROM public.institucion
            ORDER BY nombre_institucion ASC
        ")->fetchAll(\PDO::FETCH_ASSOC);

        return ResponseHelper::json($response, $rows);
    }

}
