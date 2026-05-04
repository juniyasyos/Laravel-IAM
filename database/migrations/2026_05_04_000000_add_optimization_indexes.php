<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * OPTIMIZATION: Add indexes for common N+1 query patterns
     */
    public function up(): void
    {
        // Index for user relationships queries
        Schema::table('user_access_profiles', function (Blueprint $table) {
            if (!Schema::hasColumn('user_access_profiles', 'user_id')) {
                $table->index(['user_id', 'access_profile_id'])->after('id');
            } else {
                $table->index(['user_id', 'is_active'])->change();
            }
        });

        Schema::table('user_unit_kerja', function (Blueprint $table) {
            $table->index(['user_id'])->after('id');
        });

        Schema::table('iam_user_application_roles', function (Blueprint $table) {
            $table->index(['user_id'])->after('id');
        });

        // Index for access profile queries
        Schema::table('access_profiles', function (Blueprint $table) {
            $table->index(['is_active'])->after('id');
        });

        // Index for application queries
        Schema::table('applications', function (Blueprint $table) {
            $table->index(['app_key'])->after('id');
            $table->index(['enabled'])->after('app_key');
        });

        // Index for session queries
        Schema::table('sessions', function (Blueprint $table) {
            $table->index(['user_id', 'is_active'])->after('id');
            $table->index(['last_activity'])->after('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_access_profiles', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'access_profile_id']);
        });

        Schema::table('user_unit_kerja', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
        });

        Schema::table('iam_user_application_roles', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
        });

        Schema::table('access_profiles', function (Blueprint $table) {
            $table->dropIndex(['is_active']);
        });

        Schema::table('applications', function (Blueprint $table) {
            $table->dropIndex(['app_key']);
            $table->dropIndex(['enabled']);
        });

        Schema::table('sessions', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'is_active']);
            $table->dropIndex(['last_activity']);
        });
    }
};
