<?php
/**
 * Favorites API Endpoint
 * 
 * This file handles user's favorite books.
 */

// Set headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE');
header('Access-Control-Allow-Headers: Access-Control-Allow-Headers, Content-Type, Access-Control-Allow-Methods, Authorization, X-Requested-With');

// Include required files
require_once '../../includes/session.php';

// Require user to be logged in
requireLogin();

// Get the user ID from the session
$userId = $_SESSION['user_id'];

// Handle GET request (get user's favorites)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Get all favorites with pagination
    $page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
    $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 10;
    $offset = ($page - 1) * $limit;
    
    // Get total count for pagination
    $countQuery = "SELECT COUNT(*) as total
                   FROM favorites
                   WHERE user_id = $userId";
    $countResult = mysqli_query($conn, $countQuery);
    $totalItems = mysqli_fetch_assoc($countResult)['total'];
    
    // Get favorites
    $query = "SELECT f.*, b.title, b.author, b.cover_image, b.isbn, b.genre, b.status, b.available_copies
              FROM favorites f
              JOIN books b ON f.book_id = b.id
              WHERE f.user_id = $userId
              ORDER BY f.created_at DESC
              LIMIT $offset, $limit";
    $result = mysqli_query($conn, $query);
    
    $favorites = [];
    if ($result && mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            $favorites[] = $row;
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
    
    // Return the favorites data
    echo json_encode([
        'success' => true,
        'favorites' => $favorites,
        'pagination' => $pagination
    ]);
    exit;
}

// Handle POST request (add to favorites)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get the posted data
    $data = json_decode(file_get_contents('php://input'), true);
    
    // If no data was received, check for form data
    if (!$data) {
        $data = $_POST;
    }
    
    // Validate required fields
    if (!isset($data['book_id']) || empty($data['book_id'])) {
        http_response_code(400); // Bad Request
        echo json_encode(['success' => false, 'message' => 'Book ID is required']);
        exit;
    }
    
    $bookId = (int) $data['book_id'];
    
    // Check if the book exists
    $query = "SELECT title FROM books WHERE id = $bookId";
    $result = mysqli_query($conn, $query);
    
    if (!$result || mysqli_num_rows($result) === 0) {
        http_response_code(404); // Not Found
        echo json_encode(['success' => false, 'message' => 'Book not found']);
        exit;
    }
    
    $bookTitle = mysqli_fetch_assoc($result)['title'];
    
    // Check if the book is already in favorites
    $query = "SELECT id FROM favorites WHERE user_id = $userId AND book_id = $bookId";
    $result = mysqli_query($conn, $query);
    
    if ($result && mysqli_num_rows($result) > 0) {
        http_response_code(409); // Conflict
        echo json_encode(['success' => false, 'message' => 'Book is already in favorites']);
        exit;
    }
    
    // Add to favorites
    $query = "INSERT INTO favorites (user_id, book_id) VALUES ($userId, $bookId)";
    
    if (mysqli_query($conn, $query)) {
        $favoriteId = mysqli_insert_id($conn);
        
        // Log the action
        logAction('favorite_add', "Added book to favorites: $bookTitle");
        
        // Generate recommendations based on this favorite
        generateRecommendations($userId, $bookId);
        
        // Return success response
        http_response_code(201); // Created
        echo json_encode([
            'success' => true, 
            'message' => 'Book added to favorites',
            'favorite_id' => $favoriteId
        ]);
    } else {
        // Return error response
        http_response_code(500); // Internal Server Error
        echo json_encode([
            'success' => false, 
            'message' => 'Failed to add book to favorites: ' . mysqli_error($conn)
        ]);
    }
    exit;
}

// Handle DELETE request (remove from favorites)
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    // Get the book ID
    if (!isset($_GET['book_id'])) {
        http_response_code(400); // Bad Request
        echo json_encode(['success' => false, 'message' => 'Book ID is required']);
        exit;
    }
    
    $bookId = (int) $_GET['book_id'];
    
    // Check if the favorite exists
    $query = "SELECT f.id, b.title 
              FROM favorites f
              JOIN books b ON f.book_id = b.id
              WHERE f.user_id = $userId AND f.book_id = $bookId";
    $result = mysqli_query($conn, $query);
    
    if (!$result || mysqli_num_rows($result) === 0) {
        http_response_code(404); // Not Found
        echo json_encode(['success' => false, 'message' => 'Book not found in favorites']);
        exit;
    }
    
    $favorite = mysqli_fetch_assoc($result);
    $favoriteId = $favorite['id'];
    $bookTitle = $favorite['title'];
    
    // Remove from favorites
    $query = "DELETE FROM favorites WHERE id = $favoriteId";
    
    if (mysqli_query($conn, $query)) {
        // Log the action
        logAction('favorite_remove', "Removed book from favorites: $bookTitle");
        
        // Return success response
        echo json_encode(['success' => true, 'message' => 'Book removed from favorites']);
    } else {
        // Return error response
        http_response_code(500); // Internal Server Error
        echo json_encode(['success' => false, 'message' => 'Failed to remove book from favorites: ' . mysqli_error($conn)]);
    }
    exit;
}

// If the request method is not supported
http_response_code(405); // Method Not Allowed
echo json_encode(['success' => false, 'message' => 'Method not allowed']);

/**
 * Generate book recommendations based on a favorite
 * 
 * @param int $userId The user ID
 * @param int $bookId The book ID
 * @return void
 */
function generateRecommendations($userId, $bookId) {
    global $conn;
    
    // Get the book's genres
    $query = "SELECT g.id, g.name
              FROM book_genres bg
              JOIN genres g ON bg.genre_id = g.id
              WHERE bg.book_id = $bookId";
    $result = mysqli_query($conn, $query);
    
    if (!$result || mysqli_num_rows($result) === 0) {
        return;
    }
    
    $genres = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $genres[] = $row['id'];
    }
    
    // Get the book's author
    $query = "SELECT author FROM books WHERE id = $bookId";
    $result = mysqli_query($conn, $query);
    $author = mysqli_fetch_assoc($result)['author'];
    
    // Find books with the same genres or author
    $genreIds = implode(',', $genres);
    $query = "SELECT DISTINCT b.id, b.title, 'genre' as reason
              FROM books b
              JOIN book_genres bg ON b.id = bg.book_id
              WHERE bg.genre_id IN ($genreIds)
              AND b.id != $bookId
              AND b.id NOT IN (SELECT book_id FROM favorites WHERE user_id = $userId)
              AND b.id NOT IN (SELECT book_id FROM recommendations WHERE user_id = $userId)
              UNION
              SELECT id, title, 'author' as reason
              FROM books
              WHERE author = '$author'
              AND id != $bookId
              AND id NOT IN (SELECT book_id FROM favorites WHERE user_id = $userId)
              AND id NOT IN (SELECT book_id FROM recommendations WHERE user_id = $userId)
              LIMIT 5";
    $result = mysqli_query($conn, $query);
    
    if (!$result || mysqli_num_rows($result) === 0) {
        return;
    }
    
    // Add recommendations
    while ($book = mysqli_fetch_assoc($result)) {
        $recBookId = $book['id'];
        $reason = $book['reason'] === 'genre' ? 'Similar genre' : 'Same author';
        
        $query = "INSERT INTO recommendations (user_id, book_id, reason) 
                  VALUES ($userId, $recBookId, '$reason')";
        mysqli_query($conn, $query);
    }
} 