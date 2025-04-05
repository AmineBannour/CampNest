<?php
// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: index.php?page=login');
    exit();
}

// Handle campsite actions
if (isset($_POST['action']) && $_POST['action'] === 'update_campsite') {
    $campsite_id = (int)$_POST['campsite_id'];
    $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);
    $type = filter_input(INPUT_POST, 'type', FILTER_SANITIZE_STRING);
    $price = filter_input(INPUT_POST, 'price', FILTER_VALIDATE_FLOAT);
    $capacity = filter_input(INPUT_POST, 'capacity', FILTER_VALIDATE_INT);
    $description = filter_input(INPUT_POST, 'description', FILTER_SANITIZE_STRING);
    $status = filter_input(INPUT_POST, 'status', FILTER_SANITIZE_STRING);
    
    $stmt = $conn->prepare("
        UPDATE campsites 
        SET name = ?, type = ?, price_per_night = ?, capacity = ?, description = ?, status = ?
        WHERE id = ?
    ");
    $stmt->execute([$name, $type, $price, $capacity, $description, $status, $campsite_id]);
    
    $success = "Campsite updated successfully";
}

// Handle booking actions
if (isset($_POST['action']) && $_POST['action'] === 'update_booking_status') {
    $booking_id = (int)$_POST['booking_id'];
    $status = filter_input(INPUT_POST, 'status', FILTER_SANITIZE_STRING);
    
    $stmt = $conn->prepare("UPDATE bookings SET status = ? WHERE id = ?");
    $stmt->execute([$status, $booking_id]);
    
    $success = "Booking status updated successfully";
}

// Handle service actions
if (isset($_POST['action']) && $_POST['action'] === 'update_service') {
    $service_id = (int)$_POST['service_id'];
    $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);
    $price = filter_input(INPUT_POST, 'price', FILTER_VALIDATE_FLOAT);
    $description = filter_input(INPUT_POST, 'description', FILTER_SANITIZE_STRING);
    $status = filter_input(INPUT_POST, 'status', FILTER_SANITIZE_STRING);
    
    $stmt = $conn->prepare("
        UPDATE services 
        SET name = ?, price = ?, description = ?, status = ?
        WHERE id = ?
    ");
    $stmt->execute([$name, $price, $description, $status, $service_id]);
    
    $success = "Service updated successfully";
}

// Handle add campsite action
if (isset($_POST['action']) && $_POST['action'] === 'add_campsite') {
    $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);
    $type = filter_input(INPUT_POST, 'type', FILTER_SANITIZE_STRING);
    $price = filter_input(INPUT_POST, 'price', FILTER_VALIDATE_FLOAT);
    $capacity = filter_input(INPUT_POST, 'capacity', FILTER_VALIDATE_INT);
    $description = filter_input(INPUT_POST, 'description', FILTER_SANITIZE_STRING);
    
    $stmt = $conn->prepare("
        INSERT INTO campsites (name, type, price_per_night, capacity, description, status)
        VALUES (?, ?, ?, ?, ?, 'active')
    ");
    $stmt->execute([$name, $type, $price, $capacity, $description]);
    
    $success = "Campsite added successfully";
}

// Handle add service action
if (isset($_POST['action']) && $_POST['action'] === 'add_service') {
    $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);
    $price = filter_input(INPUT_POST, 'price', FILTER_VALIDATE_FLOAT);
    $description = filter_input(INPUT_POST, 'description', FILTER_SANITIZE_STRING);
    
    $stmt = $conn->prepare("
        INSERT INTO services (name, price, description, status)
        VALUES (?, ?, ?, 'active')
    ");
    $stmt->execute([$name, $price, $description]);
    
    $success = "Service added successfully";
}

// Fetch statistics
$stats = [
    'total_bookings' => $conn->query("SELECT COUNT(*) FROM bookings")->fetchColumn(),
    'pending_bookings' => $conn->query("SELECT COUNT(*) FROM bookings WHERE status = 'pending'")->fetchColumn(),
    'active_campsites' => $conn->query("SELECT COUNT(*) FROM campsites WHERE status = 'active'")->fetchColumn(),
    'total_revenue' => $conn->query("SELECT SUM(total_price) FROM bookings WHERE status IN ('confirmed', 'completed')")->fetchColumn()
];

// Fetch recent bookings
$stmt = $conn->prepare("
    SELECT b.*, u.email as user_email, c.name as campsite_name
    FROM bookings b
    JOIN users u ON b.user_id = u.id
    JOIN campsites c ON b.campsite_id = c.id
    ORDER BY b.created_at DESC
    LIMIT 10
");
$stmt->execute();
$recent_bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch campsites
$stmt = $conn->query("SELECT * FROM campsites ORDER BY name");
$campsites = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch services
$stmt = $conn->query("SELECT * FROM services ORDER BY name");
$services = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch booking details for modal
if (isset($_GET['fetch_booking_details']) && isset($_GET['booking_id'])) {
    $booking_id = (int)$_GET['booking_id'];
    
    // Fetch booking details with user and campsite info
    $stmt = $conn->prepare("
        SELECT b.*, 
               u.email, u.first_name, u.last_name,
               c.name as campsite_name, c.type as campsite_type
        FROM bookings b
        JOIN users u ON b.user_id = u.id
        JOIN campsites c ON b.campsite_id = c.id
        WHERE b.id = ?
    ");
    $stmt->execute([$booking_id]);
    $booking_details = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Fetch booked services
    $stmt = $conn->prepare("
        SELECT s.name, s.price, bs.quantity
        FROM booking_services bs
        JOIN services s ON bs.service_id = s.id
        WHERE bs.booking_id = ?
    ");
    $stmt->execute([$booking_id]);
    $booked_services = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Return JSON response
    header('Content-Type: application/json');
    echo json_encode([
        'booking' => $booking_details,
        'services' => $booked_services
    ]);
    exit();
}
?>

<div class="container">
    <div class="admin-dashboard">
        <div class="admin-header">
            <h1>Admin Dashboard</h1>
            <div class="stats-grid">
                <div class="stat-card">
                    <h3>Total Bookings</h3>
                    <p class="stat-number"><?php echo number_format($stats['total_bookings']); ?></p>
                </div>
                <div class="stat-card">
                    <h3>Pending Bookings</h3>
                    <p class="stat-number"><?php echo number_format($stats['pending_bookings']); ?></p>
                </div>
                <div class="stat-card">
                    <h3>Active Campsites</h3>
                    <p class="stat-number"><?php echo number_format($stats['active_campsites']); ?></p>
                </div>
                <div class="stat-card">
                    <h3>Total Revenue</h3>
                    <p class="stat-number">$<?php echo number_format($stats['total_revenue'], 2); ?></p>
                </div>
            </div>
        </div>
        
        <?php if (isset($success)): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>
        
        <div class="admin-tabs">
            <div class="tab-buttons">
                <button class="tab-btn active" data-tab="bookings">Bookings</button>
                <button class="tab-btn" data-tab="campsites">Campsites</button>
                <button class="tab-btn" data-tab="services">Services</button>
            </div>
            
            <div class="tab-content active" id="bookings-tab">
                <h2>Recent Bookings</h2>
                <div class="table-responsive">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>User</th>
                                <th>Campsite</th>
                                <th>Check In</th>
                                <th>Check Out</th>
                                <th>Total</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_bookings as $booking): ?>
                                <tr>
                                    <td>#<?php echo $booking['id']; ?></td>
                                    <td><?php echo htmlspecialchars($booking['user_email']); ?></td>
                                    <td><?php echo htmlspecialchars($booking['campsite_name']); ?></td>
                                    <td><?php echo date('M j, Y', strtotime($booking['check_in_date'])); ?></td>
                                    <td><?php echo date('M j, Y', strtotime($booking['check_out_date'])); ?></td>
                                    <td><?php echo number_format($booking['total_price'], 2); ?> TND</td>
                                    <td>
                                        <form method="POST" class="status-form">
                                            <input type="hidden" name="action" value="update_booking_status">
                                            <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                            <select name="status" onchange="this.form.submit()">
                                                <option value="pending" <?php echo $booking['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                <option value="confirmed" <?php echo $booking['status'] === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                                                <option value="completed" <?php echo $booking['status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                                <option value="cancelled" <?php echo $booking['status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                            </select>
                                        </form>
                                    </td>
                                    <td>
                                        <button class="btn btn-small" onclick="viewBookingDetails(<?php echo $booking['id']; ?>)">View Details</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <div class="tab-content" id="campsites-tab">
                <div class="tab-header">
                    <h2>Manage Campsites</h2>
                    <button class="btn btn-primary" onclick="showAddCampsiteModal()">Add New Campsite</button>
                </div>
                <div class="table-responsive">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Type</th>
                                <th>Price/Night</th>
                                <th>Capacity</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($campsites as $campsite): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($campsite['name']); ?></td>
                                    <td><?php echo htmlspecialchars($campsite['type']); ?></td>
                                    <td><?php echo number_format($campsite['price_per_night'], 2); ?> TND</td>
                                    <td><?php echo $campsite['capacity']; ?></td>
                                    <td>
                                        <form method="POST" class="status-form">
                                            <input type="hidden" name="action" value="update_campsite">
                                            <input type="hidden" name="campsite_id" value="<?php echo $campsite['id']; ?>">
                                            <select name="status" onchange="this.form.submit()">
                                                <option value="active" <?php echo $campsite['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                                <option value="maintenance" <?php echo $campsite['status'] === 'maintenance' ? 'selected' : ''; ?>>Maintenance</option>
                                                <option value="inactive" <?php echo $campsite['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                            </select>
                                        </form>
                                    </td>
                                    <td>
                                        <button class="btn btn-small" onclick="editCampsite(<?php echo $campsite['id']; ?>)">Edit</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <div class="tab-content" id="services-tab">
                <div class="tab-header">
                    <h2>Manage Services</h2>
                    <button class="btn btn-primary" onclick="showAddServiceModal()">Add New Service</button>
                </div>
                <div class="table-responsive">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Price</th>
                                <th>Description</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($services as $service): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($service['name']); ?></td>
                                    <td><?php echo number_format($service['price'], 2); ?> TND</td>
                                    <td><?php echo htmlspecialchars($service['description']); ?></td>
                                    <td>
                                        <form method="POST" class="status-form">
                                            <input type="hidden" name="action" value="update_service">
                                            <input type="hidden" name="service_id" value="<?php echo $service['id']; ?>">
                                            <select name="status" onchange="this.form.submit()">
                                                <option value="active" <?php echo $service['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                                <option value="inactive" <?php echo $service['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                            </select>
                                        </form>
                                    </td>
                                    <td>
                                        <button class="btn btn-small" onclick="editService(<?php echo $service['id']; ?>)">Edit</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Campsite Modal -->
<div id="campsiteModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="campsiteModalTitle">Add New Campsite</h2>
            <button class="close-modal" onclick="closeCampsiteModal()">&times;</button>
        </div>
        <form id="campsiteForm" method="POST" action="">
            <input type="hidden" name="action" id="campsite_action" value="add_campsite">
            <input type="hidden" name="campsite_id" id="campsite_id" value="">
            
            <div class="form-group">
                <label for="campsite_name">Name</label>
                <input type="text" id="campsite_name" name="name" required>
            </div>
            
            <div class="form-group">
                <label for="campsite_type">Type</label>
                <select id="campsite_type" name="type" required>
                    <option value="tent">Tent Site</option>
                    <option value="rv">RV Site</option>
                    <option value="cabin">Cabin</option>
                    <option value="glamping">Glamping</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="campsite_price">Price per Night ($)</label>
                <input type="number" id="campsite_price" name="price" step="0.01" min="0" required>
            </div>
            
            <div class="form-group">
                <label for="campsite_capacity">Capacity</label>
                <input type="number" id="campsite_capacity" name="capacity" min="1" required>
            </div>
            
            <div class="form-group">
                <label for="campsite_description">Description</label>
                <textarea id="campsite_description" name="description" rows="4" required></textarea>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeCampsiteModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Campsite</button>
            </div>
        </form>
    </div>
</div>

<!-- Service Modal -->
<div id="serviceModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="serviceModalTitle">Add New Service</h2>
            <button class="close-modal" onclick="closeServiceModal()">&times;</button>
        </div>
        <form id="serviceForm" method="POST" action="">
            <input type="hidden" name="action" id="service_action" value="add_service">
            <input type="hidden" name="service_id" id="service_id" value="">
            
            <div class="form-group">
                <label for="service_name">Name</label>
                <input type="text" id="service_name" name="name" required>
            </div>
            
            <div class="form-group">
                <label for="service_price">Price ($)</label>
                <input type="number" id="service_price" name="price" step="0.01" min="0" required>
            </div>
            
            <div class="form-group">
                <label for="service_description">Description</label>
                <textarea id="service_description" name="description" rows="4" required></textarea>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeServiceModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Service</button>
            </div>
        </form>
    </div>
</div>

<!-- Booking Details Modal -->
<div id="bookingDetailsModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Booking Details</h2>
            <button class="close-modal" onclick="closeBookingDetailsModal()">&times;</button>
        </div>
        <div class="booking-details-content">
            <div class="booking-info-section">
                <h3>Booking Information</h3>
                <div class="info-grid">
                    <div class="info-item">
                        <label>Booking ID</label>
                        <p id="booking_id"></p>
                    </div>
                    <div class="info-item">
                        <label>Status</label>
                        <p id="booking_status"></p>
                    </div>
                    <div class="info-item">
                        <label>Created At</label>
                        <p id="booking_created"></p>
                    </div>
                </div>
            </div>
            
            <div class="customer-info-section">
                <h3>Customer Information</h3>
                <div class="info-grid">
                    <div class="info-item">
                        <label>Name</label>
                        <p id="customer_name"></p>
                    </div>
                    <div class="info-item">
                        <label>Email</label>
                        <p id="customer_email"></p>
                    </div>
                </div>
            </div>
            
            <div class="stay-info-section">
                <h3>Stay Details</h3>
                <div class="info-grid">
                    <div class="info-item">
                        <label>Campsite</label>
                        <p id="campsite_name"></p>
                    </div>
                    <div class="info-item">
                        <label>Type</label>
                        <p id="campsite_type"></p>
                    </div>
                    <div class="info-item">
                        <label>Check In</label>
                        <p id="check_in_date"></p>
                    </div>
                    <div class="info-item">
                        <label>Check Out</label>
                        <p id="check_out_date"></p>
                    </div>
                    <div class="info-item">
                        <label>Duration</label>
                        <p id="stay_duration"></p>
                    </div>
                </div>
            </div>
            
            <div class="services-section">
                <h3>Booked Services</h3>
                <div id="booked_services_list">
                    <!-- Services will be populated dynamically -->
                </div>
            </div>
            
            <div class="payment-section">
                <h3>Payment Details</h3>
                <div class="payment-summary">
                    <div class="payment-item">
                        <span>Accommodation</span>
                        <span id="accommodation_cost"></span>
                    </div>
                    <div class="payment-item">
                        <span>Services</span>
                        <span id="services_cost"></span>
                    </div>
                    <div class="payment-item total">
                        <span>Total Amount</span>
                        <span id="total_amount"></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.admin-dashboard {
    padding: 2rem 0;
}

.admin-header {
    margin-bottom: 2rem;
}

.admin-header h1 {
    color: var(--primary-color);
    margin-bottom: 1.5rem;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
}

.stat-card {
    background: white;
    padding: 1.5rem;
    border-radius: 0.5rem;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    text-align: center;
}

.stat-card h3 {
    color: #666;
    font-size: 0.875rem;
    margin-bottom: 0.5rem;
}

.stat-number {
    font-size: 1.5rem;
    font-weight: 600;
    color: var(--primary-color);
}

.admin-tabs {
    background: white;
    border-radius: 0.5rem;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    overflow: hidden;
}

.tab-buttons {
    display: flex;
    border-bottom: 1px solid #eee;
}

.tab-btn {
    padding: 1rem 2rem;
    border: none;
    background: none;
    font-weight: 500;
    color: #666;
    cursor: pointer;
}

.tab-btn.active {
    color: var(--primary-color);
    border-bottom: 2px solid var(--primary-color);
}

.tab-content {
    display: none;
    padding: 2rem;
}

.tab-content.active {
    display: block;
}

.tab-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
}

.admin-table {
    width: 100%;
    border-collapse: collapse;
}

.admin-table th,
.admin-table td {
    padding: 1rem;
    text-align: left;
    border-bottom: 1px solid #eee;
}

.admin-table th {
    font-weight: 500;
    color: #666;
}

.status-select {
    padding: 0.25rem 0.5rem;
    border-radius: 0.25rem;
    border: 1px solid #ddd;
}

.status-select.pending {
    background: #fef3c7;
    color: #92400e;
}

.status-select.confirmed {
    background: #ecfdf5;
    color: #065f46;
}

.status-select.completed {
    background: #eff6ff;
    color: #1e40af;
}

.status-select.cancelled {
    background: #fef2f2;
    color: #991b1b;
}

.status-select.active {
    background: #ecfdf5;
    color: #065f46;
}

.status-select.maintenance {
    background: #fef3c7;
    color: #92400e;
}

.status-select.inactive {
    background: #fef2f2;
    color: #991b1b;
}

.btn-sm {
    padding: 0.25rem 0.5rem;
    font-size: 0.875rem;
}

.inline-form {
    display: inline-block;
}

@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: 1fr 1fr;
    }
    
    .tab-buttons {
        flex-direction: column;
    }
    
    .tab-btn {
        width: 100%;
        text-align: left;
    }
    
    .table-responsive {
        overflow-x: auto;
    }
}

.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
    z-index: 1000;
}

.modal-content {
    position: relative;
    background-color: white;
    margin: 2rem auto;
    padding: 0;
    width: 90%;
    max-width: 600px;
    border-radius: 0.5rem;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1.5rem;
    border-bottom: 1px solid #eee;
}

.modal-header h2 {
    margin: 0;
    color: var(--primary-color);
}

.close-modal {
    background: none;
    border: none;
    font-size: 1.5rem;
    cursor: pointer;
    color: #666;
}

.close-modal:hover {
    color: #000;
}

.modal form {
    padding: 1.5rem;
}

.form-group {
    margin-bottom: 1rem;
}

.form-group label {
    display: block;
    margin-bottom: 0.5rem;
    color: #666;
}

.form-group input,
.form-group select,
.form-group textarea {
    width: 100%;
    padding: 0.5rem;
    border: 1px solid #ddd;
    border-radius: 0.25rem;
    font-size: 1rem;
}

.form-group textarea {
    resize: vertical;
}

.modal-footer {
    padding: 1rem 0 0;
    display: flex;
    justify-content: flex-end;
    gap: 1rem;
}

.booking-details-content {
    padding: 1.5rem;
}

.booking-details-content h3 {
    color: var(--primary-color);
    margin-bottom: 1rem;
    padding-bottom: 0.5rem;
    border-bottom: 1px solid #eee;
}

.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
}

.info-item label {
    display: block;
    font-size: 0.875rem;
    color: #666;
    margin-bottom: 0.25rem;
}

.info-item p {
    font-size: 1rem;
    color: #333;
    margin: 0;
}

#booking_status {
    display: inline-block;
    padding: 0.25rem 0.75rem;
    border-radius: 1rem;
    font-size: 0.875rem;
    font-weight: 500;
}

.services-section {
    margin-bottom: 2rem;
}

#booked_services_list {
    display: grid;
    gap: 0.5rem;
}

.service-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.75rem;
    background: #f9fafb;
    border-radius: 0.25rem;
}

.service-item .service-details {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.service-item .quantity {
    background: #e5e7eb;
    padding: 0.25rem 0.5rem;
    border-radius: 0.25rem;
    font-size: 0.875rem;
}

.payment-summary {
    background: #f9fafb;
    padding: 1rem;
    border-radius: 0.5rem;
}

.payment-item {
    display: flex;
    justify-content: space-between;
    padding: 0.5rem 0;
}

.payment-item.total {
    border-top: 2px solid #eee;
    margin-top: 0.5rem;
    padding-top: 1rem;
    font-weight: 600;
}

@media (max-width: 768px) {
    .info-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Tab switching
    const tabButtons = document.querySelectorAll('.tab-btn');
    const tabContents = document.querySelectorAll('.tab-content');
    
    tabButtons.forEach(button => {
        button.addEventListener('click', () => {
            const tabName = button.dataset.tab;
            
            tabButtons.forEach(btn => btn.classList.remove('active'));
            tabContents.forEach(content => content.classList.remove('active'));
            
            button.classList.add('active');
            document.getElementById(`${tabName}-tab`).classList.add('active');
        });
    });
});

function viewBookingDetails(bookingId) {
    // Fetch booking details from server
    fetch(`index.php?page=admin&fetch_booking_details=1&booking_id=${bookingId}`)
        .then(response => response.json())
        .then(data => {
            const booking = data.booking;
            const services = data.services;
            
            // Update booking information
            document.getElementById('booking_id').textContent = `#${booking.id}`;
            document.getElementById('booking_status').textContent = booking.status.charAt(0).toUpperCase() + booking.status.slice(1);
            document.getElementById('booking_status').className = `status-label ${booking.status}`;
            document.getElementById('booking_created').textContent = new Date(booking.created_at).toLocaleString();
            
            // Update customer information
            document.getElementById('customer_name').textContent = `${booking.first_name} ${booking.last_name}`;
            document.getElementById('customer_email').textContent = booking.email;
            
            // Update stay details
            document.getElementById('campsite_name').textContent = booking.campsite_name;
            document.getElementById('campsite_type').textContent = booking.campsite_type.charAt(0).toUpperCase() + booking.campsite_type.slice(1);
            document.getElementById('check_in_date').textContent = new Date(booking.check_in_date).toLocaleDateString();
            document.getElementById('check_out_date').textContent = new Date(booking.check_out_date).toLocaleDateString();
            
            // Calculate stay duration
            const checkIn = new Date(booking.check_in_date);
            const checkOut = new Date(booking.check_out_date);
            const nights = Math.round((checkOut - checkIn) / (1000 * 60 * 60 * 24));
            document.getElementById('stay_duration').textContent = `${nights} night${nights !== 1 ? 's' : ''}`;
            
            // Update services list
            const servicesList = document.getElementById('booked_services_list');
            servicesList.innerHTML = '';
            let servicesTotal = 0;
            
            if (services.length > 0) {
                services.forEach(service => {
                    const serviceTotal = service.price * service.quantity;
                    servicesTotal += serviceTotal;
                    
                    servicesList.innerHTML += `
                        <div class="service-item">
                            <div class="service-details">
                                <span class="quantity">${service.quantity}x</span>
                                <span>${service.name}</span>
                            </div>
                            <span>$${serviceTotal.toFixed(2)}</span>
                        </div>
                    `;
                });
            } else {
                servicesList.innerHTML = '<p class="text-muted">No additional services booked</p>';
            }
            
            // Update payment details
            const accommodationCost = booking.total_price - servicesTotal;
            document.getElementById('accommodation_cost').textContent = `$${accommodationCost.toFixed(2)}`;
            document.getElementById('services_cost').textContent = `$${servicesTotal.toFixed(2)}`;
            document.getElementById('total_amount').textContent = `$${booking.total_price.toFixed(2)}`;
            
            // Show modal
            document.getElementById('bookingDetailsModal').style.display = 'block';
        })
        .catch(error => {
            console.error('Error fetching booking details:', error);
            alert('Failed to load booking details. Please try again.');
        });
}

function closeBookingDetailsModal() {
    document.getElementById('bookingDetailsModal').style.display = 'none';
}

function showAddCampsiteModal() {
    document.getElementById('campsiteModalTitle').textContent = 'Add New Campsite';
    document.getElementById('campsite_action').value = 'add_campsite';
    document.getElementById('campsite_id').value = '';
    document.getElementById('campsiteForm').reset();
    document.getElementById('campsiteModal').style.display = 'block';
}

function editCampsite(campsiteId) {
    document.getElementById('campsiteModalTitle').textContent = 'Edit Campsite';
    document.getElementById('campsite_action').value = 'update_campsite';
    document.getElementById('campsite_id').value = campsiteId;
    
    // Fetch campsite data and populate form
    const row = event.target.closest('tr');
    document.getElementById('campsite_name').value = row.cells[0].textContent;
    document.getElementById('campsite_type').value = row.cells[1].textContent.toLowerCase();
    document.getElementById('campsite_price').value = row.cells[2].textContent.replace('$', '');
    document.getElementById('campsite_capacity').value = row.cells[3].textContent;
    
    document.getElementById('campsiteModal').style.display = 'block';
}

function closeCampsiteModal() {
    document.getElementById('campsiteModal').style.display = 'none';
}

function showAddServiceModal() {
    document.getElementById('serviceModalTitle').textContent = 'Add New Service';
    document.getElementById('service_action').value = 'add_service';
    document.getElementById('service_id').value = '';
    document.getElementById('serviceForm').reset();
    document.getElementById('serviceModal').style.display = 'block';
}

function editService(serviceId) {
    document.getElementById('serviceModalTitle').textContent = 'Edit Service';
    document.getElementById('service_action').value = 'update_service';
    document.getElementById('service_id').value = serviceId;
    
    // Fetch service data and populate form
    const row = event.target.closest('tr');
    document.getElementById('service_name').value = row.cells[0].textContent;
    document.getElementById('service_price').value = row.cells[1].textContent.replace('$', '');
    document.getElementById('service_description').value = row.cells[2].textContent;
    
    document.getElementById('serviceModal').style.display = 'block';
}

function closeServiceModal() {
    document.getElementById('serviceModal').style.display = 'none';
}

// Close modals when clicking outside
window.onclick = function(event) {
    if (event.target.className === 'modal') {
        event.target.style.display = 'none';
    }
}
</script> 