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
        Schema::create('maintenances', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name', 50);
            $table->string('description')->nullable();
            $table->date('start_date');
            $table->date('end_date');
            $table->string('is_completed')->default(false);
            $table->string('is_approved')->default(false);
            $table->ulid('building');
            $table->foreign('building')->references('id')->on('buildings');
            $table->ulid('user');
            $table->foreign('user')->references('id')->on('users');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('maintenances');
    }
};
