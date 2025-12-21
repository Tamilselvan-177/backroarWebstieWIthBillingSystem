<?php

namespace App\Models;

class StockMovement extends BaseModel
{
    protected $table = 'stock_movements';

    public function log(int $storeId, int $productId, int $quantity, string $direction, string $reason, string $refType = null, int $refId = null, string $notes = null)
    {
        return $this->create([
            'store_id' => $storeId,
            'product_id' => $productId,
            'quantity' => $quantity,
            'direction' => $direction,
            'reason' => $reason,
            'reference_type' => $refType,
            'reference_id' => $refId,
            'notes' => $notes
        ]);
    }
}

