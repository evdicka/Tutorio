<?php

declare(strict_types=1);

/**
 * Datenzugriff für das Hilfsgesuche-Modul (Board mit Fragen + Antworten).
 */
final class HelpRequestRepository
{
    public function __construct(private PDO $db)
    {
    }

    /**
     * Liste aller Gesuche für's Board, optional nach Fach gefiltert.
     * Für die Karten reicht die Antwortanzahl — die Antworten selbst
     * holt man erst auf der Detailseite (findById).
     */
    public function list(?int $subjectId): array
    {
        $sql = '
            SELECT
                hr.id, hr.title, hr.description, hr.created_at,
                u.display_name AS author_name,
                s.id AS subject_id, s.name AS subject_name,
                (SELECT COUNT(*) FROM help_request_replies r WHERE r.request_id = hr.id) AS reply_count
            FROM help_requests hr
            INNER JOIN users u ON u.id = hr.author_id
            INNER JOIN subjects s ON s.id = hr.subject_id
        ';
        $params = [];

        if ($subjectId !== null) {
            $sql .= ' WHERE hr.subject_id = :subject_id ';
            $params['subject_id'] = $subjectId;
        }

        $sql .= ' ORDER BY hr.created_at DESC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Ein Gesuch inkl. aller Antworten (chronologisch) für die Detailseite.
     * null, wenn es die ID nicht gibt -> Endpunkt antwortet dann mit 404.
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('
            SELECT
                hr.id, hr.title, hr.description, hr.created_at,
                u.display_name AS author_name,
                s.id AS subject_id, s.name AS subject_name
            FROM help_requests hr
            INNER JOIN users u ON u.id = hr.author_id
            INNER JOIN subjects s ON s.id = hr.subject_id
            WHERE hr.id = :id
        ');
        $stmt->execute(['id' => $id]);
        $request = $stmt->fetch();

        if ($request === false) {
            return null;
        }

        $request['replies'] = $this->repliesFor($id);
        return $request;
    }

    public function create(int $authorId, int $subjectId, string $title, string $description): int
    {
        $stmt = $this->db->prepare('
            INSERT INTO help_requests (author_id, subject_id, title, description)
            VALUES (:author_id, :subject_id, :title, :description)
        ');
        $stmt->execute([
            'author_id' => $authorId,
            'subject_id' => $subjectId,
            'title' => $title,
            'description' => $description,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function addReply(int $requestId, int $authorId, string $content): int
    {
        $stmt = $this->db->prepare('
            INSERT INTO help_request_replies (request_id, author_id, content)
            VALUES (:request_id, :author_id, :content)
        ');
        $stmt->execute([
            'request_id' => $requestId,
            'author_id' => $authorId,
            'content' => $content,
        ]);

        return (int) $this->db->lastInsertId();
    }

    /** Prüft nur, ob es das Gesuch gibt — für create.php/reply.php ohne vollen Datensatz. */
    public function exists(int $id): bool
    {
        $stmt = $this->db->prepare('SELECT 1 FROM help_requests WHERE id = :id');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() !== false;
    }

    private function repliesFor(int $requestId): array
    {
        $stmt = $this->db->prepare('
            SELECT r.content, r.created_at, u.display_name AS author_name
            FROM help_request_replies r
            INNER JOIN users u ON u.id = r.author_id
            WHERE r.request_id = :id
            ORDER BY r.created_at ASC
        ');
        $stmt->execute(['id' => $requestId]);
        return $stmt->fetchAll();
    }
}
