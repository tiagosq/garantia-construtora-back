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
        Schema::create('business_users', function (Blueprint $table) {
            $table->ulid('business');
            $table->ulid('user');
            $table->ulid('role');

            $table->primary(['business','user']);

            $table->foreign('business')->references('id')->on('businesses');
            $table->foreign('user')->references('id')->on('users');
            $table->foreign('role')->references('id')->on('roles');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('business_users');
    }
};
