<?php
/**
 * User Profile API Endpoint
 * 
 * This file handles retrieving and updating user profile information.
 */

// Set headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT');
header('Access-Control-Allow-Headers: Access-Control-Allow-Headers, Content-Type, Access-Control-Allow-Methods, Authorization, X-Requested-With');

// Include required files
require_once '../../includes/session.php';

// Require user to be logged in
requireLogin();

// Get the user ID from the session
$userId = $_SESSION['user_id'];

// Handle GET request (retrieve profile)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Get the user data
    $query = "SELECT id, username, email, full_name, role, profile_image, contact_number, 
              street_address, city, province, postal_code, created_at, last_login 
              FROM users WHERE id = $userId";
    $result = mysqli_query($conn, $query);
    
    if (!$result || mysqli_num_rows($result) === 0) {
        http_response_code(404); // Not Found
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit;
    }
    
    // Get the user data
    $user = mysqli_fetch_assoc($result);
    
    // Get user's borrowing history
    $query = "SELECT b.id, b.book_id, books.title, books.author, books.cover_image, 
              b.borrow_date, b.due_date, b.return_date, b.status 
              FROM borrowings b 
              JOIN books ON b.book_id = books.id 
              WHERE b.user_id = $userId 
              ORDER BY b.borrow_date DESC";
    $result = mysqli_query($conn, $query);
    
    $borrowings = [];
    if ($result && mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            $borrowings[] = $row;
        }
    }
    
    // Get user's favorite books
    $query = "SELECT f.id, f.book_id, books.title, books.author, books.cover_image, 
              books.genre, f.created_at 
              FROM favorites f 
              JOIN books ON f.book_id = books.id 
              WHERE f.user_id = $userId 
              ORDER BY f.created_at DESC";
    $result = mysqli_query($conn, $query);
    
    $favorites = [];
    if ($result && mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            $favorites[] = $row;
        }
    }
    
    // Get user's reviews
    $query = "SELECT r.id, r.book_id, books.title, books.author, books.cover_image, 
              r.rating, r.review_text, r.created_at, r.status 
              FROM reviews r 
              JOIN books ON r.book_id = books.id 
              WHERE r.user_id = $userId 
              ORDER BY r.created_at DESC";
    $result = mysqli_query($conn, $query);
    
    $reviews = [];
    if ($result && mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            $reviews[] = $row;
        }
    }
    
    // Return the user data
    echo json_encode([
        'success' => true,
        'user' => $user,
        'borrowings' => $borrowings,
        'favorites' => $favorites,
        'reviews' => $reviews
    ]);
    exit;
}

// Handle POST/PUT request (update profile)
if ($_SERVER['REQUEST_METHOD'] === 'POST' || $_SERVER['REQUEST_METHOD'] === 'PUT') {
    // Get the posted data
    $data = json_decode(file_get_contents('php://input'), true);
    
    // If no data was received, check for form data
    if (!$data) {
        $data = $_POST;
    }
    
    // Initialize update fields
    $updateFields = [];
    
    // Update full name if provided
    if (isset($data['full_name']) && !empty($data['full_name'])) {
        $fullName = sanitize($data['full_name']);
        $updateFields[] = "full_name = '$fullName'";
    }
    
    // Update contact number if provided
    if (isset($data['contact_number'])) {
        $contactNumber = sanitize($data['contact_number']);
        $updateFields[] = "contact_number = '$contactNumber'";
    }
    
    // Update address fields if provided
    if (isset($data['street_address'])) {
        $streetAddress = sanitize($data['street_address']);
        $updateFields[] = "street_address = '$streetAddress'";
    }
    
    if (isset($data['city'])) {
        $city = sanitize($data['city']);
        $updateFields[] = "city = '$city'";
    }
    
    if (isset($data['province'])) {
        $province = sanitize($data['province']);
        $updateFields[] = "province = '$province'";
    }
    
    if (isset($data['postal_code'])) {
        $postalCode = sanitize($data['postal_code']);
        $updateFields[] = "postal_code = '$postalCode'";
    }
    
    // Update email if provided
    if (isset($data['email']) && !empty($data['email'])) {
        $email = sanitize($data['email']);
        
        // Validate email format
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            http_response_code(400); // Bad Request
            echo json_encode(['success' => false, 'message' => 'Invalid email format']);
            exit;
        }
        
        // Check if email already exists for another user
        $query = "SELECT id FROM users WHERE email = '$email' AND id != $userId";
        $result = mysqli_query($conn, $query);
        
        if ($result && mysqli_num_rows($result) > 0) {
            http_response_code(409); // Conflict
            echo json_encode(['success' => false, 'message' => 'Email already exists']);
            exit;
        }
        
        $updateFields[] = "email = '$email'";
    }
    
    // Update password if provided
    if (isset($data['current_password']) && isset($data['new_password']) && isset($data['confirm_password'])) {
        $currentPassword = $data['current_password'];
        $newPassword = $data['new_password'];
        $confirmPassword = $data['confirm_password'];
        
        // Check if passwords match
        if ($newPassword !== $confirmPassword) {
            http_response_code(400); // Bad Request
            echo json_encode(['success' => false, 'message' => 'New passwords do not match']);
            exit;
        }
        
        // Validate password strength
        if (strlen($newPassword) < 8 || !preg_match('/[A-Za-z]/', $newPassword) || !preg_match('/[0-9]/', $newPassword)) {
            http_response_code(400); // Bad Request
            echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters long and contain both letters and numbers']);
            exit;
        }
        
        // Get the current password hash
        $query = "SELECT password FROM users WHERE id = $userId";
        $result = mysqli_query($conn, $query);
        
        if (!$result || mysqli_num_rows($result) === 0) {
            http_response_code(404); // Not Found
            echo json_encode(['success' => false, 'message' => 'User not found']);
            exit;
        }
        
        $user = mysqli_fetch_assoc($result);
        
        // Verify the current password
        if (!verifyPassword($currentPassword, $user['password'])) {
            http_response_code(401); // Unauthorized
            echo json_encode(['success' => false, 'message' => 'Current password is incorrect']);
            exit;
        }
        
        // Hash the new password
        $hashedPassword = hashPassword($newPassword);
        $updateFields[] = "password = '$hashedPassword'";
    }
    
    // Handle profile image upload if provided
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
        $uploadedImage = uploadFile($_FILES['profile_image'], UPLOAD_PATH_PROFILES, $allowedTypes);
        
        if ($uploadedImage) {
            $updateFields[] = "profile_image = '$uploadedImage'";
        } else {
            http_response_code(400); // Bad Request
            echo json_encode(['success' => false, 'message' => 'Failed to upload profile image']);
            exit;
        }
    }
    
    // If no fields to update, return error
    if (empty($updateFields)) {
        http_response_code(400); // Bad Request
        echo json_encode(['success' => false, 'message' => 'No fields to update']);
        exit;
    }
    
    // Update the user data
    $updateFieldsStr = implode(', ', $updateFields);
    $query = "UPDATE users SET $updateFieldsStr WHERE id = $userId";
    
    if (mysqli_query($conn, $query)) {
        // Log the profile update
        logAction('profile_update', 'User profile updated');
        
        // Return success response
        echo json_encode(['success' => true, 'message' => 'Profile updated successfully']);
    } else {
        // Return error response
        http_response_code(500); // Internal Server Error
        echo json_encode(['success' => false, 'message' => 'Failed to update profile: ' . mysqli_error($conn)]);
    }
    exit;
}

// If the request method is not supported
http_response_code(405); // Method Not Allowed
echo json_encode(['success' => false, 'message' => 'Method not allowed']); 