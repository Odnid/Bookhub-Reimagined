<?php
/**
 * Registration API Endpoint
 * 
 * This file handles user registration.
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

// Validate required fields
$requiredFields = ['username', 'password', 'email', 'fullname', 'contact', 'street_address', 'city', 'province', 'postal_code'];
$missingFields = [];

foreach ($requiredFields as $field) {
    if (!isset($data[$field]) || empty($data[$field])) {
        $missingFields[] = $field;
    }
}

if (!empty($missingFields)) {
    http_response_code(400); // Bad Request
    echo json_encode([
        'success' => false, 
        'message' => 'Missing required fields', 
        'missing_fields' => $missingFields
    ]);
    exit;
}

// Sanitize input
$username = sanitize($data['username']);
$password = $data['password'];
$email = sanitize($data['email']);
$fullName = sanitize($data['fullname']);
$contactNumber = sanitize($data['contact']);
$streetAddress = sanitize($data['street_address']);
$city = sanitize($data['city']);
$province = sanitize($data['province']);
$postalCode = sanitize($data['postal_code']);

// Validate email format
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400); // Bad Request
    echo json_encode(['success' => false, 'message' => 'Invalid email format']);
    exit;
}

// Check if username already exists
$query = "SELECT id FROM users WHERE username = '$username'";
$result = mysqli_query($conn, $query);

if ($result && mysqli_num_rows($result) > 0) {
    http_response_code(409); // Conflict
    echo json_encode(['success' => false, 'message' => 'Username already exists']);
    exit;
}

// Check if email already exists
$query = "SELECT id FROM users WHERE email = '$email'";
$result = mysqli_query($conn, $query);

if ($result && mysqli_num_rows($result) > 0) {
    http_response_code(409); // Conflict
    echo json_encode(['success' => false, 'message' => 'Email already exists']);
    exit;
}

// Validate password strength (at least 8 characters with letters and numbers)
if (strlen($password) < 8 || !preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
    http_response_code(400); // Bad Request
    echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters long and contain both letters and numbers']);
    exit;
}

// Hash the password
$hashedPassword = hashPassword($password);

// Handle profile image upload if provided
$profileImage = 'default_profile.jpg';
if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
    $uploadedImage = uploadFile($_FILES['profile_image'], UPLOAD_PATH_PROFILES, $allowedTypes);
    
    if ($uploadedImage) {
        $profileImage = $uploadedImage;
    }
}

// Insert the new user into the database
$query = "INSERT INTO users (username, password, email, full_name, contact_number, street_address, city, province, postal_code, profile_image, role, status) 
          VALUES ('$username', '$hashedPassword', '$email', '$fullName', '$contactNumber', '$streetAddress', '$city', '$province', '$postalCode', '$profileImage', 'user', 'active')";

if (mysqli_query($conn, $query)) {
    // Get the new user's ID
    $userId = mysqli_insert_id($conn);
    
    // Log the registration
    logAction('registration', 'New user registered', $userId);
    
    // Create a welcome notification
    $welcomeMessage = "Welcome to Bookhub, $fullName! We're excited to have you join our community.";
    $query = "INSERT INTO notifications (user_id, message) VALUES ($userId, '$welcomeMessage')";
    mysqli_query($conn, $query);
    
    // Return success response
    http_response_code(201); // Created
    echo json_encode([
        'success' => true, 
        'message' => 'Registration successful', 
        'redirect' => '../login.html',
        'user_id' => $userId
    ]);
} else {
    // Return error response
    http_response_code(500); // Internal Server Error
    echo json_encode([
        'success' => false, 
        'message' => 'Registration failed: ' . mysqli_error($conn)
    ]);
} 