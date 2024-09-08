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
        Schema::table('questions', function (Blueprint $table) {
            // First, drop the existing foreign key constraint
            $table->dropForeign(['maintenance']);

            // Then, add the new foreign key constraint with onDelete('cascade')
            $table->foreign('maintenance')->references('id')->on('maintenances')->onDelete('cascade')->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            // Rollback: drop the new foreign key constraint
            $table->dropForeign(['maintenance']);

            // Re-add the original foreign key (assuming no cascade originally)
            $table->foreign('maintenance')->references('id')->on('maintenances');
        });
    }
};
