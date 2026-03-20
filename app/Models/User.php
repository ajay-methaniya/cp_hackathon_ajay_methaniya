<?php

declare(strict_types=1);

namespace App\Models;

use App\Database\Connection;
use PDO;

final class User
{
    /**
     * @return array<string, mixed>|null
     */
    public static function findById(int $id): ?array
    {
        $stmt = Connection::pdo()->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function findByEmail(string $email): ?array
    {
        $stmt = Connection::pdo()->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function agentsForSelect(): array
    {
        $sql = "SELECT id, name, email, role FROM users WHERE role IN ('admin','agent') ORDER BY name ASC";
        return Connection::pdo()->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function create(string $name, string $email, string $passwordHash, string $role = 'agent'): int
    {
        $stmt = Connection::pdo()->prepare(
            'INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$name, $email, $passwordHash, $role]);
        return (int) Connection::pdo()->lastInsertId();
    }
}
