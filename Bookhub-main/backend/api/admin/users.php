<?php
/**
 * Admin User Management API Endpoint
 * 
 * This file handles user management for administrators.
 */

// Set headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Access-Control-Allow-Headers, Content-Type, Access-Control-Allow-Methods, Authorization, X-Requested-With');

// Include required files
require_once '../../includes/session.php';

// Require admin privileges
requireAdmin();

// Handle GET request (get users)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Check if a specific user ID is requested
    if (isset($_GET['id'])) {
        $userId = (int) $_GET['id'];
        
        // Get the user data
        $query = "SELECT id, username, email, full_name, role, profile_image, contact_number, 
                  street_address, city, province, postal_code, created_at, last_login, status
                  FROM users WHERE id = $userId";
        $result = mysqli_query($conn, $query);
        
        if (!$result || mysqli_num_rows($result) === 0) {
            http_response_code(404); // Not Found
            echo json_encode(['success' => false, 'message' => 'User not found']);
            exit;
        }
        
        // Get the user data
        $user = mysqli_fetch_assoc($result);
        
        // Get user's borrowing history
        $query = "SELECT b.id, b.book_id, books.title, books.author, 
                  b.borrow_date, b.due_date, b.return_date, b.status 
                  FROM borrowings b 
                  JOIN books ON b.book_id = books.id 
                  WHERE b.user_id = $userId 
                  ORDER BY b.borrow_date DESC";
        $result = mysqli_query($conn, $query);
        
        $borrowings = [];
        if ($result && mysqli_num_rows($result) > 0) {
            while ($row = mysqli_fetch_assoc($result)) {
                $borrowings[] = $row;
            }
        }
        
        // Get user's activity logs
        $query = "SELECT id, action, details, created_at, ip_address
                  FROM activity_logs 
                  WHERE user_id = $userId 
                  ORDER BY created_at DESC 
                  LIMIT 50";
        $result = mysqli_query($conn, $query);
        
        $activityLogs = [];
        if ($result && mysqli_num_rows($result) > 0) {
            while ($row = mysqli_fetch_assoc($result)) {
                $activityLogs[] = $row;
            }
        }
        
        // Add borrowings and activity logs to user data
        $user['borrowings'] = $borrowings;
        $user['activity_logs'] = $activityLogs;
        
        // Return the user data
        echo json_encode(['success' => true, 'user' => $user]);
        exit;
    }
    
    // Get all users with pagination and filtering
    $page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
    $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 10;
    $offset = ($page - 1) * $limit;
    
    // Build the query based on filters
    $whereClause = [];
    
    // Filter by username or email
    if (isset($_GET['search']) && !empty($_GET['search'])) {
        $search = sanitize($_GET['search']);
        $whereClause[] = "(username LIKE '%$search%' OR email LIKE '%$search%' OR full_name LIKE '%$search%')";
    }
    
    // Filter by role
    if (isset($_GET['role']) && !empty($_GET['role'])) {
        $role = sanitize($_GET['role']);
        $whereClause[] = "role = '$role'";
    }
    
    // Filter by status
    if (isset($_GET['status']) && !empty($_GET['status'])) {
        $status = sanitize($_GET['status']);
        $whereClause[] = "status = '$status'";
    }
    
    // Combine where clauses
    $whereStr = '';
    if (!empty($whereClause)) {
        $whereStr = 'WHERE ' . implode(' AND ', $whereClause);
    }
    
    // Get total count for pagination
    $countQuery = "SELECT COUNT(*) as total FROM users $whereStr";
    $countResult = mysqli_query($conn, $countQuery);
    $totalItems = mysqli_fetch_assoc($countResult)['total'];
    
    // Get users
    $query = "SELECT id, username, email, full_name, role, profile_image, created_at, last_login, status
              FROM users
              $whereStr
              ORDER BY id ASC
              LIMIT $offset, $limit";
    $result = mysqli_query($conn, $query);
    
    $users = [];
    if ($result && mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            $users[] = $row;
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
    
    // Return the users data
    echo json_encode([
        'success' => true,
        'users' => $users,
        'pagination' => $pagination
    ]);
    exit;
}

// Handle POST request (create user)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get the posted data
    $data = json_decode(file_get_contents('php://input'), true);
    
    // If no data was received, check for form data
    if (!$data) {
        $data = $_POST;
    }
    
    // Validate required fields
    $requiredFields = ['username', 'password', 'email', 'full_name', 'role'];
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
    $username = sanitize($data['username']);
    $password = $data['password'];
    $email = sanitize($data['email']);
    $fullName = sanitize($data['full_name']);
    $role = sanitize($data['role']);
    $contactNumber = isset($data['contact_number']) ? sanitize($data['contact_number']) : '';
    $streetAddress = isset($data['street_address']) ? sanitize($data['street_address']) : '';
    $city = isset($data['city']) ? sanitize($data['city']) : '';
    $province = isset($data['province']) ? sanitize($data['province']) : '';
    $postalCode = isset($data['postal_code']) ? sanitize($data['postal_code']) : '';
    $status = isset($data['status']) ? sanitize($data['status']) : 'active';
    
    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400); // Bad Request
        echo json_encode(['success' => false, 'message' => 'Invalid email format']);
        exit;
    }
    
    // Validate role
    if (!in_array($role, ['user', 'admin'])) {
        http_response_code(400); // Bad Request
        echo json_encode(['success' => false, 'message' => 'Invalid role']);
        exit;
    }
    
    // Validate status
    if (!in_array($status, ['active', 'inactive', 'suspended'])) {
        http_response_code(400); // Bad Request
        echo json_encode(['success' => false, 'message' => 'Invalid status']);
        exit;
    }
    
    // Check if username already exists
    $query = "SELECT id FROM users WHERE username = '$username'";
    $result = mysqli_query($conn, $query);
    
    if ($result && mysqli_num_rows($result) > 0) {
        http_response_code(409); // Conflict
        echo json_encode(['success' => false, 'message' => 'Username already exists']);
        exit;
    }
    
    // Check if email already exists
    $query = "SELECT id FROM users WHERE email = '$email'";
    $result = mysqli_query($conn, $query);
    
    if ($result && mysqli_num_rows($result) > 0) {
        http_response_code(409); // Conflict
        echo json_encode(['success' => false, 'message' => 'Email already exists']);
        exit;
    }
    
    // Validate password strength (at least 8 characters with letters and numbers)
    if (strlen($password) < 8 || !preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
        http_response_code(400); // Bad Request
        echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters long and contain both letters and numbers']);
        exit;
    }
    
    // Hash the password
    $hashedPassword = hashPassword($password);
    
    // Handle profile image upload if provided
    $profileImage = 'default_profile.jpg';
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
        $uploadedImage = uploadFile($_FILES['profile_image'], UPLOAD_PATH_PROFILES, $allowedTypes);
        
        if ($uploadedImage) {
            $profileImage = $uploadedImage;
        }
    }
    
    // Insert the new user into the database
    $query = "INSERT INTO users (username, password, email, full_name, role, profile_image, contact_number, street_address, city, province, postal_code, status) 
              VALUES ('$username', '$hashedPassword', '$email', '$fullName', '$role', '$profileImage', '$contactNumber', '$streetAddress', '$city', '$province', '$postalCode', '$status')";
    
    if (mysqli_query($conn, $query)) {
        $userId = mysqli_insert_id($conn);
        
        // Log the user creation
        logAction('user_create', "Created user: $username");
        
        // Return success response
        http_response_code(201); // Created
        echo json_encode([
            'success' => true, 
            'message' => 'User created successfully',
            'user_id' => $userId
        ]);
    } else {
        // Return error response
        http_response_code(500); // Internal Server Error
        echo json_encode([
            'success' => false, 
            'message' => 'Failed to create user: ' . mysqli_error($conn)
        ]);
    }
    exit;
}

// Handle PUT request (update user)
if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    // Get the user ID
    if (!isset($_GET['id'])) {
        http_response_code(400); // Bad Request
        echo json_encode(['success' => false, 'message' => 'User ID is required']);
        exit;
    }
    
    $userId = (int) $_GET['id'];
    
    // Check if the user exists
    $query = "SELECT * FROM users WHERE id = $userId";
    $result = mysqli_query($conn, $query);
    
    if (!$result || mysqli_num_rows($result) === 0) {
        http_response_code(404); // Not Found
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit;
    }
    
    $user = mysqli_fetch_assoc($result);
    
    // Get the posted data
    $data = json_decode(file_get_contents('php://input'), true);
    
    // If no data was received, check for form data
    if (!$data) {
        $data = $_POST;
    }
    
    // Initialize update fields
    $updateFields = [];
    
    // Update username if provided
    if (isset($data['username']) && !empty($data['username'])) {
        $username = sanitize($data['username']);
        
        // Check if username already exists for another user
        $query = "SELECT id FROM users WHERE username = '$username' AND id != $userId";
        $result = mysqli_query($conn, $query);
        
        if ($result && mysqli_num_rows($result) > 0) {
            http_response_code(409); // Conflict
            echo json_encode(['success' => false, 'message' => 'Username already exists']);
            exit;
        }
        
        $updateFields[] = "username = '$username'";
    }
    
    // Update email if provided
    if (isset($data['email']) && !empty($data['email'])) {
        $email = sanitize($data['email']);
        
        // Validate email format
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            http_response_code(400); // Bad Request
            echo json_encode(['success' => false, 'message' => 'Invalid email format']);
            exit;
        }
        
        // Check if email already exists for another user
        $query = "SELECT id FROM users WHERE email = '$email' AND id != $userId";
        $result = mysqli_query($conn, $query);
        
        if ($result && mysqli_num_rows($result) > 0) {
            http_response_code(409); // Conflict
            echo json_encode(['success' => false, 'message' => 'Email already exists']);
            exit;
        }
        
        $updateFields[] = "email = '$email'";
    }
    
    // Update full name if provided
    if (isset($data['full_name']) && !empty($data['full_name'])) {
        $fullName = sanitize($data['full_name']);
        $updateFields[] = "full_name = '$fullName'";
    }
    
    // Update role if provided
    if (isset($data['role']) && !empty($data['role'])) {
        $role = sanitize($data['role']);
        
        // Validate role
        if (!in_array($role, ['user', 'admin'])) {
            http_response_code(400); // Bad Request
            echo json_encode(['success' => false, 'message' => 'Invalid role']);
            exit;
        }
        
        $updateFields[] = "role = '$role'";
    }
    
    // Update status if provided
    if (isset($data['status']) && !empty($data['status'])) {
        $status = sanitize($data['status']);
        
        // Validate status
        if (!in_array($status, ['active', 'inactive', 'suspended'])) {
            http_response_code(400); // Bad Request
            echo json_encode(['success' => false, 'message' => 'Invalid status']);
            exit;
        }
        
        $updateFields[] = "status = '$status'";
    }
    
    // Update contact number if provided
    if (isset($data['contact_number'])) {
        $contactNumber = sanitize($data['contact_number']);
        $updateFields[] = "contact_number = '$contactNumber'";
    }
    
    // Update address fields if provided
    if (isset($data['street_address'])) {
        $streetAddress = sanitize($data['street_address']);
        $updateFields[] = "street_address = '$streetAddress'";
    }
    
    if (isset($data['city'])) {
        $city = sanitize($data['city']);
        $updateFields[] = "city = '$city'";
    }
    
    if (isset($data['province'])) {
        $province = sanitize($data['province']);
        $updateFields[] = "province = '$province'";
    }
    
    if (isset($data['postal_code'])) {
        $postalCode = sanitize($data['postal_code']);
        $updateFields[] = "postal_code = '$postalCode'";
    }
    
    // Update password if provided
    if (isset($data['password']) && !empty($data['password'])) {
        $password = $data['password'];
        
        // Validate password strength
        if (strlen($password) < 8 || !preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
            http_response_code(400); // Bad Request
            echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters long and contain both letters and numbers']);
            exit;
        }
        
        // Hash the password
        $hashedPassword = hashPassword($password);
        $updateFields[] = "password = '$hashedPassword'";
    }
    
    // Handle profile image upload if provided
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
        $uploadedImage = uploadFile($_FILES['profile_image'], UPLOAD_PATH_PROFILES, $allowedTypes);
        
        if ($uploadedImage) {
            $updateFields[] = "profile_image = '$uploadedImage'";
        } else {
            http_response_code(400); // Bad Request
            echo json_encode(['success' => false, 'message' => 'Failed to upload profile image']);
            exit;
        }
    }
    
    // If no fields to update, return error
    if (empty($updateFields)) {
        http_response_code(400); // Bad Request
        echo json_encode(['success' => false, 'message' => 'No fields to update']);
        exit;
    }
    
    // Update the user data
    $updateFieldsStr = implode(', ', $updateFields);
    $query = "UPDATE users SET $updateFieldsStr WHERE id = $userId";
    
    if (mysqli_query($conn, $query)) {
        // Log the user update
        logAction('user_update', "Updated user ID: $userId");
        
        // Return success response
        echo json_encode(['success' => true, 'message' => 'User updated successfully']);
    } else {
        // Return error response
        http_response_code(500); // Internal Server Error
        echo json_encode(['success' => false, 'message' => 'Failed to update user: ' . mysqli_error($conn)]);
    }
    exit;
}

// Handle DELETE request (delete user)
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    // Get the user ID
    if (!isset($_GET['id'])) {
        http_response_code(400); // Bad Request
        echo json_encode(['success' => false, 'message' => 'User ID is required']);
        exit;
    }
    
    $userId = (int) $_GET['id'];
    
    // Check if the user exists
    $query = "SELECT username FROM users WHERE id = $userId";
    $result = mysqli_query($conn, $query);
    
    if (!$result || mysqli_num_rows($result) === 0) {
        http_response_code(404); // Not Found
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit;
    }
    
    $username = mysqli_fetch_assoc($result)['username'];
    
    // Check if the user has active borrowings
    $query = "SELECT id FROM borrowings WHERE user_id = $userId AND status IN ('pending', 'approved', 'borrowed', 'overdue')";
    $result = mysqli_query($conn, $query);
    
    if ($result && mysqli_num_rows($result) > 0) {
        http_response_code(409); // Conflict
        echo json_encode(['success' => false, 'message' => 'Cannot delete user with active borrowings']);
        exit;
    }
    
    // Delete the user
    $query = "DELETE FROM users WHERE id = $userId";
    
    if (mysqli_query($conn, $query)) {
        // Log the user deletion
        logAction('user_delete', "Deleted user: $username");
        
        // Return success response
        echo json_encode(['success' => true, 'message' => 'User deleted successfully']);
    } else {
        // Return error response
        http_response_code(500); // Internal Server Error
        echo json_encode(['success' => false, 'message' => 'Failed to delete user: ' . mysqli_error($conn)]);
    }
    exit;
}

// If the request method is not supported
http_response_code(405); // Method Not Allowed
echo json_encode(['success' => false, 'message' => 'Method not allowed']); 