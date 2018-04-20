<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateOrdersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->increments('id')->comment('自增ID');
            $table->string('uin', 30)->comment('渠道用户ID');
            $table->string('appid',30)->comment('应用ID');
            $table->string('sdkid', 30)->comment('渠道ID');
            $table->string('extra',100)->comment('透传信息');
            $table->integer('fee', false, true)->comment('金额（分）');
            $table->string('ssid', 50)->comment('渠道流水号');
            $table->string('tcd', 50)->comment('易接订单号');
            $table->string('ver', 30)->comment('协议版本号');
            $table->tinyInteger('st', false, true)->comment('是否成功');
            $table->bigInteger('ct', false, true)->comment('支付完成时间');
            $table->bigInteger('pt', false, true)->comment('付费时间');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('orders');
    }
}
