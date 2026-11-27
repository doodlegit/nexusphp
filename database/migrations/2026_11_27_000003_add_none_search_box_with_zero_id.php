<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('searchbox')) {
            return;
        }

        $zeroSearchBox = DB::table('searchbox')->where('id', 0)->first();
        if ($zeroSearchBox && $zeroSearchBox->name !== 'None') {
            throw new RuntimeException('Search box ID 0 is already assigned to another section.');
        }

        $noneIds = DB::table('searchbox')->where('name', 'None')->pluck('id');
        foreach ($noneIds as $noneId) {
            if ((int) $noneId === 0) {
                continue;
            }

            if (Schema::hasTable('categories')) {
                DB::table('categories')->where('mode', $noneId)->update(['mode' => 0]);
            }
            DB::table('searchbox')->where('id', $noneId)->delete();
        }

        $attributes = [
            'name' => 'None',
            'showsubcat' => 1,
            'showsource' => 1,
            'showmedium' => 1,
            'showcodec' => 1,
            'showstandard' => 1,
            'showprocessing' => 1,
            'showteam' => 1,
            'showaudiocodec' => 1,
            'catsperrow' => 8,
            'catpadding' => 3,
            'custom_fields' => '',
            'custom_fields_display_name' => '',
            'custom_fields_display' => '',
        ];

        if ($zeroSearchBox) {
            DB::table('searchbox')->where('id', 0)->update($attributes);
            return;
        }

        DB::table('searchbox')->insert($attributes);
        $generatedId = DB::table('searchbox')->where('name', 'None')->value('id');
        DB::table('searchbox')->where('id', $generatedId)->update(['id' => 0]);
    }

    public function down(): void
    {
        if (!Schema::hasTable('searchbox')) {
            return;
        }

        if (Schema::hasTable('categories') && DB::table('categories')->where('mode', 0)->exists()) {
            throw new RuntimeException('Cannot remove the None search box while categories are assigned to it.');
        }

        DB::table('searchbox')->where('id', 0)->where('name', 'None')->delete();
    }
};
