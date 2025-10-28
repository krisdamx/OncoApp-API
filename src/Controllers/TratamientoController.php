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
}
