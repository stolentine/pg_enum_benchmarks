<?php

class ReportRepository
{
    public function __construct(
        private readonly PDO $pdo,
    ) {
    }

    public function getStats(): array
    {
        $stmt = $this->pdo->query(<<<SQL
            SELECT
                query,
                type,
                min(execute_time_ms),
                max(execute_time_ms),
                avg(execute_time_ms),
                PERCENTILE_CONT(0.50) WITHIN GROUP(ORDER BY execute_time_ms) as median,
                PERCENTILE_CONT(0.05) WITHIN GROUP(ORDER BY execute_time_ms) as p_05,
                PERCENTILE_CONT(0.10) WITHIN GROUP(ORDER BY execute_time_ms) as p_10,
                PERCENTILE_CONT(0.25) WITHIN GROUP(ORDER BY execute_time_ms) as p_25,
                PERCENTILE_CONT(0.75) WITHIN GROUP(ORDER BY execute_time_ms) as p_75,
                PERCENTILE_CONT(0.90) WITHIN GROUP(ORDER BY execute_time_ms) as p_90,
                PERCENTILE_CONT(0.95) WITHIN GROUP(ORDER BY execute_time_ms) as p_95
            from results
            GROUP BY query, type
        SQL);

        $toFloat = function ($value) {
            return floor(($value + 0) * 100) / 100;
        };

        $stats = [];
        foreach ($stmt->fetchAll() as $row) {
            if (!array_key_exists($row['query'], $stats)) {
                $stats[$row['query']] = [];
            }

            $stats[$row['query']][] = [
                'type' => $row['type'],
                'min'       => $toFloat($row['min']),
                'max'       => $toFloat($row['max']),
                'avg'       => $toFloat($row['avg']),
                'median'    => $toFloat($row['median']),
                'p_05'      => $toFloat($row['p_05']),
                'p_10'      => $toFloat($row['p_10']),
                'p_25'      => $toFloat($row['p_25']),
                'p_75'      => $toFloat($row['p_75']),
                'p_90'      => $toFloat($row['p_90']),
                'p_95'      => $toFloat($row['p_95']),
            ];
        }

        return $stats;
    }
}

function makeReport(PDO $pdo)
{
    $reportRepository = new ReportRepository($pdo);
    $stats = $reportRepository->getStats();

    $json = json_encode($stats);
    file_put_contents("/app/report/data.js", "const data = {$json};");

    printLn('Open report/index.html');
}

