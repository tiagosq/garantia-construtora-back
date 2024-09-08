<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('buildings', function (Blueprint $table) {
            $table->dateTime('construction_date')->nullable();
            $table->dateTime('delivered_date')->nullable();
            $table->dateTime('warranty_date')->nullable();
        });
    }

    public function down()
    {
        Schema::table('buildings', function (Blueprint $table) {
            $table->dropColumn(['construction_date', 'delivered_date', 'warranty_date']);
        });
    }
};
