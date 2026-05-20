<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('estados', function (Blueprint $table) {
            $table->string('id_cigam', 6)->nullable()->after('id');
        });

        DB::table('estados')
            ->orderBy('id')
            ->pluck('id')
            ->each(function (int $id): void {
                DB::table('estados')
                    ->where('id', $id)
                    ->update([
                        'id_cigam' => str_pad((string) $id, 6, '0', STR_PAD_LEFT),
                    ]);
            });

        Schema::table('estados', function (Blueprint $table) {
            $table->string('id_cigam', 6)->nullable(false)->change();
            $table->unique('id_cigam');
        });
    }

    public function down(): void
    {
        Schema::table('estados', function (Blueprint $table) {
            $table->dropUnique(['id_cigam']);
            $table->dropColumn('id_cigam');
        });
    }
};
