<?php
namespace App\Controllers;

use App\Database;
use App\Helpers\ResponseHelper;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use OpenApi\Attributes as OA;

#[OA\Tag(name: "Diagnósticos", description: "Endpoints relacionados con diagnósticos y tratamientos")]
class DiagnosticoController
{
    #[OA\Get(
        path: "/api/v1/diagnosticos/{id}/tratamientos",
        summary: "Listar tratamientos asociados a un diagnóstico",
        tags: ["Diagnósticos"],
        parameters: [
            new OA\Parameter(
                name: "id",
                description: "ID del diagnóstico",
                in: "path",
                required: true,
                schema: new OA\Schema(type: "integer")
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Lista de tratamientos",
                content: new OA\JsonContent(
                    type: "array",
                    items: new OA\Items(
                        properties: [
                            new OA\Property(property: "id_tratamiento", type: "integer"),
                            new OA\Property(property: "tipo_tratamiento", type: "string"),
                            new OA\Property(property: "fecha_inicio", type: "string", format: "date"),
                            new OA\Property(property: "fecha_fin", type: "string", format: "date", nullable: true),
                            new OA\Property(property: "resultado_clinico_final", type: "string", nullable: true),
                            new OA\Property(property: "observaciones", type: "string", nullable: true),
                            new OA\Property(
                                property: "unidad_atencion",
                                type: "object",
                                nullable: true,
                                properties: [
                                    new OA\Property(property: "id_unidad", type: "integer"),
                                    new OA\Property(property: "nombre_unidad", type: "string")
                                ]
                            )
                        ]
                    )
                )
            )
        ]
    )]
    public function getTratamientos(Request $request, Response $response, array $args): Response
    {
        $idDiagnostico = (int) $args['id'];
        $conn = Database::getConnection();

        // Nota:
        // - tratamiento NO tiene id_unidad ni observaciones.
        // - La unidad y observaciones vienen de atencion (a) -> unidad_atencion (u).
        // Usamos agregaciones para evitar duplicar tratamientos si hay múltiples atenciones.
        $sql = "
            SELECT
                t.id_tratamiento,
                t.tipo_tratamiento,
                t.fecha_inicio,
                t.fecha_fin,
                t.resultado_clinico_final,
                MAX(a.observaciones)           AS observaciones,
                MAX(u.id_unidad)               AS id_unidad,
                MAX(u.nombre_unidad)           AS nombre_unidad
            FROM tratamiento t
            LEFT JOIN atencion a
                ON a.id_tratamiento = t.id_tratamiento
            LEFT JOIN unidad_atencion u
                ON u.id_unidad = a.id_unidad
            WHERE t.id_diagnostico = :id
            GROUP BY
                t.id_tratamiento,
                t.tipo_tratamiento,
                t.fecha_inicio,
                t.fecha_fin,
                t.resultado_clinico_final
            ORDER BY t.id_tratamiento
        ";

        $stmt = $conn->prepare($sql);
        $stmt->execute(['id' => $idDiagnostico]);
        $rows = $stmt->fetchAll();

        $data = array_map(function ($r) {
            $unidad = ($r['id_unidad'] !== null)
                ? ['id_unidad' => (int)$r['id_unidad'], 'nombre_unidad' => $r['nombre_unidad']]
                : null;

            return [
                'id_tratamiento'          => (int)$r['id_tratamiento'],
                'tipo_tratamiento'        => $r['tipo_tratamiento'],
                'fecha_inicio'            => $r['fecha_inicio'],
                'fecha_fin'               => $r['fecha_fin'] ?? null,
                'resultado_clinico_final' => $r['resultado_clinico_final'] ?? null,
                'observaciones'           => $r['observaciones'] ?? null,
                'unidad_atencion'         => $unidad,
            ];
        }, $rows);

        return ResponseHelper::json($response, $data);
    }
}
