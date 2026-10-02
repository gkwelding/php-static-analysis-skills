<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\OrderReport;
use Illuminate\Http\JsonResponse;

class OrderController extends Controller
{
    public function show(int $id): JsonResponse
    {
        $order = Order::with('lines')->find($id);

        return response()->json([
            'invoice' => $order->invoiceNumber(),
            'customer' => $order->customer->name,
            'total_pence' => $order->totalPence(),
            'shipped_on' => $order->shipped_at?->toDateString(),
        ]);
    }

    public function unshipped(OrderReport $report): JsonResponse
    {
        return response()->json($report->unshipped()->pluck('reference'));
    }
}
