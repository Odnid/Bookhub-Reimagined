<?php
/**
 * Bookhub Backend API
 * 
 * This is the main entry point for the Bookhub backend API.
 */

// Set headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Include required files
require_once 'includes/session.php';

// Return API information
echo json_encode([
    'name' => 'Bookhub API',
    'version' => '1.0.0',
    'description' => 'RESTful API for the Bookhub library management system',
    'endpoints' => [
        'auth' => [
            'login' => '/api/auth/login.php',
            'register' => '/api/auth/register.php',
            'forgot_password' => '/api/auth/forgot_password.php',
            'reset_password' => '/api/auth/reset_password.php',
            'logout' => '/api/auth/logout.php'
        ],
        'user' => [
            'profile' => '/api/user/profile.php',
            'notifications' => '/api/user/notifications.php'
        ],
        'books' => [
            'books' => '/api/books/books.php',
            'borrow' => '/api/books/borrow.php',
            'favorites' => '/api/books/favorites.php',
            'reviews' => '/api/books/reviews.php',
            'recommendations' => '/api/books/recommendations.php'
        ],
        'admin' => [
            'users' => '/api/admin/users.php',
            'stats' => '/api/admin/stats.php'
        ]
    ],
    'documentation' => 'See README.md for more information'
]); 