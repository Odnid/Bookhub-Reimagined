<?php
/**
 * Admin Statistics API Endpoint
 * 
 * This file provides statistics for administrators.
 */

// Set headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Access-Control-Allow-Headers, Content-Type, Access-Control-Allow-Methods, Authorization, X-Requested-With');

// Include required files
require_once '../../includes/session.php';

// Require admin privileges
requireAdmin();

// Handle GET request
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stats = [];
    
    // Get user statistics
    $query = "SELECT 
                COUNT(*) as total_users,
                SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_users,
                SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactive_users,
                SUM(CASE WHEN status = 'suspended' THEN 1 ELSE 0 END) as suspended_users,
                SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END) as admin_users,
                SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as new_users_30_days
              FROM users";
    $result = mysqli_query($conn, $query);
    
    if ($result && mysqli_num_rows($result) > 0) {
        $stats['users'] = mysqli_fetch_assoc($result);
    }
    
    // Get book statistics
    $query = "SELECT 
                COUNT(*) as total_books,
                SUM(available_copies) as available_copies,
                SUM(total_copies) as total_copies,
                SUM(CASE WHEN status = 'available' THEN 1 ELSE 0 END) as available_books,
                SUM(CASE WHEN status = 'unavailable' THEN 1 ELSE 0 END) as unavailable_books,
                SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as new_books_30_days
              FROM books";
    $result = mysqli_query($conn, $query);
    
    if ($result && mysqli_num_rows($result) > 0) {
        $stats['books'] = mysqli_fetch_assoc($result);
    }
    
    // Get borrowing statistics
    $query = "SELECT 
                COUNT(*) as total_borrowings,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_borrowings,
                SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_borrowings,
                SUM(CASE WHEN status = 'borrowed' THEN 1 ELSE 0 END) as active_borrowings,
                SUM(CASE WHEN status = 'returned' THEN 1 ELSE 0 END) as returned_borrowings,
                SUM(CASE WHEN status = 'overdue' THEN 1 ELSE 0 END) as overdue_borrowings,
                SUM(CASE WHEN borrow_date >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as new_borrowings_30_days
              FROM borrowings";
    $result = mysqli_query($conn, $query);
    
    if ($result && mysqli_num_rows($result) > 0) {
        $stats['borrowings'] = mysqli_fetch_assoc($result);
    }
    
    // Get most popular books (most borrowed)
    $query = "SELECT b.id, b.title, b.author, b.cover_image, COUNT(br.id) as borrow_count
              FROM books b
              JOIN borrowings br ON b.id = br.book_id
              GROUP BY b.id
              ORDER BY borrow_count DESC
              LIMIT 5";
    $result = mysqli_query($conn, $query);
    
    $popularBooks = [];
    if ($result && mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            $popularBooks[] = $row;
        }
    }
    $stats['popular_books'] = $popularBooks;
    
    // Get most active users (most borrowings)
    $query = "SELECT u.id, u.username, u.full_name, u.profile_image, COUNT(br.id) as borrow_count
              FROM users u
              JOIN borrowings br ON u.id = br.user_id
              GROUP BY u.id
              ORDER BY borrow_count DESC
              LIMIT 5";
    $result = mysqli_query($conn, $query);
    
    $activeUsers = [];
    if ($result && mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            $activeUsers[] = $row;
        }
    }
    $stats['active_users'] = $activeUsers;
    
    // Get genre distribution
    $query = "SELECT g.name, COUNT(bg.book_id) as book_count
              FROM genres g
              JOIN book_genres bg ON g.id = bg.genre_id
              GROUP BY g.id
              ORDER BY book_count DESC";
    $result = mysqli_query($conn, $query);
    
    $genreDistribution = [];
    if ($result && mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            $genreDistribution[] = $row;
        }
    }
    $stats['genre_distribution'] = $genreDistribution;
    
    // Get monthly borrowing trends for the past 12 months
    $query = "SELECT 
                DATE_FORMAT(borrow_date, '%Y-%m') as month,
                COUNT(*) as borrow_count
              FROM borrowings
              WHERE borrow_date >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
              GROUP BY month
              ORDER BY month ASC";
    $result = mysqli_query($conn, $query);
    
    $borrowingTrends = [];
    if ($result && mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            $borrowingTrends[] = $row;
        }
    }
    $stats['borrowing_trends'] = $borrowingTrends;
    
    // Get recent activity logs
    $query = "SELECT al.id, al.action, al.details, al.created_at, u.username, u.profile_image
              FROM activity_logs al
              LEFT JOIN users u ON al.user_id = u.id
              ORDER BY al.created_at DESC
              LIMIT 10";
    $result = mysqli_query($conn, $query);
    
    $recentActivity = [];
    if ($result && mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            $recentActivity[] = $row;
        }
    }
    $stats['recent_activity'] = $recentActivity;
    
    // Return the statistics
    echo json_encode(['success' => true, 'stats' => $stats]);
    exit;
}

// If the request method is not supported
http_response_code(405); // Method Not Allowed
echo json_encode(['success' => false, 'message' => 'Method not allowed']); 