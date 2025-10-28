<?php
namespace App\Controllers;

use App\Database;
use App\Helpers\ResponseHelper;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use OpenApi\Attributes as OA;

#[OA\Tag(name: "Pacientes", description: "Endpoints relacionados con pacientes")]
class PacienteController
{
    #[OA\Get(
        path: "/api/v1/pacientes",
        summary: "Listar pacientes",
        tags: ["Pacientes"],
        responses: [
            new OA\Response(
                response: 200,
                description: "Listado de pacientes",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "items", type: "array", items: new OA\Items(
                            properties: [
                                new OA\Property(property: "id_paciente", type: "integer"),
                                new OA\Property(property: "primer_nombre", type: "string"),
                                new OA\Property(property: "primer_apellido", type: "string"),
                                new OA\Property(property: "segundo_apellido", type: "string"),
                                new OA\Property(property: "sexo", type: "string"),
                                new OA\Property(property: "fecha_nacimiento", type: "string", format: "date"),
                                new OA\Property(property: "tipo_seguridad_social", type: "string"),
                                new OA\Property(property: "correo_electronico", type: "string"),
                                new OA\Property(property: "celular", type: "string"),
                                new OA\Property(property: "municipio", type: "string"),
                                new OA\Property(property: "foto_url", type: "string", nullable: true)
                            ]
                        )),
                        new OA\Property(property: "page", type: "integer"),
                        new OA\Property(property: "size", type: "integer"),
                        new OA\Property(property: "total", type: "integer")
                    ]
                )
            )
        ]
    )]
    public function getAll(Request $request, Response $response): Response
    {
        $conn = Database::getConnection();
        $query = "SELECT id_paciente, primer_nombre, primer_apellido, segundo_apellido,
                         sexo, fecha_nacimiento, tipo_seguridad_social, correo_electronico,
                         celular, municipio
                  FROM paciente
                  LIMIT 20";
        $stmt = $conn->query($query);
        $items = $stmt->fetchAll();

        // Agregar foto_url null
        foreach ($items as &$p) {
            $p['foto_url'] = null;
        }

        $data = [
            'items' => $items,
            'page' => 1,
            'size' => count($items),
            'total' => count($items)
        ];
        return ResponseHelper::json($response, $data);
    }

    #[OA\Get(
        path: "/api/v1/pacientes/{id}",
        summary: "Obtener detalle de paciente",
        tags: ["Pacientes"],
        parameters: [new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))],
        responses: [new OA\Response(response: 200, description: "Paciente encontrado")]
    )]
    public function getById(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $db = Database::getConnection();

        $sql = "
        SELECT
            p.id_paciente,
            p.primer_nombre,
            COALESCE(p.segundo_nombre, '—') AS segundo_nombre,
            p.primer_apellido,
            p.segundo_apellido,
            p.sexo,
            p.fecha_nacimiento,
            p.tipo_seguridad_social,
            p.correo_electronico,
            p.telefono_fijo,
            p.celular,
            p.direccion,
            p.municipio,
            p.departamento,
            p.pais,
            p.tipo_sangre,
            NULL::text AS foto_url,  -- la BD no tiene esta columna; devolvemos null para cumplir contrato

            ins.id_institucion,
            ins.nombre_institucion,
            ins.nivel_complejidad,
            ins.ciudad
        FROM paciente p
        LEFT JOIN LATERAL (
            SELECT
                i2.id_institucion,
                i2.nombre_institucion,
                i2.nivel_complejidad,
                i2.ciudad
            FROM diagnostico d
            JOIN tratamiento t      ON t.id_diagnostico = d.id_diagnostico
            JOIN atencion a         ON a.id_tratamiento = t.id_tratamiento
            JOIN unidad_atencion u  ON u.id_unidad = a.id_unidad
            JOIN institucion i2     ON i2.id_institucion = u.id_institucion
            WHERE d.id_paciente = p.id_paciente
            -- Si tienes fecha de atención, puedes elegir la más reciente:
            -- ORDER BY a.fecha_atencion DESC NULLS LAST
            LIMIT 1
        ) ins ON TRUE
        WHERE p.id_paciente = :id
        LIMIT 1
    ";

        $stmt = $db->prepare($sql);
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        if (!$row) {
            return ResponseHelper::json($response, ['message' => 'Paciente no encontrado'], 404);
        }

        $institucion = null;
        if ($row['id_institucion'] !== null) {
            $institucion = [
                'id_institucion'     => (int)$row['id_institucion'],
                'nombre_institucion' => $row['nombre_institucion'],
                'nivel_complejidad'  => $row['nivel_complejidad'] ?? null,
                'ciudad'             => $row['ciudad'] ?? null,
            ];
        }

        $data = [
            'id_paciente'           => (int)$row['id_paciente'],
            'primer_nombre'         => $row['primer_nombre'],
            'segundo_nombre'        => $row['segundo_nombre'],
            'primer_apellido'       => $row['primer_apellido'],
            'segundo_apellido'      => $row['segundo_apellido'],
            'sexo'                  => $row['sexo'],
            'fecha_nacimiento'      => $row['fecha_nacimiento'],
            'tipo_seguridad_social' => $row['tipo_seguridad_social'],
            'correo_electronico'    => $row['correo_electronico'],
            'telefono_fijo'         => $row['telefono_fijo'] ?? null,
            'celular'               => $row['celular'],
            'direccion'             => $row['direccion'],
            'municipio'             => $row['municipio'],
            'departamento'          => $row['departamento'],
            'pais'                  => $row['pais'],
            'tipo_sangre'           => $row['tipo_sangre'],
            'foto_url'              => $row['foto_url'], // null por diseño actual
            'institucion'           => $institucion,
        ];

        return ResponseHelper::json($response, $data);
    }

    #[OA\Get(
        path: "/api/v1/pacientes/{id}/diagnosticos",
        summary: "Listar diagnósticos de un paciente",
        tags: ["Pacientes"],
        parameters: [new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))],
        responses: [new OA\Response(response: 200, description: "Lista de diagnósticos")]
    )]
    public function getDiagnosticos(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $db = Database::getConnection();

        // Nota: En este esquema, tipo_cancer viene como texto en diagnostico.tipo_cancer.
        // No existe tipo_cancer_cat.id_tipo_cancer, por eso se elimina el JOIN.
        // medico_responsable y estado se devuelven como NULL por ahora (contrato pide campos, pero no hay columnas claras).
        $sql = "
        SELECT
            d.id_diagnostico,
            d.tipo_cancer,
            d.estadio_enfermedad,
            d.fecha_diagnostico,
            d.observaciones,
            NULL::text AS medico_responsable,
            NULL::text AS estado
        FROM diagnostico d
        WHERE d.id_paciente = :id
        ORDER BY d.fecha_diagnostico DESC NULLS LAST, d.id_diagnostico
    ";

        $stmt = $db->prepare($sql);
        $stmt->execute(['id' => $id]);
        $rows = $stmt->fetchAll();

        // Formato exacto solicitado por el front
        $out = array_map(function ($r) {
            return [
                'id_diagnostico'       => (int)$r['id_diagnostico'],
                'tipo_cancer'          => $r['tipo_cancer'],
                'estadio_enfermedad'   => $r['estadio_enfermedad'],
                'fecha_diagnostico'    => $r['fecha_diagnostico'],
                'observaciones'        => $r['observaciones'] ?? null,
                'medico_responsable'   => $r['medico_responsable'] ?? null,
                'estado'               => $r['estado'] ?? null,
            ];
        }, $rows);

        return ResponseHelper::json($response, $out);
    }

}
