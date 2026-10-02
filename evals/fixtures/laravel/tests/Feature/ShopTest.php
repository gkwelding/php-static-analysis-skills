<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Services\OrderReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShopTest extends TestCase
{
    use RefreshDatabase;

    private function order(Customer $customer, string $reference, array $lines, ?string $shippedAt = null): Order
    {
        $order = Order::create(['customer_id' => $customer->id, 'reference' => $reference, 'shipped_at' => $shippedAt]);
        foreach ($lines as [$sku, $quantity, $price]) {
            $order->lines()->create(['sku' => $sku, 'quantity' => $quantity, 'unit_price_pence' => $price]);
        }

        return $order;
    }

    public function test_order_totals_and_customer_lifetime_value(): void
    {
        $ann = Customer::create(['name' => 'Ann', 'email' => 'ann@example.com', 'tier' => 'gold']);
        $this->order($ann, 'R1', [['A1', 2, 1250], ['B2', 1, 300]]);
        $this->order($ann, 'R2', [['B2', 4, 300]]);

        $this->assertSame(2800, Order::where('reference', 'R1')->firstOrFail()->totalPence());
        $this->assertSame(4000, $ann->lifetimeValuePence());
    }

    public function test_tier_scope(): void
    {
        Customer::create(['name' => 'Ann', 'email' => 'ann@example.com', 'tier' => 'gold']);
        Customer::create(['name' => 'Bob', 'email' => 'bob@example.com']);

        $this->assertSame(['Ann'], Customer::tier('gold')->pluck('name')->all());
    }

    public function test_invoice_number_and_shipped_date(): void
    {
        $ann = Customer::create(['name' => 'Ann', 'email' => 'ann@example.com']);
        $order = $this->order($ann, 'R1', [], '2026-03-04 10:00:00');

        $this->assertSame('INV-'.str_pad((string) $order->id, 6, '0', STR_PAD_LEFT), $order->invoiceNumber());
        $this->assertSame('2026-03-04', $order->shippedOn());
    }

    public function test_report_latest_unshipped_top_customers_and_emails(): void
    {
        $ann = Customer::create(['name' => 'Ann', 'email' => 'ann@example.com']);
        $bob = Customer::create(['name' => 'Bob', 'email' => 'bob@example.com']);
        $cat = Customer::create(['name' => 'Cat', 'email' => 'cat@example.com']);
        $this->order($ann, 'R1', [['A1', 1, 1000]], '2026-03-01 09:00:00');
        $this->order($ann, 'R2', [['A1', 1, 500]]);
        $this->order($bob, 'R3', [['A1', 3, 1000]]);
        $this->order($cat, 'R4', [['B2', 1, 100]]);
        $report = new OrderReport();

        $this->assertSame('R2', $report->latestFor($ann)->reference);
        $this->assertSame(['R2', 'R3', 'R4'], $report->unshipped()->pluck('reference')->all());
        $this->assertSame([['name' => 'Bob', 'value' => 3000], ['name' => 'Ann', 'value' => 1500]], $report->topCustomers(2));
        $this->assertSame(['ann@example.com', 'bob@example.com'], $report->emailsFor(Order::whereIn('reference', ['R1', 'R2', 'R3'])->get()));
    }

    public function test_order_endpoints(): void
    {
        $ann = Customer::create(['name' => 'Ann', 'email' => 'ann@example.com']);
        $shipped = $this->order($ann, 'R1', [['A1', 2, 1250]], '2026-03-04 10:00:00');
        $open = $this->order($ann, 'R2', [['B2', 1, 300]]);

        $this->getJson("/orders/{$shipped->id}")->assertOk()->assertExactJson([
            'invoice' => $shipped->invoiceNumber(),
            'customer' => 'Ann',
            'total_pence' => 2500,
            'shipped_on' => '2026-03-04',
        ]);
        $this->getJson("/orders/{$open->id}")->assertOk()->assertJsonPath('shipped_on', null);
        $this->getJson('/orders/unshipped')->assertOk()->assertExactJson(['R2']);
    }
}
