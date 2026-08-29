<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offices', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('keycloak_sub')->nullable()->unique()->after('id');
        });

        Schema::create('office_user', function (Blueprint $table) {
            $table->foreignId('office_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['office_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('office_user');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('keycloak_sub'));
        Schema::dropIfExists('offices');
    }
};
