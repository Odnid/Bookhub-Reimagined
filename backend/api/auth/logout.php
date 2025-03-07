<?php
/**
 * Logout API Endpoint
 * 
 * This file handles user logout.
 */

// Set headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET');
header('Access-Control-Allow-Headers: Access-Control-Allow-Headers, Content-Type, Access-Control-Allow-Methods, Authorization, X-Requested-With');

// Include required files
require_once '../../includes/session.php';

// Destroy the user session
destroyUserSession();

// Clear remember me cookies if they exist
if (isset($_COOKIE['remember_token'])) {
    setcookie('remember_token', '', time() - 3600, '/');
}

if (isset($_COOKIE['remember_user'])) {
    setcookie('remember_user', '', time() - 3600, '/');
}

// Return success response
echo json_encode([
    'success' => true, 
    'message' => 'Logout successful',
    'redirect' => '../index.html'
]); 