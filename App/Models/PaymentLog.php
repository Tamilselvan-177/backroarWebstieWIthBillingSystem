<?php

namespace App\Models;

class PaymentLog extends BaseModel
{
    protected $table = 'payment_logs';

    public function log($orderId, $method, $transactionId, $amount, $status, $response)
    {
        return $this->create([
            'order_id' => (int)$orderId,
            'payment_method' => $method,
            'transaction_id' => $transactionId,
            'amount' => $amount,
            'status' => $status,
            'response_data' => is_string($response) ? $response : json_encode($response)
        ]);
    }
}

