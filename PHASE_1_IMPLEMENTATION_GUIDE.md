# Phase 1 Implementation Guide - Database Settings Foundation
**Detailed implementation steps and code templates**

---

## 📋 Phase 1 Overview
- **Duration:** Week 1-2
- **Goal:** Complete database and repository layer
- **Deliverables:** Model, Migration, Repository, Service, Tests

---

## Step 1: Create Migration

**File:** `database/migrations/2026_05_17_000000_create_settings_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            
            // Core fields
            $table->string('key')->unique()->index();
            $table->string('group')->index();  // 'sso', 'iam', 'auth', 'fortify'
            $table->longText('value');
            $table->string('type')->default('string'); // string, integer, boolean, array, json
            $table->text('description')->nullable();
            
            // Input configuration for UI
            $table->string('input_type')->default('text'); // text, number, toggle, select, textarea, email, url
            $table->json('select_options')->nullable();     // For select/radio/checkbox
            $table->json('validation_rules')->nullable();   // ['required', 'min:300', 'max:3600']
            
            // Security & metadata
            $table->boolean('is_readonly')->default(false);
            $table->boolean('is_sensitive')->default(false);
            $table->string('environment')->nullable();
            $table->string('category')->nullable();  // For UI grouping
            
            // Timestamps
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes for performance
            $table->index(['group', 'key']);
            $table->index(['created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
```

---

## Step 2: Create Setting Model

**File:** `app/Models/Setting.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Setting extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'key',
        'group',
        'value',
        'type',
        'description',
        'input_type',
        'select_options',
        'validation_rules',
        'is_readonly',
        'is_sensitive',
        'environment',
        'category',
    ];

    protected $casts = [
        'value' => 'string',
        'select_options' => 'array',
        'validation_rules' => 'array',
        'is_readonly' => 'boolean',
        'is_sensitive' => 'boolean',
    ];

    /**
     * Cast value to appropriate type
     */
    public function getValue(): mixed
    {
        return match ($this->type) {
            'integer' => (int) $this->value,
            'boolean' => filter_var($this->value, FILTER_VALIDATE_BOOLEAN),
            'array' => json_decode($this->value, true) ?? [],
            'json' => json_decode($this->value, true),
            default => (string) $this->value,
        };
    }

    /**
     * Get setting by key with optional group filter
     */
    public static function getByKey(string $key, ?string $group = null, mixed $default = null): mixed
    {
        $query = static::where('key', $key);
        
        if ($group) {
            $query->where('group', $group);
        }
        
        $setting = $query->first();
        return $setting?->getValue() ?? $default;
    }

    /**
     * Get all settings by group
     */
    public static function getByGroup(string $group): array
    {
        return static::where('group', $group)
            ->pluck('value', 'key')
            ->map(fn ($value, $key) => static::where('key', $key)->first()?->getValue() ?? $value)
            ->toArray();
    }

    /**
     * Get all settings as array
     */
    public static function all_as_array(): array
    {
        return static::all()
            ->mapWithKeys(fn ($setting) => [$setting->key => $setting->getValue()])
            ->toArray();
    }

    /**
     * Attribute mutator for formatted value display
     */
    protected function displayValue(): Attribute
    {
        return Attribute::make(
            get: function () {
                if ($this->is_sensitive) {
                    return '***masked***';
                }
                
                if ($this->type === 'json' || $this->type === 'array') {
                    return json_encode($this->getValue(), JSON_PRETTY_PRINT);
                }
                
                return (string) $this->value;
            }
        );
    }
}
```

---

## Step 3: Create Repository Interface

**File:** `app/Repositories/Contracts/SettingRepositoryInterface.php`

```php
<?php

namespace App\Repositories\Contracts;

use App\Models\Setting;

interface SettingRepositoryInterface
{
    /**
     * Get all settings
     */
    public function all(): array;

    /**
     * Get paginated settings
     */
    public function paginate(int $perPage = 15): mixed;

    /**
     * Get setting by key
     */
    public function getByKey(string $key): ?Setting;

    /**
     * Get all by group
     */
    public function getByGroup(string $group): array;

    /**
     * Create or update setting
     */
    public function upsert(string $key, mixed $value, string $type = 'string', string $group = 'general'): Setting;

    /**
     * Update setting
     */
    public function update(int $id, array $data): Setting;

    /**
     * Find by id
     */
    public function find(int $id): ?Setting;

    /**
     * Delete setting (soft delete)
     */
    public function delete(int $id): bool;

    /**
     * Get settings by group with filtering
     */
    public function getFiltered(string $group = null, array $filters = []): array;

    /**
     * Bulk upsert
     */
    public function bulkUpsert(array $settings): array;

    /**
     * Clear all cache
     */
    public function clearCache(): void;
}
```

---

## Step 4: Create Repository Implementation

**File:** `app/Repositories/SettingRepository.php`

```php
<?php

namespace App\Repositories;

use App\Models\Setting;
use App\Repositories\Contracts\SettingRepositoryInterface;
use Illuminate\Support\Facades\Cache;

class SettingRepository implements SettingRepositoryInterface
{
    private const CACHE_TTL = 3600; // 1 hour
    private const CACHE_PREFIX = 'setting:';

    public function all(): array
    {
        return Setting::all()
            ->mapWithKeys(fn ($s) => [$s->key => $s->getValue()])
            ->toArray();
    }

    public function paginate(int $perPage = 15): mixed
    {
        return Setting::paginate($perPage);
    }

    public function getByKey(string $key): ?Setting
    {
        $cacheKey = self::CACHE_PREFIX . $key;
        
        return Cache::remember(
            $cacheKey,
            self::CACHE_TTL,
            fn () => Setting::where('key', $key)->first()
        );
    }

    public function getByGroup(string $group): array
    {
        $cacheKey = self::CACHE_PREFIX . "group:{$group}";
        
        return Cache::remember(
            $cacheKey,
            self::CACHE_TTL,
            fn () => Setting::where('group', $group)
                ->pluck('value', 'key')
                ->mapWithKeys(function ($value, $key) {
                    $setting = Setting::where('key', $key)->first();
                    return [$key => $setting?->getValue() ?? $value];
                })
                ->toArray()
        );
    }

    public function upsert(string $key, mixed $value, string $type = 'string', string $group = 'general'): Setting
    {
        $setting = Setting::updateOrCreate(
            ['key' => $key],
            [
                'group' => $group,
                'value' => is_string($value) ? $value : json_encode($value),
                'type' => $type,
            ]
        );

        $this->invalidateCache($key);
        return $setting;
    }

    public function update(int $id, array $data): Setting
    {
        $setting = Setting::findOrFail($id);
        $setting->update($data);

        if (isset($data['value'])) {
            $this->invalidateCache($setting->key);
        }

        return $setting;
    }

    public function find(int $id): ?Setting
    {
        return Setting::find($id);
    }

    public function delete(int $id): bool
    {
        $setting = Setting::findOrFail($id);
        $key = $setting->key;
        
        $result = $setting->delete();
        $this->invalidateCache($key);
        
        return $result;
    }

    public function getFiltered(string $group = null, array $filters = []): array
    {
        $query = Setting::query();

        if ($group) {
            $query->where('group', $group);
        }

        if (isset($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('key', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if (isset($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        return $query->get()
            ->mapWithKeys(fn ($s) => [$s->key => $s->getValue()])
            ->toArray();
    }

    public function bulkUpsert(array $settings): array
    {
        $upserted = [];

        foreach ($settings as $key => $data) {
            $value = $data['value'] ?? null;
            $type = $data['type'] ?? 'string';
            $group = $data['group'] ?? 'general';

            $setting = $this->upsert($key, $value, $type, $group);
            $upserted[] = $setting;
        }

        return $upserted;
    }

    public function clearCache(): void
    {
        Cache::tags([self::CACHE_PREFIX])->flush();
    }

    private function invalidateCache(string $key): void
    {
        Cache::forget(self::CACHE_PREFIX . $key);
        
        // Also invalidate group cache
        $setting = Setting::where('key', $key)->first();
        if ($setting) {
            Cache::forget(self::CACHE_PREFIX . "group:{$setting->group}");
        }
    }
}
```

---

## Step 5: Create Service Layer

**File:** `app/Services/SettingService.php`

```php
<?php

namespace App\Services;

use App\Models\Setting;
use App\Repositories\Contracts\SettingRepositoryInterface;
use Illuminate\Validation\ValidationException;

class SettingService
{
    public function __construct(
        private SettingRepositoryInterface $repository,
    ) {}

    /**
     * Get setting value
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $setting = $this->repository->getByKey($key);
        return $setting?->getValue() ?? $default;
    }

    /**
     * Get all settings by group
     */
    public function getGroup(string $group): array
    {
        return $this->repository->getByGroup($group);
    }

    /**
     * Set/update a setting
     */
    public function set(string $key, mixed $value, string $type = 'string', string $group = 'general'): Setting
    {
        $setting = $this->repository->upsert($key, $value, $type, $group);
        return $setting;
    }

    /**
     * Update multiple settings at once
     */
    public function setMultiple(array $settings): array
    {
        return $this->repository->bulkUpsert($settings);
    }

    /**
     * Get with validation
     */
    public function getWithValidation(string $key, array $validationRules = []): mixed
    {
        $setting = $this->repository->getByKey($key);

        if (!$setting) {
            throw new \Exception("Setting not found: {$key}");
        }

        $value = $setting->getValue();

        if ($validationRules) {
            // Validate using Laravel Validator
            $validator = validator(['value' => $value], ['value' => $validationRules]);
            if ($validator->fails()) {
                throw ValidationException::withMessages(['value' => $validator->errors()->first()]);
            }
        }

        return $value;
    }

    /**
     * Check if setting exists
     */
    public function has(string $key): bool
    {
        return $this->repository->getByKey($key) !== null;
    }

    /**
     * Delete setting
     */
    public function delete(int $id): bool
    {
        return $this->repository->delete($id);
    }

    /**
     * Get all settings as array
     */
    public function all(): array
    {
        return $this->repository->all();
    }
}
```

---

## Step 6: Create Service Provider

**File:** `app/Providers/SettingServiceProvider.php`

```php
<?php

namespace App\Providers;

use App\Repositories\Contracts\SettingRepositoryInterface;
use App\Repositories\SettingRepository;
use App\Services\SettingService;
use Illuminate\Support\ServiceProvider;

class SettingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Bind repository to interface
        $this->app->singleton(
            SettingRepositoryInterface::class,
            SettingRepository::class
        );

        // Bind service
        $this->app->singleton(SettingService::class, function ($app) {
            return new SettingService(
                $app->make(SettingRepositoryInterface::class)
            );
        });
    }

    public function boot(): void
    {
        // Register commands, publish config, etc.
    }
}
```

**Register in `config/app.php`:**

```php
'providers' => [
    // ... other providers
    App\Providers\SettingServiceProvider::class,
],
```

---

## Step 7: Create Seeder

**File:** `database/seeders/SettingsSeeder.php`

```php
<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        // SSO Settings
        $ssoSettings = [
            [
                'key' => 'sso.issuer',
                'group' => 'sso',
                'value' => config('sso.issuer', env('APP_URL', 'iam-server')),
                'type' => 'string',
                'description' => 'Identifies the IAM server in issued tokens',
                'input_type' => 'text',
                'validation_rules' => ['required', 'string', 'min:3'],
            ],
            [
                'key' => 'sso.ttl',
                'group' => 'sso',
                'value' => (string) config('sso.ttl', 300),
                'type' => 'integer',
                'description' => 'Token time-to-live in seconds',
                'input_type' => 'number',
                'validation_rules' => ['required', 'integer', 'min:300', 'max:86400'],
            ],
            [
                'key' => 'sso.backchannel.signature_header',
                'group' => 'sso',
                'value' => config('sso.backchannel.signature_header', 'IAM-Signature'),
                'type' => 'string',
                'description' => 'Custom header for backchannel signature verification',
                'input_type' => 'text',
                'validation_rules' => ['required', 'string'],
            ],
        ];

        // IAM Settings
        $iamSettings = [
            [
                'key' => 'iam.issuer',
                'group' => 'iam',
                'value' => config('iam.issuer', 'https://iam.local'),
                'type' => 'string',
                'description' => 'IAM issuer identifier for JWT tokens',
                'input_type' => 'url',
                'validation_rules' => ['required', 'url'],
            ],
            [
                'key' => 'iam.token_ttl',
                'group' => 'iam',
                'value' => (string) config('iam.token_ttl', 3600),
                'type' => 'integer',
                'description' => 'Default token lifetime in seconds',
                'input_type' => 'number',
                'validation_rules' => ['required', 'integer', 'min:300', 'max:604800'],
            ],
            [
                'key' => 'iam.unit_kerja_delete_soft',
                'group' => 'iam',
                'value' => config('iam.unit_kerja_delete_soft', 'false') ? 'true' : 'false',
                'type' => 'boolean',
                'description' => 'Use soft delete for unit kerja records',
                'input_type' => 'toggle',
            ],
        ];

        // Auth Settings
        $authSettings = [
            [
                'key' => 'auth.default_guard',
                'group' => 'auth',
                'value' => config('auth.defaults.guard', 'web'),
                'type' => 'string',
                'description' => 'Default authentication guard',
                'input_type' => 'select',
                'select_options' => ['web', 'api'],
                'validation_rules' => ['required', 'in:web,api'],
            ],
        ];

        // Fortify Settings
        $fortifySettings = [
            [
                'key' => 'fortify.username',
                'group' => 'fortify',
                'value' => config('fortify.username', 'nip'),
                'type' => 'string',
                'description' => 'Username field for authentication',
                'input_type' => 'text',
                'validation_rules' => ['required', 'string'],
            ],
            [
                'key' => 'fortify.lowercase_usernames',
                'group' => 'fortify',
                'value' => config('fortify.lowercase_usernames', 'true') ? 'true' : 'false',
                'type' => 'boolean',
                'description' => 'Force lowercase usernames',
                'input_type' => 'toggle',
            ],
        ];

        $allSettings = array_merge($ssoSettings, $iamSettings, $authSettings, $fortifySettings);

        foreach ($allSettings as $setting) {
            Setting::updateOrCreate(
                ['key' => $setting['key']],
                $setting
            );
        }

        $this->command->info('✓ Settings seeded successfully');
    }
}
```

---

## Step 8: Create Tests

### Unit Tests

**File:** `tests/Unit/Services/SettingServiceTest.php`

```php
<?php

namespace Tests\Unit\Services;

use App\Models\Setting;
use App\Repositories\SettingRepository;
use App\Services\SettingService;
use Tests\TestCase;

class SettingServiceTest extends TestCase
{
    private SettingService $service;
    private SettingRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->repository = new SettingRepository();
        $this->service = new SettingService($this->repository);
    }

    public function test_can_get_setting_value(): void
    {
        $setting = Setting::factory()->create([
            'key' => 'test.key',
            'value' => 'test-value',
            'type' => 'string',
        ]);

        $value = $this->service->get('test.key');

        $this->assertEquals('test-value', $value);
    }

    public function test_returns_default_when_setting_not_found(): void
    {
        $value = $this->service->get('nonexistent.key', 'default-value');

        $this->assertEquals('default-value', $value);
    }

    public function test_can_set_setting(): void
    {
        $setting = $this->service->set('new.setting', 'value', 'string', 'test');

        $this->assertNotNull($setting->id);
        $this->assertEquals('new.setting', $setting->key);
        $this->assertEquals('value', $setting->value);
    }

    public function test_can_get_settings_by_group(): void
    {
        Setting::factory()->create(['group' => 'sso', 'key' => 'sso.ttl', 'value' => '300']);
        Setting::factory()->create(['group' => 'sso', 'key' => 'sso.issuer', 'value' => 'iam']);

        $settings = $this->service->getGroup('sso');

        $this->assertCount(2, $settings);
        $this->assertEquals('300', $settings['sso.ttl']);
    }

    public function test_integer_type_is_cast_properly(): void
    {
        Setting::factory()->create([
            'key' => 'test.int',
            'value' => '300',
            'type' => 'integer',
        ]);

        $value = $this->service->get('test.int');

        $this->assertIsInt($value);
        $this->assertEquals(300, $value);
    }

    public function test_boolean_type_is_cast_properly(): void
    {
        Setting::factory()->create([
            'key' => 'test.bool',
            'value' => 'true',
            'type' => 'boolean',
        ]);

        $value = $this->service->get('test.bool');

        $this->assertIsBool($value);
        $this->assertTrue($value);
    }

    public function test_can_delete_setting(): void
    {
        $setting = Setting::factory()->create();

        $result = $this->service->delete($setting->id);

        $this->assertTrue($result);
        $this->assertTrue($setting->fresh()->trashed());
    }
}
```

---

## Step 9: Database & Cache Integration

### Create Config Helper

**File:** `config/settings.php`

```php
<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Settings Configuration
    |--------------------------------------------------------------------------
    */

    'groups' => [
        'sso' => 'SSO Configuration',
        'iam' => 'IAM Configuration',
        'auth' => 'Authentication Settings',
        'fortify' => 'Fortify Settings',
    ],

    'cache_ttl' => 3600, // 1 hour

    'sensitive_keys' => [
        'sso.secret',
        'iam.jwt_secret',
        'iam.signing_key',
    ],
];
```

---

## Step 10: Publish & Migrate

```bash
# Create migration
php artisan make:migration create_settings_table

# Seed initial data
php artisan db:seed SettingsSeeder

# Test the service
php artisan tinker
>>> app(App\Services\SettingService::class)->get('sso.ttl')
>>> app(App\Services\SettingService::class)->getGroup('sso')
```

---

## Usage Examples

### In Controllers/Services

```php
use App\Services\SettingService;

class MyController
{
    public function __construct(
        private SettingService $settings
    ) {}

    public function show()
    {
        $ssoTtl = $this->settings->get('sso.ttl', 300);
        $iamSettings = $this->settings->getGroup('iam');
        
        return view('my.view', [
            'sso_ttl' => $ssoTtl,
            'iam_settings' => $iamSettings,
        ]);
    }
}
```

### In Config Files

```php
// config/sso.php
return [
    'ttl' => app(App\Services\SettingService::class)->get('sso.ttl', env('SSO_TTL', 300)),
    'issuer' => app(App\Services\SettingService::class)->get('sso.issuer', env('SSO_ISSUER')),
];
```

---

## Next Steps (Phase 2)

1. Implement Filament Resource & Pages
2. Add form validation with dynamic rules
3. Implement activity logging
4. Create grouped settings view

---

**Created:** May 17, 2026
