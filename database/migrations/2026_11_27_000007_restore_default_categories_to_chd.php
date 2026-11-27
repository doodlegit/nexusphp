<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Nexus\Database\NexusDB;

return new class extends Migration
{
    private const DEFAULT_CATEGORY_IDS = [401, 402, 403, 404, 405, 406, 407, 408, 409];

    public function up(): void
    {
        if (!Schema::hasTable('searchbox') || !Schema::hasTable('categories')) {
            throw new RuntimeException('Search box and category tables are required to restore the default chd categories.');
        }

        $chdSearchBox = DB::table('searchbox')->where('name', 'chd')->first();
        if (!$chdSearchBox) {
            throw new RuntimeException('The chd search box was not found; unable to restore its default categories.');
        }

        DB::table('categories')
            ->whereIn('id', self::DEFAULT_CATEGORY_IDS)
            ->where('mode', 0)
            ->update(['mode' => $chdSearchBox->id]);

        $this->clearCategoryCaches((int) $chdSearchBox->id);
    }

    public function down(): void
    {
        if (Schema::hasTable('categories') && Schema::hasTable('searchbox')) {
            $chdMode = DB::table('searchbox')->where('name', 'chd')->value('id');
            if ($chdMode === null) {
                throw new RuntimeException('The chd search box was not found; unable to reverse the category migration.');
            }

            DB::table('categories')
                ->whereIn('id', self::DEFAULT_CATEGORY_IDS)
                ->where('mode', $chdMode)
                ->update(['mode' => 0]);

            $this->clearCategoryCaches((int) $chdMode);
        }
    }

    private function clearCategoryCaches(int $chdMode): void
    {
        NexusDB::cache_del('category_content');
        NexusDB::cache_del("category_list_mode_{$chdMode}");
        NexusDB::cache_del('category_list_mode_0');
    }
};
