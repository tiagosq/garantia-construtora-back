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
        Schema::table('attachments', function (Blueprint $table) {
            // First, drop the existing foreign key constraint
            $table->dropForeign(['question']); // Drop the foreign key for the 'question' column
            $table->dropForeign(['user']); // Drop the foreign key for the 'user' column

            // Then, add the new foreign key constraint with onDelete('cascade')
            $table->foreign('question')->references('id')->on('questions')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('user')->references('id')->on('users')->onDelete('cascade')->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attachments', function (Blueprint $table) {
            // Rollback: drop the new foreign key constraint
            $table->dropForeign(['question']); // Drop the foreign key for the 'question' column
            $table->dropForeign(['user']); // Drop the foreign key for the 'user' column

            // Re-add the original foreign key (assuming no cascade originally)
            $table->foreign('question')->references('id')->on('questions');
            $table->foreign('user')->references('id')->on('users');
        });
    }
};
