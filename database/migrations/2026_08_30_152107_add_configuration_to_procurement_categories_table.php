<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('procurement_categories', function (Blueprint $table): void {
            $table->string('type', 30)->default('goods')->index();
            $table->boolean('requires_batch')->default(false);
            $table->boolean('requires_jamaah')->default(false);
            $table->boolean('requires_vendor')->default(false);
            $table->boolean('requires_quotation')->default(false);
            $table->boolean('requires_receipt')->default(false);
            $table->boolean('requires_invoice')->default(false);
            $table->boolean('requires_po')->default(false);
            $table->string('workflow_reference', 100)->nullable();
            $table->string('number_template', 255)->nullable();
            $table->timestamp('disabled_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('procurement_categories', function (Blueprint $table): void {
            $table->dropColumn([
                'type',
                'requires_batch',
                'requires_jamaah',
                'requires_vendor',
                'requires_quotation',
                'requires_receipt',
                'requires_invoice',
                'requires_po',
                'workflow_reference',
                'number_template',
                'disabled_at',
            ]);
        });
    }
};
