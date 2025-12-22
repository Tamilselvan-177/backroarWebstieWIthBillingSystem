<?php

namespace App\Models;

class PosOrder extends BaseModel
{
    protected $table = 'pos_orders';

    public function createOrder(array $data): int|false
    {
        return $this->create($data);
    }
}
