<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usuarios', function (Blueprint $table) {
            $table->primary('email');
            $table->string('email');
            $table->string('password');
            $table->string('nombre');
            $table->string('apikey');
            $table->string('rol_nombre');
            $table->foreign(['rol_nombre'])->references(['nombre'])->on('rols');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usuarios');
    }
};