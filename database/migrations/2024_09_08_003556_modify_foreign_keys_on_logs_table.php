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
        Schema::table('logs', function (Blueprint $table) {
            // First, drop the existing foreign key constraint
            $table->dropForeign(['user']); // Drop the foreign key for 'user'

            // Then, add the new foreign key constraint with onDelete('cascade')
            $table->foreign('user')->references('id')->on('users')->onDelete('set null')->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('logs', function (Blueprint $table) {
            // Rollback: drop the new foreign key constraint
            $table->dropForeign(['user']); // Drop the foreign key for 'user'

            // Re-add the original foreign key (assuming no cascade originally)
            $table->foreign('user')->references('id')->on('users');
        });
    }
};
