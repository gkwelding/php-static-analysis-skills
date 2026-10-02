<?php

declare(strict_types=1);

namespace Shop;

final class Catalogue
{
    private array $products = [];

    public function add(Product $product): void
    {
        $this->products[$product->sku] = $product;
    }

    public function find(string $sku): ?Product
    {
        return $this->products[$sku] ?? null;
    }

    public function priceOf(string $sku): int
    {
        return $this->find($sku)->pricePence;
    }

    public function inCategory(string $category): array
    {
        return array_values(array_filter(
            $this->products,
            fn (Product $product) => $product->category === $category,
        ));
    }

    public function cheapest(): Product
    {
        $sorted = $this->products;
        usort($sorted, fn (Product $a, Product $b) => $a->pricePence <=> $b->pricePence);

        return $sorted[0] ?? null;
    }
}
