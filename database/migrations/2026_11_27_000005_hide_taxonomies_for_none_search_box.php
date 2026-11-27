<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Nexus\Database\NexusDB;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('searchbox')) {
            throw new RuntimeException('Search box table is required to configure None.');
        }

        $noneSearchBox = DB::table('searchbox')->where('id', 0)->first();
        if (!$noneSearchBox || $noneSearchBox->name !== 'None') {
            throw new RuntimeException('Search box ID 0 must be assigned to None.');
        }

        DB::table('searchbox')->where('id', 0)->update(['showsubcat' => 0]);

        NexusDB::cache_del('search_box_content');
    }

    public function down(): void
    {
        if (Schema::hasTable('searchbox')) {
            DB::table('searchbox')->where('id', 0)->where('name', 'None')->update(['showsubcat' => 1]);
            NexusDB::cache_del('search_box_content');
        }
    }
};
