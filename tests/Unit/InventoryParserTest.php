<?php

namespace Tests\Unit;

use App\Services\Appraisal\InventoryParser;
use PHPUnit\Framework\TestCase;

class InventoryParserTest extends TestCase
{
    public function test_it_parses_tab_separated_inventory_and_merges_duplicates(): void
    {
        $items = (new InventoryParser())->parse("Tritanium\t100\nPyerite\t25\nTritanium\t50");

        $this->assertSame([
            ['name' => 'Tritanium', 'quantity' => 150],
            ['name' => 'Pyerite', 'quantity' => 25],
        ], $items);
    }

    public function test_it_parses_quantity_prefix_and_suffix(): void
    {
        $items = (new InventoryParser())->parse("1,000 x Tritanium\nPyerite x 25");

        $this->assertSame([
            ['name' => 'Tritanium', 'quantity' => 1000],
            ['name' => 'Pyerite', 'quantity' => 25],
        ], $items);
    }

    public function test_it_parses_plain_quantity_prefix_without_x(): void
    {
        $items = (new InventoryParser())->parse("10000 Compressed Mercoxit\n10,000 Compressed Mercoxit");

        $this->assertSame([
            ['name' => 'Compressed Mercoxit', 'quantity' => 20000],
        ], $items);
    }
}
