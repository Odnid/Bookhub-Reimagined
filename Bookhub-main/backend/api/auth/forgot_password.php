<?php
/**
 * Forgot Password API Endpoint
 * 
 * This file handles password reset requests.
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
if (!isset($data['email']) || empty($data['email'])) {
    http_response_code(400); // Bad Request
    echo json_encode(['success' => false, 'message' => 'Email is required']);
    exit;
}

// Sanitize input
$email = sanitize($data['email']);

// Check if the email exists
$query = "SELECT id, username, full_name FROM users WHERE email = '$email' AND status = 'active'";
$result = mysqli_query($conn, $query);

if (!$result || mysqli_num_rows($result) === 0) {
    // For security reasons, don't reveal that the email doesn't exist
    // Instead, pretend that we sent an email
    echo json_encode([
        'success' => true, 
        'message' => 'If your email exists in our system, you will receive a password reset link shortly.'
    ]);
    exit;
}

// Get the user data
$user = mysqli_fetch_assoc($result);
$userId = $user['id'];
$username = $user['username'];
$fullName = $user['full_name'];

// Generate a reset token
$token = generateToken();
$tokenHash = hashPassword($token);
$expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));

// Store the token in the database
$query = "UPDATE users SET reset_token = '$tokenHash', reset_token_expires = '$expiry' WHERE id = $userId";
if (!mysqli_query($conn, $query)) {
    http_response_code(500); // Internal Server Error
    echo json_encode(['success' => false, 'message' => 'Failed to generate reset token']);
    exit;
}

// Log the password reset request
logAction('password_reset_request', 'Password reset requested', $userId);

// In a real application, you would send an email with the reset link
// For this example, we'll just return the token in the response
$resetLink = SITE_URL . '/reset_password.html?token=' . $token . '&email=' . urlencode($email);

// For demonstration purposes only - in a real app, you would send this via email
$message = "
Hello $fullName,

You recently requested to reset your password for your Bookhub account. Use the link below to reset it.
This password reset link is only valid for the next hour.

$resetLink

If you did not request a password reset, please ignore this email or contact support if you have questions.

Thanks,
The Bookhub Team
";

// In a real application, you would use a proper email sending library
// mail($email, 'Bookhub Password Reset', $message);

// Return success response
echo json_encode([
    'success' => true, 
    'message' => 'If your email exists in our system, you will receive a password reset link shortly.',
    // The following would be removed in production, it's just for demonstration
    'debug_info' => [
        'reset_link' => $resetLink,
        'token' => $token,
        'email' => $email
    ]
]); 