<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $table = (string) config('files.tables.configuration', 'file_configurations');

        if (! Schema::hasTable($table) || Schema::hasColumn($table, 'preferred_disks')) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint): void {
            $blueprint->json('preferred_disks')->nullable()->after('allowed_extensions');
        });
    }

    public function down(): void
    {
        $table = (string) config('files.tables.configuration', 'file_configurations');

        if (Schema::hasTable($table) && Schema::hasColumn($table, 'preferred_disks')) {
            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropColumn('preferred_disks'));
        }
    }
};
