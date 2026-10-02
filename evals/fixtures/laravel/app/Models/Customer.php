<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    protected $fillable = ['name', 'email', 'tier'];

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function scopeTier($query, string $tier)
    {
        return $query->where('tier', $tier);
    }

    public function lifetimeValuePence(): int
    {
        return $this->orders->sum(fn (Order $order) => $order->totalPence());
    }
}
