#!/usr/bin/env php
<?php
/**
 * User Sync Debug Script
 * 
 * Menampilkan:
 * 1. Application roles yang tersedia
 * 2. Access profiles dan roles yang link ke mereka
 * 3. User yang sudah di-assign ke profiles
 * 4. Mana yang matching dan mana yang tidak
 * 
 * Usage:
 *   php debug-user-sync.php
 *   php debug-user-sync.php --app-key=my-app
 *   php debug-user-sync.php --user-email=john@example.com
 */

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/bootstrap/app.php';

use App\Domain\Iam\Models\Application;
use App\Domain\Iam\Models\ApplicationRole;
use App\Domain\Iam\Models\AccessProfile;
use App\Models\User;

$app = app();
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);

// Get command line arguments
$appKey = null;
$userEmail = null;

foreach ($argv as $arg) {
    if (strpos($arg, '--app-key=') === 0) {
        $appKey = substr($arg, strlen('--app-key='));
    }
    if (strpos($arg, '--user-email=') === 0) {
        $userEmail = substr($arg, strlen('--user-email='));
    }
}

echo "\n";
echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║           User Sync Debug Report                               ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n";
echo "\n";

// 1. List all applications and their roles
if ($appKey) {
    echo "🔍 Filtering for: app_key = \"$appKey\"\n\n";
}

$appQuery = Application::query();
if ($appKey) {
    $appQuery->where('app_key', $appKey);
}
$applications = $appQuery->with('roles')->get();

if ($applications->isEmpty()) {
    echo "❌ No applications found" . ($appKey ? " with app_key=$appKey" : "") . "\n";
    exit(1);
}

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "1️⃣  APPLICATION ROLES (iam_roles)\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "\n";

foreach ($applications as $app) {
    echo "📱 Application: {$app->name} (app_key: {$app->app_key})\n";
    echo "   ID: {$app->id} | Enabled: " . ($app->enabled ? "✅" : "❌") . "\n";

    $roles = $app->roles()->get();
    if ($roles->isEmpty()) {
        echo "   ⚠️  No roles defined\n";
    } else {
        echo "   Roles:\n";
        foreach ($roles as $role) {
            echo "      • {$role->slug}\n";
            echo "        Name: {$role->name}\n";
            echo "        ID: {$role->id}\n";
        }
    }
    echo "\n";
}

echo "\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "2️⃣  ACCESS PROFILES (access_profiles) & Role Links\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "\n";

foreach ($applications as $app) {
    echo "📱 {$app->app_key}:\n";

    $profiles = AccessProfile::query()
        ->whereHas('roles', function ($q) use ($app) {
            $q->where('application_id', $app->id);
        })
        ->with(['roles' => function ($q) use ($app) {
            $q->where('application_id', $app->id);
        }])
        ->get();

    if ($profiles->isEmpty()) {
        echo "   ⚠️  No access profiles link to this application's roles\n";
    } else {
        foreach ($profiles as $profile) {
            echo "   📦 Profile: {$profile->name}\n";
            echo "      Slug: {$profile->slug}\n";
            echo "      ID: {$profile->id}\n";
            echo "      Linked Roles (this app only):\n";

            $linkedRoles = $profile->roles()
                ->where('application_id', $app->id)
                ->get();

            if ($linkedRoles->isEmpty()) {
                echo "         (none)\n";
            } else {
                foreach ($linkedRoles as $role) {
                    echo "         • {$role->slug}\n";
                }
            }
            echo "\n";
        }
    }
}

echo "\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "3️⃣  SLUG MATCHING ANALYSIS\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "\n";

foreach ($applications as $app) {
    echo "📱 {$app->app_key}:\n";

    $roles = $app->roles()->pluck('slug')->toArray();
    echo "   ✓ Application Roles: " . (empty($roles) ? "(none)" : implode(", ", $roles)) . "\n";

    $profiles = AccessProfile::query()
        ->whereHas('roles', function ($q) use ($app) {
            $q->where('application_id', $app->id);
        })
        ->with(['roles' => function ($q) use ($app) {
            $q->where('application_id', $app->id);
        }])
        ->get();

    $coveredSlugs = [];
    foreach ($profiles as $profile) {
        foreach ($profile->roles as $role) {
            if (!in_array($role->slug, $coveredSlugs)) {
                $coveredSlugs[] = $role->slug;
            }
        }
    }

    echo "   ✓ Covered by Profiles: " . (empty($coveredSlugs) ? "(none)" : implode(", ", $coveredSlugs)) . "\n";

    $missing = array_diff($roles, $coveredSlugs);
    if (!empty($missing)) {
        echo "   ❌ MISSING PROFILE COVERAGE: " . implode(", ", $missing) . "\n";
        echo "      → Create AccessProfile or link roles to existing profile\n";
    } else if (!empty($roles)) {
        echo "   ✅ All roles have profile coverage\n";
    }

    echo "\n";
}

echo "\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "4️⃣  USER PROFILE ASSIGNMENTS (user_access_profiles)\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "\n";

foreach ($applications as $app) {
    echo "📱 {$app->app_key}:\n";

    $userQuery = User::query()
        ->whereHas('accessProfiles.roles', function ($q) use ($app) {
            $q->where('application_id', $app->id);
        });

    if ($userEmail) {
        $userQuery->where('email', $userEmail);
    }

    $users = $userQuery->with(['accessProfiles' => function ($q) use ($app) {
        $q->whereHas('roles', function ($q2) use ($app) {
            $q2->where('application_id', $app->id);
        })->with(['roles' => function ($q3) use ($app) {
            $q3->where('application_id', $app->id);
        }]);
    }])->get();

    if ($users->isEmpty()) {
        echo "   ℹ️  No users assigned to profiles for this app" . ($userEmail ? " with email=$userEmail" : "") . "\n";
    } else {
        foreach ($users as $user) {
            echo "   👤 User: {$user->name}\n";
            echo "      Email: {$user->email}\n";
            echo "      NIP: {$user->nip}\n";

            $profiles = $user->accessProfiles()
                ->whereHas('roles', function ($q) use ($app) {
                    $q->where('application_id', $app->id);
                })
                ->with(['roles' => function ($q) use ($app) {
                    $q->where('application_id', $app->id);
                }])
                ->get();

            if ($profiles->isEmpty()) {
                echo "      ⚠️  No profiles assigned\n";
            } else {
                echo "      📦 Assigned Profiles:\n";
                foreach ($profiles as $profile) {
                    $roleSlugs = $profile->roles->pluck('slug')->toArray();
                    echo "         • {$profile->name} (slug: {$profile->slug})\n";
                    echo "           Roles: " . implode(", ", $roleSlugs) . "\n";
                }
            }
            echo "\n";
        }
    }
}

echo "\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "✅ Debug report complete\n";
echo "\n";
echo "📝 How to use this output:\n";
echo "   1. Check 'SLUG MATCHING ANALYSIS' for ❌ MISSING PROFILE COVERAGE\n";
echo "   2. Create AccessProfiles for missing slugs\n";
echo "   3. Run user sync again\n";
echo "   4. Check 'USER PROFILE ASSIGNMENTS' to verify\n";
echo "\n";
echo "📖 Read USER-SYNC-DEBUG-GUIDE.md for detailed explanation\n";
echo "\n";
