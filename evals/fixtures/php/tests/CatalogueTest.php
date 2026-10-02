<?php

declare(strict_types=1);

namespace Shop\Tests;

use PHPUnit\Framework\TestCase;
use Shop\Catalogue;
use Shop\Product;

final class CatalogueTest extends TestCase
{
    private function catalogue(): Catalogue
    {
        $catalogue = new Catalogue();
        $catalogue->add(new Product('A1', 'Hammer', 1250, 'tools'));
        $catalogue->add(new Product('B2', 'Nails', 300, 'tools'));
        $catalogue->add(new Product('C3', 'Gift card', 2000));

        return $catalogue;
    }

    public function testFindReturnsTheProductOrNull(): void
    {
        $this->assertSame('Hammer', $this->catalogue()->find('A1')?->name);
        $this->assertNull($this->catalogue()->find('Z9'));
    }

    public function testPriceOfAKnownProduct(): void
    {
        $this->assertSame(300, $this->catalogue()->priceOf('B2'));
    }

    public function testInCategoryKeepsInsertionOrder(): void
    {
        $names = array_map(fn (Product $p) => $p->name, $this->catalogue()->inCategory('tools'));

        $this->assertSame(['Hammer', 'Nails'], $names);
    }

    public function testCheapest(): void
    {
        $this->assertSame('B2', $this->catalogue()->cheapest()->sku);
    }

    public function testProductFromArray(): void
    {
        $product = Product::fromArray(['sku' => 'D4', 'name' => 'Saw', 'price' => 1800]);

        $this->assertSame(1800, $product->pricePence);
        $this->assertSame('Uncategorised', $product->categoryLabel());
    }
}
