<?php
// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php?page=login');
    exit();
}

// Get booking ID from URL
$booking_id = isset($_GET['booking_id']) ? (int)$_GET['booking_id'] : 0;

// Verify booking exists and belongs to user
$stmt = $conn->prepare("
    SELECT b.*, c.name as campsite_name, c.id as campsite_id
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
$stmt = $conn->prepare("SELECT id FROM reviews WHERE booking_id = ?");
$stmt->execute([$booking_id]);
if ($stmt->fetch()) {
    header('Location: index.php?page=campsite&id=' . $booking['campsite_id']);
    exit();
}

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rating = filter_input(INPUT_POST, 'rating', FILTER_VALIDATE_INT);
    $title = filter_input(INPUT_POST, 'title', FILTER_SANITIZE_STRING);
    $comment = filter_input(INPUT_POST, 'comment', FILTER_SANITIZE_STRING);
    
    // Debug information
    error_log("Review submission attempt - Rating: $rating, Title: $title, Comment length: " . strlen($comment));
    
    // Validation
    if (!$rating || $rating < 1 || $rating > 5) {
        $errors[] = "Please select a valid rating";
    }
    
    if (empty($title)) {
        $errors[] = "Please enter a title for your review";
    }
    
    if (empty($comment)) {
        $errors[] = "Please enter your review comment";
    }
    
    if (empty($errors)) {
        try {
            $conn->beginTransaction();
            
            // Insert review
            $stmt = $conn->prepare("
                INSERT INTO reviews (booking_id, user_id, campsite_id, rating, title, comment, created_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            
            // Debug the values being inserted
            error_log("Attempting to insert review with values: " . 
                     "booking_id=$booking_id, " .
                     "user_id={$_SESSION['user_id']}, " .
                     "campsite_id={$booking['campsite_id']}, " .
                     "rating=$rating, " .
                     "title='$title'");
            
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
                    SELECT COALESCE(AVG(rating), 0)
                    FROM reviews
                    WHERE campsite_id = c.id
                )
                WHERE c.id = ?
            ");
            $stmt->execute([$booking['campsite_id']]);
            
            $conn->commit();
            $success = true;
            
            // Redirect to campsite page after successful submission
            header('Location: index.php?page=campsite&id=' . $booking['campsite_id']);
            exit();
            
        } catch (PDOException $e) {
            $conn->rollBack();
            $errors[] = "Database error: " . $e->getMessage();
            error_log("Review submission error: " . $e->getMessage());
            error_log("SQL State: " . $e->getCode());
            error_log("Error Info: " . print_r($stmt->errorInfo(), true));
        } catch (Exception $e) {
            $conn->rollBack();
            $errors[] = "An unexpected error occurred: " . $e->getMessage();
            error_log("Unexpected error: " . $e->getMessage());
        }
    }
}
?>

<div class="container">
    <div class="review-form-container">
        <h1>Write a Review</h1>
        <p class="campsite-name"><?php echo htmlspecialchars($booking['campsite_name']); ?></p>
        
        <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <?php foreach ($errors as $error): ?>
                    <p><?php echo htmlspecialchars($error); ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="" class="review-form">
            <div class="form-group">
                <label>Rating</label>
                <div class="star-rating">
                    <?php for ($i = 5; $i >= 1; $i--): ?>
                        <input type="radio" id="star<?php echo $i; ?>" name="rating" value="<?php echo $i; ?>" required
                               class="star-input" aria-label="<?php echo $i; ?> stars">
                        <label for="star<?php echo $i; ?>" class="star-label">★</label>
                    <?php endfor; ?>
                </div>
            </div>
            
            <div class="form-group">
                <label for="title">Title</label>
                <input type="text" id="title" name="title" required 
                       placeholder="Summarize your experience"
                       value="<?php echo isset($_POST['title']) ? htmlspecialchars($_POST['title']) : ''; ?>">
            </div>
            
            <div class="form-group">
                <label for="comment">Your Review</label>
                <textarea id="comment" name="comment" rows="6" required
                          placeholder="Tell us about your experience at this campsite"><?php echo isset($_POST['comment']) ? htmlspecialchars($_POST['comment']) : ''; ?></textarea>
            </div>
            
            <button type="submit" class="btn btn-primary">Submit Review</button>
        </form>
    </div>
</div>

<style>
.review-form-container {
    max-width: 600px;
    margin: 2rem auto;
    padding: 2rem;
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.review-form-container h1 {
    color: #2c3e50;
    margin-bottom: 0.5rem;
}

.campsite-name {
    color: #7f8c8d;
    margin-bottom: 2rem;
}

.star-rating {
    display: flex;
    flex-direction: row-reverse;
    justify-content: flex-end;
    gap: 0.5rem;
    position: relative;
}

.star-input {
    position: absolute;
    opacity: 0;
    width: 0;
    height: 0;
}

.star-label {
    font-size: 2rem;
    color: #ddd;
    cursor: pointer;
    transition: color 0.2s ease;
    padding: 0 0.25rem;
}

.star-label:hover,
.star-label:hover ~ .star-label,
.star-input:checked ~ .star-label {
    color: #f1c40f;
}

.form-group {
    margin-bottom: 1.5rem;
}

.form-group label {
    display: block;
    margin-bottom: 0.5rem;
    color: #2c3e50;
    font-weight: 500;
}

.form-group input[type="text"],
.form-group textarea {
    width: 100%;
    padding: 0.75rem;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 1rem;
}

.form-group textarea {
    resize: vertical;
}

.btn-primary {
    width: 100%;
    padding: 1rem;
    font-size: 1.1rem;
}

.alert {
    padding: 1rem;
    margin-bottom: 1.5rem;
    border-radius: 4px;
}

.alert-error {
    background: #fee2e2;
    color: #991b1b;
    border: 1px solid #fecaca;
}

@media (max-width: 768px) {
    .review-form-container {
        margin: 1rem;
        padding: 1.5rem;
    }
    
    .star-label {
        font-size: 1.75rem;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const starInputs = document.querySelectorAll('.star-input');
    const starLabels = document.querySelectorAll('.star-label');
    
    starLabels.forEach((label, index) => {
        label.addEventListener('mouseover', function() {
            this.style.color = '#f1c40f';
            let prev = this.nextElementSibling;
            while (prev) {
                prev.style.color = '#f1c40f';
                prev = prev.nextElementSibling;
            }
        });
        
        label.addEventListener('mouseout', function() {
            const checkedInput = document.querySelector('.star-input:checked');
            if (!checkedInput) {
                this.style.color = '#ddd';
                let prev = this.nextElementSibling;
                while (prev) {
                    prev.style.color = '#ddd';
                    prev = prev.nextElementSibling;
                }
            }
        });
    });
    
    // Ensure at least one star is selected
    document.querySelector('.review-form').addEventListener('submit', function(e) {
        const rating = document.querySelector('input[name="rating"]:checked');
        if (!rating) {
            e.preventDefault();
            alert('Please select a rating');
        }
    });
});
</script> 