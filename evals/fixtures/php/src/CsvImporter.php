<?php

namespace Shop;

final class CsvImporter
{
    public function __construct(private readonly Catalogue $catalogue)
    {
    }

    /**
     * Each line is "sku,name,price in pence[,category]". Returns the number of products added.
     */
    public function import(string $csv): int
    {
        $added = 0;
        foreach (explode("\n", trim($csv)) as $line) {
            $fields = str_getcsv($line, escape: '');
            $this->catalogue->add(new Product($fields[0], $fields[1], $fields[2], $fields[3] ?? null));
            $added++;
        }

        return $added;
    }

    public function importFile(string $path): int
    {
        return $this->import(file_get_contents($path));
    }
}
