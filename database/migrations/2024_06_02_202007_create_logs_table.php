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
        Schema::create('logs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('user')->nullable();
            $table->foreign('user')->references('id')->on('users');
            $table->string('ip');
            $table->string('user_agent');
            $table->string('action');
            $table->string('method');
            $table->string('body')->nullable();
            $table->string('description')->nullable();
            $table->text('before')->nullable();
            $table->text('after')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('logs');
    }
};
