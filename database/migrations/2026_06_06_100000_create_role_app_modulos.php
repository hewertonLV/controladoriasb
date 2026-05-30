<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('role_app_modulos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->string('app_modulo', 32);
            $table->timestamps();

            $table->unique(['role_id', 'app_modulo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_app_modulos');
    }
};
