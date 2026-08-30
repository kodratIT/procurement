<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->string('status', 30)->default('active')->after('is_active')->index();
            $table->text('status_note')->nullable()->after('status');
            $table->timestamp('status_changed_at')->nullable()->after('status_note');
        });
    }

    public function down(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropColumn(['status', 'status_note', 'status_changed_at']);
        });
    }
};
