<?php

namespace App\Models;

class Store extends BaseModel
{
    protected $table = 'stores';

    public function getActive()
    {
        $sql = "SELECT * FROM {$this->table} WHERE is_active = 1 ORDER BY name ASC";
        return $this->query($sql);
    }

    public function findByCode(string $code)
    {
        $sql = "SELECT * FROM {$this->table} WHERE code = :code LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['code' => $code]);
        return $stmt->fetch();
    }
}
