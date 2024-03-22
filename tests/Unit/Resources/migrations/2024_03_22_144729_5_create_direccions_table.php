<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('direccions', function (Blueprint $table) {
            $table->primary(['provincia', 'calle', ]);
            $table->string('cp');
            $table->string('localidad');
            $table->string('provincia');
            $table->string('calle');
            $table->integer('numero');
            $table->string('usuario_email')->nullable();
            $table->unique(['usuario_email']);
            $table->foreign(['usuario_email'])->references(['email'])->on('usuarios');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('direccions');
    }
};