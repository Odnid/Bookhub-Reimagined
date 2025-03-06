# Bookhub Backend

This is the backend for the Bookhub library management system. It provides a RESTful API for the frontend to interact with the database.

## Features

- User authentication (login, registration, password reset)
- User profile management
- Book management (add, edit, delete, search)
- Book borrowing system
- Reviews and ratings
- Favorites and recommendations
- Admin dashboard with statistics
- User management for administrators

## Requirements

- PHP 7.4 or higher
- MySQL 5.7 or higher
- Apache or Nginx web server

## Installation

1. Clone the repository
2. Import the database schema from `bookhub.sql`
3. Configure the database connection in `includes/config.php`
4. Set up your web server to serve the backend directory

## Database Setup

```sql
mysql -u root -p
source bookhub.sql
```

## API Endpoints

### Authentication

- `POST /api/auth/login.php` - Login
- `POST /api/auth/register.php` - Register
- `POST /api/auth/forgot_password.php` - Request password reset
- `POST /api/auth/reset_password.php` - Reset password
- `GET /api/auth/logout.php` - Logout

### User

- `GET /api/user/profile.php` - Get user profile
- `PUT /api/user/profile.php` - Update user profile
- `GET /api/user/notifications.php` - Get user notifications
- `PUT /api/user/notifications.php?id=X` - Mark notification as read
- `DELETE /api/user/notifications.php?id=X` - Delete notification

### Books

- `GET /api/books/books.php` - Get all books
- `GET /api/books/books.php?id=X` - Get book by ID
- `POST /api/books/books.php` - Add new book (admin only)
- `PUT /api/books/books.php?id=X` - Update book (admin only)
- `DELETE /api/books/books.php?id=X` - Delete book (admin only)
- `GET /api/books/borrow.php` - Get user's borrowings
- `POST /api/books/borrow.php` - Create borrowing request
- `PUT /api/books/borrow.php?id=X` - Update borrowing status
- `GET /api/books/favorites.php` - Get user's favorites
- `POST /api/books/favorites.php` - Add to favorites
- `DELETE /api/books/favorites.php?book_id=X` - Remove from favorites
- `GET /api/books/reviews.php` - Get user's reviews
- `GET /api/books/reviews.php?book_id=X` - Get reviews for a book
- `POST /api/books/reviews.php` - Add review
- `PUT /api/books/reviews.php?id=X` - Update review
- `DELETE /api/books/reviews.php?id=X` - Delete review
- `GET /api/books/recommendations.php` - Get recommendations
- `DELETE /api/books/recommendations.php?id=X` - Remove recommendation

### Admin

- `GET /api/admin/users.php` - Get all users
- `GET /api/admin/users.php?id=X` - Get user by ID
- `POST /api/admin/users.php` - Create user
- `PUT /api/admin/users.php?id=X` - Update user
- `DELETE /api/admin/users.php?id=X` - Delete user
- `GET /api/admin/stats.php` - Get statistics

## Default Admin Account

- Username: `admin`
- Password: `admin123`

## Directory Structure

- `api/` - API endpoints
  - `auth/` - Authentication endpoints
  - `user/` - User-related endpoints
  - `books/` - Book-related endpoints
  - `admin/` - Admin-only endpoints
- `includes/` - Shared code
  - `config.php` - Database configuration
  - `functions.php` - Utility functions
  - `session.php` - Session management
- `uploads/` - Uploaded files
  - `books/` - Book cover images
  - `profiles/` - User profile images
- `bookhub.sql` - Database schema 