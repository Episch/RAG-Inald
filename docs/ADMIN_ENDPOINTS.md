# 🔐 Admin API Endpoints - Secured Access

## 🎯 Overview

The admin endpoints provide **system diagnostics** and **configuration management** with **HTTP Basic Authentication** protection through Symfony Security.

## 🔒 Authentication

### **HTTP Basic Auth Credentials:**
```
Username: admin
Password: admin123

OR

Username: debug  
Password: debug456
```

### **cURL Example:**
```bash
curl -u admin:admin123 http://localhost:8000/api/admin/debug/ollama
```

### **Postman/Swagger UI:**
- **Authorization Type:** Basic Auth
- **Username:** admin
- **Password:** admin123

## 📋 Available Admin Endpoints

### 1. **🔧 Debug - Ollama Diagnostics**
**`GET /api/admin/debug/ollama`**

**Purpose:** Comprehensive LLM service troubleshooting

**Response Example:**
```json
{
  "ollama_debug": {
    "base_url": "http://localhost:11434",
    "endpoints": {
      "generate": {"available": true, "status_code": 200},
      "models": {"available": true, "status_code": 200}
    },
    "models": {
      "response": {
        "status_code": 200,
        "content": {"models": [{"name": "llama3.2", "size": "2.0GB"}]}
      }
    },
    "generate_test": {
      "response": {"status_code": 200, "content_preview": "Test response..."}
    },
    "recommendations": [
      "✅ Basic connectivity looks good",
      "💡 Useful commands:",
      "  - ollama list (show installed models)",
      "  - ollama pull llama3.2 (download model)"
    ]
  }
}
```

### 2. **⚙️ Config Status**
**`GET /api/admin/config/status`**

**Purpose:** System configuration validation report

**Response Example:**
```json
{
  "configuration": {
    "services": {
      "tika": {"status": "configured", "url": "http://localhost:9998"},
      "neo4j": {"status": "configured", "url": "bolt://neo4j:7687"},
      "ollama": {"status": "configured", "url": "http://localhost:11434"}
    },
    "validation": {"passed": true, "errors": []}
  },
  "timestamp": "2024-01-16T15:30:00+00:00",
  "environment": "dev"
}
```

### 3. **🌍 Environment Info**
**`GET /api/admin/config/env`**

**Purpose:** Environment variables and system information

**Response Example:**
```json
{
  "environment_variables": {
    "APP_ENV": {"set": true, "value": "dev", "sensitive": false},
    "DOCUMENT_EXTRACTOR_URL": {"set": true, "value": "http://localhost:9998", "sensitive": false},
    "NEO4J_RAG_DATABASE": {"set": true, "value": "bolt://...", "sensitive": true},
    "LMM_URL": {"set": true, "value": "http://localhost:11434", "sensitive": false}
  },
  "php_version": "8.3.6",
  "symfony_version": "7.3.0",
  "system_info": {
    "os": "Linux",
    "memory_limit": "256M",
    "max_execution_time": "30"
  }
}
```

### 4. **📁 File Management**
**`GET /api/admin/files`**

**Purpose:** List and manage stored extraction and LLM response files

**Response Example:**
```json
{
  "status": "success",
  "count": 3,
  "files": [
    {
      "file_id": "ext_67890abc_2024-01-16_14-30-25",
      "type": "extraction",
      "created_at": "2024-01-16T14:30:25+00:00",
      "size": 15420
    },
    {
      "file_id": "llm_12345def_2024-01-16_15-00-45", 
      "type": "llm_response",
      "created_at": "2024-01-16T15:00:45+00:00",
      "size": 8932
    }
  ]
}
```

**Additional Endpoints:**
- **`GET /api/admin/files/{fileId}`** - Get specific file data
- **`GET /api/admin/files/{fileId}/content`** - Get file content only
- **`DELETE /api/admin/files/{fileId}`** - Delete specific file

## 🚀 Usage Examples

### **1. Quick Health Check:**
```bash
# Check if Ollama is working
curl -u admin:admin123 http://localhost:8000/api/admin/debug/ollama | jq '.ollama_debug.recommendations'
```

### **2. Environment Audit:**
```bash
# Check environment configuration
curl -u admin:admin123 http://localhost:8000/api/admin/config/env | jq '.environment_variables'
```

### **3. File Management:**
```bash
# List all stored files
curl -u admin:admin123 http://localhost:8000/api/admin/files | jq '.files'

# Get specific file data
curl -u admin:admin123 http://localhost:8000/api/admin/files/ext_67890abc_2024-01-16_14-30-25

# Clean up old files
curl -u admin:admin123 -X DELETE http://localhost:8000/api/admin/files/old_file_id
```

## 🔐 Security Features

### **✅ What's Protected:**
- **HTTP Basic Auth** - Username/password required
- **Role-Based Access** - `ROLE_ADMIN` required
- **Sensitive Data Flagged** - Database URLs marked as sensitive
- **Separate URL Space** - `/api/admin/*` isolated from public API

### **🔒 Security Configuration:**
```yaml
# config/packages/security.yaml
firewalls:
  admin_area:
    pattern: ^/api/admin
    stateless: true
    http_basic: true
    provider: admin_provider

access_control:
  - { path: ^/api/admin, roles: ROLE_ADMIN }
```

## 📊 Swagger Documentation

The admin endpoints appear in Swagger UI with:
- 🔒 **Lock Icon** - Indicates authentication required  
- 📝 **"Requires admin authentication"** in descriptions
- 🔑 **Auth Button** - Click to enter credentials

### **Swagger Authentication:**
1. Click **"Authorize"** button in Swagger UI
2. Select **"Basic Auth"**
3. Enter: `admin` / `admin123`
4. All admin endpoints will be accessible

## 🚨 Production Recommendations

### **⚡ Change Default Passwords:**
```bash
# Generate new password hash
php bin/console security:hash-password your-secure-password

# Update config/packages/security.yaml
# Replace default hashes with generated ones
```

### **🔒 Additional Security:**
- Use **environment variables** for admin passwords
- Enable **HTTPS only** for production
- Consider **IP restrictions** for admin endpoints
- Add **rate limiting** for brute force protection

### **📝 Environment Variables:**
```env
# .env.local (not committed)
ADMIN_PASSWORD_HASH='$2y$13$...'
DEBUG_PASSWORD_HASH='$2y$13$...'
```

## 🎯 Use Cases

| Use Case | Endpoint | Purpose |
|----------|----------|---------|
| **LLM Not Working** | `/admin/debug/ollama` | Diagnose Ollama connectivity |
| **Deployment Check** | `/admin/config/status` | Validate configuration |
| **Security Audit** | `/admin/config/env` | Review environment setup |
| **File Management** | `/admin/files` | Monitor and manage stored files |
| **Storage Cleanup** | `/admin/files/{id}` (DELETE) | Remove old extraction/LLM files |

## 🛡️ Best Practices

1. **Regular Monitoring** - Check `/admin/config/status` periodically
2. **Pre-deployment** - Validate `/admin/config/status` before releases  
3. **Troubleshooting** - Start with `/admin/debug/ollama` for LLM issues
4. **File Management** - Use `/admin/files` to monitor storage usage and clean up old files
5. **Security** - Rotate admin passwords regularly
6. **Documentation** - Keep credentials secure and documented

---

**🔐 These endpoints provide powerful system insights while maintaining security through HTTP Basic Authentication and role-based access control.**
