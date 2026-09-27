<?php

/**
 * The shared PDO connection, opened on first use.
 */
function db()
{
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                'mysql:host=' . config('db.host') . ';dbname=' . config('db.name') . ';charset=utf8mb4',
                config('db.user'),
                config('db.pass'),
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]
            );
        } catch (PDOException $e) {
            error_log('DB connection failed: ' . $e->getMessage());
            throw new HttpException('Service temporarily unavailable', 503);
        }
        $GLOBALS['DB_CONNECTED'] = true;
    }
    return $pdo;
}

function dbConnected()
{
    return !empty($GLOBALS['DB_CONNECTED']);
}

/**
 * Run a query and return the statement.
 */
function dbRun($sql, array $params = [])
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

/** First row, or null. */
function dbOne($sql, array $params = [])
{
    $row = dbRun($sql, $params)->fetch();
    return $row === false ? null : $row;
}

/** All rows. */
function dbAll($sql, array $params = [])
{
    return dbRun($sql, $params)->fetchAll();
}

/** First column of the first row, or null. */
function dbValue($sql, array $params = [])
{
    $value = dbRun($sql, $params)->fetchColumn();
    return $value === false ? null : $value;
}

/**
 * Run $work inside a transaction. Rolls back and rethrows on any error.
 */
function dbTransaction(callable $work)
{
    $pdo = db();
    if ($pdo->inTransaction()) {
        return $work();
    }
    $pdo->beginTransaction();
    try {
        $result = $work();
        $pdo->commit();
        return $result;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}
