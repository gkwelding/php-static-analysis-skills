<?php

declare(strict_types=1);

namespace Shop;

final class CartLine
{
    public function __construct(
        public readonly Product $product,
        public readonly int $quantity,
    ) {
    }

    public function subtotalPence(): int
    {
        return $this->product->pricePence * $this->quantity;
    }
}
