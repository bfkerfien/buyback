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


    public function test_it_parses_marketbaiters_api_items_without_double_counting_reprocessing_sub_items(): void
    {
        $items = (new InventoryParser())->parseMarketBaitersJson([
            'token' => 'mBLZSEgQ',
            'total_payout' => 3089385232.64,
            'items' => [
                [
                    'name' => 'Compressed Arkonor',
                    'type_id' => 62568,
                    'quantity' => 1000,
                    'payout' => 2886188.14,
                    'sub_items' => [
                        ['name' => 'Pyerite', 'type_id' => 35, 'quantity' => 29001, 'payout' => 427451.54],
                        ['name' => 'Mexallon', 'type_id' => 36, 'quantity' => 10875, 'payout' => 538606.12],
                    ],
                ],
                [
                    'name' => 'Compressed Arkonor II-Grade',
                    'type_id' => 62569,
                    'quantity' => 1000,
                    'payout' => 3031758.83,
                    'sub_items' => [],
                ],
            ],
        ]);

        $this->assertCount(2, $items);
        $this->assertSame('Compressed Arkonor', $items[0]['name']);
        $this->assertSame(1000, $items[0]['quantity']);
        $this->assertEqualsWithDelta(2886.18814, $items[0]['unit_payout'], 0.00001);
        $this->assertSame('Compressed Arkonor II-Grade', $items[1]['name']);
        $this->assertSame(1000, $items[1]['quantity']);
        $this->assertEqualsWithDelta(3031.75883, $items[1]['unit_price'], 0.00001);
    }

    public function test_it_parses_marketbaiters_refine_cards_without_turning_result_columns_into_item_names(): void
    {
        $items = (new InventoryParser())->parseMarketBaiters(implode("\n", [
            'Compressed Arkonor',
            'Refine',
            '10 batches · 90.63% yield',
            "1,000\t160\tore\trefined\t\t2.87M",
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
            "Refine\n10 batches · 90.63% yield\n1,000 160 ore refined 2.87M\n"
            ."This is not an item\nSome unrelated 123"
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
    public function test_it_parses_rendered_marketbaiters_appraisal_table_html(): void
    {
        $html = <<<'HTML'
<table>
    <tbody>
        <tr>
            <td>
                <div>
                    <span class="font-medium text-bright">Compressed Arkonor</span>
                    <button>Refine</button>
                </div>
                <div>10 batches · 90.63% yield</div>
            </td>
            <td>1,000</td>
            <td>160</td>
            <td data-line-type="true">ore</td>
            <td><span>refined</span></td>
            <td></td>
            <td><span class="text-money">2.89M</span></td>
        </tr>
        <tr>
            <td>
                <div>
                    <span class="font-medium text-bright">Compressed Arkonor II-Grade</span>
                    <button>Refine</button>
                </div>
                <div>10 batches · 90.63% yield</div>
            </td>
            <td>1,000</td>
            <td>160</td>
            <td data-line-type="true">ore</td>
            <td><span>refined</span></td>
            <td></td>
            <td><span class="text-money">3.03M</span></td>
        </tr>
    </tbody>
</table>
HTML;

        $items = (new InventoryParser())->parseMarketBaitersHtml($html);

        $this->assertCount(2, $items);
        $this->assertSame('Compressed Arkonor', $items[0]['name']);
        $this->assertSame(1000, $items[0]['quantity']);
        $this->assertEqualsWithDelta(2890.0, $items[0]['unit_payout'], 0.01);
        $this->assertSame('Compressed Arkonor II-Grade', $items[1]['name']);
        $this->assertSame(1000, $items[1]['quantity']);
        $this->assertEqualsWithDelta(3030.0, $items[1]['unit_price'], 0.01);
    }

}
