<?php
/**
 * Book Borrowing API Endpoint
 * 
 * This file handles book borrowing requests.
 */

// Set headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT');
header('Access-Control-Allow-Headers: Access-Control-Allow-Headers, Content-Type, Access-Control-Allow-Methods, Authorization, X-Requested-With');

// Include required files
require_once '../../includes/session.php';

// Require user to be logged in
requireLogin();

// Get the user ID from the session
$userId = $_SESSION['user_id'];

// Handle GET request (get user's borrowings)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Check if a specific borrowing ID is requested
    if (isset($_GET['id'])) {
        $borrowingId = (int) $_GET['id'];
        
        // Get the borrowing data
        $query = "SELECT b.*, books.title, books.author, books.cover_image, books.isbn
                  FROM borrowings b
                  JOIN books ON b.book_id = books.id
                  WHERE b.id = $borrowingId AND b.user_id = $userId";
        $result = mysqli_query($conn, $query);
        
        if (!$result || mysqli_num_rows($result) === 0) {
            http_response_code(404); // Not Found
            echo json_encode(['success' => false, 'message' => 'Borrowing record not found']);
            exit;
        }
        
        // Get the borrowing data
        $borrowing = mysqli_fetch_assoc($result);
        
        // Return the borrowing data
        echo json_encode(['success' => true, 'borrowing' => $borrowing]);
        exit;
    }
    
    // Get all borrowings with pagination and filtering
    $page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
    $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 10;
    $offset = ($page - 1) * $limit;
    
    // Build the query based on filters
    $whereClause = ["b.user_id = $userId"];
    
    // Filter by status
    if (isset($_GET['status']) && !empty($_GET['status'])) {
        $status = sanitize($_GET['status']);
        $whereClause[] = "b.status = '$status'";
    }
    
    // Filter by book title
    if (isset($_GET['title']) && !empty($_GET['title'])) {
        $title = sanitize($_GET['title']);
        $whereClause[] = "books.title LIKE '%$title%'";
    }
    
    // Combine where clauses
    $whereStr = 'WHERE ' . implode(' AND ', $whereClause);
    
    // Get total count for pagination
    $countQuery = "SELECT COUNT(*) as total
                   FROM borrowings b
                   JOIN books ON b.book_id = books.id
                   $whereStr";
    $countResult = mysqli_query($conn, $countQuery);
    $totalItems = mysqli_fetch_assoc($countResult)['total'];
    
    // Get borrowings
    $query = "SELECT b.*, books.title, books.author, books.cover_image, books.isbn
              FROM borrowings b
              JOIN books ON b.book_id = books.id
              $whereStr
              ORDER BY b.borrow_date DESC
              LIMIT $offset, $limit";
    $result = mysqli_query($conn, $query);
    
    $borrowings = [];
    if ($result && mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            $borrowings[] = $row;
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
    
    // Return the borrowings data
    echo json_encode([
        'success' => true,
        'borrowings' => $borrowings,
        'pagination' => $pagination
    ]);
    exit;
}

// Handle POST request (create borrowing request)
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
    
    // Check if the book exists and is available
    $query = "SELECT id, title, available_copies, status FROM books WHERE id = $bookId";
    $result = mysqli_query($conn, $query);
    
    if (!$result || mysqli_num_rows($result) === 0) {
        http_response_code(404); // Not Found
        echo json_encode(['success' => false, 'message' => 'Book not found']);
        exit;
    }
    
    $book = mysqli_fetch_assoc($result);
    
    // Check if the book is available
    if ($book['status'] !== 'available' || $book['available_copies'] <= 0) {
        http_response_code(400); // Bad Request
        echo json_encode(['success' => false, 'message' => 'Book is not available for borrowing']);
        exit;
    }
    
    // Check if the user already has an active borrowing for this book
    $query = "SELECT id FROM borrowings 
              WHERE user_id = $userId AND book_id = $bookId 
              AND status IN ('pending', 'approved', 'borrowed', 'overdue')";
    $result = mysqli_query($conn, $query);
    
    if ($result && mysqli_num_rows($result) > 0) {
        http_response_code(409); // Conflict
        echo json_encode(['success' => false, 'message' => 'You already have an active borrowing request for this book']);
        exit;
    }
    
    // Calculate due date (default: 14 days from now)
    $borrowDays = isset($data['borrow_days']) ? (int) $data['borrow_days'] : 14;
    $dueDate = date('Y-m-d', strtotime("+$borrowDays days"));
    
    // Add notes if provided
    $notes = isset($data['notes']) ? sanitize($data['notes']) : '';
    
    // Create the borrowing request
    $query = "INSERT INTO borrowings (user_id, book_id, due_date, notes, status) 
              VALUES ($userId, $bookId, '$dueDate', '$notes', 'pending')";
    
    if (mysqli_query($conn, $query)) {
        $borrowingId = mysqli_insert_id($conn);
        
        // Log the borrowing request
        logAction('borrow_request', "Requested to borrow book: {$book['title']}");
        
        // Create a notification for admins
        $query = "SELECT id FROM users WHERE role = 'admin'";
        $result = mysqli_query($conn, $query);
        
        if ($result && mysqli_num_rows($result) > 0) {
            while ($admin = mysqli_fetch_assoc($result)) {
                $adminId = $admin['id'];
                $message = "New borrowing request for book: {$book['title']}";
                $query = "INSERT INTO notifications (user_id, message) VALUES ($adminId, '$message')";
                mysqli_query($conn, $query);
            }
        }
        
        // Return success response
        http_response_code(201); // Created
        echo json_encode([
            'success' => true, 
            'message' => 'Borrowing request created successfully',
            'borrowing_id' => $borrowingId
        ]);
    } else {
        // Return error response
        http_response_code(500); // Internal Server Error
        echo json_encode([
            'success' => false, 
            'message' => 'Failed to create borrowing request: ' . mysqli_error($conn)
        ]);
    }
    exit;
}

// Handle PUT request (update borrowing status - admin only)
if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    // Check if the user is an admin for approval/rejection
    $isAdmin = isAdmin();
    
    // Get the borrowing ID
    if (!isset($_GET['id'])) {
        http_response_code(400); // Bad Request
        echo json_encode(['success' => false, 'message' => 'Borrowing ID is required']);
        exit;
    }
    
    $borrowingId = (int) $_GET['id'];
    
    // Get the posted data
    $data = json_decode(file_get_contents('php://input'), true);
    
    // If no data was received, check for form data
    if (!$data) {
        $data = $_POST;
    }
    
    // Validate required fields
    if (!isset($data['status']) || empty($data['status'])) {
        http_response_code(400); // Bad Request
        echo json_encode(['success' => false, 'message' => 'Status is required']);
        exit;
    }
    
    $status = sanitize($data['status']);
    $notes = isset($data['notes']) ? sanitize($data['notes']) : '';
    
    // Check if the borrowing exists
    $query = "SELECT b.*, books.title, books.available_copies 
              FROM borrowings b
              JOIN books ON b.book_id = books.id
              WHERE b.id = $borrowingId";
    
    // If not admin, restrict to user's own borrowings
    if (!$isAdmin) {
        $query .= " AND b.user_id = $userId";
    }
    
    $result = mysqli_query($conn, $query);
    
    if (!$result || mysqli_num_rows($result) === 0) {
        http_response_code(404); // Not Found
        echo json_encode(['success' => false, 'message' => 'Borrowing record not found']);
        exit;
    }
    
    $borrowing = mysqli_fetch_assoc($result);
    $bookId = $borrowing['book_id'];
    $bookTitle = $borrowing['title'];
    $borrowingUserId = $borrowing['user_id'];
    $currentStatus = $borrowing['status'];
    $availableCopies = $borrowing['available_copies'];
    
    // Validate status transitions
    $validTransitions = [
        'pending' => ['approved', 'rejected'],
        'approved' => ['borrowed', 'rejected'],
        'borrowed' => ['returned', 'overdue'],
        'overdue' => ['returned']
    ];
    
    // Check if the status transition is valid
    if (!isset($validTransitions[$currentStatus]) || !in_array($status, $validTransitions[$currentStatus])) {
        http_response_code(400); // Bad Request
        echo json_encode(['success' => false, 'message' => "Invalid status transition from '$currentStatus' to '$status'"]);
        exit;
    }
    
    // Check if the user has permission for this status change
    if (!$isAdmin && !in_array($status, ['returned'])) {
        http_response_code(403); // Forbidden
        echo json_encode(['success' => false, 'message' => 'You do not have permission to change the status to ' . $status]);
        exit;
    }
    
    // Update book available copies if necessary
    if ($status === 'borrowed' && $currentStatus === 'approved') {
        // Decrease available copies when book is borrowed
        if ($availableCopies <= 0) {
            http_response_code(400); // Bad Request
            echo json_encode(['success' => false, 'message' => 'No copies available for borrowing']);
            exit;
        }
        
        $query = "UPDATE books SET available_copies = available_copies - 1 WHERE id = $bookId";
        mysqli_query($conn, $query);
    } else if ($status === 'returned' && in_array($currentStatus, ['borrowed', 'overdue'])) {
        // Increase available copies when book is returned
        $query = "UPDATE books SET available_copies = available_copies + 1 WHERE id = $bookId";
        mysqli_query($conn, $query);
    }
    
    // Update the borrowing status
    $updateFields = ["status = '$status'"];
    
    // Add return date if the book is being returned
    if ($status === 'returned') {
        $updateFields[] = "return_date = NOW()";
    }
    
    // Add notes if provided
    if (!empty($notes)) {
        $updateFields[] = "notes = '$notes'";
    }
    
    // Add approved_by if the status is being changed by an admin
    if ($isAdmin && in_array($status, ['approved', 'rejected', 'borrowed'])) {
        $updateFields[] = "approved_by = $userId";
    }
    
    $updateFieldsStr = implode(', ', $updateFields);
    $query = "UPDATE borrowings SET $updateFieldsStr WHERE id = $borrowingId";
    
    if (mysqli_query($conn, $query)) {
        // Log the status change
        $actionType = 'borrowing_' . $status;
        $actionDetails = "Changed borrowing status to $status for book: $bookTitle";
        logAction($actionType, $actionDetails);
        
        // Create a notification for the user
        $message = '';
        switch ($status) {
            case 'approved':
                $message = "Your borrowing request for '$bookTitle' has been approved. You can now pick up the book.";
                break;
            case 'rejected':
                $message = "Your borrowing request for '$bookTitle' has been rejected.";
                break;
            case 'borrowed':
                $message = "You have borrowed '$bookTitle'. It is due on {$borrowing['due_date']}.";
                break;
            case 'returned':
                $message = "You have returned '$bookTitle'. Thank you!";
                break;
            case 'overdue':
                $message = "Your borrowed book '$bookTitle' is overdue. Please return it as soon as possible.";
                break;
        }
        
        if (!empty($message)) {
            $query = "INSERT INTO notifications (user_id, message) VALUES ($borrowingUserId, '$message')";
            mysqli_query($conn, $query);
        }
        
        // Return success response
        echo json_encode(['success' => true, 'message' => 'Borrowing status updated successfully']);
    } else {
        // Return error response
        http_response_code(500); // Internal Server Error
        echo json_encode(['success' => false, 'message' => 'Failed to update borrowing status: ' . mysqli_error($conn)]);
    }
    exit;
}

// If the request method is not supported
http_response_code(405); // Method Not Allowed
echo json_encode(['success' => false, 'message' => 'Method not allowed']); 