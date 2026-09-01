<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add the name constraints required to keep organization identities
     * unique within their ownership scope.
     */
    public function up(): void
    {
        Schema::table('offices', function (Blueprint $table): void {
            $table->unique('name', 'offices_name_unique');
        });

        Schema::table('branches', function (Blueprint $table): void {
            $table->unique(['office_id', 'name'], 'branches_office_name_unique');
        });

        Schema::table('departments', function (Blueprint $table): void {
            $table->unique(['office_id', 'name'], 'departments_office_name_unique');
        });

        Schema::table('cost_centers', function (Blueprint $table): void {
            $table->unique(['office_id', 'name'], 'cost_centers_office_name_unique');
        });
    }

    public function down(): void
    {
        Schema::table('cost_centers', function (Blueprint $table): void {
            $table->dropUnique('cost_centers_office_name_unique');
        });

        Schema::table('departments', function (Blueprint $table): void {
            $table->dropUnique('departments_office_name_unique');
        });

        Schema::table('branches', function (Blueprint $table): void {
            $table->dropUnique('branches_office_name_unique');
        });

        Schema::table('offices', function (Blueprint $table): void {
            $table->dropUnique('offices_name_unique');
        });
    }
};
