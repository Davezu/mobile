<!DOCTYPE html>
<html>
<head>
    <title>API Info</title>
    <style>
        body { font-family: Arial; max-width: 800px; margin: 50px auto; padding: 20px; background: #f5f5f5; }
        .card { background: white; padding: 20px; margin: 20px 0; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #667eea; }
        h2 { color: #333; border-bottom: 2px solid #667eea; padding-bottom: 10px; }
        .endpoint { background: #f0f0f0; padding: 10px; margin: 10px 0; border-radius: 4px; font-family: monospace; }
        .method { display: inline-block; padding: 4px 8px; border-radius: 4px; font-weight: bold; margin-right: 10px; }
        .post { background: #28a745; color: white; }
        .get { background: #007bff; color: white; }
        .info { background: #e7f3ff; border-left: 4px solid #2196F3; padding: 15px; margin: 15px 0; }
        .warning { background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 15px 0; }
        a { color: #667eea; text-decoration: none; font-weight: bold; }
        a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <h1>🛒 E-Commerce API Information</h1>
    
    <div class="card">
        <h2>Server Details</h2>
        <p><strong>Server:</strong> <?= $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown' ?></p>
        <p><strong>Host:</strong> <?= $_SERVER['HTTP_HOST'] ?? 'Unknown' ?></p>
        <p><strong>Document Root:</strong> <?= $_SERVER['DOCUMENT_ROOT'] ?></p>
        <p><strong>Current URL:</strong> <?= 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] ?></p>
        <p><strong>PHP Version:</strong> <?= PHP_VERSION ?></p>
    </div>

    <?php
    $host = $_SERVER['HTTP_HOST'];
    $isPort8000 = strpos($host, ':8000') !== false;
    $isLocalhost80 = strpos($host, 'localhost') !== false && strpos($host, ':8000') === false;
    ?>

    <?php if ($isPort8000): ?>
        <div class="info">
            <strong>✓ You're using PHP Built-in Server (Port 8000)</strong><br>
            This is the recommended setup for development!
        </div>
        
        <div class="card">
            <h2>Your API Endpoints</h2>
            
            <div class="endpoint">
                <span class="method post">POST</span>
                <a href="http://localhost:8000/api/auth/register" target="_blank">
                    http://localhost:8000/api/auth/register
                </a>
                <p>Register a new user</p>
            </div>
            
            <div class="endpoint">
                <span class="method post">POST</span>
                <a href="http://localhost:8000/api/auth/login" target="_blank">
                    http://localhost:8000/api/auth/login
                </a>
                <p>Login user</p>
            </div>
            
            <div class="endpoint">
                <span class="method get">GET</span>
                <a href="http://localhost:8000/api/auth/me" target="_blank">
                    http://localhost:8000/api/auth/me
                </a>
                <p>Get current user (requires login)</p>
            </div>
            
            <div class="endpoint">
                <span class="method post">POST</span>
                <a href="http://localhost:8000/api/auth/logout" target="_blank">
                    http://localhost:8000/api/auth/logout
                </a>
                <p>Logout user</p>
            </div>
        </div>
        
        <div class="card">
            <h2>Test Interface</h2>
            <p><a href="http://localhost:8000/test_auth_form.html">→ Open Test Form</a></p>
        </div>
        
    <?php elseif ($isLocalhost80): ?>
        <div class="warning">
            <strong>⚠ You're using Apache (Port 80)</strong><br>
            The clean URLs might not work without proper .htaccess configuration.
        </div>
        
        <div class="card">
            <h2>Your API Endpoints</h2>
            
            <div class="endpoint">
                <span class="method post">POST</span>
                http://localhost/e-commerce/public/api/auth/register
                <p>Register a new user</p>
            </div>
            
            <div class="endpoint">
                <span class="method post">POST</span>
                http://localhost/e-commerce/public/api/auth/login
                <p>Login user</p>
            </div>
        </div>
        
        <div class="info">
            <strong>💡 Recommendation:</strong> Use PHP built-in server for easier development:<br>
            <code>cd public && php -S localhost:8000 router.php</code><br>
            Then visit: <a href="http://localhost:8000/info.php">http://localhost:8000/info.php</a>
        </div>
    <?php endif; ?>
    
    <div class="card">
        <h2>Alternative Direct Endpoints (Always Work)</h2>
        <p>These bypass the router and always work:</p>
        
        <div class="endpoint">
            <span class="method post">POST</span>
            <a href="<?= 'http://' . $_SERVER['HTTP_HOST'] . '/api_test_simple.php' ?>" target="_blank">
                <?= 'http://' . $_SERVER['HTTP_HOST'] ?>/api_test_simple.php
            </a>
            <p>Register user (direct file)</p>
        </div>
    </div>
    
    <div class="card">
        <h2>Documentation</h2>
        <p>📖 <a href="/API_Examples.md">View API Documentation</a> (if serving from root)</p>
    </div>
</body>
</html>

