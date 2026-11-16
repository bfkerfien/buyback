<?php

namespace App\\Http\\Controllers;

use Illuminate\\Support\\Facades\\DB;
use Illuminate\\View\\View;

class PiPlannerController extends Controller
{
    public function index(): View
    {
        $recipes = DB::table('eve_planet_schematics as s')
            ->join('eve_item_types as output', 'output.type_id', '=', 's.product_type_id')
            ->join('eve_item_groups as output_group', 'output_group.group_id', '=', 'output.group_id')
            ->leftJoin('eve_planet_schematic_inputs as i', 'i.schematic_id', '=', 's.schematic_id')
            ->leftJoin('eve_item_types as input', 'input.type_id', '=', 'i.type_id')
            ->select(
                's.schematic_id', 's.name as schematic_name', 's.cycle_time',
                's.product_type_id', 's.product_quantity',
                'output.type_name as product_name', 'output_group.group_name as product_group',
                'i.type_id as input_type_id', 'i.quantity as input_quantity',
                'input.type_name as input_name'
            )
            ->orderBy('output.type_name')
            ->get()
            ->groupBy('schematic_id')
            ->map(function ($rows) {
                $first = $rows->first();
                return [
                    'id' => (int) $first->schematic_id,
                    'name' => $first->schematic_name,
                    'cycle_time' => (int) $first->cycle_time,
                    'output_id' => (int) $first->product_type_id,
                    'output_name' => $first->product_name,
                    'output_group' => $first->product_group,
                    'output_quantity' => (int) $first->product_quantity,
                    'inputs' => $rows->filter(fn ($r) => $r->input_type_id !== null)->map(fn ($r) => [
                        'id' => (int) $r->input_type_id,
                        'name' => $r->input_name ?: ('Type #' . $r->input_type_id),
                        'quantity' => (int) $r->input_quantity,
                    ])->values(),
                ];
            })->values();

        return view('pi-planner', ['recipes' => $recipes]);
    }
}
