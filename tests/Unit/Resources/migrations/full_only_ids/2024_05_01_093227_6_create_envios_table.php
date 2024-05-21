<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('envios', function (Blueprint $table) {
            $table->id();
            $table->string('codigo');
            $table->float('peso');
            $table->float('precio');
            $table->string('observaciones')->nullable();
            $table->unsignedBigInteger('direccion_id1')->nullable();
            $table->foreign(['direccion_id1'])->references(['id'])->on('direccions');
            $table->unsignedBigInteger('direccion_id2');
            $table->foreign(['direccion_id2'])->references(['id'])->on('direccions');
            $table->foreignId('usuario_id')->nullable()->constrained();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('envios');
    }
};