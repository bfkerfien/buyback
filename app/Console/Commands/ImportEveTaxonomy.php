<?php

namespace App\\Console\\Commands;

use Illuminate\\Console\\Command;
use Illuminate\\Support\\Facades\\DB;
use Throwable;

class ImportEveTaxonomy extends Command
{
    protected $signature = 'buyback:import-taxonomy {categories : CCP categories.jsonl path} {groups : CCP groups.jsonl path}';
    protected $description = 'Import EVE item category and group names used for buyback rate rules.';

    public function handle(): int
    {
        foreach ([$this->argument('categories'), $this->argument('groups')] as $path) {
            if (!is_file($path) || !is_readable($path)) { $this->error("Cannot read SDE file: {$path}"); return self::FAILURE; }
        }
        $categories = [];
        foreach (file($this->argument('categories'), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            try { $row = json_decode($line, true, 512, JSON_THROW_ON_ERROR); } catch (Throwable) { continue; }
            $id = $row['_key'] ?? $row['categoryID'] ?? $row['category_id'] ?? null;
            $name = $row['name']['en'] ?? $row['name'] ?? $row['categoryName'] ?? null;
            if ($id && is_numeric($id) && is_string($name) && ($row['published'] ?? true)) $categories[(int)$id] = trim($name);
        }
        $batch = []; $count = 0; $now = now();
        foreach (file($this->argument('groups'), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            try { $row = json_decode($line, true, 512, JSON_THROW_ON_ERROR); } catch (Throwable) { continue; }
            $id = $row['_key'] ?? $row['groupID'] ?? $row['group_id'] ?? null;
            $categoryId = $row['categoryID'] ?? $row['category_id'] ?? null;
            $name = $row['name']['en'] ?? $row['name'] ?? $row['groupName'] ?? null;
            if (!$id || !is_numeric($id) || !$categoryId || !isset($categories[(int)$categoryId]) || !is_string($name) || !($row['published'] ?? true)) continue;
            $batch[] = ['group_id'=>(int)$id,'category_id'=>(int)$categoryId,'group_name'=>trim($name),'category_name'=>$categories[(int)$categoryId],'created_at'=>$now,'updated_at'=>$now];
            if (count($batch) >= 1000) { DB::table('eve_item_groups')->upsert($batch,['group_id'],['category_id','group_name','category_name','updated_at']); $count += count($batch); $batch=[]; }
        }
        if ($batch) { DB::table('eve_item_groups')->upsert($batch,['group_id'],['category_id','group_name','category_name','updated_at']); $count += count($batch); }
        $this->info("Imported or updated {$count} EVE item groups across " . count($categories) . " categories.");
        return self::SUCCESS;
    }
}
