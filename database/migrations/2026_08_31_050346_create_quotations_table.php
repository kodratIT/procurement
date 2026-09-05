<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('purchase_request_id')->constrained('purchase_requests')->cascadeOnDelete();
            $table->foreignId('vendor_id')->constrained('vendors')->restrictOnDelete();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('quotation_number', 100);
            $table->date('quoted_at')->nullable();
            $table->date('valid_until')->nullable();
            $table->char('currency', 3)->default('IDR');
            $table->decimal('subtotal_amount', 14, 2)->default(0);
            $table->decimal('discount_amount', 14, 2)->default(0);
            $table->decimal('tax_amount', 14, 2)->default(0);
            $table->decimal('shipping_amount', 14, 2)->default(0);
            $table->decimal('total_amount', 14, 2)->default(0);
            $table->string('status', 30)->default('draft');
            $table->text('notes')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->unique(['purchase_request_id', 'quotation_number']);
            $table->index(['purchase_request_id', 'status']);
            $table->index(['vendor_id', 'status']);
        });

        Schema::create('quotation_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('quotation_id')->constrained('quotations')->cascadeOnDelete();
            $table->foreignId('purchase_request_item_id')->constrained('purchase_request_items')->cascadeOnDelete();
            $table->string('description')->nullable();
            $table->json('specifications')->nullable();
            $table->decimal('quantity', 14, 2)->default(0);
            $table->string('unit_name', 30)->nullable();
            $table->decimal('unit_price', 14, 2)->default(0);
            $table->decimal('line_total', 14, 2)->default(0);
            $table->decimal('total_price', 14, 2)->default(0);
            $table->text('notes')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['quotation_id', 'purchase_request_item_id']);
            $table->index(['purchase_request_item_id', 'quotation_id']);
        });

        Schema::create('quotation_recommendations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('purchase_request_id')->constrained('purchase_requests')->cascadeOnDelete();
            $table->foreignId('quotation_id')->constrained('quotations')->restrictOnDelete();
            $table->foreignId('vendor_id')->constrained('vendors')->restrictOnDelete();
            $table->foreignId('recommended_by_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('office_id')->constrained('offices')->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->text('reason');
            $table->json('evidence_attachment_ids')->nullable();
            $table->json('comparison_snapshot');
            $table->timestamps();

            $table->unique(['purchase_request_id', 'version']);
            $table->index(['purchase_request_id', 'created_at']);
            $table->index(['quotation_id', 'version']);
        });

        Schema::table('purchase_requests', function (Blueprint $table): void {
            $table->foreignId('recommended_quotation_id')->nullable()->after('vendor_id')->constrained('quotations')->nullOnDelete();
            $table->text('recommendation_reason')->nullable()->after('recommended_quotation_id');
            $table->unsignedInteger('recommendation_version')->nullable()->after('recommendation_reason');
            $table->timestamp('recommended_at')->nullable()->after('recommendation_version');
            $table->foreignId('recommended_by_id')->nullable()->after('recommended_at')->constrained('users')->nullOnDelete();
            $table->index(['recommended_quotation_id', 'recommendation_version'], 'purchase_requests_recommended_version_index');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_requests', function (Blueprint $table): void {
            $table->dropForeign(['recommended_quotation_id']);
            $table->dropForeign(['recommended_by_id']);
            $table->dropIndex('purchase_requests_recommended_version_index');
            $table->dropColumn([
                'recommended_quotation_id',
                'recommendation_reason',
                'recommendation_version',
                'recommended_at',
                'recommended_by_id',
            ]);
        });

        Schema::dropIfExists('quotation_recommendations');
        Schema::dropIfExists('quotation_items');
        Schema::dropIfExists('quotations');
    }
};
