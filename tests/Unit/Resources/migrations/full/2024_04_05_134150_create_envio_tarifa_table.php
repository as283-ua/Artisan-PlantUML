<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('envio_tarifa', function (Blueprint $table) {
            $table->id();
            $table->string('envio_codigo');
            $table->float('envio_peso');
            $table->foreign(['envio_codigo', 'envio_peso'])->references(['codigo', 'peso'])->on('envios');
            $table->foreignId('tarifa_id')->constrained();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('envio_tarifa');
    }
};