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
        Schema::create('businesses', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->string('cnpj')->unique();
            $table->string('email')->unique();
            $table->string('phone')->unique();
            $table->string('address');
            $table->string('city');
            $table->string('state');
            $table->string('zip');
            $table->boolean('status')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('businesses');
    }
};
