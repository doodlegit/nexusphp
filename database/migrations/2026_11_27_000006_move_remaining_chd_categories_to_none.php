<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Nexus\Database\NexusDB;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('searchbox') || !Schema::hasTable('categories')) {
            throw new RuntimeException('Search box and category tables are required to move categories to None.');
        }

        $noneSearchBox = DB::table('searchbox')->where('id', 0)->first();
        if (!$noneSearchBox || $noneSearchBox->name !== 'None') {
            throw new RuntimeException('Search box ID 0 must be assigned to None before moving categories.');
        }

        $chdSearchBox = DB::table('searchbox')->where('name', 'chd')->first();
        if (!$chdSearchBox) {
            throw new RuntimeException('The chd search box was not found; unable to move its categories.');
        }

        DB::table('categories')
            ->where('mode', $chdSearchBox->id)
            ->update(['mode' => 0]);

        NexusDB::cache_del('category_content');
        NexusDB::cache_del("category_list_mode_{$chdSearchBox->id}");
        NexusDB::cache_del('category_list_mode_0');
    }

    public function down(): void
    {
        if (Schema::hasTable('categories') && DB::table('categories')->where('mode', 0)->exists()) {
            throw new RuntimeException('Cannot safely restore categories from None because new None categories may have been added.');
        }
    }
};
