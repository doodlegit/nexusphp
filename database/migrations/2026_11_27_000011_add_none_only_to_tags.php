<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('tags', 'none_only')) {
            Schema::table('tags', function (Blueprint $table) {
                $table->boolean('none_only')->default(false);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('tags', 'none_only')) {
            Schema::table('tags', function (Blueprint $table) {
                $table->dropColumn('none_only');
            });
        }
    }
};
