<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('departure_batches', function (Blueprint $table) {
            // SQLite rebuilds the table for foreign keys and cannot compile the
            // expression index through Blueprint::rawIndex(). Add the nullable
            // column during the rebuild, then add the FK explicitly where the
            // database supports it.
            $table->foreignId('office_id')->nullable()->after('id')->index();
            $table->unsignedInteger('pax_count')->nullable()->after('capacity');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE departure_batches ADD CONSTRAINT departure_batches_office_id_foreign FOREIGN KEY (office_id) REFERENCES offices (id) ON DELETE SET NULL');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE departure_batches DROP CONSTRAINT IF EXISTS departure_batches_office_id_foreign');
        }

        Schema::table('departure_batches', function (Blueprint $table) {
            $table->dropIndex(['office_id']);
            $table->dropColumn(['office_id', 'pax_count']);
        });
    }
};
