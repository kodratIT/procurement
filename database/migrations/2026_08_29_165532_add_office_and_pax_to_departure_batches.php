<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('departure_batches', function (Blueprint $table) {
            $table->foreignId('office_id')->nullable()->after('id')->constrained('offices')->nullOnDelete();
            $table->unsignedInteger('pax_count')->nullable()->after('capacity');
        });
    }

    public function down(): void
    {
        Schema::table('departure_batches', function (Blueprint $table) {
            $table->dropConstrainedForeignId('office_id');
            $table->dropColumn('pax_count');
        });
    }
};
