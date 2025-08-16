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

include_once "src/report.php";
makeReport($pdo);
