<?php
declare(strict_types=1);

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', cfg('db_host', 'localhost'), (int)cfg('db_port', 3306), cfg('db_name'));
        $pdo = new PDO($dsn, cfg('db_user'), cfg('db_pass'), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $pdo->exec("SET time_zone = '" . date('P') . "'");
    }
    return $pdo;
}

function db_exec(string $sql, array $params = []): PDOStatement
{
    $st = db()->prepare($sql);
    $st->execute(array_values($params));
    return $st;
}

function db_one(string $sql, array $params = []): ?array
{
    $r = db_exec($sql, $params)->fetch();
    return $r === false ? null : $r;
}

function db_all(string $sql, array $params = []): array
{
    return db_exec($sql, $params)->fetchAll();
}

function db_val(string $sql, array $params = [])
{
    $r = db_exec($sql, $params)->fetchColumn();
    return $r === false ? null : $r;
}

/** Table/column names come only from code, never from user input. */
function db_insert(string $table, array $data): int
{
    $cols = array_keys($data);
    $sql = 'INSERT INTO `' . $table . '` (`' . implode('`,`', $cols) . '`) VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')';
    db_exec($sql, array_values($data));
    return (int)db()->lastInsertId();
}

function db_update(string $table, array $data, string $where, array $whereParams = []): int
{
    $set = implode(', ', array_map(fn($c) => '`' . $c . '` = ?', array_keys($data)));
    $st = db_exec('UPDATE `' . $table . '` SET ' . $set . ' WHERE ' . $where, array_merge(array_values($data), $whereParams));
    return $st->rowCount();
}
