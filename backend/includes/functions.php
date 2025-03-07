<?php
/**
 * Utility Functions
 * 
 * This file contains common functions used throughout the Bookhub application.
 */

// Include database configuration
require_once 'config.php';

/**
 * Sanitize user input to prevent SQL injection
 * 
 * @param string $data The data to be sanitized
 * @return string The sanitized data
 */
function sanitize($data) {
    global $conn;
    return mysqli_real_escape_string($conn, trim($data));
}

/**
 * Hash a password using bcrypt
 * 
 * @param string $password The password to hash
 * @return string The hashed password
 */
function hashPassword($password) {
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
}

/**
 * Verify a password against a hash
 * 
 * @param string $password The password to verify
 * @param string $hash The hash to verify against
 * @return bool True if the password matches the hash, false otherwise
 */
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * Generate a random token
 * 
 * @param int $length The length of the token
 * @return string The generated token
 */
function generateToken($length = 32) {
    return bin2hex(random_bytes($length));
}

/**
 * Check if a user is logged in
 * 
 * @return bool True if the user is logged in, false otherwise
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

/**
 * Check if a user is an admin
 * 
 * @return bool True if the user is an admin, false otherwise
 */
function isAdmin() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

function isUser() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'user';
}


/**
 * Redirect to a URL
 * 
 * @param string $url The URL to redirect to
 * @return void
 */
function redirect($url) {
    header("Location: $url");
    exit;
}

/**
 * Upload a file
 * 
 * @param array $file The file to upload ($_FILES array element)
 * @param string $destination The destination directory
 * @param array $allowedTypes Array of allowed MIME types
 * @param int $maxSize Maximum file size in bytes
 * @return string|bool The filename if successful, false otherwise
 */
function uploadFile($file, $destination, $allowedTypes = [], $maxSize = 5242880) {
    // Check if file was uploaded without errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return false;
    }
    
    // Check file size
    if ($file['size'] > $maxSize) {
        return false;
    }
    
    // Check file type if specified
    if (!empty($allowedTypes) && !in_array($file['type'], $allowedTypes)) {
        return false;
    }
    
    // Generate a unique filename
    $filename = uniqid() . '_' . basename($file['name']);
    $uploadPath = $destination . $filename;
    
    // Move the uploaded file
    if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
        return $filename;
    }
    
    return false;
}

/**
 * Get a user by ID
 * 
 * @param int $userId The user ID
 * @return array|bool The user data if found, false otherwise
 */
function getUserById($userId) {
    global $conn;
    $userId = (int) $userId;
    
    $query = "SELECT * FROM users WHERE id = $userId";
    $result = mysqli_query($conn, $query);
    
    if ($result && mysqli_num_rows($result) > 0) {
        return mysqli_fetch_assoc($result);
    }
    
    return false;
}

/**
 * Get a book by ID
 * 
 * @param int $bookId The book ID
 * @return array|bool The book data if found, false otherwise
 */
function getBookById($bookId) {
    global $conn;
    $bookId = (int) $bookId;
    
    $query = "SELECT * FROM books WHERE id = $bookId";
    $result = mysqli_query($conn, $query);
    
    if ($result && mysqli_num_rows($result) > 0) {
        return mysqli_fetch_assoc($result);
    }
    
    return false;
}

/**
 * Log an action in the system
 * 
 * @param string $action The action performed
 * @param string $details Additional details about the action
 * @param int $userId The ID of the user who performed the action
 * @return bool True if the log was created, false otherwise
 */
function logAction($action, $details, $userId = null) {
    global $conn;
    
    if ($userId === null && isset($_SESSION['user_id'])) {
        $userId = $_SESSION['user_id'];
    }
    
    $action = sanitize($action);
    $details = sanitize($details);
    $userId = $userId ? (int) $userId : 'NULL';
    
    $query = "INSERT INTO activity_logs (user_id, action, details, created_at) 
              VALUES ($userId, '$action', '$details', NOW())";
    
    return mysqli_query($conn, $query);
}

/**
 * Format a date in a human-readable format
 * 
 * @param string $date The date to format
 * @param string $format The format to use
 * @return string The formatted date
 */
function formatDate($date, $format = 'M j, Y') {
    return date($format, strtotime($date));
}

/**
 * Get pagination data
 * 
 * @param int $totalItems Total number of items
 * @param int $itemsPerPage Number of items per page
 * @param int $currentPage Current page number
 * @return array Pagination data
 */
function getPagination($totalItems, $itemsPerPage = 10, $currentPage = 1) {
    $totalPages = ceil($totalItems / $itemsPerPage);
    $currentPage = max(1, min($currentPage, $totalPages));
    $offset = ($currentPage - 1) * $itemsPerPage;
    
    return [
        'total_items' => $totalItems,
        'items_per_page' => $itemsPerPage,
        'current_page' => $currentPage,
        'total_pages' => $totalPages,
        'offset' => $offset
    ];
} 