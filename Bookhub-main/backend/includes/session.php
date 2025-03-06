<?php
/**
 * Session Management
 * 
 * This file handles session initialization and management.
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    // Set secure session parameters
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    
    // Start the session
    session_start();
}

// Include utility functions
require_once 'functions.php';

/**
 * Create a new user session
 * 
 * @param array $user User data
 * @return void
 */
function createUserSession($user) {
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['last_activity'] = time();
    
    // Log the login action
    logAction('login', 'User logged in', $user['id']);
}

/**
 * Destroy the current user session
 * 
 * @return void
 */
function destroyUserSession() {
    // Log the logout action if user is logged in
    if (isset($_SESSION['user_id'])) {
        logAction('logout', 'User logged out', $_SESSION['user_id']);
    }
    
    // Unset all session variables
    $_SESSION = [];
    
    // Delete the session cookie
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }
    
    // Destroy the session
    session_destroy();
}

/**
 * Check if the session has expired
 * 
 * @param int $maxLifetime Maximum session lifetime in seconds (default: 30 minutes)
 * @return bool True if the session has expired, false otherwise
 */
function isSessionExpired($maxLifetime = 1800) {
    if (!isset($_SESSION['last_activity'])) {
        return true;
    }
    
    if (time() - $_SESSION['last_activity'] > $maxLifetime) {
        destroyUserSession();
        return true;
    }
    
    // Update last activity time
    $_SESSION['last_activity'] = time();
    return false;
}

/**
 * Require user to be logged in
 * 
 * @param string $redirectUrl URL to redirect to if not logged in
 * @return void
 */
function requireLogin($redirectUrl = '../login.html') {
    if (!isLoggedIn() || isSessionExpired()) {
        redirect($redirectUrl);
    }
}

/**
 * Require user to be an admin
 * 
 * @param string $redirectUrl URL to redirect to if not an admin
 * @return void
 */
function requireAdmin($redirectUrl = '../login.html') {
    requireLogin($redirectUrl);
    
    if (!isAdmin()) {
        redirect($redirectUrl);
    }
}

// Check for session expiration on every page load
if (isLoggedIn()) {
    isSessionExpired();
} 