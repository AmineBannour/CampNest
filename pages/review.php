<?php
// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php?page=login');
    exit();
}

// Check if booking_id is provided and valid
if (!isset($_GET['booking_id'])) {
    header('Location: index.php?page=profile');
    exit();
}

$booking_id = (int)$_GET['booking_id'];

// Verify the booking belongs to the user and is completed
$stmt = $conn->prepare("
    SELECT b.*, c.name as campsite_name, c.type as campsite_type
    FROM bookings b
    JOIN campsites c ON b.campsite_id = c.id
    WHERE b.id = ? AND b.user_id = ? AND b.status = 'completed'
");
$stmt->execute([$booking_id, $_SESSION['user_id']]);
$booking = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$booking) {
    header('Location: index.php?page=profile');
    exit();
}

// Check if review already exists
$stmt = $conn->prepare("
    SELECT *
    FROM reviews
    WHERE booking_id = ?
");
$stmt->execute([$booking_id]);
$existing_review = $stmt->fetch(PDO::FETCH_ASSOC);

// Handle review submission
if (isset($_POST['submit_review']) && !$existing_review) {
    $rating = filter_input(INPUT_POST, 'rating', FILTER_VALIDATE_INT);
    $title = filter_input(INPUT_POST, 'title', FILTER_SANITIZE_STRING);
    $comment = filter_input(INPUT_POST, 'comment', FILTER_SANITIZE_STRING);
    
    // Validate input
    $errors = [];
    if ($rating < 1 || $rating > 5) {
        $errors[] = "Please select a rating between 1 and 5 stars";
    }
    if (empty($title)) {
        $errors[] = "Please provide a review title";
    }
    if (empty($comment)) {
        $errors[] = "Please provide review comments";
    }
    
    if (empty($errors)) {
        $stmt = $conn->prepare("
            INSERT INTO reviews (booking_id, user_id, campsite_id, rating, title, comment, created_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $booking_id,
            $_SESSION['user_id'],
            $booking['campsite_id'],
            $rating,
            $title,
            $comment
        ]);
        
        // Update campsite average rating
        $stmt = $conn->prepare("
            UPDATE campsites c
            SET average_rating = (
                SELECT AVG(rating)
                FROM reviews
                WHERE campsite_id = c.id
            )
            WHERE id = ?
        ");
        $stmt->execute([$booking['campsite_id']]);
        
        $success = "Thank you for your review!";
    }
}
?>

<div class="container">
    <div class="review-page">
        <?php if (isset($success)): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($success); ?>
                <p class="mt-2">
                    <a href="index.php?page=profile" class="btn btn-primary">Return to Profile</a>
                </p>
            </div>
        <?php else: ?>
            <div class="review-header">
                <h1>Write a Review</h1>
                <p class="stay-details">
                    for your stay at <strong><?php echo htmlspecialchars($booking['campsite_name']); ?></strong>
                    (<?php echo date('M j, Y', strtotime($booking['check_in_date'])); ?> - 
                    <?php echo date('M j, Y', strtotime($booking['check_out_date'])); ?>)
                </p>
            </div>
            
            <?php if (!empty($errors)): ?>
                <div class="alert alert-error">
                    <?php foreach ($errors as $error): ?>
                        <p><?php echo htmlspecialchars($error); ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($existing_review): ?>
                <div class="alert alert-info">
                    <p>You have already submitted a review for this stay.</p>
                    <p class="mt-2">
                        <a href="index.php?page=profile" class="btn btn-primary">Return to Profile</a>
                    </p>
                </div>
            <?php else: ?>
                <form method="POST" action="" class="review-form">
                    <div class="form-group">
                        <label>Rating</label>
                        <div class="star-rating">
                            <?php for ($i = 5; $i >= 1; $i--): ?>
                                <input type="radio" id="star<?php echo $i; ?>" name="rating" value="<?php echo $i; ?>" required>
                                <label for="star<?php echo $i; ?>" title="<?php echo $i; ?> stars">★</label>
                            <?php endfor; ?>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="title">Review Title</label>
                        <input type="text" id="title" name="title" required
                               placeholder="Sum up your experience in a few words"
                               value="<?php echo isset($_POST['title']) ? htmlspecialchars($_POST['title']) : ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="comment">Your Review</label>
                        <textarea id="comment" name="comment" rows="6" required
                                  placeholder="Tell others about your camping experience..."><?php echo isset($_POST['comment']) ? htmlspecialchars($_POST['comment']) : ''; ?></textarea>
                        <small>Your review will help other campers make better decisions</small>
                    </div>
                    
                    <div class="form-actions">
                        <a href="index.php?page=profile" class="btn btn-secondary">Cancel</a>
                        <button type="submit" name="submit_review" class="btn btn-primary">Submit Review</button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.review-page {
    max-width: 800px;
    margin: 2rem auto;
    padding: 2rem;
    background: white;
    border-radius: 0.5rem;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.review-header {
    margin-bottom: 2rem;
    text-align: center;
}

.review-header h1 {
    color: var(--primary-color);
    margin-bottom: 0.5rem;
}

.stay-details {
    color: #666;
}

.review-form {
    max-width: 600px;
    margin: 0 auto;
}

.star-rating {
    display: flex;
    flex-direction: row-reverse;
    justify-content: flex-end;
    gap: 0.25rem;
}

.star-rating input {
    display: none;
}

.star-rating label {
    font-size: 2rem;
    color: #ddd;
    cursor: pointer;
    transition: color 0.2s ease-in-out;
}

.star-rating label:hover,
.star-rating label:hover ~ label,
.star-rating input:checked ~ label {
    color: #ffd700;
}

.form-group {
    margin-bottom: 1.5rem;
}

.form-group label {
    display: block;
    margin-bottom: 0.5rem;
    color: #333;
    font-weight: 500;
}

.form-group input[type="text"],
.form-group textarea {
    width: 100%;
    padding: 0.75rem;
    border: 1px solid #ddd;
    border-radius: 0.25rem;
    font-size: 1rem;
}

.form-group textarea {
    resize: vertical;
}

.form-group small {
    display: block;
    margin-top: 0.25rem;
    color: #666;
    font-size: 0.875rem;
}

.form-actions {
    display: flex;
    justify-content: flex-end;
    gap: 1rem;
    margin-top: 2rem;
}

.alert {
    text-align: center;
}

.mt-2 {
    margin-top: 1rem;
}

@media (max-width: 768px) {
    .review-page {
        padding: 1rem;
        margin: 1rem;
    }
    
    .star-rating label {
        font-size: 1.75rem;
    }
}
</style> 