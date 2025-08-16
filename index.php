<?php

$start = microtime(true);

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

include_once "src/migrations.php";
migrate($pdo);


// make random rows
//$pdo->exec("DELETE FROM entity_random");

$stmt = $pdo->prepare("INSERT INTO entity_random (status) SELECT floor(random() * 6 + 1) FROM generate_series(1, :row_count)");
$stmt->execute(["row_count" => ROW_COUNT]);


enum EnumType: string
{
    case StringConstraint = 'string_constraint';
    case StringFk = 'string_fk';
    case IntConstraint = 'int_constraint';
    case IntFk = 'int_fk';
    case Enum = 'enum';

    function isString(): bool
    {
        return match($this) {
            EnumType::StringConstraint, EnumType::StringFk, EnumType::Enum => true,
            EnumType::IntConstraint, EnumType::IntFk => false
        };
    }

    function table(): string
    {
        return match($this) {
            EnumType::StringConstraint => 'entity_string',
            EnumType::StringFk => 'entity_string_fk',
            EnumType::IntConstraint => 'entity_int',
            EnumType::IntFk => 'entity_int_fk',
            EnumType::Enum => 'entity_enum',
        };
    }

    function serializeStatus(Status $status): int|string
    {
        return $this->isString() ? $status->toString() : $status->toInt();
    }
}

enum Status: int
{
    case DRAFT = 1;
    case PUBLISHED = 2;
    case APPROVE = 3;
    case REJECT = 4;
    case DELIVERY = 5;
    case COMPLETE = 6;

    public function toString(): string
    {
        return strtolower($this->name);
    }

    public function toInt(): int
    {
        return $this->value;
    }

    public static function random(): self
    {
        return self::from(random_int(1, 6));
    }

    public function next(): self
    {
        $nextId = ($this->toInt() % 6) + 1 ;

        return self::from($nextId);
    }
}

final readonly class TestResult
{
    public function __construct(
        public int $totalRowCount,
        public int $attemptNum,
        public string $query,
        public EnumType $type,
        public float $executeTimeMs,
    ) {
    }

    public static function new(int $attemptNum, string $query, EnumType $type, float $executeTimeMs)
    {
        return new self(ROW_COUNT, $attemptNum, $query, $type, $executeTimeMs);
    }
}

function parseExecutionTime(array $result): float
{
    $lastRow = array_pop($result);
    $lastValue = array_pop($lastRow);

    if (!is_string($lastValue) || !str_starts_with($lastValue, "Execution Time: ")) {
        var_dump($lastValue);
        throw new RuntimeException("Unexpected last value");
    }

    $lastValue = str_replace("Execution Time: ", "", $lastValue);
    $lastValue = str_replace(" ms", "", $lastValue);

    if (!is_numeric($lastValue)) {
        var_dump($lastValue);
        throw new RuntimeException("Unexpected Execution Time");
    }

    return $lastValue+0;
}

class ResultRepository
{
    public function __construct(
        private readonly PDO $pdo,
    ) {
    }

    function save(TestResult $result): void
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO results ( total_row_count, attempt_num, query, type, execute_time_ms) VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->execute([$result->totalRowCount, $result->attemptNum, $result->query, $result->type->value, $result->executeTimeMs]);
//        printLn("save result");
    }
}



final readonly class Test {
    public function __construct(
        private PDO $pdo,
        private ResultRepository $resultRepository,
    ) {
    }


    private function execute(EnumType $type, int $attemptNum, string $queryType, PDOStatement $query, bool $needSave): void {
//        printLn("running ".$queryType.' '.$type->table());

        $result = $query->fetchAll();
        if (!$needSave) {
            return;
        }

        $executionTime = parseExecutionTime($result);
        $testResult = TestResult::new($attemptNum, $queryType, $type, $executionTime);

        $this->resultRepository->save($testResult);

    }

    public function insert(EnumType $type, int $attemptNum, bool $needSave = true): void {
        if ($type->isString()) {
            $select = "SELECT (ARRAY['draft', 'published', 'approve', 'reject', 'delivery', 'complete'])[status]";

            if ($type === EnumType::Enum) {
                $select .= "::status_enum";
            }
        } else {
            $select = "SELECT status";
        }

        $table = $type->table();
        $query = $this->pdo->query("EXPLAIN ANALYZE INSERT INTO $table (status) $select FROM entity_random;");

        $this->execute($type, $attemptNum, 'insert', $query, $needSave);
    }

    public function deleteAll(EnumType $type, int $attemptNum, bool $needSave = true): void {
        $table = $type->table();
        $query = $this->pdo->query("EXPLAIN ANALYZE DELETE FROM $table");

        $this->execute($type, $attemptNum, 'delete all', $query, $needSave);
    }

    public function deleteWhere(EnumType $type, int $attemptNum, Status $status, bool $needSave = true): void {
        $table = $type->table();
        $rawStatus = $type->serializeStatus($status);

        $query = $this->pdo->query("EXPLAIN ANALYZE DELETE FROM $table WHERE status = '$rawStatus';");

        $this->execute($type, $attemptNum, 'delete where', $query, $needSave);
    }

    public function update(EnumType $type, int $attemptNum, Status $status, bool $needSave = true): void {
        $table = $type->table();
        $rawStatus = $type->serializeStatus($status);

        $query = $this->pdo->query("EXPLAIN ANALYZE UPDATE $table SET version = version + 1 WHERE status = '$rawStatus';");

        $this->execute($type, $attemptNum, 'update where', $query, $needSave);
    }

    public function select(EnumType $type, int $attemptNum, Status $status, bool $needSave = true): void {
        $table = $type->table();
        $rawStatus = $type->serializeStatus($status);

        $query = $this->pdo->query("EXPLAIN ANALYZE SELECT * FROM $table WHERE status = '$rawStatus';");

        $this->execute($type, $attemptNum, 'select where', $query, $needSave);
    }
}


$resultRepository = new ResultRepository($pdo);
$test = new Test($pdo, $resultRepository);


//for ($attempt = 0; $attempt < ATTEMPT_COUNT; $attempt++) {
//    foreach (EnumType::cases() as $type) {
//        $test->insert($type, $attempt);
//        $test->deleteAll($type, $attempt);
//    }
//}
//
//for ($attempt = 0; $attempt < ATTEMPT_COUNT; $attempt++) {
//    $satus = Status::random();
//
//    foreach (EnumType::cases() as $type) {
//        $test->insert($type, $attempt, needSave: false);
//        $test->select($type, $attempt, $satus);
//        $test->update($type, $attempt, $satus); //меняет порядок срок, что влияет на следующие тесты
//        $test->deleteAll($type, $attempt, needSave: false);
//    }
//}
//
//for ($attempt = 0; $attempt < ATTEMPT_COUNT; $attempt++) {
//    $satus = Status::random();
//
//    foreach (EnumType::cases() as $type) {
//        $test->insert($type, $attempt, needSave: false);
//        $test->deleteWhere($type, $attempt, $satus);
//        $test->deleteAll($type, $attempt, needSave: false);
//    }
//}

function humanTime(int $seconds): string {
    if ($seconds < 0) {
        return '0s'; // или можно выбросить исключение
    }

    $units = [
        'd' => 86400,
        'h' => 3600,
        'm' => 60,
        's' => 1
    ];

    $result = [];

    foreach ($units as $name => $divisor) {
        $value = intdiv($seconds, $divisor);
        if ($value > 0) {
            $result[] = $value . $name;
            $seconds -= $value * $divisor;
        }
    }

    return implode(' ', $result) ?: '0s';
}

function printProgress($percent, $wait = 0) {
    static $lastLength = 0; // Запоминаем длину предыдущего вывода
    $time = humanTime($wait);

    $percent = floor($percent * 10) / 10;
    // Формируем строку с прогрессом
    $output = "Progress: {$percent}%\t wait: {$time}";

    // Дополняем строку пробелами если новая строка короче предыдущей
    $outputLength = mb_strlen($output);
    if ($outputLength < $lastLength) {
        $output .= str_repeat(' ', $lastLength - $outputLength);
    }
    $lastLength = $outputLength;

    // Выводим с возвратом каретки
    echo "\r{$output}";
    flush(); // Принудительно сбрасываем буфер вывода
}

$lastPrint = 0;

for ($attempt = 0; $attempt < ATTEMPT_COUNT; $attempt++) {
    $checkProgres = function () use ($attempt, &$lastPrint, $start) {
        if (microtime(true) - $lastPrint > 1) {
            $lastPrint = microtime(true);
            $percent = ($attempt * 100 / ATTEMPT_COUNT);
            $timeSpent = microtime(true) - $start;
            $timeNeed = $percent != 0 ? ($timeSpent * 100 / $percent) : 0;

            printProgress($percent, floor($timeNeed - $timeSpent));
        }
    };

    $checkProgres();
    $satus = Status::random();

    foreach (EnumType::cases() as $type) {
        $test->insert($type, $attempt);
        $checkProgres();
        $test->select($type, $attempt, $satus);
        $checkProgres();
        $test->deleteWhere($type, $attempt, $satus);
        $checkProgres();
        $test->update($type, $attempt, $satus->next()); //меняет порядок срок, что влияет на следующие тесты
        $checkProgres();
        $test->deleteAll($type, $attempt);
    }
}

$runningTime = humanTime(floor((microtime(true) - $start)));
printLn('');
printLn('done. Runing: '. $runningTime );

include_once "src/report.php";
makeReport($pdo);

