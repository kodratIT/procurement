<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add an office scope to departure batches.
     *
     * NOTE: SQLite rebuilds the whole table on ALTER TABLE ADD COLUMN with a
     * foreign key, and Laravel's SQLite grammar cannot re-emit the raw
     * expression index (departure_batches_return_check) during the rebuild,
     * producing `create index ... ()` errors. We therefore add the column as
     * a plain nullable unsigned integer with an index, and enforce office
     * referential integrity in the application layer (OfficeScoped concern +
     * policies). Postgres/MySQL can add the FK constraint separately.
     */
    public function up(): void
    {
        Schema::table('departure_batches', function (Blueprint $table) {
            $table->unsignedBigInteger('office_id')->nullable()->after('id');
            $table->index(['office_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::table('departure_batches', function (Blueprint $table) {
            $table->dropIndex(['office_id', 'is_active']);
            $table->dropColumn('office_id');
        });
    }
};
