<?php
namespace App\Controllers;

use App\Database;
use App\Helpers\ResponseHelper;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use OpenApi\Attributes as OA;

#[OA\Tag(name: "Reportes", description: "Endpoints agregados para dashboard")]
class ReporteController
{
    #[OA\Get(
        path: "/api/v1/reportes/resumen",
        summary: "Resumen KPI (pacientes activos, nuevos mes, tratamientos en curso, altas mes)",
        tags: ["Reportes"],
        responses: [
            new OA\Response(
                response: 200,
                description: "Resumen",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "pacientes_activos", type: "integer"),
                        new OA\Property(property: "nuevos_mes", type: "integer"),
                        new OA\Property(property: "tratamientos_en_curso", type: "integer"),
                        new OA\Property(property: "altas_mes", type: "integer")
                    ]
                )
            )
        ]
    )]
    public function getResumen(Request $request, Response $response): Response
    {
        $db = Database::getConnection();

        $sql = "
            SELECT
              (SELECT COUNT(DISTINCT t.id_paciente)
                 FROM tratamiento t
                 WHERE t.fecha_inicio <= CURRENT_DATE
                   AND (t.fecha_fin IS NULL OR t.fecha_fin >= CURRENT_DATE)
              ) AS pacientes_activos,
              (SELECT COUNT(*)
                 FROM diagnostico d
                 WHERE date_trunc('month', d.fecha_diagnostico) = date_trunc('month', CURRENT_DATE)
              ) AS nuevos_mes,
              (SELECT COUNT(*)
                 FROM tratamiento t
                 WHERE t.fecha_inicio <= CURRENT_DATE
                   AND (t.fecha_fin IS NULL OR t.fecha_fin >= CURRENT_DATE)
              ) AS tratamientos_en_curso,
              (SELECT COUNT(*)
                 FROM tratamiento t
                 WHERE t.fecha_fin IS NOT NULL
                   AND date_trunc('month', t.fecha_fin) = date_trunc('month', CURRENT_DATE)
              ) AS altas_mes
        ";
        $res = $db->query($sql)->fetch();

        return ResponseHelper::json($response, [
            'pacientes_activos'       => (int)($res['pacientes_activos'] ?? 0),
            'nuevos_mes'              => (int)($res['nuevos_mes'] ?? 0),
            'tratamientos_en_curso'   => (int)($res['tratamientos_en_curso'] ?? 0),
            'altas_mes'               => (int)($res['altas_mes'] ?? 0),
        ]);
    }

    #[OA\Get(
        path: "/api/v1/reportes/cancer-tipos",
        summary: "Distribución por tipo de cáncer",
        tags: ["Reportes"],
        responses: [
            new OA\Response(
                response: 200,
                description: "Lista tipo { name, value }",
                content: new OA\JsonContent(
                    type: "array",
                    items: new OA\Items(
                        type: "object",
                        properties: [
                            new OA\Property(property: "name", type: "string"),
                            new OA\Property(property: "value", type: "integer")
                        ]
                    )
                )
            )
        ]
    )]
    public function getCancerTipos(Request $request, Response $response): Response
    {
        $db = Database::getConnection();

        // En este esquema, el tipo de cáncer está como texto en diagnostico.tipo_cancer
        $sql = "
        SELECT
          COALESCE(d.tipo_cancer, 'Desconocido') AS name,
          COUNT(*)::int AS value
        FROM diagnostico d
        GROUP BY COALESCE(d.tipo_cancer, 'Desconocido')
        ORDER BY value DESC
    ";
        $rows = $db->query($sql)->fetchAll();

        return ResponseHelper::json($response, $rows);
    }


    #[OA\Get(
        path: "/api/v1/reportes/costos-tratamiento",
        summary: "Costos por tipo de tratamiento (demo: usa conteo como métrica)",
        tags: ["Reportes"],
        responses: [
            new OA\Response(
                response: 200,
                description: "Lista tipo { name, cost }",
                content: new OA\JsonContent(
                    type: "array",
                    items: new OA\Items(
                        type: "object",
                        properties: [
                            new OA\Property(property: "name", type: "string"),
                            new OA\Property(property: "cost", type: "number", format: "float")
                        ]
                    )
                )
            )
        ]
    )]
    public function getCostosTratamiento(Request $request, Response $response): Response
    {
        $db = Database::getConnection();

        // DEMO estable: sin columnas de costo reales. Usamos el conteo por tipo como "cost".
        $sql = "
        SELECT
            t.tipo_tratamiento AS name,
            COUNT(*)::numeric    AS cost
        FROM tratamiento t
        WHERE t.tipo_tratamiento IS NOT NULL
        GROUP BY t.tipo_tratamiento
        ORDER BY cost DESC, name
    ";
        $rows = $db->query($sql)->fetchAll();

        return ResponseHelper::json($response, $rows);
    }


    #[OA\Get(
        path: "/api/v1/reportes/inventario",
        summary: "Inventario por medicamento (demo: usa conteo de prescripciones como stock)",
        tags: ["Reportes"],
        responses: [
            new OA\Response(
                response: 200,
                description: "Lista tipo { name, stock }",
                content: new OA\JsonContent(
                    type: "array",
                    items: new OA\Items(
                        type: "object",
                        properties: [
                            new OA\Property(property: "name", type: "string"),
                            new OA\Property(property: "stock", type: "integer")
                        ]
                    )
                )
            )
        ]
    )]
    public function getInventario(Request $request, Response $response): Response
    {
        $db = Database::getConnection();

        // DEMO estable: si no hay columna de inventario real, usamos el número de prescripciones por medicamento.
        $sql = "
        SELECT
          m.nombre_medicamento AS name,
          COUNT(p.id_prescripcion)::int AS stock
        FROM medicamento m
        LEFT JOIN prescripcion p ON p.id_medicamento = m.id_medicamento
        GROUP BY m.nombre_medicamento
        ORDER BY stock DESC, name
    ";
        $rows = $db->query($sql)->fetchAll();

        return ResponseHelper::json($response, $rows);
    }


    #[OA\Get(
        path: "/api/v1/reportes/edad-por-cancer",
        summary: "Distribución % por grupos de edad y tipo de cáncer",
        tags: ["Reportes"],
        responses: [
            new OA\Response(
                response: 200,
                description: "Arreglo con filas por grupo de edad: { grupo, Mama, Prostata, Pulmon, Colon, total }",
                content: new OA\JsonContent(
                    type: "array",
                    items: new OA\Items(
                        type: "object",
                        properties: [
                            new OA\Property(property: "grupo", type: "string"),
                            new OA\Property(property: "Mama", type: "number", format: "float"),
                            new OA\Property(property: "Prostata", type: "number", format: "float"),
                            new OA\Property(property: "Pulmon", type: "number", format: "float"),
                            new OA\Property(property: "Colon", type: "number", format: "float"),
                            new OA\Property(property: "total", type: "number", format: "float")
                        ]
                    )
                )
            )
        ]
    )]
    public function getEdadPorCancer(Request $request, Response $response): Response
    {
        $db = Database::getConnection();

        $sql = "
        WITH base AS (
          SELECT
            CASE
              WHEN EXTRACT(YEAR FROM age(CURRENT_DATE, p.fecha_nacimiento)) BETWEEN 18 AND 39 THEN '18-39'
              WHEN EXTRACT(YEAR FROM age(CURRENT_DATE, p.fecha_nacimiento)) BETWEEN 40 AND 59 THEN '40-59'
              WHEN EXTRACT(YEAR FROM age(CURRENT_DATE, p.fecha_nacimiento)) BETWEEN 60 AND 79 THEN '60-79'
              ELSE '80+'
            END AS grupo,
            COALESCE(d.tipo_cancer, 'Otro') AS cancer
          FROM diagnostico d
          JOIN paciente p ON p.id_paciente = d.id_paciente
        ),
        agg AS (
          SELECT grupo, cancer, COUNT(*)::int AS cnt
          FROM base
          GROUP BY grupo, cancer
        ),
        total_grp AS (
          SELECT grupo, SUM(cnt) AS total
          FROM agg
          GROUP BY grupo
        )
        SELECT
          a.grupo,
          ROUND(100.0 * SUM(CASE WHEN a.cancer ILIKE 'Mama' THEN a.cnt ELSE 0 END) / NULLIF(t.total,0), 2) AS \"Mama\",
          ROUND(100.0 * SUM(CASE WHEN a.cancer ILIKE 'Pr%stata' THEN a.cnt ELSE 0 END) / NULLIF(t.total,0), 2) AS \"Prostata\",
          ROUND(100.0 * SUM(CASE WHEN a.cancer ILIKE 'Pulm%' THEN a.cnt ELSE 0 END) / NULLIF(t.total,0), 2) AS \"Pulmon\",
          ROUND(100.0 * SUM(CASE WHEN a.cancer ILIKE 'Colon' THEN a.cnt ELSE 0 END) / NULLIF(t.total,0), 2) AS \"Colon\",
          100.0::numeric AS total
        FROM agg a
        JOIN total_grp t ON t.grupo = a.grupo
        GROUP BY a.grupo, t.total
        ORDER BY a.grupo
    ";
        $rows = $db->query($sql)->fetchAll();

        return ResponseHelper::json($response, $rows);
    }

}
