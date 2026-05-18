<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('categorias_descarte')) {
            return;
        }

        Schema::create('categorias_descarte', function (Blueprint $table): void {
            $table->id();
            $table->string('nome', 120)->unique();
            $table->text('descricao')->nullable();
            $table->boolean('impacta_kpi_perda')->default(true);
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categorias_descarte');
    }
};
