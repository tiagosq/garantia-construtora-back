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
            $table->ulid('role')->nullable();
            $table->ulid('maintenance')->nullable();
            $table->ulid('building')->nullable();
            $table->ulid('business')->nullable();
            $table->foreign('user')->references('id')->on('users');
            $table->foreign('role')->references('id')->on('roles');
            $table->foreign('maintenance')->references('id')->on('maintenances');
            $table->foreign('building')->references('id')->on('buildings');
            $table->foreign('business')->references('id')->on('businesses');
            $table->string('ip');
            $table->string('user_agent');
            $table->string('action');
            $table->string('description');
            $table->string('before');
            $table->string('after');
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
