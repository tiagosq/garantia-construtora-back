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
        Schema::table('maintenances', function (Blueprint $table) {
            // First, drop the existing foreign key constraint
            $table->dropForeign(['building']); // Drop foreign key for 'building'
            $table->dropForeign(['user']); // Drop foreign key for 'user'

            // Then, add the new foreign key constraint with onDelete('cascade')
            $table->foreign('building')->references('id')->on('buildings')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('user')->references('id')->on('users')->onDelete('cascade')->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('maintenances', function (Blueprint $table) {
            // Rollback: drop the new foreign key constraint
            $table->dropForeign(['building']); // Drop foreign key for 'building'
            $table->dropForeign(['user']); // Drop foreign key for 'user'

            // Re-add the original foreign key (assuming no cascade originally)
            $table->foreign('building')->references('id')->on('buildings');
            $table->foreign('user')->references('id')->on('users');
        });
    }
};
