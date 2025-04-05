<?php
// Fetch featured campsites
$stmt = $conn->query("SELECT * FROM campsites ORDER BY RAND() LIMIT 3");
$featured_campsites = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="hero">
    <div class="hero-content">
        <h1>Find Your Perfect Camping Spot</h1>
        <p>Discover beautiful campsites across the country and book your next outdoor adventure.</p>
        <form action="index.php" method="GET" class="search-form">
            <input type="hidden" name="page" value="campsites">
            <div class="search-inputs">
                <div class="form-group">
                    <label for="check_in">Check In</label>
                    <input type="date" id="check_in" name="check_in" required
                           min="<?php echo date('Y-m-d'); ?>">
                </div>
                <div class="form-group">
                    <label for="check_out">Check Out</label>
                    <input type="date" id="check_out" name="check_out" required
                           min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>">
                </div>
                <button type="submit" class="btn btn-primary">Search Campsites</button>
            </div>
        </form>
    </div>
</div>

<div class="container">
    <section class="featured-campsites">
        <h2>Featured Campsites</h2>
        <div class="campsite-grid">
            <?php foreach ($featured_campsites as $campsite): ?>
                <div class="campsite-card">
                    <div class="campsite-image">
                        <img src="<?php echo htmlspecialchars($campsite['image_url']); ?>" 
                             alt="<?php echo htmlspecialchars($campsite['name']); ?>">
                    </div>
                    <div class="campsite-info">
                        <h3><?php echo htmlspecialchars($campsite['name']); ?></h3>
                        <p class="campsite-type"><?php echo htmlspecialchars($campsite['type']); ?></p>
                        <p class="campsite-price"><?php echo number_format($campsite['price_per_night'], 2); ?> TND / night</p>
                        <a href="index.php?page=campsites&id=<?php echo $campsite['id']; ?>" 
                           class="btn btn-primary">View Details</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
    
    <section class="features">
        <h2>Why Choose CampNest?</h2>
        <div class="features-grid">
            <div class="feature">
                <i class="feature-icon">🏕️</i>
                <h3>Handpicked Locations</h3>
                <p>We carefully select the best camping spots for your perfect outdoor experience.</p>
            </div>
            <div class="feature">
                <i class="feature-icon">🎯</i>
                <h3>Easy Booking</h3>
                <p>Simple and secure booking process with instant confirmation.</p>
            </div>
            <div class="feature">
                <i class="feature-icon">🎒</i>
                <h3>Equipment Rentals</h3>
                <p>Don't have gear? Rent everything you need for your camping trip.</p>
            </div>
        </div>
    </section>
</div>

<style>
.hero {
    background: linear-gradient(rgba(0, 0, 0, 0.5), rgba(0, 0, 0, 0.5)),
                url('assets/images/hero-bg.jpg') center/cover;
    color: white;
    padding: 4rem 2rem;
    text-align: center;
}

.hero-content {
    max-width: 800px;
    margin: 0 auto;
}

.hero h1 {
    font-size: 3rem;
    margin-bottom: 1rem;
}

.hero p {
    font-size: 1.25rem;
    margin-bottom: 2rem;
}

.search-form {
    background: white;
    padding: 2rem;
    border-radius: 0.5rem;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.search-inputs {
    display: flex;
    gap: 1rem;
    align-items: flex-end;
}

.search-inputs .form-group {
    flex: 1;
}

.search-inputs label {
    color: var(--text-color);
}

.campsite-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 2rem;
    margin: 2rem 0;
}

.campsite-card {
    background: white;
    border-radius: 0.5rem;
    overflow: hidden;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    transition: transform 0.3s ease;
}

.campsite-card:hover {
    transform: translateY(-5px);
}

.campsite-image {
    height: 200px;
    overflow: hidden;
}

.campsite-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.campsite-info {
    padding: 1.5rem;
}

.campsite-type {
    color: #666;
    margin-bottom: 0.5rem;
}

.campsite-price {
    font-size: 1.25rem;
    font-weight: bold;
    color: var(--primary-color);
    margin-bottom: 1rem;
}

.features {
    margin: 4rem 0;
}

.features-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 2rem;
    margin-top: 2rem;
}

.feature {
    text-align: center;
    padding: 2rem;
    background: white;
    border-radius: 0.5rem;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.feature-icon {
    font-size: 2.5rem;
    margin-bottom: 1rem;
    display: block;
}

h2 {
    text-align: center;
    color: var(--primary-color);
    margin-bottom: 2rem;
}

@media (max-width: 768px) {
    .search-inputs {
        flex-direction: column;
    }
    
    .hero h1 {
        font-size: 2rem;
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