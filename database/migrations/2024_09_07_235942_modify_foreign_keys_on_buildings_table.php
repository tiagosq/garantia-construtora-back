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
        Schema::table('buildings', function (Blueprint $table) {
            // First, drop the existing foreign key constraint
            $table->dropForeign(['business']); // Drops foreign key constraint for 'business'
            $table->dropForeign(['owner']); // Drops foreign key constraint for 'owner'

            // Then, add the new foreign key constraint with onDelete('cascade')
            $table->foreign('business')->references('id')->on('businesses')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('owner')->references('id')->on('users')->onDelete('cascade')->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('buildings', function (Blueprint $table) {
            // Rollback: drop the new foreign key constraint
            $table->dropForeign(['business']); // Drops foreign key constraint for 'business'
            $table->dropForeign(['owner']); // Drops foreign key constraint for 'owner'

            // Re-add the original foreign key (assuming no cascade originally)
            $table->foreign('business')->references('id')->on('businesses');
            $table->foreign('owner')->references('id')->on('users');
        });
    }
};
