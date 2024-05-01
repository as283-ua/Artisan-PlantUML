<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('envios', function (Blueprint $table) {
            $table->primary(['codigo', 'peso',]);
            $table->string('codigo');
            $table->float('peso');
            $table->float('precio');
            $table->string('observaciones');
            $table->foreignId('direccion_id1')->constrained();
            $table->foreignId('direccion_id2')->nullable()->constrained();
            $table->string('usuario_email');
            $table->foreign(['usuario_email'])->references(['email'])->on('usuarios');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('envios');
    }
};
