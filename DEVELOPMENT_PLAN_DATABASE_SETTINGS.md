# Planning Pengembangan Database Settings System
**Auth Server - Dynamic Configuration Management**

---

## 📋 Overview

Konversi beberapa configuration dari file-based (`config/*.php`) menjadi dynamic settings yang dapat diatur melalui:
- **Filament Admin UI** (User-friendly panel)
- **Database Storage** (Persistent, real-time updates)
- **API Endpoints** (Programmatic access)

Sistem ini memungkinkan admin mengubah konfigurasi tanpa perlu redeploy aplikasi.

---

## 🎯 Configs yang Akan Dikonversi

### 1. **SSO Settings** (`config/sso.php`)
- `sso.issuer` - Issuer identifier untuk SSO tokens
- `sso.ttl` - Token time-to-live (seconds)
- `sso.backchannel.signature_header` - Custom signature header

### 2. **IAM Settings** (`config/iam.php`)
- `iam.home_app` - Konfigurasi home application (enabled, name, url, logo)
- `iam.issuer` - IAM issuer identifier
- `iam.token_ttl` - Default token lifetime
- `iam.unit_kerja_delete_soft` - Soft delete behavior
- `iam.user_delete_soft` - Soft delete behavior
- `iam.push_deleted_records` - Include deleted records in push

### 3. **Authentication Settings** (`config/auth.php`)
- `auth.defaults.guard` - Default authentication guard
- `auth.defaults.passwords` - Default password broker

### 4. **Fortify Settings** (`config/fortify.php`)
- `fortify.username` - Username field (nip, email, etc)
- `fortify.lowercase_usernames` - Force lowercase usernames
- `fortify.home` - Redirect path after authentication

---

## 🏗️ Architecture & System Design

```
┌─────────────────────────────────────────────────────────────┐
│                    FILAMENT UI LAYER                         │
│              (Settings Admin Panel Interface)                 │
└────────────────────┬────────────────────────────────────────┘
                     │
┌─────────────────────┴────────────────────────────────────────┐
│            APPLICATION SERVICE LAYER                          │
│  ├─ SettingService (Core Business Logic)                    │
│  ├─ ValidationService (Input validation & rules)             │
│  └─ CacheService (Redis caching layer)                       │
└────────────────────┬────────────────────────────────────────┘
                     │
┌─────────────────────┴────────────────────────────────────────┐
│            DATA ACCESS LAYER                                  │
│  ├─ SettingRepository (Database operations)                  │
│  ├─ SettingModel (Eloquent model)                            │
│  └─ SettingFactory (Data normalization)                      │
└────────────────────┬────────────────────────────────────────┘
                     │
┌─────────────────────┴────────────────────────────────────────┐
│          DATABASE & CACHE LAYER                               │
│  ├─ settings table (persistent storage)                       │
│  └─ Redis cache (performance optimization)                    │
└─────────────────────────────────────────────────────────────┘
```

### Key Principles:
1. **Write Through Cache** - Update database, then cache
2. **Cascade Invalidation** - Clear related cache keys on updates
3. **Type Safety** - Settings validated by type (string, int, bool, json)
4. **Fallback Mechanism** - Fallback ke config files jika setting tidak ada
5. **Audit Trail** - Log setiap perubahan setting di activity_log

---

## 📊 Database Schema

### Migration: `create_settings_table`

```php
Schema::create('settings', function (Blueprint $table) {
    $table->id();
    
    // Basic fields
    $table->string('key')->unique()->index();  // 'sso.ttl', 'iam.issuer', dll
    $table->string('group')->index();           // 'sso', 'iam', 'auth', 'fortify'
    $table->longText('value');                  // JSON stored value
    $table->string('type');                     // 'string', 'integer', 'boolean', 'array', 'json'
    $table->text('description')->nullable();    // UI help text
    
    // Validation & constraints
    $table->json('validation_rules')->nullable();  // ['required', 'min:300', 'max:3600']
    $table->string('input_type')->default('text'); // 'text', 'number', 'toggle', 'select', 'textarea'
    $table->json('select_options')->nullable();    // For select/radio inputs
    
    // Metadata
    $table->boolean('is_readonly')->default(false);     // Cannot be edited via UI
    $table->boolean('is_sensitive')->default(false);    // Hide value in logs
    $table->string('environment')->nullable();           // For env-specific settings
    
    // Timestamps
    $table->timestamps();
    $table->softDeletes();
    
    // Indexes
    $table->index(['group', 'key']);
    $table->index(['created_at']);
});
```

### Example Data

```sql
-- SSO Settings
| key                        | value              | type    | group |
|----------------------------|--------------------|---------|-------|
| sso.issuer                 | iam-server         | string  | sso   |
| sso.ttl                    | 300                | integer | sso   |
| sso.backchannel.sig_header | IAM-Signature      | string  | sso   |

-- IAM Settings
| iam.issuer                 | https://iam.local  | string  | iam   |
| iam.token_ttl              | 3600               | integer | iam   |
| iam.home_app               | {...json...}       | json    | iam   |

-- Auth Settings
| auth.default_guard         | web                | string  | auth  |
```

---

## 🗂️ File Structure & Components

### 1. Models & Database
```
app/Models/Setting.php                    # Eloquent Model
database/migrations/*_create_settings_table.php
database/factories/SettingFactory.php      # For testing
database/seeders/SettingsSeeder.php        # Initial seed data
```

### 2. Repositories & Data Access
```
app/Repositories/SettingRepository.php     # Query builder abstraction
app/Repositories/Contracts/SettingRepositoryInterface.php
```

### 3. Services & Business Logic
```
app/Services/SettingService.php            # Core business logic
app/Services/SettingCacheService.php       # Cache management
app/Services/SettingValidationService.php  # Validation rules
app/Services/SettingAuditService.php       # Logging changes
```

### 4. Filament Resources
```
app/Filament/Panel/Resources/SettingResource.php
app/Filament/Panel/Resources/Settings/Pages/ListSettings.php
app/Filament/Panel/Resources/Settings/Pages/EditSetting.php
app/Filament/Panel/Resources/Settings/Pages/ViewSetting.php
app/Filament/Panel/Resources/Settings/Pages/SettingsByGroup.php  # Grouped view
```

### 5. API Endpoints
```
routes/api.php                              # API routes
app/Http/Controllers/Api/SettingController.php

Endpoints:
  GET    /api/settings                      # List all settings
  GET    /api/settings/{group}              # List by group (sso, iam, etc)
  GET    /api/settings/{group}/{key}        # Get single setting
  PUT    /api/settings/{group}/{key}        # Update setting
  PUT    /api/settings/bulk                 # Update multiple settings
  DELETE /api/settings/{id}                 # Delete (soft delete)
```

### 6. Utilities & Helpers
```
app/Support/SettingDefinitions.php         # Define available settings schema
app/Support/SettingFormatter.php           # Convert between types
config/settings.php                        # Default values & schema
```

---

## 📁 Implementation Phases

### **Phase 1: Foundation (Week 1-2)**

**Tasks:**
1. ✅ Create `Setting` Eloquent Model
2. ✅ Create migration: `create_settings_table`
3. ✅ Create `SettingRepository` & `SettingRepositoryInterface`
4. ✅ Create `SettingService` (basic CRUD)
5. ✅ Create `SettingCacheService` (Redis wrapper)
6. ✅ Create seed data for current config values
7. ✅ Create unit tests for Service & Repository

**Deliverables:**
- Database layer fully functional
- Basic setting retrieval/storage
- Cache integration

---

### **Phase 2: Filament Integration (Week 2-3)**

**Tasks:**
1. ✅ Create `SettingResource` with Filament
2. ✅ Create grouped views by category (SSO, IAM, Auth, Fortify)
3. ✅ Implement form validation with dynamic rules
4. ✅ Add readonly/sensitive field handling
5. ✅ Create activity log integration (AuditService)
6. ✅ Add batch edit functionality
7. ✅ Integration tests for Filament pages

**Deliverables:**
- Fully functional Filament UI
- Settings grouped and categorized
- Audit trail of changes
- Sensitive fields masked

---

### **Phase 3: API Layer (Week 3-4)**

**Tasks:**
1. ✅ Create `SettingController` API endpoints
2. ✅ Implement GET endpoints with filtering/pagination
3. ✅ Implement PUT/PATCH endpoints with validation
4. ✅ Implement DELETE endpoint (soft delete)
5. ✅ Add bulk update endpoint
6. ✅ API versioning & documentation
7. ✅ API tests (feature tests)

**Deliverables:**
- RESTful API fully functional
- Documented API endpoints
- Rate limiting (optional)

---

### **Phase 4: Config Layer Integration (Week 4)**

**Tasks:**
1. ✅ Create `ConfigProvider` service to read from database-first
2. ✅ Create fallback mechanism to config files
3. ✅ Update `config/app.php`, `config/sso.php`, `config/iam.php` helpers
4. ✅ Create config cache warming (artisan command)
5. ✅ Update environment variable handling
6. ✅ Integration tests across config layers

**Deliverables:**
- Application reads config from database with fallback
- Backward compatible with existing code
- Configuration changes take effect immediately

---

### **Phase 5: Advanced Features (Week 5)**

**Tasks:**
1. ✅ Feature flags (settings as feature toggles)
2. ✅ Multi-environment support (dev, staging, prod)
3. ✅ Settings versioning/rollback
4. ✅ Scheduled settings changes (time-based)
5. ✅ Import/export settings
6. ✅ Settings comparison view

**Deliverables:**
- Advanced management features
- Environment-specific configs

---

### **Phase 6: Testing & Documentation (Week 5-6)**

**Tasks:**
1. ✅ Unit tests (Service, Repository, Model)
2. ✅ Feature tests (API, Filament)
3. ✅ Integration tests (Config chain)
4. ✅ Load testing (Cache performance)
5. ✅ API documentation (OpenAPI/Swagger)
6. ✅ User guide (admin manual)
7. ✅ Developer guide

**Deliverables:**
- Test coverage > 85%
- Full documentation
- Performance benchmarks

---

## 🔐 Security Considerations

### 1. **Access Control**
```php
// Only certain roles can modify settings
- Gate::define('manage-settings', fn ($user) => $user->can('manage-settings'));
- Use Filament policies
```

### 2. **Sensitive Settings**
```php
// Mask in UI
- Mark as `is_sensitive` flag
- Use password/hidden input type
- Don't log values in activity log
- Encrypt in database (optional)
```

### 3. **Validation Rules**
```php
// Type safety
- Validate ranges (ttl: 300-3600)
- Validate URLs, emails
- Prevent injection attacks
```

### 4. **Audit Trail**
```php
// Track all changes
- Who changed what and when
- Previous vs new values
- IP address, user agent
```

### 5. **Rate Limiting**
```php
// API protection
- Limit settings updates
- Throttle bulk operations
```

---

## 📝 Code Examples

### 1. Setting Model

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Setting extends Model
{
    use SoftDeletes;
    
    protected $fillable = ['key', 'group', 'value', 'type', 'description'];
    
    protected $casts = [
        'value' => 'string',
        'validation_rules' => 'json',
        'select_options' => 'json',
    ];
    
    public static function getByKey(string $key, mixed $default = null): mixed
    {
        $setting = static::where('key', $key)->first();
        return $setting ? static::castValue($setting->value, $setting->type) : $default;
    }
    
    private static function castValue(mixed $value, string $type): mixed
    {
        return match($type) {
            'integer' => (int) $value,
            'boolean' => (bool) $value,
            'array', 'json' => json_decode($value, true),
            default => (string) $value,
        };
    }
}
```

### 2. SettingService

```php
namespace App\Services;

use App\Models\Setting;
use App\Repositories\SettingRepository;
use Illuminate\Cache\Repository;

class SettingService
{
    public function __construct(
        private SettingRepository $repository,
        private SettingCacheService $cache,
        private Repository $cacheStore,
    ) {}
    
    public function get(string $key, mixed $default = null): mixed
    {
        $cacheKey = "setting:{$key}";
        
        return $this->cache->remember(
            $cacheKey,
            fn() => $this->repository->getByKey($key)?->getValue() ?? $default
        );
    }
    
    public function set(string $key, mixed $value, string $type = 'string'): Setting
    {
        $setting = $this->repository->findOrCreateByKey($key);
        $setting->update(['value' => $value, 'type' => $type]);
        
        // Invalidate cache
        $this->cache->forget("setting:{$key}");
        
        return $setting;
    }
    
    public function setMultiple(array $settings): void
    {
        foreach ($settings as $key => $value) {
            $this->set($key, $value);
        }
    }
}
```

### 3. Filament Resource

```php
namespace App\Filament\Panel\Resources;

use App\Models\Setting;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Filament\Forms\Form;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;

class SettingResource extends Resource
{
    protected static ?string $model = Setting::class;
    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';
    protected static ?string $navigationGroup = 'Configuration';
    
    public static function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('key')->unique()->required(),
            TextInput::make('value')->required(),
            // ... more fields
        ]);
    }
    
    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('key')->sortable()->searchable(),
            TextColumn::make('value')->limit(50),
            TextColumn::make('group')->badge(),
        ]);
    }
}
```

---

## 🧪 Testing Strategy

### Unit Tests
```
tests/Unit/Services/SettingServiceTest.php
tests/Unit/Repositories/SettingRepositoryTest.php
tests/Unit/Models/SettingTest.php
```

### Feature Tests
```
tests/Feature/SettingControllerTest.php      # API endpoints
tests/Feature/SettingResourceTest.php        # Filament UI
```

### Integration Tests
```
tests/Integration/ConfigLayerTest.php        # Config chain
tests/Integration/CacheInvalidationTest.php  # Cache behavior
```

---

## 📊 Performance Optimization

### 1. **Caching Strategy**
- First access: Database → Cache
- Subsequent access: Cache
- TTL: 1 hour (configurable)
- Manual invalidation on update

### 2. **Query Optimization**
- Index on `(group, key)`
- Select only needed columns
- Use `pluck()` for bulk retrieval

### 3. **Bulk Operations**
```php
// Efficient batch updates
Setting::upsert([
    ['key' => 'sso.ttl', 'value' => '300'],
    ['key' => 'iam.ttl', 'value' => '3600'],
], ['key'], ['value']);
```

---

## 🚀 Deployment & Migration

### Migration Path
1. **Create new settings table** (backward compatible)
2. **Seed initial data** from current config
3. **Update code** to read from database-first
4. **Run tests** across environments
5. **Monitor** for issues during rollout
6. **Gradual rollout** to production

### Rollback Plan
- Keep config files as fallback
- Settings service checks database first
- If database unavailable, uses config files
- Manual rollback: delete settings data, restart

---

## 📈 Success Metrics

- [ ] All identified configs converted to settings
- [ ] Filament UI fully functional
- [ ] API endpoints working with 95%+ uptime
- [ ] Test coverage > 85%
- [ ] Cache hit rate > 90%
- [ ] Zero data loss during migration
- [ ] Admin time to change setting: < 2 minutes

---

## 🔄 Maintenance & Monitoring

### Artisan Commands
```bash
# Seed initial settings
php artisan db:seed SettingsSeeder

# Warm up cache
php artisan settings:cache-warm

# Export settings
php artisan settings:export

# Import settings
php artisan settings:import

# Validate all settings
php artisan settings:validate

# Clear cache
php artisan settings:cache-clear
```

### Monitoring
- Database query performance
- Cache hit/miss ratios
- Setting modification frequency
- API response times

---

## 📚 References & Resources

- Filament Documentation: https://filamentphp.com/
- Laravel Config: https://laravel.com/docs/configuration
- Caching Best Practices: https://laravel.com/docs/cache
- Activity Log: https://github.com/spatie/laravel-activity-log

---

## 👥 Team Assignments (Example)

| Phase | Task | Owner | Duration |
|-------|------|-------|----------|
| 1 | Database Layer | Backend Dev 1 | 1 week |
| 2 | Filament UI | Backend Dev 2 | 1 week |
| 3 | API Layer | Backend Dev 1 | 1 week |
| 4 | Config Integration | Backend Dev 2 | 1 week |
| 5 | Advanced Features | Both | 1 week |
| 6 | Testing & Docs | QA + Backend | 1 week |

---

**Last Updated:** May 17, 2026  
**Status:** Planning Phase ✏️  
**Next Step:** Phase 1 Implementation Sprint
