<?php
// Get campsite ID from URL
$campsite_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Fetch campsite details
$stmt = $conn->prepare("
    SELECT c.*, 
           COALESCE(AVG(r.rating), 0) as average_rating,
           COUNT(r.id) as review_count
    FROM campsites c
    LEFT JOIN reviews r ON c.id = r.campsite_id
    WHERE c.id = ?
    GROUP BY c.id
");
$stmt->execute([$campsite_id]);
$campsite = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$campsite) {
    header('Location: index.php?page=404');
    exit();
}

// Fetch reviews for this campsite
$stmt = $conn->prepare("
    SELECT r.*, u.first_name, u.last_name 
    FROM reviews r 
    JOIN users u ON r.user_id = u.id 
    WHERE r.campsite_id = ? AND r.is_approved = 1 
    ORDER BY r.created_at DESC
");
$stmt->execute([$campsite_id]);
$reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate average rating
$avgRating = 0;
if (!empty($reviews)) {
    $totalRating = array_sum(array_column($reviews, 'rating'));
    $avgRating = round($totalRating / count($reviews), 1);
}

// Check availability if dates are set
$check_in = isset($_GET['check_in']) ? $_GET['check_in'] : '';
$check_out = isset($_GET['check_out']) ? $_GET['check_out'] : '';
$is_available = true;

if ($check_in && $check_out) {
    $stmt = $conn->prepare("
        SELECT COUNT(*) FROM bookings 
        WHERE campsite_id = ? 
        AND check_in_date <= ? 
        AND check_out_date >= ?
        AND status != 'cancelled'
    ");
    $stmt->execute([$campsite_id, $check_out, $check_in]);
    $is_available = $stmt->fetchColumn() == 0;
}

// Check if user has completed stays at this campsite
$can_review = false;
$review_booking_id = null;
if (isset($_SESSION['user_id'])) {
    $stmt = $conn->prepare("
        SELECT b.id
        FROM bookings b
        WHERE b.user_id = ? 
        AND b.campsite_id = ? 
        AND b.status = 'completed'
        AND NOT EXISTS (
            SELECT 1 FROM reviews r WHERE r.booking_id = b.id
        )
        LIMIT 1
    ");
    $stmt->execute([$_SESSION['user_id'], $campsite_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($result) {
        $can_review = true;
        $review_booking_id = $result['id'];
    }
}
?>

<div class="container">
    <div class="campsite-details">
        <div class="campsite-header">
            <div class="campsite-title">
                <h1><?php echo htmlspecialchars($campsite['name']); ?></h1>
                <p class="campsite-type"><?php echo htmlspecialchars($campsite['type']); ?></p>
            </div>
            <div class="campsite-rating">
                <?php if (!empty($reviews)): ?>
                    <div class="rating-stars">
                        <?php
                        for ($i = 1; $i <= 5; $i++) {
                            echo '<span class="star">' . ($i <= $avgRating ? '★' : '☆') . '</span>';
                        }
                        ?>
                    </div>
                    <p><?php echo $avgRating; ?> (<?php echo count($reviews); ?> reviews)</p>
                <?php else: ?>
                    <p>No reviews yet</p>
                <?php endif; ?>
            </div>
        </div>

        <div class="campsite-gallery">
            <img src="<?php echo htmlspecialchars($campsite['image_url'] ?: 'assets/images/default-campsite.jpg'); ?>" 
                 alt="<?php echo htmlspecialchars($campsite['name']); ?>"
                 class="main-image">
        </div>

        <div class="campsite-content">
            <div class="campsite-info">
                <section class="description">
                    <h2>About this campsite</h2>
                    <p><?php echo nl2br(htmlspecialchars($campsite['description'])); ?></p>
                </section>

                <section class="amenities">
                    <h2>Amenities</h2>
                    <div class="amenities-grid">
                        <?php
                        $amenities = json_decode($campsite['amenities'], true);
                        if ($amenities) {
                            foreach ($amenities as $amenity): ?>
                                <div class="amenity-item">
                                    <span class="amenity-icon">✓</span>
                                    <?php echo htmlspecialchars($amenity); ?>
                                </div>
                            <?php endforeach;
                        }
                        ?>
                    </div>
                </section>

                <section class="reviews">
                    <h2>Reviews</h2>
                    <?php if (empty($reviews)): ?>
                        <p>No reviews yet for this campsite.</p>
                    <?php else: ?>
                        <div class="reviews-list">
                            <?php foreach ($reviews as $review): ?>
                                <div class="review-card">
                                    <div class="review-header">
                                        <div class="reviewer-info">
                                            <strong>
                                                <?php echo htmlspecialchars($review['first_name'] . ' ' . $review['last_name'][0] . '.'); ?>
                                            </strong>
                                            <span class="review-date">
                                                <?php echo date('M Y', strtotime($review['created_at'])); ?>
                                            </span>
                                        </div>
                                        <div class="review-rating">
                                            <?php
                                            for ($i = 1; $i <= 5; $i++) {
                                                echo '<span class="star">' . ($i <= $review['rating'] ? '★' : '☆') . '</span>';
                                            }
                                            ?>
                                        </div>
                                    </div>
                                    <p class="review-comment"><?php echo nl2br(htmlspecialchars($review['comment'])); ?></p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>
            </div>

            <div class="booking-sidebar">
                <div class="booking-card">
                    <div class="price-info">
                        <span class="price"><?php echo number_format($campsite['price_per_night'], 2); ?> TND</span>
                        <span class="per-night">per night</span>
                    </div>

                    <form action="index.php?page=booking" method="GET" class="booking-form">
                        <input type="hidden" name="page" value="booking">
                        <input type="hidden" name="campsite_id" value="<?php echo $campsite_id; ?>">
                        
                        <div class="form-group">
                            <label for="check_in">Check In</label>
                            <input type="date" id="check_in" name="check_in" required
                                   value="<?php echo htmlspecialchars($check_in); ?>"
                                   min="<?php echo date('Y-m-d'); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="check_out">Check Out</label>
                            <input type="date" id="check_out" name="check_out" required
                                   value="<?php echo htmlspecialchars($check_out); ?>"
                                   min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>">
                        </div>

                        <?php if ($check_in && $check_out): ?>
                            <?php if ($is_available): ?>
                                <div class="availability available">
                                    <span class="icon">✓</span> Available for selected dates
                                </div>
                            <?php else: ?>
                                <div class="availability unavailable">
                                    <span class="icon">✗</span> Not available for selected dates
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>

                        <button type="submit" class="btn btn-primary btn-block"
                                <?php echo (!$is_available ? 'disabled' : ''); ?>>
                            <?php echo $check_in && $check_out ? 'Book Now' : 'Check Availability'; ?>
                        </button>
                    </form>

                    <div class="capacity-info">
                        <p><i class="capacity-icon">👥</i> Up to <?php echo $campsite['capacity']; ?> people</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="reviews-section">
    <h2>Reviews</h2>
    
    <?php if ($can_review): ?>
        <div class="review-action">
            <a href="index.php?page=review&booking_id=<?php echo $review_booking_id; ?>" class="btn btn-primary">
                Write a Review
            </a>
        </div>
    <?php endif; ?>
    
    <?php
    // Fetch reviews
    $stmt = $conn->prepare("
        SELECT r.*, u.first_name, u.last_name, DATE_FORMAT(r.created_at, '%M %d, %Y') as review_date
        FROM reviews r
        JOIN users u ON r.user_id = u.id
        WHERE r.campsite_id = ?
        ORDER BY r.created_at DESC
    ");
    $stmt->execute([$campsite_id]);
    $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate rating distribution
    $rating_distribution = array_fill(1, 5, 0);
    foreach ($reviews as $review) {
        $rating_distribution[$review['rating']]++;
    }
    ?>
    
    <div class="reviews-summary">
        <div class="average-rating">
            <div class="rating-number"><?php echo number_format($campsite['average_rating'], 1); ?></div>
            <div class="stars">
                <?php
                $full_stars = floor($campsite['average_rating']);
                $half_star = $campsite['average_rating'] - $full_stars >= 0.5;
                
                for ($i = 1; $i <= 5; $i++) {
                    if ($i <= $full_stars) {
                        echo '★';
                    } elseif ($i == $full_stars + 1 && $half_star) {
                        echo '½';
                    } else {
                        echo '☆';
                    }
                }
                ?>
            </div>
            <div class="total-reviews"><?php echo $campsite['review_count']; ?> reviews</div>
        </div>
        
        <div class="rating-bars">
            <?php for ($i = 5; $i >= 1; $i--): ?>
                <div class="rating-bar">
                    <span class="stars"><?php echo str_repeat('★', $i); ?></span>
                    <div class="bar-container">
                        <div class="bar" style="width: <?php echo $campsite['review_count'] > 0 ? ($rating_distribution[$i] / $campsite['review_count'] * 100) : 0; ?>%"></div>
                    </div>
                    <span class="count"><?php echo $rating_distribution[$i]; ?></span>
                </div>
            <?php endfor; ?>
        </div>
    </div>
    
    <div class="reviews-list">
        <?php if (!empty($reviews)): ?>
            <?php foreach ($reviews as $review): ?>
                <div class="review-card">
                    <div class="review-header">
                        <div class="reviewer-name"><?php echo htmlspecialchars($review['first_name'] . ' ' . $review['last_name']); ?></div>
                        <div class="review-date"><?php echo $review['review_date']; ?></div>
                    </div>
                    <div class="review-rating">
                        <?php echo str_repeat('★', $review['rating']) . str_repeat('☆', 5 - $review['rating']); ?>
                    </div>
                    <h3 class="review-title"><?php echo htmlspecialchars($review['title']); ?></h3>
                    <p class="review-comment"><?php echo nl2br(htmlspecialchars($review['comment'])); ?></p>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p class="no-reviews">No reviews yet. Be the first to review this campsite!</p>
        <?php endif; ?>
    </div>
</div>

<style>
.campsite-details {
    margin: 2rem 0;
}

.campsite-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 2rem;
}

.campsite-title h1 {
    color: var(--primary-color);
    margin-bottom: 0.5rem;
}

.campsite-type {
    color: #666;
    font-size: 1.1rem;
}

.rating-stars {
    color: #fbbf24;
    font-size: 1.25rem;
    margin-bottom: 0.25rem;
}

.campsite-gallery {
    margin-bottom: 2rem;
}

.main-image {
    width: 100%;
    height: 400px;
    object-fit: cover;
    border-radius: 0.5rem;
}

.campsite-content {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 2rem;
}

.description, .amenities, .reviews {
    background: white;
    padding: 2rem;
    border-radius: 0.5rem;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    margin-bottom: 2rem;
}

.amenities-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 1rem;
    margin-top: 1rem;
}

.amenity-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.amenity-icon {
    color: var(--primary-color);
}

.reviews-list {
    margin-top: 1rem;
}

.review-card {
    border-bottom: 1px solid #eee;
    padding: 1rem 0;
}

.review-card:last-child {
    border-bottom: none;
}

.review-header {
    display: flex;
    justify-content: space-between;
    margin-bottom: 0.5rem;
}

.review-date {
    color: #666;
    margin-left: 0.5rem;
}

.review-comment {
    line-height: 1.6;
}

.booking-card {
    background: white;
    padding: 2rem;
    border-radius: 0.5rem;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    position: sticky;
    top: 2rem;
}

.price-info {
    text-align: center;
    margin-bottom: 1.5rem;
}

.price {
    font-size: 2rem;
    font-weight: bold;
    color: var(--primary-color);
}

.per-night {
    color: #666;
}

.btn-block {
    width: 100%;
    margin-top: 1rem;
}

.availability {
    padding: 0.75rem;
    border-radius: 0.375rem;
    margin: 1rem 0;
    text-align: center;
}

.available {
    background: #ecfdf5;
    color: #065f46;
}

.unavailable {
    background: #fef2f2;
    color: #991b1b;
}

.capacity-info {
    margin-top: 1rem;
    padding-top: 1rem;
    border-top: 1px solid #eee;
    text-align: center;
    color: #666;
}

@media (max-width: 768px) {
    .campsite-header {
        flex-direction: column;
        gap: 1rem;
    }
    
    .campsite-content {
        grid-template-columns: 1fr;
    }
    
    .main-image {
        height: 300px;
    }
}

.reviews-section {
    margin-top: 40px;
    padding: 20px;
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.reviews-summary {
    display: flex;
    gap: 40px;
    margin-bottom: 30px;
    padding: 20px;
    background: #f8f9fa;
    border-radius: 8px;
}

.average-rating {
    text-align: center;
    min-width: 150px;
}

.rating-number {
    font-size: 48px;
    font-weight: bold;
    color: #2c3e50;
}

.stars {
    color: #f1c40f;
    font-size: 24px;
    margin: 10px 0;
}

.total-reviews {
    color: #7f8c8d;
    font-size: 14px;
}

.rating-bars {
    flex-grow: 1;
}

.rating-bar {
    display: flex;
    align-items: center;
    margin: 5px 0;
}

.bar-container {
    flex-grow: 1;
    height: 8px;
    background: #ecf0f1;
    border-radius: 4px;
    margin: 0 10px;
    overflow: hidden;
}

.bar {
    height: 100%;
    background: #f1c40f;
    border-radius: 4px;
    transition: width 0.3s ease;
}

.count {
    min-width: 30px;
    text-align: right;
    color: #7f8c8d;
    font-size: 14px;
}

.reviews-list {
    margin-top: 30px;
}

.review-card {
    padding: 20px;
    border-bottom: 1px solid #ecf0f1;
}

.review-card:last-child {
    border-bottom: none;
}

.review-header {
    display: flex;
    justify-content: space-between;
    margin-bottom: 10px;
}

.reviewer-name {
    font-weight: bold;
    color: #2c3e50;
}

.review-date {
    color: #7f8c8d;
    font-size: 14px;
}

.review-rating {
    color: #f1c40f;
    margin-bottom: 10px;
}

.review-title {
    font-size: 18px;
    color: #2c3e50;
    margin: 10px 0;
}

.review-comment {
    color: #34495e;
    line-height: 1.6;
}

.no-reviews {
    text-align: center;
    color: #7f8c8d;
    padding: 20px;
}

@media (max-width: 768px) {
    .reviews-summary {
        flex-direction: column;
        gap: 20px;
    }
    
    .average-rating {
        min-width: auto;
    }
}

.review-action {
    margin-bottom: 20px;
    text-align: right;
}

.review-action .btn {
    display: inline-block;
    padding: 10px 20px;
    background-color: #4a90e2;
    color: white;
    text-decoration: none;
    border-radius: 4px;
    transition: background-color 0.2s ease;
}

.review-action .btn:hover {
    background-color: #357abd;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const checkInInput = document.getElementById('check_in');
    const checkOutInput = document.getElementById('check_out');
    
    checkInInput.addEventListener('change', function() {
        const checkInDate = new Date(this.value);
        const minCheckOutDate = new Date(checkInDate);
        minCheckOutDate.setDate(minCheckOutDate.getDate() + 1);
        
        checkOutInput.min = minCheckOutDate.toISOString().split('T')[0];
        if (checkOutInput.value && new Date(checkOutInput.value) <= checkInDate) {
            checkOutInput.value = minCheckOutDate.toISOString().split('T')[0];
        }
    });
});
</script> 