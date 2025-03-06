<?php
/**
 * Login API Endpoint
 * 
 * This file handles user authentication.
 */

// Set headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Access-Control-Allow-Headers, Content-Type, Access-Control-Allow-Methods, Authorization, X-Requested-With');

// Include required files
require_once '../../includes/session.php';

// Check if the request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get the posted data
$data = json_decode(file_get_contents('php://input'), true);

// If no data was received, check for form data
if (!$data) {
    $data = $_POST;
}

// Validate input
if (!isset($data['username']) || !isset($data['password'])) {
    http_response_code(400); // Bad Request
    echo json_encode(['success' => false, 'message' => 'Username and password are required']);
    exit;
}

// Sanitize input
$username = sanitize($data['username']);
$password = $data['password'];
$remember = isset($data['remember']) ? (bool) $data['remember'] : false;

// Check if the user exists
$query = "SELECT * FROM users WHERE username = '$username' OR email = '$username'";
$result = mysqli_query($conn, $query);

if (!$result || mysqli_num_rows($result) === 0) {
    http_response_code(401); // Unauthorized
    echo json_encode(['success' => false, 'message' => 'Invalid username or password']);
    exit;
}

// Get the user data
$user = mysqli_fetch_assoc($result);

// Check if the user is active
if ($user['status'] !== 'active') {
    http_response_code(401); // Unauthorized
    echo json_encode(['success' => false, 'message' => 'Your account is not active. Please contact the administrator.']);
    exit;
}

// Verify the password
if (!verifyPassword($password, $user['password'])) {
    http_response_code(401); // Unauthorized
    echo json_encode(['success' => false, 'message' => 'Invalid username or password']);
    exit;
}

// Update last login time
$userId = $user['id'];
$query = "UPDATE users SET last_login = NOW() WHERE id = $userId";
mysqli_query($conn, $query);

// Create user session
createUserSession($user);

// Set remember me cookie if requested
if ($remember) {
    $token = generateToken();
    $expiry = time() + (30 * 24 * 60 * 60); // 30 days
    
    // Store token in database
    $hashedToken = hashPassword($token);
    $query = "UPDATE users SET remember_token = '$hashedToken' WHERE id = $userId";
    mysqli_query($conn, $query);
    
    // Set cookie
    setcookie('remember_token', $token, $expiry, '/', '', false, true);
    setcookie('remember_user', $userId, $expiry, '/', '', false, true);
}

// Prepare response data
$responseData = [
    'success' => true,
    'message' => 'Login successful',
    'user' => [
        'id' => $user['id'],
        'username' => $user['username'],
        'email' => $user['email'],
        'full_name' => $user['full_name'],
        'role' => $user['role'],
        'profile_image' => $user['profile_image']
    ]
];

// Determine redirect URL based on user role
if ($user['role'] === 'admin') {
    $responseData['redirect'] = '../dashboard_admin.html';
} else {
    $responseData['redirect'] = '../dashboard.html';
}

// Return success response
echo json_encode($responseData); 