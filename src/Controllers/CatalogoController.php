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
        // En esta BD, el tipo viene como texto en diagnostico.tipo_cancer
        $sql = "SELECT DISTINCT tipo_cancer FROM diagnostico WHERE tipo_cancer IS NOT NULL AND tipo_cancer <> '' ORDER BY 1";
        $rows = $db->query($sql)->fetchAll();

        // normalizamos a [{ tipo_cancer: '...' }, ...]
        $out = array_map(fn($r) => ['tipo_cancer' => $r['tipo_cancer']], $rows);
        return ResponseHelper::json($response, $out);
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
        $sql = "SELECT DISTINCT estadio_enfermedad AS estado FROM diagnostico WHERE estadio_enfermedad IS NOT NULL ORDER BY estado";
        return ResponseHelper::json($response, $db->query($sql)->fetchAll());
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
        $sql = "SELECT DISTINCT tipo_tratamiento FROM tratamiento WHERE tipo_tratamiento IS NOT NULL ORDER BY 1";
        $rows = array_map(fn($r) => ['tipo_tratamiento' => $r['tipo_tratamiento']], $db->query($sql)->fetchAll());
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
    public function getUnidades(Request $request, Response $response): Response
    {
        $db = Database::getConnection();
        $sql = "SELECT id_unidad, nombre_unidad FROM unidad_atencion ORDER BY nombre_unidad";
        return ResponseHelper::json($response, $db->query($sql)->fetchAll());
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
        $sql = "
        SELECT
            id_institucion,
            nombre_institucion,
            nivel_complejidad,
            ciudad
        FROM institucion
        ORDER BY nombre_institucion
    ";
        $rows = $db->query($sql)->fetchAll();

        // (opcional) normaliza nulls explícitamente si quieres:
        $out = array_map(fn($r) => [
            'id_institucion'     => (int)$r['id_institucion'],
            'nombre_institucion' => $r['nombre_institucion'],
            'nivel_complejidad'  => $r['nivel_complejidad'] ?? null,
            'ciudad'             => $r['ciudad'] ?? null,
        ], $rows);

        return ResponseHelper::json($response, $out);
    }

}
