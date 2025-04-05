<?php
// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    header('Location: index.php?page=login');
    exit();
}

// Get parameters
$campsite_id = isset($_GET['campsite_id']) ? (int)$_GET['campsite_id'] : 0;
$check_in = isset($_GET['check_in']) ? $_GET['check_in'] : '';
$check_out = isset($_GET['check_out']) ? $_GET['check_out'] : '';

// Validate dates
if (empty($check_in) || empty($check_out) || empty($campsite_id)) {
    header('Location: index.php?page=campsites');
    exit();
}

// Fetch campsite details
$stmt = $conn->prepare("SELECT * FROM campsites WHERE id = ?");
$stmt->execute([$campsite_id]);
$campsite = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$campsite) {
    header('Location: index.php?page=404');
    exit();
}

// Check availability again
$stmt = $conn->prepare("
    SELECT COUNT(*) FROM bookings 
    WHERE campsite_id = ? 
    AND check_in_date <= ? 
    AND check_out_date >= ?
    AND status != 'cancelled'
");
$stmt->execute([$campsite_id, $check_out, $check_in]);
if ($stmt->fetchColumn() > 0) {
    header('Location: index.php?page=campsite&id=' . $campsite_id . '&error=unavailable');
    exit();
}

// Calculate number of nights
$nights = ceil((strtotime($check_out) - strtotime($check_in)) / (60 * 60 * 24));

// Fetch available services
$stmt = $conn->prepare("SELECT * FROM services WHERE type IN ('gear_rental', 'activity') ORDER BY type, name");
$stmt->execute();
$services = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Group services by type
$grouped_services = [];
foreach ($services as $service) {
    $grouped_services[$service['type']][] = $service;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $conn->beginTransaction();
        
        // Calculate total price
        $total_price = $campsite['price_per_night'] * $nights;
        
        // Create booking
        $stmt = $conn->prepare("
            INSERT INTO bookings (user_id, campsite_id, check_in_date, check_out_date, total_price, status)
            VALUES (?, ?, ?, ?, ?, 'pending')
        ");
        $stmt->execute([
            $_SESSION['user_id'],
            $campsite_id,
            $check_in,
            $check_out,
            $total_price
        ]);
        
        $booking_id = $conn->lastInsertId();
        
        // Add selected services
        if (isset($_POST['services']) && is_array($_POST['services'])) {
            $stmt = $conn->prepare("
                INSERT INTO booking_services (booking_id, service_id, quantity, price)
                VALUES (?, ?, ?, ?)
            ");
            
            foreach ($_POST['services'] as $service_id => $quantity) {
                if ($quantity > 0) {
                    // Get service price
                    $service_stmt = $conn->prepare("SELECT price FROM services WHERE id = ?");
                    $service_stmt->execute([$service_id]);
                    $service_price = $service_stmt->fetchColumn();
                    
                    // Add service to booking
                    $stmt->execute([
                        $booking_id,
                        $service_id,
                        $quantity,
                        $service_price * $quantity
                    ]);
                    
                    // Update total price
                    $total_price += $service_price * $quantity;
                }
            }
            
            // Update booking with final price
            $stmt = $conn->prepare("UPDATE bookings SET total_price = ? WHERE id = ?");
            $stmt->execute([$total_price, $booking_id]);
        }
        
        $conn->commit();
        header('Location: index.php?page=profile&booking_success=1');
        exit();
        
    } catch (Exception $e) {
        $conn->rollBack();
        $error = "Booking failed. Please try again.";
    }
}
?>

<div class="container">
    <div class="booking-page">
        <h1>Complete Your Reservation</h1>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-error">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <div class="booking-content">
            <div class="booking-details">
                <div class="reservation-summary">
                    <h2>Reservation Summary</h2>
                    <div class="summary-card">
                        <div class="campsite-info">
                            <h3><?php echo htmlspecialchars($campsite['name']); ?></h3>
                            <p class="campsite-type"><?php echo htmlspecialchars($campsite['type']); ?></p>
                        </div>
                        
                        <div class="dates-info">
                            <div class="date-group">
                                <label>Check In</label>
                                <p><?php echo date('M j, Y', strtotime($check_in)); ?></p>
                            </div>
                            <div class="date-group">
                                <label>Check Out</label>
                                <p><?php echo date('M j, Y', strtotime($check_out)); ?></p>
                            </div>
                            <div class="nights-info">
                                <label>Duration</label>
                                <p><?php echo $nights; ?> night<?php echo $nights > 1 ? 's' : ''; ?></p>
                            </div>
                        </div>
                        
                        <div class="base-price">
                            <label>Base Price</label>
                            <p><?php echo number_format($campsite['price_per_night'], 2); ?> TND × <?php echo $nights; ?> nights</p>
                            <p class="total"><?php echo number_format($campsite['price_per_night'] * $nights, 2); ?> TND</p>
                        </div>
                    </div>
                </div>
                
                <form method="POST" action="" class="booking-form" id="bookingForm">
                    <?php if (!empty($grouped_services)): ?>
                        <div class="services-section">
                            <h2>Add-on Services</h2>
                            
                            <?php if (!empty($grouped_services['gear_rental'])): ?>
                                <div class="service-category">
                                    <h3>Gear Rentals</h3>
                                    <div class="services-grid">
                                        <?php foreach ($grouped_services['gear_rental'] as $service): ?>
                                            <div class="service-card">
                                                <div class="service-info">
                                                    <h4><?php echo htmlspecialchars($service['name']); ?></h4>
                                                    <p class="service-description">
                                                        <?php echo htmlspecialchars($service['description']); ?>
                                                    </p>
                                                    <p class="service-price">
                                                        <?php echo number_format($service['price'], 2); ?> TND per item
                                                    </p>
                                                </div>
                                                <div class="service-quantity">
                                                    <label for="service_<?php echo $service['id']; ?>">Quantity</label>
                                                    <input type="number" 
                                                           id="service_<?php echo $service['id']; ?>"
                                                           name="services[<?php echo $service['id']; ?>]"
                                                           value="0"
                                                           min="0"
                                                           max="5"
                                                           class="quantity-input"
                                                           data-price="<?php echo $service['price']; ?>">
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($grouped_services['activity'])): ?>
                                <div class="service-category">
                                    <h3>Activities</h3>
                                    <div class="services-grid">
                                        <?php foreach ($grouped_services['activity'] as $service): ?>
                                            <div class="service-card">
                                                <div class="service-info">
                                                    <h4><?php echo htmlspecialchars($service['name']); ?></h4>
                                                    <p class="service-description">
                                                        <?php echo htmlspecialchars($service['description']); ?>
                                                    </p>
                                                    <p class="service-price">
                                                        <?php echo number_format($service['price'], 2); ?> TND per person
                                                    </p>
                                                </div>
                                                <div class="service-quantity">
                                                    <label for="service_<?php echo $service['id']; ?>">Participants</label>
                                                    <input type="number" 
                                                           id="service_<?php echo $service['id']; ?>"
                                                           name="services[<?php echo $service['id']; ?>]"
                                                           value="0"
                                                           min="0"
                                                           max="10"
                                                           class="quantity-input"
                                                           data-price="<?php echo $service['price']; ?>">
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="booking-total">
                        <div class="total-card">
                            <h3>Total</h3>
                            <div class="price-breakdown">
                                <div class="price-row">
                                    <span>Base Price (<?php echo $nights; ?> nights)</span>
                                    <span><?php echo number_format($campsite['price_per_night'] * $nights, 2); ?> TND</span>
                                </div>
                                <div id="servicesBreakdown"></div>
                                <div class="price-row total">
                                    <span>Total</span>
                                    <span id="totalPrice"><?php echo number_format($campsite['price_per_night'] * $nights, 2); ?> TND</span>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary btn-block">Complete Booking</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
.booking-page {
    margin: 2rem 0;
}

.booking-page h1 {
    color: var(--primary-color);
    margin-bottom: 2rem;
}

.booking-content {
    display: grid;
    gap: 2rem;
}

.summary-card,
.service-card,
.total-card {
    background: white;
    padding: 1.5rem;
    border-radius: 0.5rem;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    margin-bottom: 1.5rem;
}

.dates-info {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 1rem;
    margin: 1.5rem 0;
    padding: 1rem 0;
    border-top: 1px solid #eee;
    border-bottom: 1px solid #eee;
}

.date-group label,
.nights-info label {
    color: #666;
    font-size: 0.875rem;
    display: block;
    margin-bottom: 0.25rem;
}

.date-group p,
.nights-info p {
    font-weight: 500;
}

.base-price {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.base-price label {
    color: #666;
}

.base-price .total {
    font-weight: bold;
    color: var(--primary-color);
}

.services-section {
    margin: 2rem 0;
}

.service-category {
    margin-bottom: 2rem;
}

.service-category h3 {
    margin-bottom: 1rem;
    color: var(--primary-color);
}

.services-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 1rem;
}

.service-card {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.service-info {
    flex: 1;
}

.service-info h4 {
    margin-bottom: 0.5rem;
}

.service-description {
    color: #666;
    font-size: 0.875rem;
    margin-bottom: 0.5rem;
}

.service-price {
    color: var(--primary-color);
    font-weight: 500;
}

.service-quantity {
    margin-left: 1rem;
}

.quantity-input {
    width: 80px;
    padding: 0.5rem;
    border: 1px solid #ddd;
    border-radius: 0.375rem;
    text-align: center;
}

.total-card {
    position: sticky;
    top: 2rem;
}

.price-breakdown {
    margin: 1rem 0;
}

.price-row {
    display: flex;
    justify-content: space-between;
    padding: 0.5rem 0;
}

.price-row.total {
    border-top: 2px solid #eee;
    margin-top: 1rem;
    padding-top: 1rem;
    font-weight: bold;
    font-size: 1.25rem;
}

@media (max-width: 768px) {
    .service-card {
        flex-direction: column;
        align-items: stretch;
    }
    
    .service-quantity {
        margin: 1rem 0 0;
    }
    
    .quantity-input {
        width: 100%;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const bookingForm = document.getElementById('bookingForm');
    const servicesBreakdown = document.getElementById('servicesBreakdown');
    const totalPriceElement = document.getElementById('totalPrice');
    const quantityInputs = document.querySelectorAll('.quantity-input');
    const basePrice = <?php echo $campsite['price_per_night'] * $nights; ?>;
    
    function updateTotal() {
        let total = basePrice;
        let servicesHtml = '';
        
        quantityInputs.forEach(input => {
            const quantity = parseInt(input.value) || 0;
            const price = parseFloat(input.dataset.price);
            const serviceTotal = quantity * price;
            
            if (quantity > 0) {
                const serviceName = input.closest('.service-card').querySelector('h4').textContent;
                servicesHtml += `
                    <div class="price-row">
                        <span>${serviceName} (×${quantity})</span>
                        <span>${serviceTotal.toFixed(2)} TND</span>
                    </div>
                `;
                total += serviceTotal;
            }
        });
        
        servicesBreakdown.innerHTML = servicesHtml;
        totalPriceElement.textContent = `${total.toFixed(2)} TND`;
    }
    
    quantityInputs.forEach(input => {
        input.addEventListener('change', updateTotal);
    });
});
</script> 