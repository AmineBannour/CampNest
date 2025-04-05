<?php
// Get campsite ID from URL
$campsite_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Fetch campsite details
$stmt = $conn->prepare("SELECT * FROM campsites WHERE id = ?");
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
    <?php
    // Fetch reviews for this campsite
    $stmt = $conn->prepare("
        SELECT r.*, u.first_name, u.last_name
        FROM reviews r
        JOIN users u ON r.user_id = u.id
        WHERE r.campsite_id = ?
        ORDER BY r.created_at DESC
    ");
    $stmt->execute([$campsite['id']]);
    $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);
    ?>
    
    <div class="reviews-summary">
        <div class="rating-overview">
            <div class="average-rating">
                <span class="rating-number"><?php echo number_format($campsite['average_rating'], 1); ?></span>
                <div class="stars">
                    <?php
                    $rating = round($campsite['average_rating'] * 2) / 2;
                    for ($i = 1; $i <= 5; $i++) {
                        if ($rating >= $i) {
                            echo '<span class="star full">★</span>';
                        } elseif ($rating > $i - 0.5) {
                            echo '<span class="star half">★</span>';
                        } else {
                            echo '<span class="star empty">★</span>';
                        }
                    }
                    ?>
                </div>
                <span class="review-count"><?php echo count($reviews); ?> reviews</span>
            </div>
            
            <div class="rating-bars">
                <?php
                $ratings = array_count_values(array_column($reviews, 'rating'));
                for ($i = 5; $i >= 1; $i--) {
                    $count = isset($ratings[$i]) ? $ratings[$i] : 0;
                    $percentage = count($reviews) > 0 ? ($count / count($reviews) * 100) : 0;
                ?>
                <div class="rating-bar">
                    <span class="stars"><?php echo $i; ?> stars</span>
                    <div class="bar-container">
                        <div class="bar" style="width: <?php echo $percentage; ?>%"></div>
                    </div>
                    <span class="count"><?php echo $count; ?></span>
                </div>
                <?php } ?>
            </div>
        </div>
    </div>
    
    <div class="reviews-list">
        <?php if (empty($reviews)): ?>
            <p class="no-reviews">No reviews yet. Be the first to review this campsite!</p>
        <?php else: ?>
            <?php foreach ($reviews as $review): ?>
                <div class="review-card">
                    <div class="review-header">
                        <div class="reviewer-info">
                            <span class="reviewer-name">
                                <?php echo htmlspecialchars($review['first_name'] . ' ' . substr($review['last_name'], 0, 1) . '.'); ?>
                            </span>
                            <span class="review-date">
                                <?php echo date('F Y', strtotime($review['created_at'])); ?>
                            </span>
                        </div>
                        <div class="review-rating">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <span class="star <?php echo $i <= $review['rating'] ? 'full' : 'empty'; ?>">★</span>
                            <?php endfor; ?>
                        </div>
                    </div>
                    
                    <h3 class="review-title"><?php echo htmlspecialchars($review['title']); ?></h3>
                    <p class="review-comment"><?php echo nl2br(htmlspecialchars($review['comment'])); ?></p>
                </div>
            <?php endforeach; ?>
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
    margin-top: 3rem;
    padding-top: 2rem;
    border-top: 1px solid #eee;
}

.reviews-summary {
    background: white;
    padding: 1.5rem;
    border-radius: 0.5rem;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    margin-bottom: 2rem;
}

.rating-overview {
    display: grid;
    grid-template-columns: auto 1fr;
    gap: 2rem;
    align-items: center;
}

.average-rating {
    text-align: center;
}

.rating-number {
    font-size: 3rem;
    font-weight: 600;
    color: var(--primary-color);
    line-height: 1;
}

.stars {
    margin: 0.5rem 0;
    color: #ffd700;
    font-size: 1.25rem;
}

.star {
    margin: 0 1px;
}

.star.empty {
    color: #ddd;
}

.star.half {
    position: relative;
    display: inline-block;
}

.star.half:after {
    content: '★';
    position: absolute;
    left: 0;
    top: 0;
    width: 50%;
    overflow: hidden;
    color: #ffd700;
}

.review-count {
    color: #666;
    font-size: 0.875rem;
}

.rating-bars {
    display: grid;
    gap: 0.5rem;
}

.rating-bar {
    display: grid;
    grid-template-columns: 60px 1fr 40px;
    align-items: center;
    gap: 1rem;
    font-size: 0.875rem;
}

.bar-container {
    height: 8px;
    background: #eee;
    border-radius: 4px;
    overflow: hidden;
}

.bar {
    height: 100%;
    background: var(--primary-color);
    border-radius: 4px;
    transition: width 0.3s ease;
}

.reviews-list {
    display: grid;
    gap: 1rem;
}

.review-card {
    background: white;
    padding: 1.5rem;
    border-radius: 0.5rem;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.review-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 1rem;
}

.reviewer-info {
    display: flex;
    flex-direction: column;
}

.reviewer-name {
    font-weight: 500;
}

.review-date {
    color: #666;
    font-size: 0.875rem;
}

.review-title {
    font-size: 1.125rem;
    margin-bottom: 0.5rem;
    color: var(--primary-color);
}

.review-comment {
    color: #333;
    line-height: 1.5;
}

.no-reviews {
    text-align: center;
    color: #666;
    padding: 2rem;
    background: white;
    border-radius: 0.5rem;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

@media (max-width: 768px) {
    .rating-overview {
        grid-template-columns: 1fr;
        text-align: center;
    }
    
    .rating-bars {
        max-width: 400px;
        margin: 0 auto;
    }
    
    .review-header {
        flex-direction: column;
        gap: 0.5rem;
    }
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