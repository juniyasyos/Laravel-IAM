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
        Schema::table('user_access_profiles', function (Blueprint $table) {
            // Drop foreign keys first
            $table->dropForeign('user_access_profiles_user_id_foreign');
            $table->dropForeign('user_access_profiles_access_profile_id_foreign');
            $table->dropForeign('user_access_profiles_assigned_by_foreign');

            // Drop the old composite primary key
            $table->dropPrimary();

            // Add id as new primary key
            $table->id()->first();

            // Add unique constraint to prevent duplicate assignments
            $table->unique(['user_id', 'access_profile_id']);

            // Re-add foreign keys
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('access_profile_id')->references('id')->on('access_profiles')->onDelete('cascade');
            $table->foreign('assigned_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_access_profiles', function (Blueprint $table) {
            // Drop foreign keys
            $table->dropForeign('user_access_profiles_user_id_foreign');
            $table->dropForeign('user_access_profiles_access_profile_id_foreign');
            $table->dropForeign('user_access_profiles_assigned_by_foreign');

            // Drop unique constraint
            $table->dropUnique(['user_id', 'access_profile_id']);

            // Drop id column
            $table->dropColumn('id');

            // Restore composite primary key
            $table->primary(['user_id', 'access_profile_id']);

            // Re-add foreign keys with original names
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('access_profile_id')->references('id')->on('access_profiles')->onDelete('cascade');
            $table->foreign('assigned_by')->references('id')->on('users')->onDelete('set null');
        });
    }
};
