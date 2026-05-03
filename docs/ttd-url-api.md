# TTD URL Resolver API

## Overview

This endpoint provides a pre-signed MinIO URL for accessing user TTD (digital signature) files. The URL is valid for 15 minutes and can be used to download or display the TTD file without exposing MinIO credentials.

## Endpoint

```
GET /api/users/{userId}/ttd-url
```

## Authentication

Required header:
```
Authorization: Bearer {sso_jwt_token}
```

The JWT token must:
- Be valid and not expired (TTL: 3600 seconds)
- Be issued for the authenticated user
- Match the `{userId}` parameter (users can only access their own TTD)

## Prerequisites

1. User must have an SSO JWT token
2. User must have a `ttd_url` value set in the database
3. The TTD file must exist in S3 storage

## Response

### Success (200 OK)

```json
{
  "url": "http://minio.example.com/ttd/abc123.png?X-Amz-Algorithm=AWS4-HMAC-SHA256&X-Amz-Credential=..."
}
```

### Error Responses

| Status | Message | Cause |
|--------|---------|-------|
| 401 | `Unauthenticated: bearer token is required.` | Missing Authorization header |
| 401 | `Unauthenticated: invalid or expired SSO token.` | Invalid or expired JWT token |
| 403 | `You are not authorized to access this TTD file.` | userId doesn't match authenticated user |
| 404 | `User does not have a TTD file configured.` | User has no ttd_url set |
| 404 | `TTD file not found in storage.` | TTD file doesn't exist in MinIO |

## Client Usage Examples

### 1. JavaScript/Fetch (React, Vue, etc)

```javascript
// Step 1: Get SSO JWT token (from your auth system)
const token = localStorage.getItem('sso_token'); // atau dari state management
const userId = 42; // Current user ID

// Step 2: Fetch presigned URL from API
async function getTtdUrl(userId, token) {
  try {
    const response = await fetch(`/api/users/${userId}/ttd-url`, {
      method: 'GET',
      headers: {
        'Authorization': `Bearer ${token}`,
        'Accept': 'application/json'
      }
    });

    if (!response.ok) {
      const error = await response.json();
      throw new Error(error.message);
    }

    const data = await response.json();
    return data.url;
  } catch (error) {
    console.error('Error fetching TTD URL:', error.message);
    throw error;
  }
}

// Step 3: Use the presigned URL
async function displayTtdFile(userId, token) {
  try {
    const presignedUrl = await getTtdUrl(userId, token);
    
    // Option A: Display in img tag
    document.getElementById('ttd-image').src = presignedUrl;
    
    // Option B: Download the file
    const link = document.createElement('a');
    link.href = presignedUrl;
    link.download = 'signature.png';
    link.click();
    
  } catch (error) {
    alert('Failed to load TTD: ' + error.message);
  }
}

// Usage:
displayTtdFile(userId, token);
```

### 2. cURL (for testing)

```bash
# First, get your SSO JWT token (example)
JWT_TOKEN="eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiI0MiIsImlhdCI6MTcxNDY2MDAwMH0.xxx"
USER_ID=42

# Fetch presigned URL
curl -X GET "https://example.com/api/users/${USER_ID}/ttd-url" \
  -H "Authorization: Bearer ${JWT_TOKEN}" \
  -H "Accept: application/json" \
  -s | jq .

# Response:
# {
#   "url": "http://minio.example.com/ttd/signature.png?X-Amz-Algorithm=..."
# }

# Download the file using presigned URL from MinIO
PRESIGNED_URL=$(curl -s -X GET "https://example.com/api/users/${USER_ID}/ttd-url" \
  -H "Authorization: Bearer ${JWT_TOKEN}" | jq -r '.url')

curl -o ttd-file.png "$PRESIGNED_URL"
```

### 3. Axios (Vue.js / Node.js)

```javascript
import axios from 'axios';

const api = axios.create({
  baseURL: 'https://example.com',
  headers: {
    'Accept': 'application/json'
  }
});

async function getTtdFileUrl(userId, token) {
  try {
    const response = await api.get(`/api/users/${userId}/ttd-url`, {
      headers: {
        'Authorization': `Bearer ${token}`
      }
    });
    
    return response.data.url;
  } catch (error) {
    if (error.response?.status === 401) {
      console.error('Authentication failed - token may have expired');
    } else if (error.response?.status === 403) {
      console.error('Not authorized to access this user\'s TTD');
    } else if (error.response?.status === 404) {
      console.error('TTD file not found');
    }
    throw error;
  }
}

// Usage:
const ttdUrl = await getTtdFileUrl(42, token);
console.log('Presigned URL valid for 15 minutes:', ttdUrl);
```

### 4. Python (requests)

```python
import requests
import json

def get_ttd_url(user_id, jwt_token, base_url='https://example.com'):
    """
    Fetch presigned S3 URL for user's TTD file
    """
    headers = {
        'Authorization': f'Bearer {jwt_token}',
        'Accept': 'application/json'
    }
    
    try:
        response = requests.get(
            f'{base_url}/api/users/{user_id}/ttd-url',
            headers=headers,
            timeout=10
        )
        response.raise_for_status()
        
        data = response.json()
        return data['url']
        
    except requests.exceptions.HTTPError as e:
        if e.response.status_code == 401:
            print('Authentication failed - invalid or expired token')
        elif e.response.status_code == 403:
            print('Not authorized to access this user\'s TTD')
        elif e.response.status_code == 404:
            print('TTD file not found for this user')
        raise
    except requests.exceptions.RequestException as e:
        print(f'Request failed: {e}')
        raise

# Usage:
jwt_token = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...'
ttd_url = get_ttd_url(42, jwt_token)
print(f'Presigned URL: {ttd_url}')

# Download the file
response = requests.get(ttd_url)
with open('user_signature.png', 'wb') as f:
    f.write(response.content)
```

## Using the Presigned URL

After obtaining the presigned URL, you can:

1. **Display in browser** (img, iframe, etc):
   ```html
   <img src="http://minio.example.com/ttd/file.png?X-Amz-..." alt="User Signature" />
   ```

2. **Download file**:
   ```javascript
   fetch(presignedUrl)
     .then(res => res.blob())
     .then(blob => {
       const url = window.URL.createObjectURL(blob);
       const a = document.createElement('a');
       a.href = url;
       a.download = 'signature.png';
       a.click();
     });
   ```

3. **Use in canvas/editor** (for signature verification):
   ```javascript
   const img = new Image();
   img.crossOrigin = 'anonymous';
   img.onload = () => {
     const canvas = document.getElementById('signature-canvas');
     const ctx = canvas.getContext('2d');
     ctx.drawImage(img, 0, 0);
   };
   img.src = presignedUrl;
   ```

## Security Notes

- Presigned URLs expire after **15 minutes**
- URLs are **HTTPS only** (non-local environments)
- Each request generates a **new URL** - don't cache the URL itself
- Users can only access **their own** TTD files
- The API never exposes MinIO credentials
- JWT token must be kept secure (HttpOnly cookies recommended)

## Testing in Development

```bash
# 1. Get a test JWT token from your auth system
TOKEN=$(php artisan tinker --execute="
  \$user = \App\Models\User::first();
  echo app(\App\Domain\Iam\Services\TokenBuilder::class)->buildTokenForUser(\$user);
")

# 2. Make a request
curl -X GET "http://localhost:8000/api/users/1/ttd-url" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Accept: application/json"

# 3. Copy the presigned URL from MinIO and open it in browser or download with curl
```
