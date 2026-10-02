<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderLine extends Model
{
    public $timestamps = false;

    protected $fillable = ['order_id', 'sku', 'quantity', 'unit_price_pence'];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
