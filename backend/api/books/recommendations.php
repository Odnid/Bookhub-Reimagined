<?php
/**
 * Book Recommendations API Endpoint
 * 
 * This file handles book recommendations for users.
 */

// Set headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, DELETE');
header('Access-Control-Allow-Headers: Access-Control-Allow-Headers, Content-Type, Access-Control-Allow-Methods, Authorization, X-Requested-With');

// Include required files
require_once '../../includes/session.php';

// Require user to be logged in
requireLogin();

// Get the user ID from the session
$userId = $_SESSION['user_id'];

// Handle GET request (get recommendations)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Get all recommendations with pagination
    $page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
    $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 10;
    $offset = ($page - 1) * $limit;
    
    // Get total count for pagination
    $countQuery = "SELECT COUNT(*) as total FROM recommendations WHERE user_id = $userId";
    $countResult = mysqli_query($conn, $countQuery);
    $totalItems = mysqli_fetch_assoc($countResult)['total'];
    
    // Get recommendations
    $query = "SELECT r.*, b.title, b.author, b.cover_image, b.isbn, b.genre, b.status, b.available_copies
              FROM recommendations r
              JOIN books b ON r.book_id = b.id
              WHERE r.user_id = $userId
              ORDER BY r.created_at DESC
              LIMIT $offset, $limit";
    $result = mysqli_query($conn, $query);
    
    $recommendations = [];
    if ($result && mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            $recommendations[] = $row;
        }
    }
    
    // If no recommendations, generate some based on user's borrowing history
    if (empty($recommendations) && $page === 1) {
        generateRecommendations($userId);
        
        // Try to get recommendations again
        $result = mysqli_query($conn, $query);
        
        if ($result && mysqli_num_rows($result) > 0) {
            while ($row = mysqli_fetch_assoc($result)) {
                $recommendations[] = $row;
            }
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
    
    // Return the recommendations data
    echo json_encode([
        'success' => true,
        'recommendations' => $recommendations,
        'pagination' => $pagination
    ]);
    exit;
}

// Handle DELETE request (delete recommendation)
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    // Check if a specific recommendation ID is requested
    if (isset($_GET['id'])) {
        $recommendationId = (int) $_GET['id'];
        
        // Check if the recommendation exists
        $query = "SELECT r.id, b.title 
                  FROM recommendations r
                  JOIN books b ON r.book_id = b.id
                  WHERE r.id = $recommendationId AND r.user_id = $userId";
        $result = mysqli_query($conn, $query);
        
        if (!$result || mysqli_num_rows($result) === 0) {
            http_response_code(404); // Not Found
            echo json_encode(['success' => false, 'message' => 'Recommendation not found']);
            exit;
        }
        
        $recommendation = mysqli_fetch_assoc($result);
        $bookTitle = $recommendation['title'];
        
        // Delete the recommendation
        $query = "DELETE FROM recommendations WHERE id = $recommendationId";
        
        if (mysqli_query($conn, $query)) {
            // Return success response
            echo json_encode(['success' => true, 'message' => 'Recommendation removed']);
        } else {
            // Return error response
            http_response_code(500); // Internal Server Error
            echo json_encode(['success' => false, 'message' => 'Failed to remove recommendation: ' . mysqli_error($conn)]);
        }
        exit;
    }
    
    // Check if deleting all recommendations
    if (isset($_GET['all']) && $_GET['all'] === 'true') {
        // Delete all recommendations
        $query = "DELETE FROM recommendations WHERE user_id = $userId";
        
        if (mysqli_query($conn, $query)) {
            // Return success response
            echo json_encode(['success' => true, 'message' => 'All recommendations removed']);
        } else {
            // Return error response
            http_response_code(500); // Internal Server Error
            echo json_encode(['success' => false, 'message' => 'Failed to remove recommendations: ' . mysqli_error($conn)]);
        }
        exit;
    }
    
    // If no ID or 'all' parameter is provided
    http_response_code(400); // Bad Request
    echo json_encode(['success' => false, 'message' => 'Recommendation ID or "all" parameter is required']);
    exit;
}

// If the request method is not supported
http_response_code(405); // Method Not Allowed
echo json_encode(['success' => false, 'message' => 'Method not allowed']);

/**
 * Generate book recommendations based on user's borrowing history and favorites
 * 
 * @param int $userId The user ID
 * @return void
 */
function generateRecommendations($userId) {
    global $conn;
    
    // Get user's borrowed books
    $query = "SELECT DISTINCT book_id FROM borrowings WHERE user_id = $userId";
    $result = mysqli_query($conn, $query);
    
    $borrowedBooks = [];
    if ($result && mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            $borrowedBooks[] = $row['book_id'];
        }
    }
    
    // Get user's favorite books
    $query = "SELECT book_id FROM favorites WHERE user_id = $userId";
    $result = mysqli_query($conn, $query);
    
    $favoriteBooks = [];
    if ($result && mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            $favoriteBooks[] = $row['book_id'];
        }
    }
    
    // Combine borrowed and favorite books
    $userBooks = array_unique(array_merge($borrowedBooks, $favoriteBooks));
    
    // If user has no books, recommend popular books
    if (empty($userBooks)) {
        recommendPopularBooks($userId);
        return;
    }
    
    // Get genres of user's books
    $userBooksStr = implode(',', $userBooks);
    $query = "SELECT DISTINCT g.id, g.name
              FROM book_genres bg
              JOIN genres g ON bg.genre_id = g.id
              WHERE bg.book_id IN ($userBooksStr)";
    $result = mysqli_query($conn, $query);
    
    $genres = [];
    if ($result && mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            $genres[] = $row['id'];
        }
    }
    
    // Get authors of user's books
    $query = "SELECT DISTINCT author FROM books WHERE id IN ($userBooksStr)";
    $result = mysqli_query($conn, $query);
    
    $authors = [];
    if ($result && mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            $authors[] = "'" . $row['author'] . "'";
        }
    }
    
    // Find books with the same genres or authors
    $recommendations = [];
    
    if (!empty($genres)) {
        $genreIds = implode(',', $genres);
        $query = "SELECT DISTINCT b.id, b.title, 'Similar genre' as reason
                  FROM books b
                  JOIN book_genres bg ON b.id = bg.book_id
                  WHERE bg.genre_id IN ($genreIds)
                  AND b.id NOT IN ($userBooksStr)
                  AND b.id NOT IN (SELECT book_id FROM recommendations WHERE user_id = $userId)
                  LIMIT 5";
        $result = mysqli_query($conn, $query);
        
        if ($result && mysqli_num_rows($result) > 0) {
            while ($row = mysqli_fetch_assoc($result)) {
                $recommendations[] = [
                    'book_id' => $row['id'],
                    'reason' => $row['reason']
                ];
            }
        }
    }
    
    if (!empty($authors)) {
        $authorsStr = implode(',', $authors);
        $query = "SELECT id, title, CONCAT('More by ', author) as reason
                  FROM books
                  WHERE author IN ($authorsStr)
                  AND id NOT IN ($userBooksStr)
                  AND id NOT IN (SELECT book_id FROM recommendations WHERE user_id = $userId)
                  LIMIT 5";
        $result = mysqli_query($conn, $query);
        
        if ($result && mysqli_num_rows($result) > 0) {
            while ($row = mysqli_fetch_assoc($result)) {
                $recommendations[] = [
                    'book_id' => $row['id'],
                    'reason' => $row['reason']
                ];
            }
        }
    }
    
    // If still not enough recommendations, add popular books
    if (count($recommendations) < 5) {
        $existingRecsStr = '';
        if (!empty($recommendations)) {
            $existingRecs = array_column($recommendations, 'book_id');
            $existingRecsStr = implode(',', $existingRecs);
            $existingRecsStr = "AND id NOT IN ($existingRecsStr)";
        }
        
        $query = "SELECT b.id, b.title, 'Popular book' as reason
                  FROM books b
                  LEFT JOIN borrowings br ON b.id = br.book_id
                  WHERE b.id NOT IN ($userBooksStr)
                  AND b.id NOT IN (SELECT book_id FROM recommendations WHERE user_id = $userId)
                  $existingRecsStr
                  GROUP BY b.id
                  ORDER BY COUNT(br.id) DESC
                  LIMIT " . (5 - count($recommendations));
        $result = mysqli_query($conn, $query);
        
        if ($result && mysqli_num_rows($result) > 0) {
            while ($row = mysqli_fetch_assoc($result)) {
                $recommendations[] = [
                    'book_id' => $row['id'],
                    'reason' => $row['reason']
                ];
            }
        }
    }
    
    // Insert recommendations
    foreach ($recommendations as $rec) {
        $bookId = $rec['book_id'];
        $reason = $rec['reason'];
        
        $query = "INSERT INTO recommendations (user_id, book_id, reason) 
                  VALUES ($userId, $bookId, '$reason')";
        mysqli_query($conn, $query);
    }
}

/**
 * Recommend popular books to a user
 * 
 * @param int $userId The user ID
 * @return void
 */
function recommendPopularBooks($userId) {
    global $conn;
    
    // Get popular books
    $query = "SELECT b.id, b.title, 'Popular book' as reason
              FROM books b
              LEFT JOIN borrowings br ON b.id = br.book_id
              WHERE b.id NOT IN (SELECT book_id FROM recommendations WHERE user_id = $userId)
              GROUP BY b.id
              ORDER BY COUNT(br.id) DESC
              LIMIT 5";
    $result = mysqli_query($conn, $query);
    
    if (!$result || mysqli_num_rows($result) === 0) {
        return;
    }
    
    // Insert recommendations
    while ($book = mysqli_fetch_assoc($result)) {
        $bookId = $book['id'];
        $reason = $book['reason'];
        
        $query = "INSERT INTO recommendations (user_id, book_id, reason) 
                  VALUES ($userId, $bookId, '$reason')";
        mysqli_query($conn, $query);
    }
} 