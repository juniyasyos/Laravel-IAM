<?php

use App\Domain\Iam\Models\AccessProfile;
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
        Schema::table('access_profiles', function (Blueprint $table) {
            $table->string('key_hash', 64)->nullable()->unique()->after('id');
        });

        AccessProfile::query()
            ->select('id')
            ->orderBy('id')
            ->chunkById(100, function ($accessProfiles): void {
                foreach ($accessProfiles as $accessProfile) {
                    AccessProfile::whereKey($accessProfile->id)->update([
                        'key_hash' => AccessProfile::generateKeyHash(),
                    ]);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('access_profiles', function (Blueprint $table) {
            $table->dropUnique('access_profiles_key_hash_unique');
            $table->dropColumn('key_hash');
        });
    }
};
