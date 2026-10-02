<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Order;
use Illuminate\Database\Eloquent\Collection;

class OrderReport
{
    public function latestFor(Customer $customer): Order
    {
        return $customer->orders()->latest('id')->first();
    }

    public function unshipped(): Collection
    {
        return Order::query()->whereNull('shipped_at')->orderBy('id')->get();
    }

    public function topCustomers(int $limit = 3): array
    {
        return Customer::query()->with('orders.lines')->get()
            ->sortByDesc(fn (Customer $customer) => $customer->lifetimeValuePence())
            ->take($limit)
            ->map(fn (Customer $customer) => ['name' => $customer->name, 'value' => $customer->lifetimeValuePence()])
            ->values()
            ->all();
    }

    public function emailsFor(Collection $orders): array
    {
        return $orders->map(fn (Order $order) => $order->customer->email)->unique()->values()->all();
    }
}
