-- Bookhub Database Schema

-- Drop database if it exists
DROP DATABASE IF EXISTS bookhub;

-- Create database
CREATE DATABASE bookhub CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Use the database
USE bookhub;

-- Users table
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    full_name VARCHAR(100) NOT NULL,
    role ENUM('user', 'admin') NOT NULL DEFAULT 'user',
    profile_image VARCHAR(255) DEFAULT 'default_profile.jpg',
    contact_number VARCHAR(20),
    street_address VARCHAR(255),
    city VARCHAR(100),
    province VARCHAR(100),
    postal_code VARCHAR(20),
    reset_token VARCHAR(64) DEFAULT NULL,
    reset_token_expires DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_login DATETIME DEFAULT NULL,
    status ENUM('active', 'inactive', 'suspended') DEFAULT 'active'
);

-- Books table
CREATE TABLE books (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    author VARCHAR(100) NOT NULL,
    isbn VARCHAR(20) UNIQUE,
    publication_year INT,
    publisher VARCHAR(100),
    description TEXT,
    cover_image VARCHAR(255) DEFAULT 'default_cover.jpg',
    genre VARCHAR(50),
    language VARCHAR(50) DEFAULT 'English',
    pages INT,
    available_copies INT NOT NULL DEFAULT 0,
    total_copies INT NOT NULL DEFAULT 0,
    added_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    status ENUM('available', 'unavailable') DEFAULT 'available',
    FOREIGN KEY (added_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Book categories/genres table
CREATE TABLE genres (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Book-Genre relationship (many-to-many)
CREATE TABLE book_genres (
    book_id INT,
    genre_id INT,
    PRIMARY KEY (book_id, genre_id),
    FOREIGN KEY (book_id) REFERENCES books(id) ON DELETE CASCADE,
    FOREIGN KEY (genre_id) REFERENCES genres(id) ON DELETE CASCADE
);

-- Borrowing records
CREATE TABLE borrowings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    book_id INT NOT NULL,
    borrow_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    due_date DATE NOT NULL,
    return_date DATE DEFAULT NULL,
    status ENUM('pending', 'approved', 'rejected', 'borrowed', 'returned', 'overdue') DEFAULT 'pending',
    approved_by INT DEFAULT NULL,
    notes TEXT,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (book_id) REFERENCES books(id) ON DELETE CASCADE,
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Book reviews
CREATE TABLE reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    book_id INT NOT NULL,
    rating INT NOT NULL CHECK (rating BETWEEN 1 AND 5),
    review_text TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (book_id) REFERENCES books(id) ON DELETE CASCADE
);

-- User favorites (for book recommendations)
CREATE TABLE favorites (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    book_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (book_id) REFERENCES books(id) ON DELETE CASCADE,
    UNIQUE KEY (user_id, book_id)
);

-- Activity logs
CREATE TABLE activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    details TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Notifications
CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    message TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Book recommendations
CREATE TABLE recommendations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    book_id INT NOT NULL,
    reason VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (book_id) REFERENCES books(id) ON DELETE CASCADE
);

-- Insert default admin user (password: admin123)
INSERT INTO users (username, password, email, full_name, role, status) 
VALUES ('admin', '$2y$12$hZUzQqGTECmxLN7lJTIxYeB7uSXVQa3XHpXUKk.XvTLnuoXDMvl6G', 'admin@bookhub.com', 'System Administrator', 'admin', 'active');

-- Insert sample genres
INSERT INTO genres (name, description) VALUES 
('Fiction', 'Literary works created from the imagination'),
('Non-Fiction', 'Informational and factual writing'),
('Science Fiction', 'Fiction based on scientific discoveries or advanced technology'),
('Fantasy', 'Fiction with magical or supernatural elements'),
('Mystery', 'Fiction dealing with the solution of a crime or puzzle'),
('Romance', 'Fiction focusing on romantic relationships'),
('Thriller', 'Fiction characterized by suspense and excitement'),
('Horror', 'Fiction intended to scare or frighten'),
('Biography', 'Non-fiction account of someone\'s life'),
('History', 'Non-fiction about past events'),
('Self-Help', 'Books aimed at personal improvement'),
('Science', 'Non-fiction about scientific topics'),
('Technology', 'Non-fiction about technological topics'),
('Philosophy', 'Non-fiction exploring fundamental questions'),
('Poetry', 'Literary work characterized by rhythm and imagery');

-- Insert sample books
INSERT INTO books (title, author, isbn, publication_year, publisher, description, genre, pages, available_copies, total_copies, status) VALUES
('The Great Gatsby', 'F. Scott Fitzgerald', '9780743273565', 1925, 'Scribner', 'A novel about the mysterious Jay Gatsby and his love for Daisy Buchanan.', 'Fiction', 180, 5, 5, 'available'),
('To Kill a Mockingbird', 'Harper Lee', '9780061120084', 1960, 'HarperCollins', 'A novel about racial inequality and moral growth in the American South.', 'Fiction', 281, 3, 3, 'available'),
('1984', 'George Orwell', '9780451524935', 1949, 'Signet Classics', 'A dystopian novel set in a totalitarian society.', 'Science Fiction', 328, 2, 2, 'available'),
('Pride and Prejudice', 'Jane Austen', '9780141439518', 1813, 'Penguin Classics', 'A romantic novel about the Bennet sisters and their suitors.', 'Romance', 432, 4, 4, 'available'),
('The Hobbit', 'J.R.R. Tolkien', '9780547928227', 1937, 'Houghton Mifflin Harcourt', 'A fantasy novel about Bilbo Baggins and his quest to win a share of the treasure.', 'Fantasy', 300, 3, 3, 'available'),
('The Catcher in the Rye', 'J.D. Salinger', '9780316769488', 1951, 'Little, Brown and Company', 'A novel about teenage angst and alienation.', 'Fiction', 277, 2, 2, 'available'),
('The Lord of the Rings', 'J.R.R. Tolkien', '9780618640157', 1954, 'Houghton Mifflin Harcourt', 'An epic fantasy novel about the quest to destroy the One Ring.', 'Fantasy', 1178, 1, 1, 'available'),
('Harry Potter and the Sorcerer\'s Stone', 'J.K. Rowling', '9780590353427', 1997, 'Scholastic', 'A fantasy novel about a young wizard, Harry Potter.', 'Fantasy', 309, 0, 3, 'unavailable'),
('The Da Vinci Code', 'Dan Brown', '9780307474278', 2003, 'Anchor', 'A mystery thriller novel about symbologist Robert Langdon.', 'Mystery', 454, 2, 2, 'available'),
('The Alchemist', 'Paulo Coelho', '9780062315007', 1988, 'HarperOne', 'A philosophical novel about a shepherd named Santiago.', 'Fiction', 197, 1, 1, 'available');

-- Connect books with genres
INSERT INTO book_genres (book_id, genre_id) VALUES
(1, 1), -- The Great Gatsby - Fiction
(2, 1), -- To Kill a Mockingbird - Fiction
(3, 3), -- 1984 - Science Fiction
(4, 6), -- Pride and Prejudice - Romance
(5, 4), -- The Hobbit - Fantasy
(6, 1), -- The Catcher in the Rye - Fiction
(7, 4), -- The Lord of the Rings - Fantasy
(8, 4), -- Harry Potter - Fantasy
(9, 5), -- The Da Vinci Code - Mystery
(10, 1), -- The Alchemist - Fiction
(10, 14); -- The Alchemist - Philosophy 