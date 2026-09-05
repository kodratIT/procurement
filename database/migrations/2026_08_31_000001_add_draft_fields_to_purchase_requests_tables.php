<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_requests', function (Blueprint $table): void {
            $table->string('pr_number', 30)->nullable()->change();
            $table->text('reason')->nullable()->after('notes');
            $table->string('priority', 20)->default('normal')->after('required_date');
            $table->index(['office_id', 'status', 'updated_at'], 'purchase_requests_office_status_updated_index');
        });

        Schema::table('purchase_request_items', function (Blueprint $table): void {
            $table->text('description')->nullable()->after('item_name');
            $table->json('specifications')->nullable()->after('unit_name');
            $table->index(['purchase_request_id', 'procurement_item_id'], 'purchase_request_items_request_item_index');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_request_items', function (Blueprint $table): void {
            $table->dropIndex('purchase_request_items_request_item_index');
            $table->dropColumn(['description', 'specifications']);
        });

        Schema::table('purchase_requests', function (Blueprint $table): void {
            $table->dropIndex('purchase_requests_office_status_updated_index');
            $table->dropColumn(['reason', 'priority']);
        });
    }
};
