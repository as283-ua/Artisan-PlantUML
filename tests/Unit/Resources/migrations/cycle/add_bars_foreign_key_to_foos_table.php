<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('foos', function (Blueprint $table) {
            $table->integer('bars_id');
            $table->foreign('bars_id')->references('id')->on('bars');
        });
    }
    public function down(): void
    {
        Schema::table('foos', function (Blueprint $table) {
            $table->dropForeign('bars_id');
            $table->dropColumn('bars_id');
        });
    }
};
