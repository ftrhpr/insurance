<?php
// Prevent direct access
if (!defined('API_ACCESS')) {
    die('Direct access not permitted');
}

// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'otoexpre_userdb');
define('DB_USER', 'otoexpre_userdb');
define('DB_PASS', 'p52DSsthB}=0AeZ#');
define('DB_CHARSET', 'utf8mb4');

// API Security Key (generate a random string)
define('API_KEY', 'm3RZpQRAKCv8X9JtY2hpbGxAZXhhbXBsZS5jb20=');

// Enable error reporting for development (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set timezone
date_default_timezone_set('Asia/Tbilisi'); // Georgia timezone

// CORS headers for React Native
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key');
header('Content-Type: application/json; charset=UTF-8');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Database connection function
function getDBConnection() {
    try {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        return $pdo;
    } catch (PDOException $e) {
        error_log("Database connection failed: " . $e->getMessage());
        throw new Exception("Database connection failed");
    }
}

// Verify API key
function verifyAPIKey() {
    // Try multiple methods to get the API key header
    $apiKey = '';
    
    // Debug: Log all headers and _SERVER vars
    error_log("=== API Key Debug ===");
    error_log("All _SERVER HTTP headers: " . json_encode(array_filter($_SERVER, function($key) {
        return strpos($key, 'HTTP_') === 0;
    }, ARRAY_FILTER_USE_KEY)));
    
    // Method 1: getallheaders() - works on Apache
    if (function_exists('getallheaders')) {
        $headers = getallheaders();
        error_log("getallheaders: " . json_encode($headers));
        $apiKey = isset($headers['X-API-Key']) ? $headers['X-API-Key'] : 
                  (isset($headers['x-api-key']) ? $headers['x-api-key'] : 
                  (isset($headers['X-Api-Key']) ? $headers['X-Api-Key'] : ''));
    }
    
    // Method 2: $_SERVER - works on nginx and most servers
    if (empty($apiKey)) {
        $apiKey = isset($_SERVER['HTTP_X_API_KEY']) ? $_SERVER['HTTP_X_API_KEY'] : '';
    }
    
    // Method 3: apache_request_headers() - alternative for Apache
    if (empty($apiKey) && function_exists('apache_request_headers')) {
        $headers = apache_request_headers();
        $apiKey = isset($headers['X-API-Key']) ? $headers['X-API-Key'] : 
                  (isset($headers['x-api-key']) ? $headers['x-api-key'] : '');
    }
    
    // Debug: Log what we received
    error_log("Received API Key: " . ($apiKey ? substr($apiKey, 0, 10) . "..." : "EMPTY"));
    error_log("Expected API Key: " . substr(API_KEY, 0, 10) . "...");
    error_log("===================");
    
    if (empty($apiKey)) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Unauthorized: No API key provided', 'debug' => 'Check server error logs for header details']);
        exit();
    }
    
    if ($apiKey !== API_KEY) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Unauthorized: Invalid API key']);
        exit();
    }
}

// Send JSON response
function sendResponse($success, $data = null, $error = null, $code = 200) {
    http_response_code($code);
    $response = ['success' => $success];
    
    if ($data !== null) {
        $response['data'] = $data;
    }
    
    if ($error !== null) {
        $response['error'] = $error;
    }
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit();
}
?>
