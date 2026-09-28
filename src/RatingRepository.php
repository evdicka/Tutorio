<?php

declare(strict_types=1);

final class RatingRepository
{
    public function __construct(private PDO $db)
    {
    }

    /** Prüft, ob dieser Nutzer diesen Tutor bereits bewertet hat (eine Bewertung pro Paar). */
    public function hasRated(int $tutorId, int $raterId): bool
    {
        $stmt = $this->db->prepare('
            SELECT 1 FROM ratings WHERE tutor_id = :tutor_id AND rater_id = :rater_id
        ');
        $stmt->execute(['tutor_id' => $tutorId, 'rater_id' => $raterId]);
        return $stmt->fetch() !== false;
    }

    public function create(int $tutorId, int $raterId, int $stars, ?string $comment): int
    {
        $stmt = $this->db->prepare('
            INSERT INTO ratings (tutor_id, rater_id, stars, comment)
            VALUES (:tutor_id, :rater_id, :stars, :comment)
        ');
        $stmt->execute([
            'tutor_id' => $tutorId,
            'rater_id' => $raterId,
            'stars' => $stars,
            'comment' => $comment,
        ]);

        return (int) $this->db->lastInsertId();
    }

    /** Für die Validierung: gibt es diesen Tutor überhaupt? */
    public function tutorExists(int $tutorId): bool
    {
        $stmt = $this->db->prepare("SELECT 1 FROM users WHERE id = :id AND role = 'tutor'");
        $stmt->execute(['id' => $tutorId]);
        return $stmt->fetch() !== false;
    }
}
