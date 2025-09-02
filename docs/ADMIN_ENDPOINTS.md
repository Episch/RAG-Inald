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

### 3. **🧪 Config Test**
**`GET /api/admin/config/test`**

**Purpose:** Live service connectivity testing

**Response Example:**
```json
{
  "overall_success": true,
  "configuration_valid": true,
  "configuration_errors": [],
  "connector_tests": {
    "tika": {
      "success": true,
      "response_time_ms": 45,
      "status_code": 200
    },
    "neo4j": {
      "success": true,
      "response_time_ms": 23,
      "status_code": 200
    },
    "ollama": {
      "success": false,
      "error": "Connection refused",
      "response_time_ms": 5000
    }
  },
  "recommendations": [
    {
      "type": "warning",
      "message": "Service ollama is not accessible: Connection refused",
      "action": "Check if ollama service is running and accessible"
    }
  ]
}
```

### 4. **🌍 Environment Info**
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

## 🚀 Usage Examples

### **1. Quick Health Check:**
```bash
# Check if Ollama is working
curl -u admin:admin123 http://localhost:8000/api/admin/debug/ollama | jq '.ollama_debug.recommendations'
```

### **2. Service Connectivity Test:**
```bash
# Test all services
curl -u admin:admin123 http://localhost:8000/api/admin/config/test | jq '.connector_tests'
```

### **3. Environment Audit:**
```bash
# Check environment configuration
curl -u admin:admin123 http://localhost:8000/api/admin/config/env | jq '.environment_variables'
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
| **Service Outage** | `/admin/config/test` | Test all service connections |
| **Deployment Check** | `/admin/config/status` | Validate configuration |
| **Security Audit** | `/admin/config/env` | Review environment setup |
| **Performance Issues** | `/admin/config/test` | Check response times |

## 🛡️ Best Practices

1. **Regular Monitoring** - Check `/admin/config/test` periodically
2. **Pre-deployment** - Validate `/admin/config/status` before releases  
3. **Troubleshooting** - Start with `/admin/debug/ollama` for LLM issues
4. **Security** - Rotate admin passwords regularly
5. **Documentation** - Keep credentials secure and documented

---

**🔐 These endpoints provide powerful system insights while maintaining security through HTTP Basic Authentication and role-based access control.**
