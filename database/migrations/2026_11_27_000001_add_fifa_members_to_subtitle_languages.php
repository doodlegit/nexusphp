<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('language') || !Schema::hasTable('countries')) {
            return;
        }

        $historicalOrNonFifaIds = [38, 58, 63, 71, 92, 105, 107];
        $existingNames = DB::table('language')->pluck('lang_name')->map(fn ($name) => trim((string) $name))->all();

        $countries = DB::table('countries')
            ->whereBetween('id', [1, 226])
            ->whereNotIn('id', $historicalOrNonFifaIds)
            ->orderBy('name')
            ->get();

        $nextId = ((int) DB::table('language')->max('id')) + 1;

        foreach ($countries as $country) {
            $name = trim($country->name);
            if ($name === '' || in_array($name, $existingNames, true)) {
                continue;
            }

            DB::table('language')->insert([
                'id' => $nextId++,
                'lang_name' => $name,
                'flagpic' => $country->flagpic,
                'sub_lang' => 1,
                'rule_lang' => 0,
                'site_lang' => 0,
                'site_lang_folder' => '',
                'trans_state' => 'unavailable',
            ]);
            $existingNames[] = $name;
        }

        \Nexus\Database\NexusDB::cache_del('sub_lang_lang_list');
    }

    public function down(): void
    {
        if (!Schema::hasTable('language')) {
            return;
        }
        DB::table('language')->where('id', '>', 31)->delete();
        \Nexus\Database\NexusDB::cache_del('sub_lang_lang_list');
    }
};
