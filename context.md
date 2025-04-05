# 🏕️ CampNest Reservation System

## 1. Project Breakdown

**App Name:** CampNest  
**Platform:** Web Application  
**Summary:**  
CampNest is a full-featured camping reservation system designed to simplify campsite discovery, booking, and management. It allows users to find and reserve campsites with optional services (gear rentals, activities), and provides admins with tools to manage inventory, bookings, and promotions.  

### Primary Use Case:
- **Clients:**
  - Browse campsites by check in check ou date
  - Book stays and add-ons
  - Receive confirmation and reminder emails
  - Submit post-stay reviews

- **Admins:**
  - Manage campsite inventory
  - Adjust pricing and apply promotions
  - Monitor reservations and user feedback

- **Super Admins:**
  - Manage user roles and permissions
  - Audit system activity

### Authentication Requirements:
- **Tiered Roles:**
  - Clients: Email/password login system using PHP sessions
  - Admins: Login with elevated privileges (role stored in database)
  - Super Admins: Manually assigned role, with full backend access

---

## 2. Tech Stack Overview

- **Frontend:** HTML5, CSS3, JavaScript
- **Backend:** PHP 8.x
- **Database:** MySQL 8.x
- **Deployment:** Apache/Nginx web server (e.g., XAMPP/LAMP for local dev)

---

## 3. Core Features

### BF1: User Authentication
- Registration and login using PHP sessions
- Passwords hashed with `password_hash()`
- Role-based access for different user types

### BF2: Campsite Management (Admin)
- Admin dashboard to create/edit/delete campsites
- Fields: Name, type, price, capacity, description, amenities
- Image upload (stored in filesystem or DB reference)

### BF3: Booking Flow
- Date selector and availability checker via JavaScript
- Clients choose campsite, services, and dates
- Booking stored in database, confirmation email sent via PHP `mail()` or SMTP

### BF4: Add-on Services & Promotions
- Clients can add gear rentals, activities, etc.
- Admins create/edit services from dashboard
- Discount codes or time-limited promotions applied at checkout

### BF5: Reservation Calendar
- Admin view with arrival/departure color-coded calendar
- Implemented with JavaScript calendar libraries (e.g., FullCalendar)
- Backend: Fetch and display bookings dynamically

### BF6: Reviews System
- Clients can leave ratings and comments post-stay
- Admins can moderate reviews before public display

---

## 4. User Flow

### Client Journey:
1. **Browse Campsites** – List view with filters (date, amenities)
2. **View Details** – Click to see full description, gallery, map
3. **Booking Process** – Multi-step form with personal info, date, add-ons
4. **Confirmation** – Receive email + dashboard view of upcoming trip
5. **Review Submission** – Prompted after trip ends

### Admin Journey:
1. **Login to Admin Panel**
2. **Manage Listings** – Add/edit/delete campsites and services
3. **Monitor Bookings** – View calendar, sort by date or status
4. **Moderate Reviews** – Approve or remove inappropriate feedback
5. **Promotions** – Set discounts or flash offers

---

## 5. Design & UI/UX Guidelines

### Visual Identity:
- **Primary Color:** Forest Green `#047857`
- **Text Color:** Dark Slate `#1C1917`
- **Font:** Inter or sans-serif fallback
- **Cards:** Clean, bordered sections for each campsite
- **Tooltips:** JS-based hover tooltips for amenities

### UX Features:
- **Booking Form:** Multi-step, validates on each page using JS
- **Mobile-Responsive:** Flexbox/Grid-based layout
- **Accessibility:** Use semantic HTML, labels, and keyboard navigation

---

## 6. Technical Implementation

### Frontend (HTML/CSS/JS):
- Page routing using URL parameters and PHP includes
- JavaScript used for calendar, filters, and client-side validation
- AJAX used for real-time availability checking and booking submission

### Backend (PHP):
- Modular OOP structure (controllers/models/views) recommended
- Authentication with session-based login
- Email notifications using PHPMailer or built-in `mail()`
- File upload handling for campsite images

### Database (MySQL):
- Tables: `users`, `campsites`, `bookings`, `services`, `reviews`, `roles`, etc.
- Relationships: `users` → `bookings` → `campsites/services`

### Deployment:
- Apache or Nginx with PHP and MySQL
- Configured `.htaccess` for routing clean URLs
- Cron jobs (e.g., for sending reminder emails) via cPanel or CLI

---

## 7. Development Setup

### Tools Required:
- Code Editor: VS Code or PhpStorm
- Local Server: XAMPP, MAMP, or LAMP
- Browser DevTools: For testing and debugging

### Folder Structure Example:
