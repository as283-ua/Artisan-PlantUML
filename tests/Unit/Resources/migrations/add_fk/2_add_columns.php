<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddForeignKeyToExampleTable extends Migration
{
    public function up()
    {
        Schema::table('examples', function (Blueprint $table) {
            $table->foreignId("user_id")->constrained();
        });
    }

    public function down()
    {
        Schema::table('examples', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn('user_id');
        });
    }
}
