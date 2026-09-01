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
        Schema::table('users', function (Blueprint $table): void {
            $table->string('avatar')->nullable()->after('email');
            $table->timestamp('last_login_at')->nullable()->after('avatar');
            $table->timestamp('last_token_sync_at')->nullable()->after('last_login_at');
            $table->boolean('is_active')->default(true)->index()->after('last_token_sync_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex(['is_active']);
            $table->dropColumn(['avatar', 'last_login_at', 'last_token_sync_at', 'is_active']);
        });
    }
};
