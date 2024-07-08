<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('buildings', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name', 50);
            $table->string('address');
            $table->string('city', 50);
            $table->string('state', 2);
            $table->string('zip', 9);
            $table->string('manager_name', 15)->nullable();
            $table->string('phone', 15)->nullable();
            $table->string('email', 100)->nullable();
            $table->string('site', 100)->nullable();
            $table->boolean('status')->default(false);
            $table->ulid('business');
            $table->foreign('business')->references('id')->on('businesses');
            $table->ulid('owner')->nullable();
            $table->foreign('owner')->references('id')->on('users');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('buildings');
    }
};
