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
            $table->string('observaciones')->nullable();
            $table->foreignId('envio_id')->constrained();
            $table->foreignId('estado_id')->constrained();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('historicos');
    }
};