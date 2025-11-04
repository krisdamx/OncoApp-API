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
        $id = (int)($args['id'] ?? 0);
        if ($id <= 0) {
            return ResponseHelper::json($response, ['error' => 'tratamientoId inválido'], 400);
        }

        $db = Database::getConnection();
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
                COALESCE(a.enlace_archivo, NULL) AS archivo
            FROM public.actividad_tratamiento a
            WHERE a.id_tratamiento = :id
            ORDER BY a.fecha_actividad DESC, a.id_actividad DESC
        ";
        $stmt = $db->prepare($sql);
        $stmt->execute([':id' => $id]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Normaliza fechas a ISO 8601 (si el front manda date-time)
        $items = array_map(function ($r) {
            if (!empty($r['fecha_actividad'])) {
                // Devuelve YYYY-MM-DD (si prefieres date-time, añade 'T00:00:00Z')
                $r['fecha_actividad'] = (new \DateTime($r['fecha_actividad']))->format('Y-m-d');
            }
            // archivo ya puede ser NULL o URL
            return $r;
        }, $rows);

        return ResponseHelper::json($response, $items);
    }


}
