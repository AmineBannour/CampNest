<?php
// Handle search parameters
$check_in = isset($_GET['check_in']) ? $_GET['check_in'] : '';
$check_out = isset($_GET['check_out']) ? $_GET['check_out'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Base query
$query = "SELECT * FROM campsites WHERE 1=1";
$params = [];

// Add search conditions
if (!empty($search)) {
    $query .= " AND (name LIKE ? OR description LIKE ? OR type LIKE ?)";
    $searchTerm = "%$search%";
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
}

// If dates are selected, check availability
if (!empty($check_in) && !empty($check_out)) {
    $query .= " AND id NOT IN (
        SELECT campsite_id FROM bookings 
        WHERE (check_in_date <= ? AND check_out_date >= ?)
        AND status != 'cancelled'
    )";
    $params = array_merge($params, [$check_out, $check_in]);
}

// Execute query
$stmt = $conn->prepare($query);
$stmt->execute($params);
$campsites = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container">
    <div class="search-section">
        <h1>Available Campsites</h1>
        <form method="GET" action="" class="search-filters">
            <input type="hidden" name="page" value="campsites">
            
            <div class="filters">
                <div class="form-group">
                    <label for="check_in">Check In</label>
                    <input type="date" id="check_in" name="check_in" 
                           value="<?php echo htmlspecialchars($check_in); ?>"
                           min="<?php echo date('Y-m-d'); ?>">
                </div>
                
                <div class="form-group">
                    <label for="check_out">Check Out</label>
                    <input type="date" id="check_out" name="check_out"
                           value="<?php echo htmlspecialchars($check_out); ?>"
                           min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>">
                </div>
                
                <div class="form-group">
                    <label for="search">Search</label>
                    <input type="text" id="search" name="search" 
                           placeholder="Search by name or type"
                           value="<?php echo htmlspecialchars($search); ?>">
                </div>
                
                <button type="submit" class="btn btn-primary">Search</button>
            </div>
        </form>
    </div>

    <div class="campsite-grid">
        <?php if (empty($campsites)): ?>
            <div class="no-results">
                <h2>No campsites found</h2>
                <p>Try adjusting your search criteria or dates.</p>
            </div>
        <?php else: ?>
            <?php foreach ($campsites as $campsite): ?>
                <div class="campsite-card">
                    <div class="campsite-image">
                        <img src="<?php echo htmlspecialchars($campsite['image_url'] ?: 'assets/images/default-campsite.jpg'); ?>" 
                             alt="<?php echo htmlspecialchars($campsite['name']); ?>">
                    </div>
                    <div class="campsite-info">
                        <h3><?php echo htmlspecialchars($campsite['name']); ?></h3>
                        <p class="campsite-type"><?php echo htmlspecialchars($campsite['type']); ?></p>
                        <p class="campsite-description">
                            <?php echo htmlspecialchars(substr($campsite['description'], 0, 100)) . '...'; ?>
                        </p>
                        <div class="campsite-details">
                            <p class="campsite-price">
                                $<?php echo number_format($campsite['price_per_night'], 2); ?> / night
                            </p>
                            <p class="campsite-capacity">
                                <i class="capacity-icon">👥</i> Up to <?php echo $campsite['capacity']; ?> people
                            </p>
                        </div>
                        <div class="campsite-amenities">
                            <?php 
                            $amenities = json_decode($campsite['amenities'], true);
                            if ($amenities) {
                                foreach (array_slice($amenities, 0, 3) as $amenity): ?>
                                    <span class="amenity-tag"><?php echo htmlspecialchars($amenity); ?></span>
                                <?php endforeach;
                                if (count($amenities) > 3) {
                                    echo '<span class="amenity-tag">+' . (count($amenities) - 3) . ' more</span>';
                                }
                            }
                            ?>
                        </div>
                        <a href="index.php?page=campsite&id=<?php echo $campsite['id']; ?>" 
                           class="btn btn-primary">View Details</a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<style>
.search-section {
    margin-bottom: 2rem;
}

.search-section h1 {
    color: var(--primary-color);
    margin-bottom: 1.5rem;
}

.search-filters {
    background: white;
    padding: 1.5rem;
    border-radius: 0.5rem;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.filters {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    align-items: end;
}

.campsite-description {
    color: #666;
    margin: 0.5rem 0;
    line-height: 1.4;
}

.campsite-details {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin: 1rem 0;
}

.capacity-icon {
    font-style: normal;
}

.campsite-amenities {
    margin: 1rem 0;
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.amenity-tag {
    background: var(--secondary-color);
    padding: 0.25rem 0.75rem;
    border-radius: 1rem;
    font-size: 0.875rem;
    color: var(--text-color);
}

.no-results {
    text-align: center;
    padding: 3rem;
    background: white;
    border-radius: 0.5rem;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.no-results h2 {
    color: var(--primary-color);
    margin-bottom: 1rem;
}

@media (max-width: 768px) {
    .filters {
        grid-template-columns: 1fr;
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