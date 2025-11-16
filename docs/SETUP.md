# IAM + SSO RBAC Setup Guide

Quick setup guide untuk IAM Server.

---

## Installation

### 1. Clone Repository

```bash
git clone https://github.com/juniyasyos/laravel-iam.git
cd laravel-iam
```

### 2. Install Dependencies

```bash
composer install
npm install
```

### 3. Environment Setup

```bash
cp .env.example .env
php artisan key:generate
```

Update `.env`:

```env
APP_NAME="IAM Server"
APP_URL=https://iam.rs.id

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=iam_database
DB_USERNAME=your_username
DB_PASSWORD=your_password

CACHE_DRIVER=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
```

### 4. Run Migrations

```bash
php artisan migrate
```

This will create:
- `users` table
- `applications` table
- `roles` table
- `permissions` table
- `model_has_roles` table
- `model_has_permissions` table
- `role_has_permissions` table

### 5. Seed Sample Data (Optional)

```bash
php artisan db:seed --class=IAMSampleDataSeeder
```

This creates:
- Sample users (admin, doctor, nurse, manager)
- Sample roles & permissions
- Sample applications (SIIMUT, Incident Report, Pharmacy)

**Sample Credentials:**
- Admin: `admin@rs.id` / `password`
- Doctor: `doctor@rs.id` / `password`
- Nurse: `nurse@rs.id` / `password`
- Manager: `manager@rs.id` / `password`

**Sample Applications:**
- `siimut.app` / `siimut_secret_key_123`
- `incident-report.app` / `incident_secret_key_456`
- `pharmacy.app` / `pharmacy_secret_key_789`

### 6. Build Assets

```bash
npm run build
```

### 7. Start Server

```bash
php artisan serve
```

Visit: `http://localhost:8000`

---

## Production Deployment

### 1. Web Server Configuration

**Nginx Example:**

```nginx
server {
    listen 80;
    server_name iam.rs.id;
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    server_name iam.rs.id;
    root /var/www/laravel-iam/public;

    ssl_certificate /etc/ssl/certs/iam.rs.id.crt;
    ssl_certificate_key /etc/ssl/private/iam.rs.id.key;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;

    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

### 2. Production Environment

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://iam.rs.id

# Use strong app key
APP_KEY=base64:GENERATED_KEY_HERE

# Production database
DB_CONNECTION=mysql
DB_HOST=your_db_host
DB_DATABASE=iam_production
DB_USERNAME=iam_user
DB_PASSWORD=secure_password

# Redis cache
CACHE_DRIVER=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

# Mail configuration
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=your_username
MAIL_PASSWORD=your_password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="noreply@iam.rs.id"
MAIL_FROM_NAME="${APP_NAME}"
```

### 3. Optimize Application

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
composer install --optimize-autoloader --no-dev
```

### 4. Setup Queue Worker

```bash
# Install supervisor
sudo apt-get install supervisor

# Create supervisor config
sudo nano /etc/supervisor/conf.d/laravel-worker.conf
```

**Supervisor Config:**

```ini
[program:laravel-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/laravel-iam/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/laravel-iam/storage/logs/worker.log
stopwaitsecs=3600
```

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start laravel-worker:*
```

### 5. Setup Scheduler

```bash
crontab -e
```

Add:

```cron
* * * * * cd /var/www/laravel-iam && php artisan schedule:run >> /dev/null 2>&1
```

---

## Managing Applications

### Via Filament Panel

1. Login to admin panel: `https://iam.rs.id/admin`
2. Navigate to "Applications"
3. Click "Create Application"
4. Fill in details:
   - **App Key**: Unique identifier (e.g., `myapp.app`)
   - **Name**: Display name
   - **Description**: Optional description
   - **Enabled**: Toggle to enable/disable
   - **Redirect URIs**: JSON array of allowed redirect URIs
   - **Secret**: Client secret (will be hashed)
   - **Token Expiry**: Optional custom expiry in seconds
   - **Allowed Scopes**: JSON array of allowed permissions

### Via Tinker

```bash
php artisan tinker
```

```php
use  App\Domain\Iam\Models\Application;;
use App\Models\User;

// Create application
$admin = User::where('email', 'admin@rs.id')->first();

$app = Application::create([
    'app_key' => 'myapp.app',
    'name' => 'My Application',
    'description' => 'Application description',
    'enabled' => true,
    'redirect_uris' => [
        'http://localhost:3000/auth/callback',
        'https://myapp.rs.id/auth/callback',
    ],
    'secret' => 'my_secure_secret_key', // Will be hashed automatically
    'token_expiry' => 3600,
    'created_by' => $admin->id,
]);

// Get application details
$app = Application::where('app_key', 'myapp.app')->first();
echo "App Key: {$app->app_key}\n";
echo "Secret: Use original plaintext secret for API calls\n";

// Verify secret
$app->verifySecret('my_secure_secret_key'); // true
```

---

## Managing Roles & Permissions

### Via Filament Panel

1. Navigate to "Roles"
2. Create new role
3. Assign permissions to role
4. Navigate to "Users"
5. Edit user and assign roles

### Via Tinker

```php
use App\Models\User;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

// Create permission
Permission::create(['name' => 'read:patients']);

// Create role
$doctor = Role::create(['name' => 'doctor']);

// Assign permission to role
$doctor->givePermissionTo('read:patients');

// Assign role to user
$user = User::find(1);
$user->assignRole('doctor');

// Direct permission (without role)
$user->givePermissionTo('special-access');

// Check permissions
$user->hasPermissionTo('read:patients'); // true
$user->hasRole('doctor'); // true
```

---

## Testing SSO Flow

### 1. Start IAM Server

```bash
php artisan serve
```

### 2. Test Authorization Endpoint

Visit in browser:

```
http://localhost:8000/oauth/authorize?app_key=siimut.app&redirect_uri=http://localhost:3000/auth/callback&state=random123
```

Expected flow:
1. Redirect to login (if not logged in)
2. After login, redirect to callback with code

### 3. Test Token Exchange

```bash
curl -X POST http://localhost:8000/oauth/token \
  -H "Content-Type: application/json" \
  -d '{
    "grant_type": "authorization_code",
    "app_key": "siimut.app",
    "app_secret": "siimut_secret_key_123",
    "code": "AUTH_CODE_FROM_STEP_2",
    "redirect_uri": "http://localhost:3000/auth/callback"
  }'
```

Expected response:

```json
{
  "access_token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
  "refresh_token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
  "token_type": "Bearer",
  "expires_in": 3600
}
```

### 4. Test User Info Endpoint

```bash
curl -X GET http://localhost:8000/oauth/userinfo \
  -H "Authorization: Bearer YOUR_ACCESS_TOKEN"
```

Expected response:

```json
{
  "sub": 1,
  "name": "Dr. John Doe",
  "email": "doctor@rs.id",
  "roles": ["doctor"],
  "unit": "ICU",
  "app_key": "siimut.app"
}
```

### 5. Test Token Introspection

```bash
curl -X POST http://localhost:8000/oauth/introspect \
  -H "Content-Type: application/json" \
  -d '{
    "token": "YOUR_ACCESS_TOKEN",
    "app_key": "siimut.app",
    "app_secret": "siimut_secret_key_123"
  }'
```

---

## Monitoring & Logs

### View Logs

```bash
# Laravel logs
tail -f storage/logs/laravel.log

# Nginx access logs
tail -f /var/log/nginx/access.log

# Nginx error logs
tail -f /var/log/nginx/error.log
```

### Database Monitoring

```bash
php artisan tinker
```

```php
// Check active tokens (in cache)
use Illuminate\Support\Facades\Cache;

// List all cache keys (Redis)
$redis = Redis::connection();
$keys = $redis->keys('*');

// Check specific token
$authCode = Cache::get('auth_code:SOME_CODE');
$refreshToken = Cache::get('refresh_token:1:siimut.app');
```

---

## Maintenance

### Clear Cache

```bash
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

### Database Backup

```bash
# Backup
mysqldump -u username -p iam_database > backup.sql

# Restore
mysql -u username -p iam_database < backup.sql
```

### Update Application

```bash
git pull origin main
composer install --optimize-autoloader --no-dev
npm run build
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan queue:restart
```

---

## Security Checklist

- [ ] **HTTPS enabled** in production
- [ ] **APP_DEBUG=false** in production
- [ ] **Strong APP_KEY** generated
- [ ] **Database credentials** secured
- [ ] **Firewall** configured (only 80/443 open)
- [ ] **Redis** password protected
- [ ] **Regular backups** scheduled
- [ ] **Rate limiting** enabled on token endpoint
- [ ] **Logs monitoring** set up
- [ ] **Application secrets** rotated regularly

---

## Troubleshooting

### Issue: "Class 'Redis' not found"

Install Redis PHP extension:

```bash
sudo apt-get install php-redis
sudo systemctl restart php8.2-fpm
```

### Issue: "Permission denied" on storage

Fix permissions:

```bash
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
```

### Issue: Token verification fails

Check APP_KEY is same between IAM server and client applications.

### Issue: CORS errors

Add CORS middleware or configure in web server.

---

## Documentation

- **Full Documentation**: `docs/IAM-SSO-RBAC-DOCUMENTATION.md`
- **Client Integration**: `docs/CLIENT-INTEGRATION.md`
- **API Reference**: Check main documentation

---

## Support

- **Repository**: https://github.com/juniyasyos/laravel-iam
- **Email**: ahmad.ilyas@rs.id
- **Issues**: https://github.com/juniyasyos/laravel-iam/issues

---

**Last Updated:** November 14, 2025
