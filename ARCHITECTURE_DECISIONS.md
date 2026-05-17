# Architecture & Technical Decisions
**Database Settings System - Tech Deep Dive**

---

## 🏗️ Architecture Decision Records (ADR)

### ADR-001: Settings Storage & Retrieval Pattern

**Decision:** Use database as primary source with Redis caching layer

**Rationale:**
- ✅ Real-time updates without deployment
- ✅ Multiple instances access same config
- ✅ Audit trail of changes
- ✅ Redis provides sub-millisecond retrieval
- ✅ Graceful fallback to config files if database unavailable

**Trade-offs:**
- ⚠️ Slight latency on first read after update (cache invalidation)
- ⚠️ Requires database connection
- ⚠️ Additional database queries during cold cache

**Implementation:**
```
Request → Check Redis Cache
         ├─ CACHE HIT: Return value
         └─ CACHE MISS: Query DB → Store in Cache → Return value

Update → DB write → Invalidate Cache
```

---

### ADR-002: Type System for Settings

**Decision:** Support: string, integer, boolean, array, json

**Rationale:**
- String, integer, boolean: Common config types
- Array/JSON: Complex config structures (home_app, select_options)
- Type casting ensures type safety in application

**Casting Map:**
```php
'string'  → (string) $value
'integer' → (int) $value
'boolean' → filter_var($value, FILTER_VALIDATE_BOOLEAN)
'array'   → json_decode($value, true)
'json'    → json_decode($value, true)
```

---

### ADR-003: Migration Strategy - Config Files as Fallback

**Decision:** Keep config files as fallback, implement database-first lookup

**Rationale:**
- ✅ Zero-downtime deployment
- ✅ Graceful degradation if database unreachable
- ✅ Easy rollback
- ✅ Backward compatible

**Configuration Priority:**
```
1. Database Settings Table (highest priority)
2. Environment Variables (fallback)
3. Config Files (fallback)
4. Hard-coded defaults (lowest priority)
```

**Example:**
```php
// config/sso.php
return [
    'ttl' => setting('sso.ttl', env('SSO_TTL', config('sso.ttl', 300))),
];
```

---

### ADR-004: Repository Pattern for Data Access

**Decision:** Use Repository Pattern + Dependency Injection

**Rationale:**
- ✅ Testable (easy to mock)
- ✅ Decoupled from implementation
- ✅ Single responsibility
- ✅ Easy to swap implementations

**Pattern:**
```
Interface (Contract)
    ↑
    │ implements
    │
Concrete Repository → Query DB
    ↑
    │ injected into
    │
Service Layer (Business Logic)
    ↑
    │ used by
    │
Controller/Command
```

---

### ADR-005: Cache Invalidation Strategy

**Decision:** Manual invalidation on write + TTL expiration

**Rationale:**
- ✅ Predictable cache behavior
- ✅ No race conditions
- ✅ Easy to debug cache issues
- ✅ TTL provides automatic cleanup

**Invalidation Points:**
1. Setting create/update → Invalidate specific key cache
2. Setting create/update → Invalidate group cache
3. Bulk operations → Invalidate all affected caches
4. Delete operation → Invalidate caches

```php
private function invalidateCache(string $key): void
{
    Cache::forget("setting:{$key}");
    
    $setting = Setting::where('key', $key)->first();
    if ($setting) {
        Cache::forget("setting:group:{$setting->group}");
    }
}
```

---

## 🔌 Integration Points

### 1. Config Chain Integration

**How it works:**

```php
// 1. Define helper function
function setting(string $key, mixed $default = null): mixed {
    return app(SettingService::class)->get($key, $default);
}

// 2. Use in config files
// config/sso.php
return [
    'ttl' => setting('sso.ttl', env('SSO_TTL', 300)),
    'issuer' => setting('sso.issuer', env('SSO_ISSUER')),
];

// 3. Use in application
$ssoTtl = config('sso.ttl');  // Gets value from database or falls back to env/config
```

**Timeline:**
- Cold start: Database query → Cache write → Return
- Warm cache: Cache hit → Return (~1-5ms)

---

### 2. Activity Log Integration

**Audit Trail for Settings Changes:**

```php
// Using spatie/laravel-activity-log (already in composer.json)
namespace App\Services;

class SettingService
{
    public function set(string $key, mixed $value, string $type = 'string'): Setting
    {
        $oldValue = $this->repository->getByKey($key)?->getValue();
        
        $setting = $this->repository->upsert($key, $value, $type);
        
        // Log activity
        activity()
            ->causedBy(auth()->user())
            ->withProperties([
                'key' => $key,
                'old_value' => $this->maskSensitive($key, $oldValue),
                'new_value' => $this->maskSensitive($key, $value),
            ])
            ->log('Setting updated: ' . $key);
        
        return $setting;
    }
    
    private function maskSensitive(string $key, mixed $value): mixed
    {
        $sensitiveKeys = config('settings.sensitive_keys', []);
        
        if (in_array($key, $sensitiveKeys)) {
            return '***masked***';
        }
        
        return $value;
    }
}
```

**Query activity log:**
```php
// View setting change history
activity()
    ->where('description', 'like', 'Setting updated%')
    ->latest()
    ->take(20)
    ->get();
```

---

### 3. Filament Admin Panel Integration

**Resource Structure:**

```
SettingResource/
├── Pages/
│   ├── ListSettings.php          # Table view with all settings
│   ├── EditSetting.php           # Edit individual setting
│   ├── SettingsByGroup.php       # Grouped view (SSO, IAM, etc)
│   └── SettingsOverview.php      # Dashboard with quick access
└── Tables/
    └── SettingsTable.php         # Custom table configuration
```

**Feature: Grouped Edit Page**

```php
// app/Filament/Panel/Resources/Settings/Pages/SettingsByGroup.php
public function mount(string $group): void
{
    $this->group = $group;
    
    $settings = Setting::where('group', $group)->get();
    
    foreach ($settings as $setting) {
        $this->form->fill($setting->toArray());
    }
}

public function save(): void
{
    $data = $this->form->getState();
    
    foreach ($data as $key => $value) {
        Setting::where('key', $key)->update(['value' => $value]);
    }
    
    // Invalidate cache for entire group
    Cache::forget("setting:group:{$this->group}");
}
```

---

### 4. API Endpoints

**RESTful API for Settings:**

```
GET    /api/settings                    # List all settings
GET    /api/settings?group=sso          # Filter by group
GET    /api/settings/{id}               # Get single setting
PUT    /api/settings/{id}               # Update setting
PUT    /api/settings/bulk               # Batch update
DELETE /api/settings/{id}               # Delete (soft)
POST   /api/settings/restore/{id}       # Restore soft-deleted
```

**Implementation:**
```php
// app/Http/Controllers/Api/SettingController.php
public function index(Request $request)
{
    $query = Setting::query();
    
    if ($group = $request->get('group')) {
        $query->where('group', $group);
    }
    
    return $query->paginate(15);
}

public function update(Setting $setting, Request $request)
{
    $validated = $request->validate([
        'value' => 'required',
    ]);
    
    $setting->update($validated);
    
    return $setting;
}
```

---

## 🔐 Security Architecture

### Authentication & Authorization

```php
// Only admins can manage settings
Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('settings', SettingController::class)
        ->middleware('can:manage-settings');
});

// In Policy
class SettingPolicy
{
    public function update(User $user, Setting $setting): bool
    {
        return $user->hasRole('admin') || 
               $user->hasPermission('manage-settings');
    }
}
```

### Sensitive Data Handling

```php
// Mark sensitive settings
protected $sensitive_keys = [
    'sso.secret',
    'iam.jwt_secret',
    'iam.signing_key',
];

// Mask in UI
protected $casts = [
    'display_value' => function () {
        return $this->is_sensitive ? '***masked***' : $this->value;
    }
];

// Don't log sensitive values
private function maskSensitive($key, $value) {
    $sensitiveKeys = config('settings.sensitive_keys', []);
    return in_array($key, $sensitiveKeys) ? '***masked***' : $value;
}
```

---

## 📊 Data Flow Diagrams

### Setting Read Flow

```
┌─────────────────────────────────────────┐
│ Request for setting value               │
│ service->get('sso.ttl')                 │
└──────────────────┬──────────────────────┘
                   │
        ┌──────────▼───────────┐
        │ Check Redis Cache    │
        └──────────┬───────────┘
                   │
        ┌──────────┴──────────┐
        │                     │
    ┌───▼────┐         ┌─────▼──────┐
    │ HIT    │         │ MISS       │
    │Return │         │Query DB    │
    │ value │         │Store cache │
    └────────┘         │Return value│
    (1-5ms)           └────────────┘
                      (5-50ms)
```

### Setting Write Flow

```
┌──────────────────────────────────┐
│ service->set('sso.ttl', 300)     │
└──────────────┬───────────────────┘
               │
        ┌──────▼────────┐
        │ Update DB     │
        └──────┬────────┘
               │
        ┌──────▼──────────────────┐
        │ Invalidate Caches       │
        │ - setting:sso.ttl       │
        │ - setting:group:sso     │
        └──────┬───────────────────┘
               │
        ┌──────▼──────────────┐
        │ Log to activity_log │
        └─────────────────────┘
               │
        ┌──────▼────────────────┐
        │ Return updated Setting│
        └──────────────────────┘
```

---

## 🛠️ Maintenance & Operations

### Artisan Commands

```bash
# Initialize settings from current config
php artisan settings:init

# Warm up cache for all settings
php artisan settings:cache-warm

# Clear all settings cache
php artisan settings:cache-clear

# Validate all settings against rules
php artisan settings:validate

# Export settings to JSON
php artisan settings:export --group=sso

# Import settings from JSON
php artisan settings:import settings.json

# Show setting change history
php artisan settings:audit --key=sso.ttl --limit=10
```

### Monitoring & Debugging

```bash
# Check Redis cache stats
redis-cli INFO stats

# Monitor cache hit rate
php artisan settings:cache-stats

# Check database query count
php artisan settings:debug --profile

# List all settings
php artisan settings:list --group=iam
```

---

## 🚀 Performance Optimization

### Query Optimization

```php
// ❌ Bad: N+1 query issue
$settings = Setting::all();
foreach ($settings as $setting) {
    echo $setting->group;  // Additional query for each
}

// ✅ Good: Single query
$settings = Setting::all();  // Loaded in one query
foreach ($settings as $setting) {
    echo $setting->group;  // No additional queries
}

// ✅ Better: Select only needed columns
$settings = Setting::select('id', 'key', 'value', 'group')->get();
```

### Cache Hit Rate Optimization

```php
// Pre-load common settings
// In middleware or service provider
app(SettingService::class)->preload([
    'sso.ttl',
    'iam.issuer',
    'auth.default_guard',
]);

// Or use cache warming command
php artisan settings:cache-warm
```

### Batch Operations

```php
// ✅ Efficient bulk update
Setting::upsert([
    ['key' => 'sso.ttl', 'value' => '300'],
    ['key' => 'iam.ttl', 'value' => '3600'],
], ['key'], ['value']);

// Invalidate all at once
Cache::tags(['setting'])->flush();
```

---

## 📋 Comparison with Alternatives

### Alternative 1: File-based Config Only
```
Pros: ✅ Simple, no database dependency
Cons: ❌ Requires redeploy, requires restart
```

### Alternative 2: Environment Variables Only
```
Pros: ✅ Cloud-native, simple
Cons: ❌ Hard to manage many settings, requires redeploy
```

### Alternative 3: Database + Fallback (Chosen)
```
Pros: ✅ Real-time updates, zero-downtime, graceful fallback
Cons: ⚠️ Slightly more complex setup
```

---

## 🧪 Testing Strategy

### Unit Tests
- Service logic
- Repository queries
- Type casting
- Cache invalidation

### Feature Tests
- API endpoints
- Filament resource
- Permission checks
- Activity logging

### Integration Tests
- Config chain
- Cache behavior
- Database + Cache sync
- Fallback mechanism

### Performance Tests
- Cache hit/miss ratio
- Query count
- Response time
- Bulk operations

---

## 📈 Future Enhancements

1. **Feature Flags**
   - Use settings as feature toggles
   - Percentage-based rollout

2. **Settings Versioning**
   - Track setting history
   - Rollback to previous values

3. **Multi-environment**
   - Environment-specific values
   - Environment hierarchy

4. **Scheduled Changes**
   - Schedule setting changes for future
   - Automatic rollback

5. **Import/Export**
   - Backup/restore settings
   - Transfer between environments

---

**Created:** May 17, 2026  
**Last Updated:** May 17, 2026
