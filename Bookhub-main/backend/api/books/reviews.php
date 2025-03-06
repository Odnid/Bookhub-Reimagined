<?php
/**
 * Book Reviews API Endpoint
 * 
 * This file handles book reviews.
 */

// Set headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Access-Control-Allow-Headers, Content-Type, Access-Control-Allow-Methods, Authorization, X-Requested-With');

// Include required files
require_once '../../includes/session.php';

// Handle GET request (get reviews)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Check if a specific review ID is requested
    if (isset($_GET['id'])) {
        $reviewId = (int) $_GET['id'];
        
        // Get the review data
        $query = "SELECT r.*, u.username, u.profile_image, b.title as book_title
                  FROM reviews r
                  JOIN users u ON r.user_id = u.id
                  JOIN books b ON r.book_id = b.id
                  WHERE r.id = $reviewId";
        
        // If not admin, only show approved reviews or user's own reviews
        if (!isAdmin()) {
            $userId = isLoggedIn() ? $_SESSION['user_id'] : 0;
            $query .= " AND (r.status = 'approved' OR r.user_id = $userId)";
        }
        
        $result = mysqli_query($conn, $query);
        
        if (!$result || mysqli_num_rows($result) === 0) {
            http_response_code(404); // Not Found
            echo json_encode(['success' => false, 'message' => 'Review not found']);
            exit;
        }
        
        // Get the review data
        $review = mysqli_fetch_assoc($result);
        
        // Return the review data
        echo json_encode(['success' => true, 'review' => $review]);
        exit;
    }
    
    // Check if reviews for a specific book are requested
    if (isset($_GET['book_id'])) {
        $bookId = (int) $_GET['book_id'];
        
        // Get all reviews for the book with pagination
        $page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
        $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 10;
        $offset = ($page - 1) * $limit;
        
        // Build the query
        $query = "SELECT r.*, u.username, u.profile_image
                  FROM reviews r
                  JOIN users u ON r.user_id = u.id
                  WHERE r.book_id = $bookId";
        
        // If not admin, only show approved reviews or user's own reviews
        if (!isAdmin()) {
            $userId = isLoggedIn() ? $_SESSION['user_id'] : 0;
            $query .= " AND (r.status = 'approved' OR r.user_id = $userId)";
        }
        
        // Get total count for pagination
        $countQuery = $query;
        $countResult = mysqli_query($conn, $countQuery);
        $totalItems = mysqli_num_rows($countResult);
        
        // Add pagination
        $query .= " ORDER BY r.created_at DESC LIMIT $offset, $limit";
        $result = mysqli_query($conn, $query);
        
        $reviews = [];
        if ($result && mysqli_num_rows($result) > 0) {
            while ($row = mysqli_fetch_assoc($result)) {
                $reviews[] = $row;
            }
        }
        
        // Calculate pagination data
        $totalPages = ceil($totalItems / $limit);
        $pagination = [
            'total_items' => $totalItems,
            'total_pages' => $totalPages,
            'current_page' => $page,
            'limit' => $limit
        ];
        
        // Return the reviews data
        echo json_encode([
            'success' => true,
            'reviews' => $reviews,
            'pagination' => $pagination
        ]);
        exit;
    }
    
    // If no specific review or book is requested, return user's reviews
    if (isLoggedIn()) {
        $userId = $_SESSION['user_id'];
        
        // Get all reviews by the user with pagination
        $page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
        $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 10;
        $offset = ($page - 1) * $limit;
        
        // Get total count for pagination
        $countQuery = "SELECT COUNT(*) as total
                       FROM reviews
                       WHERE user_id = $userId";
        $countResult = mysqli_query($conn, $countQuery);
        $totalItems = mysqli_fetch_assoc($countResult)['total'];
        
        // Get reviews
        $query = "SELECT r.*, b.title as book_title, b.author, b.cover_image
                  FROM reviews r
                  JOIN books b ON r.book_id = b.id
                  WHERE r.user_id = $userId
                  ORDER BY r.created_at DESC
                  LIMIT $offset, $limit";
        $result = mysqli_query($conn, $query);
        
        $reviews = [];
        if ($result && mysqli_num_rows($result) > 0) {
            while ($row = mysqli_fetch_assoc($result)) {
                $reviews[] = $row;
            }
        }
        
        // Calculate pagination data
        $totalPages = ceil($totalItems / $limit);
        $pagination = [
            'total_items' => $totalItems,
            'total_pages' => $totalPages,
            'current_page' => $page,
            'limit' => $limit
        ];
        
        // Return the reviews data
        echo json_encode([
            'success' => true,
            'reviews' => $reviews,
            'pagination' => $pagination
        ]);
        exit;
    }
    
    // If not logged in and no specific review or book is requested, return error
    http_response_code(400); // Bad Request
    echo json_encode(['success' => false, 'message' => 'Book ID or review ID is required']);
    exit;
}

// Handle POST request (create review)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Require user to be logged in
    requireLogin();
    
    $userId = $_SESSION['user_id'];
    
    // Get the posted data
    $data = json_decode(file_get_contents('php://input'), true);
    
    // If no data was received, check for form data
    if (!$data) {
        $data = $_POST;
    }
    
    // Validate required fields
    if (!isset($data['book_id']) || empty($data['book_id']) || !isset($data['rating']) || empty($data['rating'])) {
        http_response_code(400); // Bad Request
        echo json_encode(['success' => false, 'message' => 'Book ID and rating are required']);
        exit;
    }
    
    $bookId = (int) $data['book_id'];
    $rating = (int) $data['rating'];
    $reviewText = isset($data['review_text']) ? sanitize($data['review_text']) : '';
    
    // Validate rating
    if ($rating < 1 || $rating > 5) {
        http_response_code(400); // Bad Request
        echo json_encode(['success' => false, 'message' => 'Rating must be between 1 and 5']);
        exit;
    }
    
    // Check if the book exists
    $query = "SELECT title FROM books WHERE id = $bookId";
    $result = mysqli_query($conn, $query);
    
    if (!$result || mysqli_num_rows($result) === 0) {
        http_response_code(404); // Not Found
        echo json_encode(['success' => false, 'message' => 'Book not found']);
        exit;
    }
    
    $bookTitle = mysqli_fetch_assoc($result)['title'];
    
    // Check if the user has already reviewed this book
    $query = "SELECT id FROM reviews WHERE user_id = $userId AND book_id = $bookId";
    $result = mysqli_query($conn, $query);
    
    if ($result && mysqli_num_rows($result) > 0) {
        http_response_code(409); // Conflict
        echo json_encode(['success' => false, 'message' => 'You have already reviewed this book']);
        exit;
    }
    
    // Set review status (auto-approve for now, can be changed to 'pending' if moderation is needed)
    $status = 'approved';
    
    // Create the review
    $query = "INSERT INTO reviews (user_id, book_id, rating, review_text, status) 
              VALUES ($userId, $bookId, $rating, '$reviewText', '$status')";
    
    if (mysqli_query($conn, $query)) {
        $reviewId = mysqli_insert_id($conn);
        
        // Log the review
        logAction('review_add', "Added review for book: $bookTitle");
        
        // Return success response
        http_response_code(201); // Created
        echo json_encode([
            'success' => true, 
            'message' => 'Review added successfully',
            'review_id' => $reviewId
        ]);
    } else {
        // Return error response
        http_response_code(500); // Internal Server Error
        echo json_encode([
            'success' => false, 
            'message' => 'Failed to add review: ' . mysqli_error($conn)
        ]);
    }
    exit;
}

// Handle PUT request (update review)
if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    // Require user to be logged in
    requireLogin();
    
    $userId = $_SESSION['user_id'];
    $isAdmin = isAdmin();
    
    // Get the review ID
    if (!isset($_GET['id'])) {
        http_response_code(400); // Bad Request
        echo json_encode(['success' => false, 'message' => 'Review ID is required']);
        exit;
    }
    
    $reviewId = (int) $_GET['id'];
    
    // Get the posted data
    $data = json_decode(file_get_contents('php://input'), true);
    
    // If no data was received, check for form data
    if (!$data) {
        $data = $_POST;
    }
    
    // Check if the review exists
    $query = "SELECT r.*, b.title as book_title 
              FROM reviews r
              JOIN books b ON r.book_id = b.id
              WHERE r.id = $reviewId";
    
    // If not admin, restrict to user's own reviews
    if (!$isAdmin) {
        $query .= " AND r.user_id = $userId";
    }
    
    $result = mysqli_query($conn, $query);
    
    if (!$result || mysqli_num_rows($result) === 0) {
        http_response_code(404); // Not Found
        echo json_encode(['success' => false, 'message' => 'Review not found']);
        exit;
    }
    
    $review = mysqli_fetch_assoc($result);
    $bookTitle = $review['book_title'];
    
    // Initialize update fields
    $updateFields = [];
    
    // Update rating if provided
    if (isset($data['rating']) && !empty($data['rating'])) {
        $rating = (int) $data['rating'];
        
        // Validate rating
        if ($rating < 1 || $rating > 5) {
            http_response_code(400); // Bad Request
            echo json_encode(['success' => false, 'message' => 'Rating must be between 1 and 5']);
            exit;
        }
        
        $updateFields[] = "rating = $rating";
    }
    
    // Update review text if provided
    if (isset($data['review_text'])) {
        $reviewText = sanitize($data['review_text']);
        $updateFields[] = "review_text = '$reviewText'";
    }
    
    // Update status if provided (admin only)
    if ($isAdmin && isset($data['status']) && !empty($data['status'])) {
        $status = sanitize($data['status']);
        
        // Validate status
        if (!in_array($status, ['pending', 'approved', 'rejected'])) {
            http_response_code(400); // Bad Request
            echo json_encode(['success' => false, 'message' => 'Invalid status']);
            exit;
        }
        
        $updateFields[] = "status = '$status'";
    }
    
    // If no fields to update, return error
    if (empty($updateFields)) {
        http_response_code(400); // Bad Request
        echo json_encode(['success' => false, 'message' => 'No fields to update']);
        exit;
    }
    
    // Update the review
    $updateFieldsStr = implode(', ', $updateFields);
    $query = "UPDATE reviews SET $updateFieldsStr, updated_at = NOW() WHERE id = $reviewId";
    
    if (mysqli_query($conn, $query)) {
        // Log the review update
        logAction('review_update', "Updated review for book: $bookTitle");
        
        // Return success response
        echo json_encode(['success' => true, 'message' => 'Review updated successfully']);
    } else {
        // Return error response
        http_response_code(500); // Internal Server Error
        echo json_encode(['success' => false, 'message' => 'Failed to update review: ' . mysqli_error($conn)]);
    }
    exit;
}

// Handle DELETE request (delete review)
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    // Require user to be logged in
    requireLogin();
    
    $userId = $_SESSION['user_id'];
    $isAdmin = isAdmin();
    
    // Get the review ID
    if (!isset($_GET['id'])) {
        http_response_code(400); // Bad Request
        echo json_encode(['success' => false, 'message' => 'Review ID is required']);
        exit;
    }
    
    $reviewId = (int) $_GET['id'];
    
    // Check if the review exists
    $query = "SELECT r.*, b.title as book_title 
              FROM reviews r
              JOIN books b ON r.book_id = b.id
              WHERE r.id = $reviewId";
    
    // If not admin, restrict to user's own reviews
    if (!$isAdmin) {
        $query .= " AND r.user_id = $userId";
    }
    
    $result = mysqli_query($conn, $query);
    
    if (!$result || mysqli_num_rows($result) === 0) {
        http_response_code(404); // Not Found
        echo json_encode(['success' => false, 'message' => 'Review not found']);
        exit;
    }
    
    $review = mysqli_fetch_assoc($result);
    $bookTitle = $review['book_title'];
    
    // Delete the review
    $query = "DELETE FROM reviews WHERE id = $reviewId";
    
    if (mysqli_query($conn, $query)) {
        // Log the review deletion
        logAction('review_delete', "Deleted review for book: $bookTitle");
        
        // Return success response
        echo json_encode(['success' => true, 'message' => 'Review deleted successfully']);
    } else {
        // Return error response
        http_response_code(500); // Internal Server Error
        echo json_encode(['success' => false, 'message' => 'Failed to delete review: ' . mysqli_error($conn)]);
    }
    exit;
}

// If the request method is not supported
http_response_code(405); // Method Not Allowed
echo json_encode(['success' => false, 'message' => 'Method not allowed']); 