<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cs', function (Blueprint $table) {
            $table->id();
            $table->string('something');
            $table->integer('other');
            $table->foreignId('c_id1')->unique()->constrained();
            $table->foreignId('c_id2')->unique()->nullable()->constrained();
            $table->foreignId('a_id')->nullable()->constrained();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cs');
    }
};