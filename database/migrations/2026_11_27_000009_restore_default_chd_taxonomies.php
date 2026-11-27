<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Nexus\Database\NexusDB;

return new class extends Migration
{
    private const DEFAULT_TAXONOMIES = [
        'codecs' => [
            ['id' => 1, 'name' => 'H.264'],
            ['id' => 2, 'name' => 'VC-1'],
            ['id' => 3, 'name' => 'Xvid'],
            ['id' => 4, 'name' => 'MPEG-2'],
            ['id' => 5, 'name' => 'Other'],
        ],
        'standards' => [
            ['id' => 1, 'name' => '1080p'],
            ['id' => 2, 'name' => '1080i'],
            ['id' => 3, 'name' => '720p'],
            ['id' => 4, 'name' => 'SD'],
        ],
        'teams' => [
            ['id' => 1, 'name' => 'HDS'],
            ['id' => 2, 'name' => 'CHD'],
            ['id' => 3, 'name' => 'MySiLU'],
            ['id' => 4, 'name' => 'WiKi'],
            ['id' => 5, 'name' => 'Other'],
        ],
    ];

    public function up(): void
    {
        if (!Schema::hasTable('searchbox')) {
            throw new RuntimeException('Search box table is required to restore the default chd taxonomy filters.');
        }

        $chdMode = DB::table('searchbox')->where('name', 'chd')->value('id');
        if ($chdMode === null) {
            throw new RuntimeException('The chd search box was not found; unable to restore its default taxonomy filters.');
        }

        foreach (self::DEFAULT_TAXONOMIES as $table => $items) {
            if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'mode')) {
                throw new RuntimeException("The {$table} table with a mode column is required to restore default chd options.");
            }

            foreach ($items as $item) {
                DB::table($table)->updateOrInsert(
                    ['id' => $item['id']],
                    [
                        'name' => $item['name'],
                        'sort_index' => 0,
                        'mode' => 0,
                    ]
                );
            }

            $this->clearTaxonomyCaches($table);
        }

        DB::table('searchbox')->where('id', $chdMode)->update([
            'showsubcat' => 1,
            'showcodec' => 1,
            'showstandard' => 1,
            'showteam' => 1,
        ]);
        NexusDB::cache_del('search_box_content');
    }

    public function down(): void
    {
        throw new RuntimeException('Cannot safely reverse the default taxonomy restoration because torrent records may reference these options.');
    }

    private function clearTaxonomyCaches(string $table): void
    {
        NexusDB::cache_del("{$table}_list_mode_0");
        foreach (DB::table('searchbox')->pluck('id') as $searchBoxId) {
            NexusDB::cache_del("{$table}_list_mode_{$searchBoxId}");
        }
    }
};
