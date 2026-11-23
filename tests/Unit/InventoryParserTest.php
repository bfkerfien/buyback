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


    public function test_it_parses_marketbaiters_refine_cards_without_turning_result_columns_into_item_names(): void
    {
        $items = (new InventoryParser())->parseMarketBaiters(implode("\\n", [
            'Compressed Arkonor',
            'Refine',
            '10 batches · 90.63% yield',
            "1,000\\t160\\tore\\trefined\\t\\t2.87M",
            'Compressed Arkonor II-Grade',
            'Refine',
            '10 batches · 90.63% yield',
            '1,000 160 ore refined 3.02M',
        ]));

        $this->assertCount(2, $items);
        $this->assertSame('Compressed Arkonor', $items[0]['name']);
        $this->assertSame(1000, $items[0]['quantity']);
        $this->assertEqualsWithDelta(2870.0, $items[0]['unit_price'], 0.01);
        $this->assertSame('Compressed Arkonor II-Grade', $items[1]['name']);
        $this->assertSame(1000, $items[1]['quantity']);
        $this->assertEqualsWithDelta(3020.0, $items[1]['unit_price'], 0.01);
    }

    public function test_it_ignores_marketbaiters_refine_labels_and_non_item_lines(): void
    {
        $items = (new InventoryParser())->parseMarketBaiters(
            "Refine\\n10 batches · 90.63% yield\\n1,000 160 ore refined 2.87M\\n"
            ."This is not an item\\nSome unrelated 123"
        );

        $this->assertSame([], $items);
    }

    public function test_it_parses_item_name_followed_by_quantity(): void
    {
        $items = (new InventoryParser())->parse("Compressed Mercoxit 100000");

        $this->assertSame([
            ['name' => 'Compressed Mercoxit', 'quantity' => 100000],
        ], $items);
    }
}
