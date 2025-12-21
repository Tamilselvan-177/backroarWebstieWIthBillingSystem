<?php

namespace App\Models;

class CounterStaff extends BaseModel
{
    protected $table = 'counter_staff';

    public function isAssigned(int $counterId, int $userId): bool
    {
        $sql = "SELECT id FROM {$this->table} WHERE counter_id = :cid AND user_id = :uid LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['cid' => $counterId, 'uid' => $userId]);
        return (bool)$stmt->fetch();
    }
}

