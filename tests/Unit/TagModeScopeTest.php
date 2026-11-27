<?php

namespace Tests\Unit;

use App\Repositories\TagRepository;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TagModeScopeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);
        DB::purge('sqlite');

        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->integer('mode')->default(0);
            $table->boolean('none_only')->default(false);
            $table->integer('priority')->default(0);
            $table->timestamps();
        });

        $migration = require base_path('database/migrations/2026_11_27_000012_scope_tag_names_by_mode.php');
        $migration->up();
    }

    public function testTagsAreScopedByModeAndSameNamesCanExistInDifferentModes(): void
    {
        DB::table('tags')->insert([
            ['name' => 'Shared', 'mode' => 0, 'none_only' => false, 'priority' => 0],
            ['name' => 'None only', 'mode' => 0, 'none_only' => true, 'priority' => 0],
            ['name' => 'CHD only', 'mode' => 4, 'none_only' => false, 'priority' => 0],
        ]);

        $cache = new \ReflectionProperty(TagRepository::class, 'allTags');
        $cache->setAccessible(true);
        $cache->setValue(null, null);

        $noneTagNames = TagRepository::listAll(0)->pluck('name')->all();
        $chdTagNames = TagRepository::listAll(4)->pluck('name')->all();

        $this->assertEqualsCanonicalizing(['Shared', 'None only'], $noneTagNames);
        $this->assertEqualsCanonicalizing(['Shared', 'CHD only'], $chdTagNames);

        DB::table('tags')->insert([
            ['name' => 'Same name', 'mode' => 0, 'none_only' => true, 'priority' => 0],
            ['name' => 'Same name', 'mode' => 4, 'none_only' => false, 'priority' => 0],
        ]);
        $this->assertSame(2, DB::table('tags')->where('name', 'Same name')->count());
    }
}
