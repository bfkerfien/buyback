<?php

namespace App\\Console\\Commands;

use Illuminate\\Console\\Command;
use Illuminate\\Support\\Facades\\Artisan;
use Illuminate\\Support\\Facades\\DB;

class RepairEveCatalog extends Command
{
    protected $signature = 'buyback:repair-catalog {directory=/var/www/html/data : Directory containing the CCP SDE JSONL files}';
    protected $description = 'Re-import the complete CCP EVE item type and category catalog and verify it.';

    public function handle(): int
    {
        $directory = rtrim($this->argument('directory'), '/');
        $files = [
            'types' => $directory . '/types.jsonl',
            'categories' => $directory . '/categories.jsonl',
            'groups' => $directory . '/groups.jsonl',
        ];

        foreach ($files as $name => $path) {
            if (!is_file($path) || !is_readable($path)) {
                $this->error("Missing or unreadable SDE file ({$name}): {$path}");
                return self::FAILURE;
            }
        }

        $this->info('Importing all published EVE item types...');
        $exit = Artisan::call('buyback:import-types', ['file' => $files['types']]);
        $this->output->write(Artisan::output());
        if ($exit !== self::SUCCESS) {
            $this->error('The item type import failed.');
            return self::FAILURE;
        }

        $this->info('Importing EVE categories and groups...');
        $exit = Artisan::call('buyback:import-taxonomy', [
            'categories' => $files['categories'],
            'groups' => $files['groups'],
        ]);
        $this->output->write(Artisan::output());
        if ($exit !== self::SUCCESS) {
            $this->error('The category/group import failed.');
            return self::FAILURE;
        }

        $typeCount = DB::table('eve_item_types')->where('published', true)->count();
        $groupCount = DB::table('eve_item_groups')->count();
        $veldspar = DB::table('eve_item_types')->where('type_id', 1230)->value('type_name');

        $this->newLine();
        $this->info('Catalog verification');
        $this->line('Published item types: ' . number_format($typeCount));
        $this->line('Item groups with category mapping: ' . number_format($groupCount));
        $this->line('Veldspar type 1230: ' . ($veldspar ?: 'MISSING'));

        if ($typeCount < 1000 || $groupCount < 100) {
            $this->error('Catalog verification failed: the database still does not contain the complete EVE catalog. Check that these are the current CCP JSONL files.');
            return self::FAILURE;
        }

        $this->info('EVE item catalog and category mappings have been repaired.');
        return self::SUCCESS;
    }
}
