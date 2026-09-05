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
        Schema::table('purchase_requests', function (Blueprint $table): void {
            $table->string('status', 100)->change();
        });
        Schema::table('purchase_request_status_histories', function (Blueprint $table): void {
            $table->string('from_status', 100)->nullable()->change();
            $table->string('to_status', 100)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_requests', function (Blueprint $table): void {
            $table->string('status', 30)->change();
        });
        Schema::table('purchase_request_status_histories', function (Blueprint $table): void {
            $table->string('from_status', 30)->nullable()->change();
            $table->string('to_status', 30)->change();
        });
    }
};
