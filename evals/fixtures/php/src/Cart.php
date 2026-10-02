<?php

declare(strict_types=1);

namespace Shop;

final class Cart implements \IteratorAggregate, \Countable
{
    private array $lines = [];

    public function __construct(private readonly Catalogue $catalogue)
    {
    }

    public function add(string $sku, int $quantity = 1): void
    {
        $existing = $this->lines[$sku] ?? null;
        $this->lines[$sku] = new CartLine(
            $this->catalogue->find($sku),
            ($existing === null ? 0 : $existing->quantity) + $quantity,
        );
    }

    public function totalPence(): int
    {
        return array_sum(array_map(fn (CartLine $line) => $line->subtotalPence(), $this->lines));
    }

    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator(array_values($this->lines));
    }

    public function count(): int
    {
        return count($this->lines);
    }
}
