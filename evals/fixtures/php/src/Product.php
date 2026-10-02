<?php

declare(strict_types=1);

namespace Shop;

final class Product
{
    public function __construct(
        public readonly string $sku,
        public readonly string $name,
        public readonly int $pricePence,
        public readonly ?string $category = null,
    ) {
    }

    public static function fromArray(array $row): self
    {
        return new self($row['sku'], $row['name'], $row['price'], $row['category'] ?? null);
    }

    public function categoryLabel(): string
    {
        return $this->category === null ? 'Uncategorised' : ucfirst($this->category);
    }
}
