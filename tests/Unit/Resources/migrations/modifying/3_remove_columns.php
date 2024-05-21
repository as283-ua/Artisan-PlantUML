<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddForeignKeyToExampleTable extends Migration
{
    public function up()
    {
        Schema::table('examples', function (Blueprint $table) {
            $table->dropColumn('description');
        });
    }

    public function down()
    {
        Schema::table('examples', function (Blueprint $table) {
        });
    }
}
