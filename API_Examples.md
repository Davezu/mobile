# E-Commerce API Documentation

## Base URL
```
http://localhost/e-commerce/public
```

## Authentication Endpoints

### 1. Register New User
**Endpoint:** `POST /api/auth/register`

**Request Headers:**
```
Content-Type: application/json
```

**Request Body:**
```json
{
    "username": "johndoe",
    "email": "johndoe@example.com",
    "password": "password123",
    "full_name": "John Doe",
    "phone": "+1234567890",
    "address": "123 Main Street, New York"
}
```

**Success Response (201):**
```json
{
    "success": true,
    "message": "User registered successfully",
    "data": {
        "user": {
            "id": 2,
            "username": "johndoe",
            "email": "johndoe@example.com",
            "full_name": "John Doe",
            "phone": "+1234567890",
            "address": "123 Main Street, New York",
            "role": "user",
            "created_at": "2025-10-20 19:33:13"
        }
    }
}
```

**Validation Error Response (422):**
```json
{
    "success": false,
    "message": "Validation failed",
    "errors": {
        "username": [
            "Username is required",
            "Username must be at least 3 characters"
        ],
        "email": [
            "Invalid email format",
            "Email already registered"
        ]
    }
}
```

**Required Fields:**
- `username` (3-50 characters, alphanumeric and underscore only)
- `email` (valid email format, must be unique)
- `password` (minimum 6 characters)

**Optional Fields:**
- `full_name` (max 100 characters)
- `phone` (valid phone format)
- `address` (text)

---

### 2. Login User
**Endpoint:** `POST /api/auth/login`

**Request Body:**
```json
{
    "email": "johndoe@example.com",
    "password": "password123"
}
```

**Success Response (200):**
```json
{
    "success": true,
    "message": "Login successful",
    "data": {
        "user": {
            "id": 2,
            "username": "johndoe",
            "email": "johndoe@example.com",
            "full_name": "John Doe",
            "phone": "+1234567890",
            "address": "123 Main Street, New York",
            "role": "user",
            "created_at": "2025-10-20 19:33:13"
        },
        "session_id": "abc123xyz"
    }
}
```

**Error Response (401):**
```json
{
    "success": false,
    "message": "Invalid credentials"
}
```

---

### 3. Get Current User
**Endpoint:** `GET /api/auth/me`

**Note:** Requires active session (must be logged in)

**Success Response (200):**
```json
{
    "success": true,
    "message": "User data retrieved",
    "data": {
        "user": {
            "id": 2,
            "username": "johndoe",
            "email": "johndoe@example.com",
            "full_name": "John Doe",
            "phone": "+1234567890",
            "address": "123 Main Street, New York",
            "role": "user",
            "created_at": "2025-10-20 19:33:13"
        }
    }
}
```

---

### 4. Logout User
**Endpoint:** `POST /api/auth/logout`

**Success Response (200):**
```json
{
    "success": true,
    "message": "Logout successful"
}
```

---

## Testing with cURL

### Register User:
```bash
curl -X POST http://localhost/e-commerce/public/api/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "username": "johndoe",
    "email": "johndoe@example.com",
    "password": "password123",
    "full_name": "John Doe"
  }'
```

### Login User:
```bash
curl -X POST http://localhost/e-commerce/public/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "johndoe@example.com",
    "password": "password123"
  }'
```

---

## Testing with Postman

1. **Import Settings:**
   - Method: POST
   - URL: `http://localhost/e-commerce/public/api/auth/register`
   - Headers: 
     - Key: `Content-Type`, Value: `application/json`
   - Body: Select `raw` and `JSON`, then paste the request body

2. **Send Request** and check the response

---

## Testing with PHP (Command Line)

Run the test script:
```bash
php test_register_direct.php
```

This will test the registration without needing a web server.

---

## Common HTTP Status Codes

- `200 OK` - Request successful
- `201 Created` - Resource created successfully
- `400 Bad Request` - Invalid request data
- `401 Unauthorized` - Authentication required
- `403 Forbidden` - Access denied
- `404 Not Found` - Endpoint not found
- `422 Unprocessable Entity` - Validation failed
- `500 Internal Server Error` - Server error

