<?php

declare(strict_types=1);

final class SubjectRepository
{
    public function __construct(private PDO $db)
    {
    }

    public function all(): array
    {
        return $this->db->query('SELECT id, name FROM subjects ORDER BY name')->fetchAll();
    }
}
