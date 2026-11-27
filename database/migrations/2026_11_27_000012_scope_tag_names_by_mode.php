<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tags', function (Blueprint $table) {
            $table->dropUnique('tags_name_unique');
            $table->unique(['name', 'mode'], 'tags_name_mode_unique');
        });
    }

    public function down(): void
    {
        $hasDuplicateNames = DB::table('tags')
            ->select('name')
            ->groupBy('name')
            ->havingRaw('COUNT(*) > 1')
            ->exists();
        if ($hasDuplicateNames) {
            throw new \RuntimeException('Cannot restore globally unique tag names while duplicate names exist across modes.');
        }

        Schema::table('tags', function (Blueprint $table) {
            $table->dropUnique('tags_name_mode_unique');
            $table->unique('name', 'tags_name_unique');
        });
    }
};
