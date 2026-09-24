<?php

namespace App\Repositories;

use PDO;
use PDOStatement;

class Connection
{

    private PDO $dbh; // Database Handler
    private PDOStatement|false $stmt;  //Statement

    public function setTimezone($timezone): void
    {
        $timezone = trim((string)$timezone);
        if (!preg_match('/^[+-](?:0\d|1[0-4]):[0-5]\d$/', $timezone)) {
            try {
                $zone = new \DateTimeZone($timezone !== '' ? $timezone : 'UTC');
                $seconds = $zone->getOffset(new \DateTimeImmutable('now', $zone));
                $sign = $seconds < 0 ? '-' : '+';
                $seconds = abs($seconds);
                $timezone = sprintf('%s%02d:%02d', $sign, intdiv($seconds, 3600), intdiv($seconds % 3600, 60));
            } catch (\Throwable $e) {
                $timezone = '+00:00';
            }
        }
        $this->dbh->exec("SET time_zone = " . $this->dbh->quote($timezone));
    }

    public function __construct()  {
        $DATABASE_URL = $_ENV["DATABASE_URL"];
        $this->dbh = new PDO ($DATABASE_URL);
        $this->dbh->exec("SET NAMES 'utf8mb4'");
    }

    /**
     * Set query
     * @param string $sql
     */
    public function query(string $sql): void
    {
        $this->stmt = $this->dbh->prepare($sql);
    }

    /**
     * Show the full query
     */
    public function showQuery(): void
    {
        $this->stmt->debugDumpParams();
    }

    public function bind($param, $value, $type=null): void
    {

        if (is_null($type)) {
            $type = match (true) {
                is_int($value) => PDO::PARAM_INT,
                is_bool($value) => PDO::PARAM_BOOL,
                is_null($value) => PDO::PARAM_NULL,
                default => PDO::PARAM_STR,
            };
        }

        $this->stmt->bindValue($param, $value , $type);

    }

    /**
     * Execute statement
     */
    public function execute(): void
    {
        $this->stmt->execute();
    }

    /**
     * Return the id of the last inserted row
     * @return false|string
     */
    public function lastId(): bool|string
    {
        return  $this->dbh->lastInsertId();
    }

    /**
     * Return the multiples rows
     * @return object[]
     */
    public function fetchAll(): array
    {
        $this->execute();
        return $this->stmt->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Return One row
     * @return object|bool
     */
    public function fetchOne(): object | bool
    {
        $this->execute();
        return $this->stmt->fetch(PDO::FETCH_OBJ);
    }

    /**
     * Return number of rows
     * @return int
     */
    public function count(): int
    {
        $this->execute();
        return $this->stmt->rowCount();
    }

    /**
     * Return number of rows affected by the last statement
     * @return int
     */
    public function rowCount(): int
    {
        return $this->stmt->rowCount();
    }

    /**
     * Begin transaction
     */
    public function beginTransaction(): bool
    {
        return $this->dbh->beginTransaction();
    }

    /**
     * Commit transaction
     */
    public function commit(): bool
    {
        return $this->dbh->commit();
    }

    /**
     * Rollback transaction
     */
    public function rollback(): bool
    {
        return $this->dbh->rollback();
    }
}
