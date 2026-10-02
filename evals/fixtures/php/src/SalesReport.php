<?php

declare(strict_types=1);

namespace Shop;

final class SalesReport
{
    /**
     * @param array $sales sku => units sold
     */
    public function topSellers(Catalogue $catalogue, array $sales, int $limit = 3): array
    {
        arsort($sales);
        $top = [];
        foreach (array_slice($sales, 0, $limit, true) as $sku => $units) {
            $top[] = ['name' => $catalogue->find($sku)->name, 'units' => $units];
        }

        return $top;
    }

    public function revenueByCategory(Catalogue $catalogue, array $sales): array
    {
        $revenue = [];
        foreach ($sales as $sku => $units) {
            $product = $catalogue->find($sku);
            $label = $product->categoryLabel();
            $revenue[$label] = ($revenue[$label] ?? 0) + $product->pricePence * $units;
        }

        return $revenue;
    }
}
