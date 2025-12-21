<?php

namespace App\Models;

use PDO;

class BillSequence extends BaseModel
{
    protected $table = 'bill_sequences';

    public function nextNumber(int $storeId, string $financialYear, string $storeCode): ?string
    {
        try {
            $this->db->beginTransaction();
            $stmt = $this->db->prepare("SELECT id, current_seq FROM {$this->table} WHERE store_id = :sid AND financial_year = :fy FOR UPDATE");
            $stmt->execute(['sid' => $storeId, 'fy' => $financialYear]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                $init = $this->db->prepare("INSERT INTO {$this->table} (store_id, financial_year, current_seq, prefix) VALUES (:sid, :fy, 0, 'POS')");
                $init->execute(['sid' => $storeId, 'fy' => $financialYear]);
                $row = ['id' => (int)$this->db->lastInsertId(), 'current_seq' => 0];
            }
            $next = (int)$row['current_seq'] + 1;
            $upd = $this->db->prepare("UPDATE {$this->table} SET current_seq = :n, updated_at = NOW() WHERE id = :id");
            $upd->execute(['n' => $next, 'id' => (int)$row['id']]);
            $seq = str_pad((string)$next, 5, '0', STR_PAD_LEFT);
            $code = strtoupper($storeCode);
            $bill = "POS-{$code}-{$financialYear}-{$seq}";
            $this->db->commit();
            return $bill;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            return null;
        }
    }
}

