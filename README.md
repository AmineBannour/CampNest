# 🏕️ CampNest - Camping Reservation System

CampNest is a full-featured camping reservation system that allows users to discover, book, and manage campsite reservations. The system includes features for both clients and administrators, making it easy to manage camping experiences from both perspectives.

## Features

- **User Authentication**
  - Client registration and login
  - Role-based access control (Client, Admin, Super Admin)
  - Secure password hashing

- **Campsite Management**
  - Browse available campsites
  - Search by date and amenities
  - View detailed campsite information
  - Image gallery for each location

- **Booking System**
  - Real-time availability checking
  - Date selection with validation
  - Add-on services and equipment rentals
  - Secure payment processing

- **Admin Features**
  - Manage campsite listings
  - Handle reservations
  - User management
  - Analytics and reporting

## Prerequisites

- PHP 8.x
- MySQL 8.x
- Web server (Apache/Nginx)
- XAMPP/WAMP/LAMP for local development

## Installation

1. Clone the repository to your web server directory:
   ```bash
   git clone https://github.com/yourusername/campnest.git
   ```

2. Create a MySQL database named 'campnest'

3. Import the database schema:
   ```bash
   mysql -u root -p campnest < database/schema.sql
   ```

4. Configure your database connection:
   - Open `config/database.php`
   - Update the database credentials if needed:
     ```php
     define('DB_HOST', 'localhost');
     define('DB_USER', 'your_username');
     define('DB_PASS', 'your_password');
     define('DB_NAME', 'campnest');
     ```

5. Set up your web server:
   - For Apache, ensure mod_rewrite is enabled
   - Point your document root to the project directory
   - Ensure the web server has write permissions for uploads

6. Create required directories:
   ```bash
   mkdir -p assets/images/uploads
   chmod 777 assets/images/uploads
   ```

## Directory Structure

```
campnest/
├── assets/
│   ├── css/
│   ├── js/
│   └── images/
├── config/
│   └── database.php
├── database/
│   └── schema.sql
├── includes/
│   ├── header.php
│   ├── footer.php
│   └── logout.php
├── pages/
│   ├── home.php
│   ├── login.php
│   ├── register.php
│   └── ...
├── index.php
└── README.md
```

## Usage

1. Start your web server and MySQL database
2. Navigate to the project URL in your web browser
3. Register a new account or login with existing credentials
4. For admin access:
   - Create a user account
   - Update the user's role to 'admin' in the database:
     ```sql
     UPDATE users SET role = 'admin' WHERE email = 'admin@example.com';
     ```

## Security Considerations

- All user passwords are hashed using PHP's `password_hash()`
- SQL injection prevention using prepared statements
- XSS protection through output escaping
- CSRF protection for forms
- Input validation and sanitization

## Contributing

1. Fork the repository
2. Create your feature branch: `git checkout -b feature/new-feature`
3. Commit your changes: `git commit -m 'Add new feature'`
4. Push to the branch: `git push origin feature/new-feature`
5. Submit a pull request

## License

This project is licensed under the MIT License - see the LICENSE file for details.

## Support

For support, please email support@campnest.com or open an issue in the GitHub repository. 