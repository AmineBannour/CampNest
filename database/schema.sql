-- Create database if it doesn't exist
CREATE DATABASE IF NOT EXISTS campnest;
USE campnest;

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    role ENUM('client', 'admin', 'super_admin') DEFAULT 'client',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Campsites table
CREATE TABLE IF NOT EXISTS campsites (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    type VARCHAR(50) NOT NULL,
    price_per_night DECIMAL(10,2) NOT NULL,
    capacity INT NOT NULL,
    amenities JSON,
    image_url VARCHAR(255),
    status ENUM('active', 'maintenance', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    average_rating DECIMAL(3,2) DEFAULT NULL
);

-- Services table (for add-ons)
CREATE TABLE IF NOT EXISTS services (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    price DECIMAL(10,2) NOT NULL,
    type ENUM('gear_rental', 'activity', 'other') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Bookings table
CREATE TABLE IF NOT EXISTS bookings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    campsite_id INT NOT NULL,
    check_in_date DATE NOT NULL,
    check_out_date DATE NOT NULL,
    total_price DECIMAL(10,2) NOT NULL,
    status ENUM('pending', 'confirmed', 'cancelled', 'completed') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (campsite_id) REFERENCES campsites(id)
);

-- Booking Services (junction table for bookings and services)
CREATE TABLE IF NOT EXISTS booking_services (
    booking_id INT NOT NULL,
    service_id INT NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    price DECIMAL(10,2) NOT NULL,
    PRIMARY KEY (booking_id, service_id),
    FOREIGN KEY (booking_id) REFERENCES bookings(id),
    FOREIGN KEY (service_id) REFERENCES services(id)
);

-- Reviews table
CREATE TABLE IF NOT EXISTS reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_id INT NOT NULL,
    user_id INT NOT NULL,
    campsite_id INT NOT NULL,
    rating INT NOT NULL CHECK (rating BETWEEN 1 AND 5),
    title VARCHAR(255) NOT NULL,
    comment TEXT NOT NULL,
    created_at DATETIME NOT NULL,
    FOREIGN KEY (booking_id) REFERENCES bookings(id),
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (campsite_id) REFERENCES campsites(id),
    UNIQUE KEY unique_booking_review (booking_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Promotions table
CREATE TABLE IF NOT EXISTS promotions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    code VARCHAR(50) NOT NULL UNIQUE,
    discount_percentage INT NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert sample services
INSERT INTO services (name, description, price, type) VALUES
-- Gear Rentals
('Tent (4-Person)', 'Spacious 4-person tent with rainfly and ground sheet', 35.00, 'gear_rental'),
('Sleeping Bag', 'All-season sleeping bag rated for 20°F', 15.00, 'gear_rental'),
('Camping Chair', 'Comfortable folding chair with cup holder', 8.00, 'gear_rental'),
('Camping Stove', '2-burner propane camping stove', 20.00, 'gear_rental'),
('Cooler', 'Large 50-quart cooler', 12.00, 'gear_rental'),

-- Activities
('Guided Hiking Tour', '3-hour guided hiking tour with experienced local guide', 45.00, 'activity'),
('Rock Climbing Lesson', '2-hour beginner rock climbing lesson with equipment', 65.00, 'activity'),
('Kayak Tour', '2-hour kayak tour on the lake with equipment', 55.00, 'activity'),
('Mountain Biking', '3-hour mountain biking adventure with bike rental', 75.00, 'activity'),
('Stargazing Tour', '2-hour evening stargazing tour with astronomer', 35.00, 'activity');

-- Insert sample campsite
INSERT INTO campsites (name, description, type, price_per_night, capacity, amenities) VALUES
('Pine Valley Retreat', 'Peaceful campsite nestled in a pine forest with mountain views. Perfect for families and nature lovers.', 'Tent Site', 45.00, 6, '["Fire Pit", "Picnic Table", "Water Access", "Parking Space", "Pet Friendly"]'),
('Lakeside Haven', 'Beautiful waterfront campsite with direct lake access. Great for fishing and water activities.', 'RV Site', 65.00, 4, '["Electric Hookup", "Water Hookup", "Fire Pit", "Picnic Table", "Lake View", "Boat Launch"]'),
('Mountain Vista', 'Elevated campsite offering panoramic mountain views. Ideal for experienced campers.', 'Tent Site', 55.00, 4, '["Fire Pit", "Bear Box", "Picnic Table", "Hiking Trails", "Scenic View"]'); 