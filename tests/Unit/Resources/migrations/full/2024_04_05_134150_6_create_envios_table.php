<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('envios', function (Blueprint $table) {
            $table->primary(['codigo', 'peso', ]);
            $table->string('codigo');
            $table->float('peso');
            $table->float('precio');
            $table->string('observaciones');
            $table->string('direccion_provincia1');
            $table->string('direccion_calle1');
            $table->foreign(['direccion_provincia1', 'direccion_calle1'])->references(['provincia', 'calle'])->on('direccions');
            $table->string('direccion_provincia2');
            $table->string('direccion_calle2');
            $table->foreign(['direccion_provincia2', 'direccion_calle2'])->references(['provincia', 'calle'])->on('direccions');
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