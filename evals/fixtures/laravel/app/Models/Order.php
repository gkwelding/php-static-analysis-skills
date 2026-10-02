<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    protected $fillable = ['customer_id', 'reference', 'shipped_at'];

    protected $casts = ['shipped_at' => 'datetime'];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(OrderLine::class);
    }

    public function totalPence(): int
    {
        return $this->lines->sum(fn (OrderLine $line) => $line->quantity * $line->unit_price_pence);
    }

    public function shippedOn(): string
    {
        return $this->shipped_at?->toDateString();
    }

    public function invoiceNumber(): string
    {
        return 'INV-'.str_pad($this->id, 6, '0', STR_PAD_LEFT);
    }
}
