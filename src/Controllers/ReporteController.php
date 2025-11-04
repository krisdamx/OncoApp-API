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

        // Cálculo directo SIN vista (evita SQLSTATE[42P01])
        $sql = "
        SELECT
          (SELECT COUNT(*) FROM public.paciente WHERE activo = true) AS pacientes_activos,
          (SELECT COUNT(*)
             FROM public.diagnostico
             WHERE date_part('month', fecha_diagnostico) = date_part('month', CURRENT_DATE)
               AND date_part('year',  fecha_diagnostico) = date_part('year',  CURRENT_DATE)
          ) AS nuevos_mes,
          (SELECT COUNT(*)
             FROM public.tratamiento
             WHERE (fecha_fin IS NULL OR fecha_fin >= CURRENT_DATE)
          ) AS tratamientos_en_curso,
          (SELECT COUNT(*)
             FROM public.tratamiento
             WHERE fecha_fin IS NOT NULL
               AND date_part('month', fecha_fin) = date_part('month', CURRENT_DATE)
               AND date_part('year',  fecha_fin) = date_part('year',  CURRENT_DATE)
          ) AS altas_mes
    ";

        try {
            $res = $db->query($sql)->fetch(\PDO::FETCH_ASSOC) ?: [];
            return ResponseHelper::json($response, [
                'pacientes_activos'       => (int)($res['pacientes_activos'] ?? 0),
                'nuevos_mes'              => (int)($res['nuevos_mes'] ?? 0),
                'tratamientos_en_curso'   => (int)($res['tratamientos_en_curso'] ?? 0),
                'altas_mes'               => (int)($res['altas_mes'] ?? 0),
            ]);
        } catch (\Throwable $e) {
            return ResponseHelper::error($response, 500, 'Internal Server Error', $e->getMessage(), $request->getUri()->getPath());
        }
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
    public function getCancerTipos(Request $request, Response $response, array $args): Response
    {
        $db = Database::getConnection();
        try {
            $sql = "
            SELECT
              COALESCE(d.tipo_cancer, 'Sin especificar') AS name,
              COUNT(*)::int AS value
            FROM public.diagnostico d
            GROUP BY d.tipo_cancer
            ORDER BY value DESC
        ";
            $rows = $db->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
            return ResponseHelper::json($response, $rows);
        } catch (\Throwable $e) {
            return ResponseHelper::error(
                $response,
                500,
                'Internal Server Error',
                $e->getMessage(),
                $request->getUri()->getPath()
            );
        }
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
    public function getCostosTratamiento(Request $request, Response $response, array $args): Response
    {
        $db = Database::getConnection();

        try {
            // Agregación directa sin depender de la vista
            $sql = "
            SELECT
              t.tipo_tratamiento AS name,
              ROUND(AVG(COALESCE(m.costo_unitario,0) * COALESCE(cm.cantidad,0)), 2)::numeric AS cost
            FROM public.costo_medicamento cm
            JOIN public.medicamento m  ON m.id_medicamento = cm.id_medicamento
            JOIN public.tratamiento t  ON t.id_tratamiento = cm.id_tratamiento
            GROUP BY t.tipo_tratamiento
            ORDER BY cost DESC
        ";
            $rows = $db->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
            return ResponseHelper::json($response, $rows);
        } catch (\Throwable $e) {
            return ResponseHelper::error(
                $response,
                500,
                'Internal Server Error',
                $e->getMessage(),
                $request->getUri()->getPath()
            );
        }
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

        $sql = "
            SELECT
                m.nombre_medicamento AS name,
                COALESCE(SUM(i.stock), 0)::int AS stock
            FROM public.medicamento m
            LEFT JOIN public.inventario_medicamento i
              ON i.id_medicamento = m.id_medicamento
            GROUP BY m.id_medicamento, m.nombre_medicamento
            HAVING COALESCE(SUM(i.stock), 0) >= 0
            ORDER BY stock DESC, name ASC
        ";
        $rows = $db->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
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

        // Buckets por edad (ajusta si necesitas otros)
        $sql = "
            WITH base AS (
              SELECT
                p.id_paciente,
                COALESCE(d.tipo_cancer, 'Otros') AS tipo_cancer,
                COALESCE(p.edad, DATE_PART('year', AGE(CURRENT_DATE, p.fecha_nacimiento)))::int AS edad
              FROM public.paciente p
              LEFT JOIN public.diagnostico d ON d.id_paciente = p.id_paciente
            ),
            bucket AS (
              SELECT
                CASE
                  WHEN edad < 18 THEN '0-17'
                  WHEN edad BETWEEN 18 AND 35 THEN '18-35'
                  WHEN edad BETWEEN 36 AND 55 THEN '36-55'
                  WHEN edad BETWEEN 56 AND 75 THEN '56-75'
                  ELSE '76+'
                END AS grupo,
                tipo_cancer
              FROM base
              WHERE edad IS NOT NULL
            ),
            piv AS (
              SELECT
                grupo,
                SUM(CASE WHEN tipo_cancer ILIKE 'Mama%'     THEN 1 ELSE 0 END) AS Mama,
                SUM(CASE WHEN tipo_cancer ILIKE 'Pr%stata%' THEN 1 ELSE 0 END) AS Prostata,
                SUM(CASE WHEN tipo_cancer ILIKE 'Pulm%'     THEN 1 ELSE 0 END) AS Pulmon,
                SUM(CASE WHEN tipo_cancer ILIKE 'Colon%'    THEN 1 ELSE 0 END) AS Colon,
                SUM(CASE WHEN tipo_cancer NOT ILIKE 'Mama%'
                       AND tipo_cancer NOT ILIKE 'Pr%stata%'
                       AND tipo_cancer NOT ILIKE 'Pulm%'
                       AND tipo_cancer NOT ILIKE 'Colon%' THEN 1 ELSE 0 END) AS Otros
              FROM bucket
              GROUP BY grupo
            )
            SELECT grupo, Mama, Prostata, Pulmon, Colon, (Mama+Prostata+Pulmon+Colon+Otros) AS total
            FROM piv
            ORDER BY CASE grupo
                      WHEN '0-17' THEN 1
                      WHEN '18-35' THEN 2
                      WHEN '36-55' THEN 3
                      WHEN '56-75' THEN 4
                      ELSE 5
                    END
        ";
        $rows = $db->query($sql)->fetchAll(\PDO::FETCH_ASSOC);

        // Opcional: transformar a porcentajes si luego activas unit=percent
        return ResponseHelper::json($response, $rows);
    }

}
