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
            $table->foreignId('envio_id')->constrained();
            $table->foreignId('tarifa_id')->constrained();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('envio_tarifa');
    }
};