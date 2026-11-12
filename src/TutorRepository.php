<?php

declare(strict_types=1);

/**
 * Kapselt alle Datenbankzugriffe für das Profil- und Such-Modul.
 * Kein ORM (ADR-004): direkte, vorbereitete SQL-Statements über PDO.
 */
final class TutorRepository
{
    public function __construct(private PDO $db)
    {
    }

    /**
     * Öffentliche Tutorenliste für die Startseite (Kapitel: Suche/Filter).
     * Nur Tutoren mit bereits ausgefülltem Profil werden angezeigt
     * (daher INNER JOIN, kein LEFT JOIN) — ein Tutor ohne Profil taucht
     * erst auf, sobald er einmal gespeichert hat.
     */
    public function search(?string $query, ?int $subjectId): array
    {
        $sql = "
            SELECT DISTINCT u.id AS tutor_id, u.display_name, tp.description, tp.availability, tp.price_model 
            FROM users u
            INNER JOIN tutor_profiles tp ON tp.user_id = u.id
            LEFT JOIN tutor_subjects ts ON ts.tutor_id = u.id
            LEFT JOIN subjects s ON s.id = ts.subject_id
            WHERE u.role = 'tutor'
        ";
        $params = [];

        if ($query !== null && $query !== '') {
            $sql .= ' AND (u.display_name LIKE :query_name OR s.name LIKE :query_subject) ';
            $params['query_name'] = "%{$query}%";
            $params['query_subject'] = "%{$query}%";
        }

        if ($subjectId !== null) {
            $sql .= '
                AND EXISTS (
                    SELECT 1 FROM tutor_subjects ts2
                    WHERE ts2.tutor_id = u.id AND ts2.subject_id = :subject_id
                )
            ';
            $params['subject_id'] = $subjectId;
        }

        $sql .= ' ORDER BY u.display_name';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $tutors = $stmt->fetchAll();

        // Für die Kartenansicht brauchen wir zusätzlich Fächer + Bewertungsschnitt.
        // Bewusst zwei zusätzliche Abfragen pro Tutor statt einem komplexen JOIN —
        // für die erwartete Nutzerzahl (NFA-001: bis 50 gleichzeitige Nutzer) völlig
        // ausreichend, dafür bleibt die Haupt-Query lesbar. Bei Bedarf später über
        // GROUP_CONCAT optimierbar.
        foreach ($tutors as &$tutor) {
            $tutorId = (int) $tutor['tutor_id'];
            $tutor['subjects'] = $this->subjectsForTutor($tutorId);
            $tutor['rating'] = $this->averageRating($tutorId);
        }

        return $tutors;
    }

    /**
     * Öffentliches Tutorprofil (für die Profil-Detailseite).
     * Gibt null zurück, wenn kein (fertig ausgefülltes) Profil existiert
     * -> Endpunkt antwortet dann mit 404.
     */
    public function findPublicById(int $tutorId): ?array
    {
        $stmt = $this->db->prepare('
            SELECT u.id AS tutor_id, u.display_name, tp.description, tp.availability, tp.price_model
            FROM users u
            INNER JOIN tutor_profiles tp ON tp.user_id = u.id
            WHERE u.id = :id AND u.role = "tutor"
        ');
        $stmt->execute(['id' => $tutorId]);
        $tutor = $stmt->fetch();

        if ($tutor === false) {
            return null;
        }

        $tutor['subjects'] = $this->subjectsForTutor($tutorId);
        $tutor['rating'] = $this->averageRating($tutorId);
        $tutor['ratings'] = $this->ratingsForTutor($tutorId);

        return $tutor;
    }

    /**
     * Eigenes Profil zum Bearbeiten laden. Anders als findPublicById()
     * mit LEFT JOIN, damit auch ein Tutor, der noch nie gespeichert hat,
     * ein (leeres) Profil zum Ausfüllen bekommt statt eines Fehlers.
     */
    public function findOwnProfile(int $userId): array
    {
        $stmt = $this->db->prepare('
            SELECT u.id AS tutor_id, u.display_name, tp.description, tp.availability, tp.price_model
            FROM users u
            LEFT JOIN tutor_profiles tp ON tp.user_id = u.id
            WHERE u.id = :id
        ');
        $stmt->execute(['id' => $userId]);
        $tutor = $stmt->fetch();

        $tutor['subjects'] = $this->subjectsForTutor($userId);

        return $tutor;
    }

    /**
     * Legt das Profil an (falls noch keins existiert) oder aktualisiert es,
     * und ersetzt die Fach-Zuordnungen. Alles in einer Transaktion, damit
     * nie ein halb gespeicherter Zustand entsteht (z. B. Profil ohne Fächer).
     */
    public function upsertProfile(int $userId, string $description, string $availability, string $priceModel, array $subjectIds): void
    {
        $this->db->beginTransaction();

        try {
            $stmt = $this->db->prepare('
                INSERT INTO tutor_profiles (user_id, description, availability, price_model)
                VALUES (:user_id, :description, :availability, :price_model)
                ON DUPLICATE KEY UPDATE
                    description = :description2,
                    availability = :availability2,
                    price_model = :price_model2
            ');
            $stmt->execute([
                'user_id' => $userId,
                'description' => $description,
                'availability' => $availability,
                'price_model' => $priceModel,
                'description2' => $description,
                'availability2' => $availability,
                'price_model2' => $priceModel,
            ]);

            $this->db->prepare('DELETE FROM tutor_subjects WHERE tutor_id = :id')
                ->execute(['id' => $userId]);

            $insertSubject = $this->db->prepare('
                INSERT INTO tutor_subjects (tutor_id, subject_id) VALUES (:tutor_id, :subject_id)
            ');
            foreach ($subjectIds as $subjectId) {
                $insertSubject->execute(['tutor_id' => $userId, 'subject_id' => (int) $subjectId]);
            }

            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    private function subjectsForTutor(int $tutorId): array
    {
        $stmt = $this->db->prepare('
            SELECT s.id, s.name
            FROM subjects s
            INNER JOIN tutor_subjects ts ON ts.subject_id = s.id
            WHERE ts.tutor_id = :id
            ORDER BY s.name
        ');
        $stmt->execute(['id' => $tutorId]);
        return $stmt->fetchAll();
    }

    private function averageRating(int $tutorId): array
    {
        $stmt = $this->db->prepare('
            SELECT ROUND(AVG(stars), 1) AS avg, COUNT(*) AS count
            FROM ratings
            WHERE tutor_id = :id
        ');
        $stmt->execute(['id' => $tutorId]);
        $row = $stmt->fetch();

        return [
            'avg' => $row['avg'] !== null ? (float) $row['avg'] : null,
            'count' => (int) $row['count'],
        ];
    }

    private function ratingsForTutor(int $tutorId, int $limit = 10): array
    {
        $stmt = $this->db->prepare('
            SELECT r.stars, r.comment, r.created_at, u.display_name AS rater_name
            FROM ratings r
            INNER JOIN users u ON u.id = r.rater_id
            WHERE r.tutor_id = :id
            ORDER BY r.created_at DESC
            LIMIT :limit
        ');
        $stmt->bindValue('id', $tutorId, PDO::PARAM_INT);
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
