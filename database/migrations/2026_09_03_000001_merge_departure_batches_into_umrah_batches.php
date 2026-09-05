<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Unify the two overlapping "departure/umrah batch" concepts into a
        // single entity: UmrahBatch (per-office package/rombongan). Departure
        // Batch was a global duplicate used only as a purchase-request label.
        // No purchase_requests rows reference it yet, so the column is simply
        // re-pointed at umrah_batches and the duplicate table is dropped.

        if (Schema::hasColumn('purchase_requests', 'departure_batch_id')) {
            Schema::table('purchase_requests', function (Blueprint $table) {
                $table->dropForeign(['departure_batch_id']);
                $table->dropIndex(['departure_batch_id', 'status']);
                $table->renameColumn('departure_batch_id', 'umrah_batch_id');
                $table->foreign('umrah_batch_id')->references('id')->on('umrah_batches')->nullOnDelete();
                $table->index(['umrah_batch_id', 'status']);
            });
        }

        Schema::dropIfExists('departure_batches');
    }

    public function down(): void
    {
        Schema::create('departure_batches', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->date('departure_date');
            $table->date('return_date')->nullable();
            $table->unsignedInteger('capacity')->nullable();
            $table->string('status', 30)->default('planned')->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->rawIndex('(return_date IS NULL OR return_date >= departure_date)', 'departure_batches_return_check');
        });

        if (Schema::hasColumn('purchase_requests', 'umrah_batch_id')) {
            Schema::table('purchase_requests', function (Blueprint $table) {
                $table->dropForeign(['umrah_batch_id']);
                $table->dropIndex(['umrah_batch_id', 'status']);
                $table->renameColumn('umrah_batch_id', 'departure_batch_id');
                $table->foreign('departure_batch_id')->references('id')->on('departure_batches')->nullOnDelete();
                $table->index(['departure_batch_id', 'status']);
            });
        }
    }
};
