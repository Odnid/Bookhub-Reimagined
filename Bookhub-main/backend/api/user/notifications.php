<?php
/**
 * User Notifications API Endpoint
 * 
 * This file handles user notifications.
 */

// Set headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, PUT, DELETE');
header('Access-Control-Allow-Headers: Access-Control-Allow-Headers, Content-Type, Access-Control-Allow-Methods, Authorization, X-Requested-With');

// Include required files
require_once '../../includes/session.php';

// Require user to be logged in
requireLogin();

// Get the user ID from the session
$userId = $_SESSION['user_id'];

// Handle GET request (get notifications)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Check if a specific notification ID is requested
    if (isset($_GET['id'])) {
        $notificationId = (int) $_GET['id'];
        
        // Get the notification data
        $query = "SELECT * FROM notifications WHERE id = $notificationId AND user_id = $userId";
        $result = mysqli_query($conn, $query);
        
        if (!$result || mysqli_num_rows($result) === 0) {
            http_response_code(404); // Not Found
            echo json_encode(['success' => false, 'message' => 'Notification not found']);
            exit;
        }
        
        // Get the notification data
        $notification = mysqli_fetch_assoc($result);
        
        // Return the notification data
        echo json_encode(['success' => true, 'notification' => $notification]);
        exit;
    }
    
    // Get all notifications with pagination
    $page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
    $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 10;
    $offset = ($page - 1) * $limit;
    
    // Get total count for pagination
    $countQuery = "SELECT COUNT(*) as total FROM notifications WHERE user_id = $userId";
    $countResult = mysqli_query($conn, $countQuery);
    $totalItems = mysqli_fetch_assoc($countResult)['total'];
    
    // Get notifications
    $query = "SELECT * FROM notifications 
              WHERE user_id = $userId 
              ORDER BY created_at DESC 
              LIMIT $offset, $limit";
    $result = mysqli_query($conn, $query);
    
    $notifications = [];
    if ($result && mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            $notifications[] = $row;
        }
    }
    
    // Get unread count
    $unreadQuery = "SELECT COUNT(*) as unread FROM notifications WHERE user_id = $userId AND is_read = 0";
    $unreadResult = mysqli_query($conn, $unreadQuery);
    $unreadCount = mysqli_fetch_assoc($unreadResult)['unread'];
    
    // Calculate pagination data
    $totalPages = ceil($totalItems / $limit);
    $pagination = [
        'total_items' => $totalItems,
        'total_pages' => $totalPages,
        'current_page' => $page,
        'limit' => $limit
    ];
    
    // Return the notifications data
    echo json_encode([
        'success' => true,
        'notifications' => $notifications,
        'unread_count' => $unreadCount,
        'pagination' => $pagination
    ]);
    exit;
}

// Handle PUT request (mark notification as read)
if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    // Check if a specific notification ID is requested
    if (isset($_GET['id'])) {
        $notificationId = (int) $_GET['id'];
        
        // Check if the notification exists
        $query = "SELECT id FROM notifications WHERE id = $notificationId AND user_id = $userId";
        $result = mysqli_query($conn, $query);
        
        if (!$result || mysqli_num_rows($result) === 0) {
            http_response_code(404); // Not Found
            echo json_encode(['success' => false, 'message' => 'Notification not found']);
            exit;
        }
        
        // Mark the notification as read
        $query = "UPDATE notifications SET is_read = 1 WHERE id = $notificationId";
        
        if (mysqli_query($conn, $query)) {
            // Return success response
            echo json_encode(['success' => true, 'message' => 'Notification marked as read']);
        } else {
            // Return error response
            http_response_code(500); // Internal Server Error
            echo json_encode(['success' => false, 'message' => 'Failed to mark notification as read: ' . mysqli_error($conn)]);
        }
        exit;
    }
    
    // Check if marking all as read
    if (isset($_GET['all']) && $_GET['all'] === 'true') {
        // Mark all notifications as read
        $query = "UPDATE notifications SET is_read = 1 WHERE user_id = $userId AND is_read = 0";
        
        if (mysqli_query($conn, $query)) {
            // Return success response
            echo json_encode(['success' => true, 'message' => 'All notifications marked as read']);
        } else {
            // Return error response
            http_response_code(500); // Internal Server Error
            echo json_encode(['success' => false, 'message' => 'Failed to mark notifications as read: ' . mysqli_error($conn)]);
        }
        exit;
    }
    
    // If no ID or 'all' parameter is provided
    http_response_code(400); // Bad Request
    echo json_encode(['success' => false, 'message' => 'Notification ID or "all" parameter is required']);
    exit;
}

// Handle DELETE request (delete notification)
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    // Check if a specific notification ID is requested
    if (isset($_GET['id'])) {
        $notificationId = (int) $_GET['id'];
        
        // Check if the notification exists
        $query = "SELECT id FROM notifications WHERE id = $notificationId AND user_id = $userId";
        $result = mysqli_query($conn, $query);
        
        if (!$result || mysqli_num_rows($result) === 0) {
            http_response_code(404); // Not Found
            echo json_encode(['success' => false, 'message' => 'Notification not found']);
            exit;
        }
        
        // Delete the notification
        $query = "DELETE FROM notifications WHERE id = $notificationId";
        
        if (mysqli_query($conn, $query)) {
            // Return success response
            echo json_encode(['success' => true, 'message' => 'Notification deleted']);
        } else {
            // Return error response
            http_response_code(500); // Internal Server Error
            echo json_encode(['success' => false, 'message' => 'Failed to delete notification: ' . mysqli_error($conn)]);
        }
        exit;
    }
    
    // Check if deleting all notifications
    if (isset($_GET['all']) && $_GET['all'] === 'true') {
        // Delete all notifications
        $query = "DELETE FROM notifications WHERE user_id = $userId";
        
        if (mysqli_query($conn, $query)) {
            // Return success response
            echo json_encode(['success' => true, 'message' => 'All notifications deleted']);
        } else {
            // Return error response
            http_response_code(500); // Internal Server Error
            echo json_encode(['success' => false, 'message' => 'Failed to delete notifications: ' . mysqli_error($conn)]);
        }
        exit;
    }
    
    // If no ID or 'all' parameter is provided
    http_response_code(400); // Bad Request
    echo json_encode(['success' => false, 'message' => 'Notification ID or "all" parameter is required']);
    exit;
}

// If the request method is not supported
http_response_code(405); // Method Not Allowed
echo json_encode(['success' => false, 'message' => 'Method not allowed']); 