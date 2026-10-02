<?php

declare(strict_types=1);

namespace Shop\Tests;

use PHPUnit\Framework\TestCase;
use Shop\Catalogue;
use Shop\Product;
use Shop\SalesReport;

final class SalesReportTest extends TestCase
{
    private function catalogue(): Catalogue
    {
        $catalogue = new Catalogue();
        $catalogue->add(new Product('A1', 'Hammer', 1250, 'tools'));
        $catalogue->add(new Product('B2', 'Nails', 300, 'tools'));
        $catalogue->add(new Product('C3', 'Gift card', 2000));

        return $catalogue;
    }

    public function testTopSellersByUnits(): void
    {
        $top = (new SalesReport())->topSellers($this->catalogue(), ['A1' => 2, 'B2' => 40, 'C3' => 5], 2);

        $this->assertSame([['name' => 'Nails', 'units' => 40], ['name' => 'Gift card', 'units' => 5]], $top);
    }

    public function testRevenueByCategory(): void
    {
        $revenue = (new SalesReport())->revenueByCategory($this->catalogue(), ['A1' => 2, 'B2' => 10, 'C3' => 1]);

        $this->assertSame(['Tools' => 5500, 'Uncategorised' => 2000], $revenue);
    }
}
