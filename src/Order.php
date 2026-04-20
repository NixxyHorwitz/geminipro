<?php
declare(strict_types=1);

namespace App;

use PDO;

class Order
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // ------------------------------------------------------------------
    // Create
    // ------------------------------------------------------------------

    public function create(array $data): array
    {
        $code      = $this->generateCode();
        $amount    = (int) ($data['amount'] ?? 309000);
        $expires   = date('Y-m-d H:i:s', time() + (15 * 60));

        $stmt = $this->pdo->prepare(
            "INSERT INTO orders 
             (order_code, email, amount, method, sso_email, activation_email,
              ip_address, user_agent, expires_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );

        $stmt->execute([
            $code,
            $data['email'],
            $amount,
            $data['method'] ?? 'link',
            $data['sso_email'] ?? null,
            $data['activation_email'] ?? null,
            $data['ip_address'] ?? null,
            $data['user_agent'] ?? null,
            $expires,
        ]);

        return $this->findByCode($code);
    }

    // ------------------------------------------------------------------
    // Read
    // ------------------------------------------------------------------

    public function findByCode(string $code): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM orders WHERE order_code = ?");
        $stmt->execute([$code]);
        return $stmt->fetch() ?: null;
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM orders WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function getPending(int $limit = 20): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM orders WHERE status = 'pending' ORDER BY created_at DESC LIMIT ?"
        );
        $stmt->execute([$limit]);
        return $stmt->fetchAll();
    }

    public function getAll(int $limit = 50, int $offset = 0): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM orders ORDER BY created_at DESC LIMIT ? OFFSET ?"
        );
        $stmt->execute([$limit, $offset]);
        return $stmt->fetchAll();
    }

    // ------------------------------------------------------------------
    // Update
    // ------------------------------------------------------------------

    public function confirm(string $code): bool
    {
        $stmt = $this->pdo->prepare(
            "UPDATE orders SET status='confirmed', confirmed_at=NOW() WHERE order_code=? AND status='pending'"
        );
        $stmt->execute([$code]);
        return $stmt->rowCount() > 0;
    }

    public function reject(string $code, string $reason = ''): bool
    {
        $stmt = $this->pdo->prepare(
            "UPDATE orders SET status='rejected', rejected_reason=? WHERE order_code=? AND status='pending'"
        );
        $stmt->execute([$reason, $code]);
        return $stmt->rowCount() > 0;
    }

    public function expire(): int
    {
        $stmt = $this->pdo->query(
            "UPDATE orders SET status='expired' WHERE status='pending' AND expires_at < NOW()"
        );
        return $stmt->rowCount();
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function generateCode(): string
    {
        return 'GAP' . strtoupper(substr(bin2hex(random_bytes(5)), 0, 8));
    }

    public static function formatRp(int $amount): string
    {
        return 'Rp ' . number_format($amount, 0, ',', '.');
    }
}
