<?php

namespace App\Models;

class Counter extends BaseModel
{
    protected $table = 'counters';

    public function getByStore(int $storeId)
    {
        $sql = "SELECT * FROM {$this->table} WHERE store_id = :sid AND is_active = 1 ORDER BY name ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['sid' => $storeId]);
        return $stmt->fetchAll();
    }

    public function verifyPin(int $counterId, string $pin): bool
    {
        $row = $this->find($counterId);
        if (!$row || !(int)($row['is_active'] ?? 0)) {
            return false;
        }
        $hash = (string)($row['pin_hash'] ?? '');
        $pin = (string)$pin;
        $info = \password_get_info($hash);
        if (($info['algo'] ?? 0) !== 0) {
            if (\password_verify($pin, $hash)) {
                if (\password_needs_rehash($hash, PASSWORD_DEFAULT)) {
                    $this->update($counterId, ['pin_hash' => \password_hash($pin, PASSWORD_DEFAULT)]);
                }
                return true;
            }
        }
        $isMd5 = (strlen($hash) === 32 && ctype_xdigit($hash));
        if ($isMd5 && strtolower($hash) === strtolower(md5($pin))) {
            $this->update($counterId, ['pin_hash' => \password_hash($pin, PASSWORD_DEFAULT)]);
            return true;
        }
        if ($hash === $pin && $hash !== '') {
            $this->update($counterId, ['pin_hash' => \password_hash($pin, PASSWORD_DEFAULT)]);
            return true;
        }
        return false;
    }
}
