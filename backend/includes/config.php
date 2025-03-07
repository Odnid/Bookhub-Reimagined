<?php
/**
 * Database Configuration File
 * 
 * This file contains the database connection parameters for the Bookhub application.
 */

// Database credentials
define('DB_HOST', 'localhost');
define('DB_USER', 'root');     // Change this to your MySQL username
define('DB_PASS', '');         // Change this to your MySQL password
define('DB_NAME', 'bookhub');  // Database name

// Attempt to connect to MySQL database
$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Set charset to ensure proper handling of special characters
mysqli_set_charset($conn, "utf8mb4");

// Application settings
define('SITE_URL', 'http://localhost/bookhub'); // Change this to your site URL
define('UPLOAD_PATH_BOOKS', '../uploads/books/');
define('UPLOAD_PATH_PROFILES', '../uploads/profiles/');
define('DEFAULT_PROFILE_IMAGE', 'default_profile.jpg');

// Session settings
//ini_set('session.cookie_httponly', 0); // Prevent JavaScript access to session cookie
//ini_set('session.use_only_cookies', 0); // Force sessions to only use cookies
//ini_set('session.cookie_secure', 0);    // Set to 1 if using HTTPS

// Error reporting (set to 0 in production)
error_reporting(E_ALL);
ini_set('display_errors', 1); 