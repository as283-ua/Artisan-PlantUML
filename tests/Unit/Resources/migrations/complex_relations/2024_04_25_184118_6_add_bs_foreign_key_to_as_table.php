<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('as', function (Blueprint $table) {
            $table->string('bs_foo');
            $table->integer('bs_bar');
            $table->foreign(['bs_foo', 'bs_bar'])->references(['foo', 'bar'])->on('bs');
        });
    }
    public function down(): void
    {
        Schema::table('as', function (Blueprint $table) {
            $table->dropForeign(['bs_foo', 'bs_bar']);
            $table->dropColumn('bs_foo');
            $table->dropColumn('bs_bar');
        });
    }
};