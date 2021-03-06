<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterChannelsTable1 extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('channels', function (Blueprint $table) {
            $table->unique('sdkid');
            $table->string('name', 30)->comment('渠道名')->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('channels', function (Blueprint $table) {
            //
        });
    }
}
