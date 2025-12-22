<?php

namespace App\Models;

class PosPayment extends BaseModel
{
    protected $table = 'pos_payments';

    public function record(int $orderId, string $method, float $amount, ?string $reference = null)
    {
        if ($amount <= 0) {
            return false;
        }
        try {
            return $this->create([
                'pos_order_id' => $orderId,
                'method' => $method,
                'amount' => $amount,
                'reference' => $reference
            ]);
        } catch (\Throwable $e) {
            return false;
        }
    }
}
