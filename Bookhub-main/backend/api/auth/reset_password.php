<?php
/**
 * Reset Password API Endpoint
 * 
 * This file handles password reset.
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
if (!isset($data['token']) || !isset($data['email']) || !isset($data['password']) || !isset($data['confirm_password'])) {
    http_response_code(400); // Bad Request
    echo json_encode(['success' => false, 'message' => 'All fields are required']);
    exit;
}

// Sanitize input
$token = $data['token'];
$email = sanitize($data['email']);
$password = $data['password'];
$confirmPassword = $data['confirm_password'];

// Check if passwords match
if ($password !== $confirmPassword) {
    http_response_code(400); // Bad Request
    echo json_encode(['success' => false, 'message' => 'Passwords do not match']);
    exit;
}

// Validate password strength (at least 8 characters with letters and numbers)
if (strlen($password) < 8 || !preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
    http_response_code(400); // Bad Request
    echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters long and contain both letters and numbers']);
    exit;
}

// Check if the email exists
$query = "SELECT id, reset_token, reset_token_expires FROM users WHERE email = '$email' AND status = 'active'";
$result = mysqli_query($conn, $query);

if (!$result || mysqli_num_rows($result) === 0) {
    http_response_code(400); // Bad Request
    echo json_encode(['success' => false, 'message' => 'Invalid or expired reset token']);
    exit;
}

// Get the user data
$user = mysqli_fetch_assoc($result);
$userId = $user['id'];
$storedToken = $user['reset_token'];
$tokenExpiry = $user['reset_token_expires'];

// Check if the token has expired
if ($tokenExpiry && strtotime($tokenExpiry) < time()) {
    http_response_code(400); // Bad Request
    echo json_encode(['success' => false, 'message' => 'Reset token has expired']);
    exit;
}

// Verify the token
if (!$storedToken || !verifyPassword($token, $storedToken)) {
    http_response_code(400); // Bad Request
    echo json_encode(['success' => false, 'message' => 'Invalid or expired reset token']);
    exit;
}

// Hash the new password
$hashedPassword = hashPassword($password);

// Update the user's password and clear the reset token
$query = "UPDATE users SET password = '$hashedPassword', reset_token = NULL, reset_token_expires = NULL WHERE id = $userId";
if (!mysqli_query($conn, $query)) {
    http_response_code(500); // Internal Server Error
    echo json_encode(['success' => false, 'message' => 'Failed to update password']);
    exit;
}

// Log the password reset
logAction('password_reset', 'Password reset completed', $userId);

// Return success response
echo json_encode([
    'success' => true, 
    'message' => 'Your password has been reset successfully. You can now log in with your new password.',
    'redirect' => '../login.html'
]); 