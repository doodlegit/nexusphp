<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('torrent_extras', 'quality')) {
            Schema::table('torrent_extras', function (Blueprint $table) {
                $table->string('quality', 255)->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('torrent_extras', 'quality')) {
            Schema::table('torrent_extras', function (Blueprint $table) {
                $table->dropColumn('quality');
            });
        }
    }
};
