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
        Schema::table('user_roles', function (Blueprint $table) {
            // First, drop the existing foreign key constraint
            $table->dropForeign(['user']); // Drop the foreign key for 'user'
            $table->dropForeign(['business']); // Drop the foreign key for 'business'
            $table->dropForeign(['role']); // Drop the foreign key for 'role'

            // Then, add the new foreign key constraint with onDelete('cascade')
            $table->foreign('user')->references('id')->on('users')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('business')->references('id')->on('businesses')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('role')->references('id')->on('roles')->onDelete('cascade')->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_roles', function (Blueprint $table) {
            // Rollback: drop the new foreign key constraint
            $table->dropForeign(['user']); // Drop the foreign key for 'user'
            $table->dropForeign(['business']); // Drop the foreign key for 'business'
            $table->dropForeign(['role']); // Drop the foreign key for 'role'

            // Re-add the original foreign key (assuming no cascade originally)
            $table->foreign('user')->references('id')->on('users');
            $table->foreign('business')->references('id')->on('businesses');
            $table->foreign('role')->references('id')->on('roles');
        });
    }
};
