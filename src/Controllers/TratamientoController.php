<?php
namespace App\Controllers;

use App\Database;
use App\Helpers\ResponseHelper;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use OpenApi\Attributes as OA;

#[OA\Tag(name: "Tratamientos", description: "Endpoints relacionados con tratamientos y prescripciones")]
class TratamientoController
{
    #[OA\Get(
        path: "/api/v1/tratamientos/{id}/prescripciones",
        summary: "Listar prescripciones asociadas a un tratamiento",
        tags: ["Tratamientos"],
        parameters: [
            new OA\Parameter(
                name: "id",
                description: "ID del tratamiento",
                in: "path",
                required: true,
                schema: new OA\Schema(type: "integer")
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Lista de prescripciones",
                content: new OA\JsonContent(
                    type: "array",
                    items: new OA\Items(
                        properties: [
                            new OA\Property(property: "id_prescripcion", type: "integer"),
                            new OA\Property(property: "id_medicamento", type: "integer"),
                            new OA\Property(property: "nombre_medicamento", type: "string"),
                            new OA\Property(property: "dosis", type: "string"),
                            new OA\Property(property: "frecuencia", type: "string"),
                            new OA\Property(property: "duracion_dias", type: "integer"),
                            new OA\Property(property: "fecha_prescripcion", type: "string", format: "date")
                        ]
                    )
                )
            )
        ]
    )]
    public function getPrescripciones(Request $request, Response $response, array $args): Response
    {
        $idTratamiento = (int) $args['id'];
        $conn = Database::getConnection();

        $query = "
            SELECT 
                p.id_prescripcion,
                p.id_medicamento,
                m.nombre_medicamento,
                p.dosis,
                p.frecuencia,
                p.duracion_dias,
                p.fecha_prescripcion
            FROM prescripcion p
            LEFT JOIN medicamento m ON m.id_medicamento = p.id_medicamento
            WHERE p.id_tratamiento = :id
        ";

        $stmt = $conn->prepare($query);
        $stmt->execute(['id' => $idTratamiento]);
        $rows = $stmt->fetchAll();

        return ResponseHelper::json($response, $rows);
    }

    #[OA\Get(
        path: "/api/v1/tratamientos/{id}/actividades",
        summary: "Listar actividades asociadas a un tratamiento",
        tags: ["Tratamientos"],
        parameters: [
            new OA\Parameter(
                name: "id",
                description: "ID del tratamiento",
                in: "path",
                required: true,
                schema: new OA\Schema(type: "integer")
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Lista de actividades",
                content: new OA\JsonContent(
                    type: "array",
                    items: new OA\Items(
                        type: "object",
                        properties: [
                            new OA\Property(property: "id_actividad", type: "integer"),
                            new OA\Property(property: "tipo_actividad", type: "string", nullable: true),
                            new OA\Property(property: "fecha_actividad", type: "string", format: "date", nullable: true),
                            new OA\Property(property: "nombre_procedimiento", type: "string", nullable: true),
                            new OA\Property(property: "id_unidad", type: "integer", nullable: true),
                            new OA\Property(property: "medico_responsable", type: "string", nullable: true),
                            new OA\Property(property: "observaciones", type: "string", nullable: true),
                            new OA\Property(property: "resultado_clinico", type: "string", nullable: true),
                            new OA\Property(property: "archivo", type: "string", nullable: true)
                        ]
                    )
                )
            )
        ]
    )]
    public function getActividades(Request $request, Response $response, array $args): Response
    {
        $idTratamiento = (int)$args['id'];
        $db = Database::getConnection();

        // Nota:
        // - La tabla es actividad_tratamiento.
        // - archivo_resultado es BYTEA y enlace_archivo es texto; el contrato pide "archivo" vacío o null.
        //   Para la demo devolvemos siempre NULL y mantenemos el campo por contrato.
        $sql = "
        SELECT
            a.id_actividad,
            a.tipo_actividad,
            a.fecha_actividad,
            a.nombre_procedimiento,
            a.id_unidad,
            a.medico_responsable,
            a.observaciones,
            a.resultado_clinico,
            NULL::text AS archivo
        FROM actividad_tratamiento a
        WHERE a.id_tratamiento = :id_tratamiento
        ORDER BY a.fecha_actividad DESC NULLS LAST, a.id_actividad
    ";

        $stmt = $db->prepare($sql);
        $stmt->execute(['id_tratamiento' => $idTratamiento]);
        $rows = $stmt->fetchAll();

        $out = array_map(function ($r) {
            return [
                'id_actividad'        => (int)$r['id_actividad'],
                'tipo_actividad'      => $r['tipo_actividad'] ?? null,
                'fecha_actividad'     => $r['fecha_actividad'] ?? null,
                'nombre_procedimiento'=> $r['nombre_procedimiento'] ?? null,
                'id_unidad'           => isset($r['id_unidad']) ? (int)$r['id_unidad'] : null,
                'medico_responsable'  => $r['medico_responsable'] ?? null,
                'observaciones'       => $r['observaciones'] ?? null,
                'resultado_clinico'   => $r['resultado_clinico'] ?? null,
                'archivo'             => null, // contrato: vacío o null
            ];
        }, $rows);

        return ResponseHelper::json($response, $out);
    }

}
