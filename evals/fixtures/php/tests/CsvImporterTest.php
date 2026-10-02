<?php

declare(strict_types=1);

namespace Shop\Tests;

use PHPUnit\Framework\TestCase;
use Shop\Catalogue;
use Shop\CsvImporter;

final class CsvImporterTest extends TestCase
{
    public function testImportsEachLine(): void
    {
        $catalogue = new Catalogue();

        $added = (new CsvImporter($catalogue))->import("A1,Hammer,1250,tools\nB2,\"Nails, 100\",300\n");

        $this->assertSame(2, $added);
        $this->assertSame(1250, $catalogue->priceOf('A1'));
        $this->assertSame('Nails, 100', $catalogue->find('B2')?->name);
        $this->assertNull($catalogue->find('B2')?->category);
    }

    public function testImportFile(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'csv');
        file_put_contents($path, "C3,Saw,1800,tools\n");
        $catalogue = new Catalogue();

        $this->assertSame(1, (new CsvImporter($catalogue))->importFile($path));
        $this->assertSame(1800, $catalogue->priceOf('C3'));
        unlink($path);
    }
}
