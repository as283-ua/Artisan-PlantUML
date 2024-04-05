<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('historicos', function (Blueprint $table) {
            $table->id();
            $table->string('observaciones');
            $table->string('envio_codigo')->nullable();
            $table->float('envio_peso')->nullable();
            $table->foreign(['envio_codigo', 'envio_peso'])->references(['codigo', 'peso'])->on('envios');
            $table->foreignId('estado_id')->nullable()->constrained();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('historicos');
    }
};
