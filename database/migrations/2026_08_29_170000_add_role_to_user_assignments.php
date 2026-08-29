<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_assignments', function (Blueprint $table): void {
            $table->string('role')->nullable()->after('office_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('user_assignments', function (Blueprint $table): void {
            $table->dropColumn('role');
        });
    }
};
