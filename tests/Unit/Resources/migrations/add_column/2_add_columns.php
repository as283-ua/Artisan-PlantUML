<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class RemoveColumnsFromExampleTable extends Migration
{
    public function up()
    {
        Schema::table('examples', function (Blueprint $table) {
            $table->string('description');
        });
    }

    public function down()
    {
        Schema::table('examples', function (Blueprint $table) {
            $table->dropColumn('description');
        });
    }
}
