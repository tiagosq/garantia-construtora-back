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
        Schema::create('attachments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->string('path');
            $table->string('url');
            $table->string('type');
            $table->unsignedInteger('size');
            $table->ulid('question');
            $table->foreign('question')->references('id')->on('questions');
            $table->ulid('user');
            $table->foreign('user')->references('id')->on('users');
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
        Schema::dropIfExists('attachments');
    }
};
