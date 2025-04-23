<?php
// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php?page=login');
    exit();
}

// Fetch user details
$stmt = $conn->prepare("
    SELECT id, email, first_name, last_name, role, created_at 
    FROM users 
    WHERE id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Fetch user's bookings
$stmt = $conn->prepare("
    SELECT b.*, c.name as campsite_name, c.type as campsite_type
    FROM bookings b
    JOIN campsites c ON b.campsite_id = c.id
    WHERE b.user_id = ?
    ORDER BY b.check_in_date DESC
");
$stmt->execute([$_SESSION['user_id']]);
$bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle booking cancellation
if (isset($_POST['cancel_booking'])) {
    $booking_id = (int)$_POST['booking_id'];
    
    // Verify booking belongs to user and is cancellable
    $stmt = $conn->prepare("
        SELECT status 
        FROM bookings 
        WHERE id = ? AND user_id = ? AND status IN ('pending', 'confirmed')
    ");
    $stmt->execute([$booking_id, $_SESSION['user_id']]);
    
    if ($stmt->fetch()) {
        $stmt = $conn->prepare("UPDATE bookings SET status = 'cancelled' WHERE id = ?");
        $stmt->execute([$booking_id]);
        header('Location: index.php?page=profile&cancel_success=1');
        exit();
    }
}

// Handle profile update
if (isset($_POST['update_profile'])) {
    $first_name = filter_input(INPUT_POST, 'first_name', FILTER_SANITIZE_STRING);
    $last_name = filter_input(INPUT_POST, 'last_name', FILTER_SANITIZE_STRING);
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    $errors = [];
    
    // Validate current password if trying to change password
    if (!empty($new_password)) {
        $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $stored_hash = $stmt->fetchColumn();
        
        if (!password_verify($current_password, $stored_hash)) {
            $errors[] = "Current password is incorrect";
        } elseif (strlen($new_password) < 8) {
            $errors[] = "New password must be at least 8 characters long";
        } elseif ($new_password !== $confirm_password) {
            $errors[] = "New passwords do not match";
        }
    }
    
    if (empty($errors)) {
        if (!empty($new_password)) {
            // Update name and password
            $stmt = $conn->prepare("
                UPDATE users 
                SET first_name = ?, last_name = ?, password = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $first_name,
                $last_name,
                password_hash($new_password, PASSWORD_DEFAULT),
                $_SESSION['user_id']
            ]);
        } else {
            // Update only name
            $stmt = $conn->prepare("
                UPDATE users 
                SET first_name = ?, last_name = ?
                WHERE id = ?
            ");
            $stmt->execute([$first_name, $last_name, $_SESSION['user_id']]);
        }
        
        $success = "Profile updated successfully";
    }
}
?>

<div class="container">
    <div class="profile-page">
        <?php if (isset($_GET['booking_success'])): ?>
            <div class="alert alert-success">
                Your booking has been confirmed! You can view the details below.
            </div>
        <?php endif; ?>
        
        <?php if (isset($_GET['cancel_success'])): ?>
            <div class="alert alert-success">
                Your booking has been cancelled successfully.
            </div>
        <?php endif; ?>
        
        <?php if (isset($success)): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>
        
        <div class="profile-header">
            <h1>My Profile</h1>
            <p class="member-since">Member since <?php echo date('F Y', strtotime($user['created_at'])); ?></p>
        </div>
        
        <div class="profile-content">
            <div class="profile-sidebar">
                <div class="profile-card">
                    <h2>Account Settings</h2>
                    
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-error">
                            <?php foreach ($errors as $error): ?>
                                <p><?php echo htmlspecialchars($error); ?></p>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="" class="profile-form">
                        <div class="form-group">
                            <label for="first_name">First Name</label>
                            <input type="text" id="first_name" name="first_name" required
                                   value="<?php echo htmlspecialchars($user['first_name']); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="last_name">Last Name</label>
                            <input type="text" id="last_name" name="last_name" required
                                   value="<?php echo htmlspecialchars($user['last_name']); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="email">Email</label>
                            <input type="email" id="email" value="<?php echo htmlspecialchars($user['email']); ?>" disabled>
                            <small>Email cannot be changed</small>
                        </div>
                        
                        <h3>Change Password</h3>
                        <div class="form-group">
                            <label for="current_password">Current Password</label>
                            <input type="password" id="current_password" name="current_password">
                        </div>
                        
                        <div class="form-group">
                            <label for="new_password">New Password</label>
                            <input type="password" id="new_password" name="new_password">
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm_password">Confirm New Password</label>
                            <input type="password" id="confirm_password" name="confirm_password">
                        </div>
                        
                        <button type="submit" name="update_profile" class="btn btn-primary">Update Profile</button>
                    </form>
                </div>
            </div>
            
            <div class="profile-main">
                <div class="bookings-section">
                    <h2>My Bookings</h2>
                    
                    <?php if (empty($bookings)): ?>
                        <div class="no-bookings">
                            <p>You haven't made any bookings yet.</p>
                            <a href="index.php?page=campsites" class="btn btn-primary">Browse Campsites</a>
                        </div>
                    <?php else: ?>
                        <div class="bookings-grid">
                            <?php foreach ($bookings as $booking): ?>
                                <div class="booking-card">
                                    <div class="booking-header">
                                        <h3><?php echo htmlspecialchars($booking['campsite_name']); ?></h3>
                                        <span class="booking-status <?php echo $booking['status']; ?>">
                                            <?php echo ucfirst($booking['status']); ?>
                                        </span>
                                    </div>
                                    
                                    <div class="booking-details">
                                        <div class="booking-dates">
                                            <div class="date-group">
                                                <label>Check In</label>
                                                <p><?php echo date('M j, Y', strtotime($booking['check_in_date'])); ?></p>
                                            </div>
                                            <div class="date-group">
                                                <label>Check Out</label>
                                                <p><?php echo date('M j, Y', strtotime($booking['check_out_date'])); ?></p>
                                            </div>
                                        </div>
                                        
                                        <div class="booking-info">
                                            <p class="campsite-type"><?php echo htmlspecialchars($booking['campsite_type']); ?></p>
                                            <p class="booking-price">Total: <?php echo number_format($booking['total_price'], 2); ?> TND</p>
                                        </div>
                                        
                                        <?php if ($booking['status'] === 'completed'): ?>
                                            <a href="index.php?page=review&booking_id=<?php echo $booking['id']; ?>" 
                                               class="btn btn-secondary">Write Review</a>
                                        <?php endif; ?>
                                        
                                        <?php if (in_array($booking['status'], ['pending', 'confirmed'])): ?>
                                            <form method="POST" action="" class="cancel-form"
                                                  onsubmit="return confirm('Are you sure you want to cancel this booking?');">
                                                <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                                <button type="submit" name="cancel_booking" class="btn btn-danger">Cancel Booking</button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.profile-page {
    margin: 2rem 0;
}

.profile-header {
    margin-bottom: 2rem;
}

.profile-header h1 {
    color: var(--primary-color);
    margin-bottom: 0.5rem;
}

.member-since {
    color: #666;
}

.profile-content {
    display: grid;
    grid-template-columns: 300px 1fr;
    gap: 2rem;
}

.profile-card {
    background: white;
    padding: 1.5rem;
    border-radius: 0.5rem;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.profile-form h3 {
    margin: 1.5rem 0 1rem;
    color: var(--primary-color);
}

.profile-form small {
    color: #666;
    font-size: 0.875rem;
}

.bookings-grid {
    display: grid;
    gap: 1rem;
}

.booking-card {
    background: white;
    padding: 1.5rem;
    border-radius: 0.5rem;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.booking-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
}

.booking-status {
    padding: 0.25rem 0.75rem;
    border-radius: 1rem;
    font-size: 0.875rem;
    font-weight: 500;
}

.booking-status.pending {
    background: #fef3c7;
    color: #92400e;
}

.booking-status.confirmed {
    background: #ecfdf5;
    color: #065f46;
}

.booking-status.cancelled {
    background: #fef2f2;
    color: #991b1b;
}

.booking-status.completed {
    background: #eff6ff;
    color: #1e40af;
}

.booking-dates {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
    margin-bottom: 1rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid #eee;
}

.date-group label {
    color: #666;
    font-size: 0.875rem;
    display: block;
    margin-bottom: 0.25rem;
}

.booking-info {
    margin-bottom: 1rem;
}

.campsite-type {
    color: #666;
    margin-bottom: 0.5rem;
}

.booking-price {
    color: var(--primary-color);
    font-weight: 500;
}

.cancel-form {
    margin-top: 1rem;
}

.btn-danger {
    background-color: #ef4444;
    color: white;
}

.btn-danger:hover {
    background-color: #dc2626;
}

.no-bookings {
    background: white;
    padding: 3rem;
    text-align: center;
    border-radius: 0.5rem;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.no-bookings p {
    margin-bottom: 1.5rem;
    color: #666;
}

@media (max-width: 768px) {
    .profile-content {
        grid-template-columns: 1fr;
    }
    
    .booking-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.5rem;
    }
    
    .booking-dates {
        grid-template-columns: 1fr;
    }
}

.booking-card {
    position: relative;
    overflow: hidden;
}

.btn-sm {
    padding: 0.5rem 1rem;
    font-size: 0.875rem;
}

.status-completed {
    color: #10b981;
}

.status-pending {
    color: #f59e0b;
}

.status-cancelled {
    color: #ef4444;
}
</style> 