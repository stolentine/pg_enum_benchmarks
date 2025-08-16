<?php

set_time_limit(0);
ini_set('max_execution_time', 0);

include_once "src/functions.php";

$config = include_once "config.php";
define('ROW_COUNT', $config['benchmark']['row_count']);
define('ATTEMPT_COUNT', $config['benchmark']['test_attempt_count']);

$pdo = new PDO(
    "pgsql:host={$config['db']['host']};dbname={$config['db']['dbname']}",
    $config['db']['user'],
    $config['db']['password']
);


class ResultRepository
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
                PERCENTILE_CONT(0.5) WITHIN GROUP(ORDER BY execute_time_ms) as median
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
            ];
        }

        return $stats;
    }
}

$resultRepository = new ResultRepository($pdo);


$stats = $resultRepository->getStats();

$json = json_encode($stats);
file_put_contents("/app/report/data.json", $json);

printLn('Open report/index.html');
