<?php
/**
 * Books API Endpoint
 * 
 * This file handles retrieving and managing books.
 */

// Set headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Access-Control-Allow-Headers, Content-Type, Access-Control-Allow-Methods, Authorization, X-Requested-With');

// Include required files
require_once '../../includes/session.php';

// Handle GET request (retrieve books)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Check if a specific book ID is requested
    if (isset($_GET['id'])) {
        $bookId = (int) $_GET['id'];
        
        // Get the book data
        $query = "SELECT b.*, GROUP_CONCAT(g.name) as genre_names
                  FROM books b
                  LEFT JOIN book_genres bg ON b.id = bg.book_id
                  LEFT JOIN genres g ON bg.genre_id = g.id
                  WHERE b.id = $bookId
                  GROUP BY b.id";
        $result = mysqli_query($conn, $query);
        
        if (!$result || mysqli_num_rows($result) === 0) {
            http_response_code(404); // Not Found
            echo json_encode(['success' => false, 'message' => 'Book not found']);
            exit;
        }
        
        // Get the book data
        $book = mysqli_fetch_assoc($result);
        
        // Get book reviews
        $query = "SELECT r.*, u.username, u.profile_image
                  FROM reviews r
                  JOIN users u ON r.user_id = u.id
                  WHERE r.book_id = $bookId AND r.status = 'approved'
                  ORDER BY r.created_at DESC";
        $result = mysqli_query($conn, $query);
        
        $reviews = [];
        if ($result && mysqli_num_rows($result) > 0) {
            while ($row = mysqli_fetch_assoc($result)) {
                $reviews[] = $row;
            }
        }
        
        // Calculate average rating
        $avgRating = 0;
        if (!empty($reviews)) {
            $totalRating = 0;
            foreach ($reviews as $review) {
                $totalRating += $review['rating'];
            }
            $avgRating = $totalRating / count($reviews);
        }
        
        // Add reviews and average rating to book data
        $book['reviews'] = $reviews;
        $book['avg_rating'] = $avgRating;
        
        // Return the book data
        echo json_encode(['success' => true, 'book' => $book]);
        exit;
    }
    
    // Get all books with pagination and filtering
    $page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
    $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 10;
    $offset = ($page - 1) * $limit;
    
    // Build the query based on filters
    $whereClause = [];
    $params = [];
    
    // Filter by title
    if (isset($_GET['title']) && !empty($_GET['title'])) {
        $title = sanitize($_GET['title']);
        $whereClause[] = "b.title LIKE '%$title%'";
    }
    
    // Filter by author
    if (isset($_GET['author']) && !empty($_GET['author'])) {
        $author = sanitize($_GET['author']);
        $whereClause[] = "b.author LIKE '%$author%'";
    }
    
    // Filter by genre
    if (isset($_GET['genre']) && !empty($_GET['genre'])) {
        $genre = sanitize($_GET['genre']);
        $whereClause[] = "g.name = '$genre'";
    }
    
    // Filter by availability
    if (isset($_GET['available']) && $_GET['available'] === 'true') {
        $whereClause[] = "b.available_copies > 0";
    }
    
    // Combine where clauses
    $whereStr = '';
    if (!empty($whereClause)) {
        $whereStr = 'WHERE ' . implode(' AND ', $whereClause);
    }
    
    // Get total count for pagination
    $countQuery = "SELECT COUNT(DISTINCT b.id) as total
                   FROM books b
                   LEFT JOIN book_genres bg ON b.id = bg.book_id
                   LEFT JOIN genres g ON bg.genre_id = g.id
                   $whereStr";
    $countResult = mysqli_query($conn, $countQuery);
    $totalItems = mysqli_fetch_assoc($countResult)['total'];
    
    // Get books
    $query = "SELECT b.*, GROUP_CONCAT(g.name) as genre_names
              FROM books b
              LEFT JOIN book_genres bg ON b.id = bg.book_id
              LEFT JOIN genres g ON bg.genre_id = g.id
              $whereStr
              GROUP BY b.id
              ORDER BY b.title ASC
              LIMIT $offset, $limit";
    $result = mysqli_query($conn, $query);
    
    $books = [];
    if ($result && mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            $books[] = $row;
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
    
    // Return the books data
    echo json_encode([
        'success' => true,
        'books' => $books,
        'pagination' => $pagination
    ]);
    exit;
}

// Handle POST request (add new book)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Require admin privileges
    requireAdmin();
    
    // Get the posted data
    $data = json_decode(file_get_contents('php://input'), true);
    
    // If no data was received, check for form data
    if (!$data) {
        $data = $_POST;
    }
    
    // Validate required fields
    $requiredFields = ['title', 'author', 'isbn', 'publication_year', 'publisher', 'description', 'genre', 'pages', 'total_copies'];
    $missingFields = [];
    
    foreach ($requiredFields as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            $missingFields[] = $field;
        }
    }
    
    if (!empty($missingFields)) {
        http_response_code(400); // Bad Request
        echo json_encode([
            'success' => false, 
            'message' => 'Missing required fields', 
            'missing_fields' => $missingFields
        ]);
        exit;
    }
    
    // Sanitize input
    $title = sanitize($data['title']);
    $author = sanitize($data['author']);
    $isbn = sanitize($data['isbn']);
    $publicationYear = (int) $data['publication_year'];
    $publisher = sanitize($data['publisher']);
    $description = sanitize($data['description']);
    $genre = sanitize($data['genre']);
    $language = isset($data['language']) ? sanitize($data['language']) : 'English';
    $pages = (int) $data['pages'];
    $totalCopies = (int) $data['total_copies'];
    $availableCopies = $totalCopies; // Initially, all copies are available
    $userId = $_SESSION['user_id'];
    
    // Check if ISBN already exists
    $query = "SELECT id FROM books WHERE isbn = '$isbn'";
    $result = mysqli_query($conn, $query);
    
    if ($result && mysqli_num_rows($result) > 0) {
        http_response_code(409); // Conflict
        echo json_encode(['success' => false, 'message' => 'ISBN already exists']);
        exit;
    }
    
    // Handle cover image upload if provided
    $coverImage = 'default_cover.jpg';
    if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] === UPLOAD_ERR_OK) {
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
        $uploadedImage = uploadFile($_FILES['cover_image'], UPLOAD_PATH_BOOKS, $allowedTypes);
        
        if ($uploadedImage) {
            $coverImage = $uploadedImage;
        }
    }
    
    // Insert the new book into the database
    $query = "INSERT INTO books (title, author, isbn, publication_year, publisher, description, cover_image, genre, language, pages, available_copies, total_copies, added_by, status) 
              VALUES ('$title', '$author', '$isbn', $publicationYear, '$publisher', '$description', '$coverImage', '$genre', '$language', $pages, $availableCopies, $totalCopies, $userId, 'available')";
    
    if (mysqli_query($conn, $query)) {
        $bookId = mysqli_insert_id($conn);
        
        // Handle genres
        if (isset($data['genres']) && is_array($data['genres'])) {
            foreach ($data['genres'] as $genreName) {
                $genreName = sanitize($genreName);
                
                // Check if genre exists
                $query = "SELECT id FROM genres WHERE name = '$genreName'";
                $result = mysqli_query($conn, $query);
                
                if ($result && mysqli_num_rows($result) > 0) {
                    $genreId = mysqli_fetch_assoc($result)['id'];
                } else {
                    // Create new genre
                    $query = "INSERT INTO genres (name) VALUES ('$genreName')";
                    mysqli_query($conn, $query);
                    $genreId = mysqli_insert_id($conn);
                }
                
                // Link book to genre
                $query = "INSERT INTO book_genres (book_id, genre_id) VALUES ($bookId, $genreId)";
                mysqli_query($conn, $query);
            }
        }
        
        // Log the book addition
        logAction('book_add', "Added book: $title");
        
        // Return success response
        http_response_code(201); // Created
        echo json_encode([
            'success' => true, 
            'message' => 'Book added successfully',
            'book_id' => $bookId
        ]);
    } else {
        // Return error response
        http_response_code(500); // Internal Server Error
        echo json_encode([
            'success' => false, 
            'message' => 'Failed to add book: ' . mysqli_error($conn)
        ]);
    }
    exit;
}

// Handle PUT request (update book)
if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    // Require admin privileges
    requireAdmin();
    
    // Get the book ID
    if (!isset($_GET['id'])) {
        http_response_code(400); // Bad Request
        echo json_encode(['success' => false, 'message' => 'Book ID is required']);
        exit;
    }
    
    $bookId = (int) $_GET['id'];
    
    // Check if the book exists
    $query = "SELECT * FROM books WHERE id = $bookId";
    $result = mysqli_query($conn, $query);
    
    if (!$result || mysqli_num_rows($result) === 0) {
        http_response_code(404); // Not Found
        echo json_encode(['success' => false, 'message' => 'Book not found']);
        exit;
    }
    
    // Get the posted data
    $data = json_decode(file_get_contents('php://input'), true);
    
    // If no data was received, check for form data
    if (!$data) {
        $data = $_POST;
    }
    
    // Initialize update fields
    $updateFields = [];
    
    // Update title if provided
    if (isset($data['title']) && !empty($data['title'])) {
        $title = sanitize($data['title']);
        $updateFields[] = "title = '$title'";
    }
    
    // Update author if provided
    if (isset($data['author']) && !empty($data['author'])) {
        $author = sanitize($data['author']);
        $updateFields[] = "author = '$author'";
    }
    
    // Update ISBN if provided
    if (isset($data['isbn']) && !empty($data['isbn'])) {
        $isbn = sanitize($data['isbn']);
        
        // Check if ISBN already exists for another book
        $query = "SELECT id FROM books WHERE isbn = '$isbn' AND id != $bookId";
        $result = mysqli_query($conn, $query);
        
        if ($result && mysqli_num_rows($result) > 0) {
            http_response_code(409); // Conflict
            echo json_encode(['success' => false, 'message' => 'ISBN already exists']);
            exit;
        }
        
        $updateFields[] = "isbn = '$isbn'";
    }
    
    // Update publication year if provided
    if (isset($data['publication_year']) && !empty($data['publication_year'])) {
        $publicationYear = (int) $data['publication_year'];
        $updateFields[] = "publication_year = $publicationYear";
    }
    
    // Update publisher if provided
    if (isset($data['publisher']) && !empty($data['publisher'])) {
        $publisher = sanitize($data['publisher']);
        $updateFields[] = "publisher = '$publisher'";
    }
    
    // Update description if provided
    if (isset($data['description']) && !empty($data['description'])) {
        $description = sanitize($data['description']);
        $updateFields[] = "description = '$description'";
    }
    
    // Update genre if provided
    if (isset($data['genre']) && !empty($data['genre'])) {
        $genre = sanitize($data['genre']);
        $updateFields[] = "genre = '$genre'";
    }
    
    // Update language if provided
    if (isset($data['language']) && !empty($data['language'])) {
        $language = sanitize($data['language']);
        $updateFields[] = "language = '$language'";
    }
    
    // Update pages if provided
    if (isset($data['pages']) && !empty($data['pages'])) {
        $pages = (int) $data['pages'];
        $updateFields[] = "pages = $pages";
    }
    
    // Update total copies if provided
    if (isset($data['total_copies']) && !empty($data['total_copies'])) {
        $totalCopies = (int) $data['total_copies'];
        
        // Get current available copies
        $query = "SELECT available_copies FROM books WHERE id = $bookId";
        $result = mysqli_query($conn, $query);
        $currentAvailable = mysqli_fetch_assoc($result)['available_copies'];
        
        // Calculate new available copies
        $availableCopies = $currentAvailable + ($totalCopies - $currentAvailable);
        
        $updateFields[] = "total_copies = $totalCopies";
        $updateFields[] = "available_copies = $availableCopies";
    }
    
    // Update available copies if provided
    if (isset($data['available_copies']) && !empty($data['available_copies'])) {
        $availableCopies = (int) $data['available_copies'];
        
        // Get current total copies
        $query = "SELECT total_copies FROM books WHERE id = $bookId";
        $result = mysqli_query($conn, $query);
        $totalCopies = mysqli_fetch_assoc($result)['total_copies'];
        
        // Ensure available copies doesn't exceed total copies
        if ($availableCopies > $totalCopies) {
            http_response_code(400); // Bad Request
            echo json_encode(['success' => false, 'message' => 'Available copies cannot exceed total copies']);
            exit;
        }
        
        $updateFields[] = "available_copies = $availableCopies";
    }
    
    // Update status if provided
    if (isset($data['status']) && !empty($data['status'])) {
        $status = sanitize($data['status']);
        
        if (!in_array($status, ['available', 'unavailable'])) {
            http_response_code(400); // Bad Request
            echo json_encode(['success' => false, 'message' => 'Invalid status']);
            exit;
        }
        
        $updateFields[] = "status = '$status'";
    }
    
    // Handle cover image upload if provided
    if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] === UPLOAD_ERR_OK) {
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
        $uploadedImage = uploadFile($_FILES['cover_image'], UPLOAD_PATH_BOOKS, $allowedTypes);
        
        if ($uploadedImage) {
            $updateFields[] = "cover_image = '$uploadedImage'";
        } else {
            http_response_code(400); // Bad Request
            echo json_encode(['success' => false, 'message' => 'Failed to upload cover image']);
            exit;
        }
    }
    
    // If no fields to update, return error
    if (empty($updateFields)) {
        http_response_code(400); // Bad Request
        echo json_encode(['success' => false, 'message' => 'No fields to update']);
        exit;
    }
    
    // Update the book data
    $updateFieldsStr = implode(', ', $updateFields);
    $query = "UPDATE books SET $updateFieldsStr WHERE id = $bookId";
    
    if (mysqli_query($conn, $query)) {
        // Handle genres if provided
        if (isset($data['genres']) && is_array($data['genres'])) {
            // Remove existing genres
            $query = "DELETE FROM book_genres WHERE book_id = $bookId";
            mysqli_query($conn, $query);
            
            // Add new genres
            foreach ($data['genres'] as $genreName) {
                $genreName = sanitize($genreName);
                
                // Check if genre exists
                $query = "SELECT id FROM genres WHERE name = '$genreName'";
                $result = mysqli_query($conn, $query);
                
                if ($result && mysqli_num_rows($result) > 0) {
                    $genreId = mysqli_fetch_assoc($result)['id'];
                } else {
                    // Create new genre
                    $query = "INSERT INTO genres (name) VALUES ('$genreName')";
                    mysqli_query($conn, $query);
                    $genreId = mysqli_insert_id($conn);
                }
                
                // Link book to genre
                $query = "INSERT INTO book_genres (book_id, genre_id) VALUES ($bookId, $genreId)";
                mysqli_query($conn, $query);
            }
        }
        
        // Log the book update
        logAction('book_update', "Updated book ID: $bookId");
        
        // Return success response
        echo json_encode(['success' => true, 'message' => 'Book updated successfully']);
    } else {
        // Return error response
        http_response_code(500); // Internal Server Error
        echo json_encode(['success' => false, 'message' => 'Failed to update book: ' . mysqli_error($conn)]);
    }
    exit;
}

// Handle DELETE request (delete book)
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    // Require admin privileges
    requireAdmin();
    
    // Get the book ID
    if (!isset($_GET['id'])) {
        http_response_code(400); // Bad Request
        echo json_encode(['success' => false, 'message' => 'Book ID is required']);
        exit;
    }
    
    $bookId = (int) $_GET['id'];
    
    // Check if the book exists
    $query = "SELECT title FROM books WHERE id = $bookId";
    $result = mysqli_query($conn, $query);
    
    if (!$result || mysqli_num_rows($result) === 0) {
        http_response_code(404); // Not Found
        echo json_encode(['success' => false, 'message' => 'Book not found']);
        exit;
    }
    
    $bookTitle = mysqli_fetch_assoc($result)['title'];
    
    // Check if the book has active borrowings
    $query = "SELECT id FROM borrowings WHERE book_id = $bookId AND status IN ('pending', 'approved', 'borrowed', 'overdue')";
    $result = mysqli_query($conn, $query);
    
    if ($result && mysqli_num_rows($result) > 0) {
        http_response_code(409); // Conflict
        echo json_encode(['success' => false, 'message' => 'Cannot delete book with active borrowings']);
        exit;
    }
    
    // Delete the book
    $query = "DELETE FROM books WHERE id = $bookId";
    
    if (mysqli_query($conn, $query)) {
        // Log the book deletion
        logAction('book_delete', "Deleted book: $bookTitle");
        
        // Return success response
        echo json_encode(['success' => true, 'message' => 'Book deleted successfully']);
    } else {
        // Return error response
        http_response_code(500); // Internal Server Error
        echo json_encode(['success' => false, 'message' => 'Failed to delete book: ' . mysqli_error($conn)]);
    }
    exit;
}

// If the request method is not supported
http_response_code(405); // Method Not Allowed
echo json_encode(['success' => false, 'message' => 'Method not allowed']); 