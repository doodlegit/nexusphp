<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Nexus\Database\NexusDB;

return new class extends Migration
{
    private const DEFAULT_CATEGORIES = [
        ['id' => 401, 'class_name' => 'c_movies', 'name' => 'Movies', 'sort_index' => 8],
        ['id' => 402, 'class_name' => 'c_tvseries', 'name' => 'TV Series', 'sort_index' => 7],
        ['id' => 403, 'class_name' => 'c_tvshows', 'name' => 'TV Shows', 'sort_index' => 6],
        ['id' => 404, 'class_name' => 'c_doc', 'name' => 'Documentaries', 'sort_index' => 5],
        ['id' => 405, 'class_name' => 'c_anime', 'name' => 'Animations', 'sort_index' => 4],
        ['id' => 406, 'class_name' => 'c_mv', 'name' => 'Music Videos', 'sort_index' => 3],
        ['id' => 407, 'class_name' => 'c_sports', 'name' => 'Sports', 'sort_index' => 2],
        ['id' => 408, 'class_name' => 'c_hqaudio', 'name' => 'HQ Audio', 'sort_index' => 1],
        ['id' => 409, 'class_name' => 'c_misc', 'name' => 'Misc', 'sort_index' => 0],
    ];

    public function up(): void
    {
        if (!Schema::hasTable('searchbox') || !Schema::hasTable('categories')) {
            throw new RuntimeException('Search box and category tables are required to restore the default chd categories.');
        }

        $chdMode = DB::table('searchbox')->where('name', 'chd')->value('id');
        if ($chdMode === null) {
            throw new RuntimeException('The chd search box was not found; unable to restore its default categories.');
        }

        foreach (self::DEFAULT_CATEGORIES as $category) {
            DB::table('categories')->updateOrInsert(
                ['id' => $category['id']],
                [
                    'mode' => $chdMode,
                    'class_name' => $category['class_name'],
                    'name' => $category['name'],
                    'image' => 'catsprites.png',
                    'sort_index' => $category['sort_index'],
                    'icon_id' => 1,
                ]
            );
        }

        $this->clearCategoryCaches((int) $chdMode);
    }

    public function down(): void
    {
        if (!Schema::hasTable('searchbox') || !Schema::hasTable('categories')) {
            return;
        }

        $chdMode = DB::table('searchbox')->where('name', 'chd')->value('id');
        if ($chdMode === null) {
            throw new RuntimeException('The chd search box was not found; unable to reverse the category migration.');
        }

        DB::table('categories')
            ->whereIn('id', array_column(self::DEFAULT_CATEGORIES, 'id'))
            ->where('mode', $chdMode)
            ->update(['mode' => 0]);

        $this->clearCategoryCaches((int) $chdMode);
    }

    private function clearCategoryCaches(int $chdMode): void
    {
        NexusDB::cache_del('category_content');
        NexusDB::cache_del("category_list_mode_{$chdMode}");
        NexusDB::cache_del('category_list_mode_0');
    }
};
