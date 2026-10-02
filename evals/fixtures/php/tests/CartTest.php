<?php

declare(strict_types=1);

namespace Shop\Tests;

use PHPUnit\Framework\TestCase;
use Shop\Cart;
use Shop\Catalogue;
use Shop\Product;

final class CartTest extends TestCase
{
    private function cart(): Cart
    {
        $catalogue = new Catalogue();
        $catalogue->add(new Product('A1', 'Hammer', 1250, 'tools'));
        $catalogue->add(new Product('B2', 'Nails', 300, 'tools'));

        return new Cart($catalogue);
    }

    public function testAddingTheSameSkuTwiceMergesTheLine(): void
    {
        $cart = $this->cart();
        $cart->add('B2', 2);
        $cart->add('B2', 3);

        $this->assertCount(1, $cart);
        $this->assertSame(1500, $cart->totalPence());
    }

    public function testTotalAcrossLines(): void
    {
        $cart = $this->cart();
        $cart->add('A1');
        $cart->add('B2', 2);

        $this->assertSame(1850, $cart->totalPence());
        $this->assertSame(['Hammer', 'Nails'], array_map(fn ($line) => $line->product->name, iterator_to_array($cart)));
    }

    public function testAnEmptyCartTotalsZero(): void
    {
        $this->assertSame(0, $this->cart()->totalPence());
    }
}
