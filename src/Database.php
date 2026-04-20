<?php
declare(strict_types=1);

namespace App;

use PDO;
use PDOException;

class Database
{
    private static ?PDO $instance = null;

    public static function connect(array $cfg): PDO
    {
        if (self::$instance !== null) {
            return self::$instance;
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            $cfg['host'] ?? '127.0.0.1',
            $cfg['port'] ?? '3306',
            $cfg['database'] ?? 'googlepro'
        );

        try {
            self::$instance = new PDO($dsn, $cfg['username'] ?? 'root', $cfg['password'] ?? '', [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            throw new \RuntimeException('Database connection failed: ' . $e->getMessage());
        }

        return self::$instance;
    }

    public static function getInstance(): ?PDO
    {
        return self::$instance;
    }

    public static function reset(): void
    {
        self::$instance = null;
    }

    /**
     * Test a connection without storing it as singleton
     */
    public static function testConnection(array $cfg): bool
    {
        try {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;charset=utf8mb4',
                $cfg['host'] ?? '127.0.0.1',
                $cfg['port'] ?? '3306'
            );
            $pdo = new PDO($dsn, $cfg['username'] ?? 'root', $cfg['password'] ?? '', [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);
            // Try to create database if not exists
            $dbName = preg_replace('/[^a-zA-Z0-9_]/', '', $cfg['database'] ?? 'googlepro');
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * Run the install schema
     */
    public static function runSchema(PDO $pdo, string $schemaPath): bool
    {
        try {
            $sql = file_get_contents($schemaPath);
            if ($sql === false) return false;
            $pdo->exec($sql);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}
