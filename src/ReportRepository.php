<?php

declare(strict_types=1);

final class ReportRepository
{
    public function __construct(private PDO $db)
    {
    }

    public function create(int $reporterId, int $reportedUserId, string $reason): int
    {
        $stmt = $this->db->prepare('
            INSERT INTO reports (reporter_id, reported_user_id, reason)
            VALUES (:reporter_id, :reported_user_id, :reason)
        ');
        $stmt->execute([
            'reporter_id' => $reporterId,
            'reported_user_id' => $reportedUserId,
            'reason' => $reason,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function userExists(int $userId): bool
    {
        $stmt = $this->db->prepare('SELECT 1 FROM users WHERE id = :id');
        $stmt->execute(['id' => $userId]);
        return $stmt->fetch() !== false;
    }
}
