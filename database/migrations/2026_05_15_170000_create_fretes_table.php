<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fretes', function (Blueprint $table) {
            $table->id();
            $table->string('nome', 255)->unique();
            $table->decimal('valor', 15, 2)->default(0);
            $table->foreignId('id_veiculo')
                ->constrained('veiculos')
                ->cascadeOnDelete();
            $table->text('descricao')->nullable();
            $table->string('status_situacao', 20)->default('ABERTA')->index();
            $table->decimal('valor_fruta_kg', 15, 2)->default(0);
            $table->softDeletes();
            $table->timestamps();

            $table->index('id_veiculo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fretes');
    }
};
