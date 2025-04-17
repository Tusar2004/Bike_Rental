<?php
// Start processing before including the header file
session_start();
require_once '../db/db.php';

// Function to ensure user is logged in
if (!function_exists('requireLogin')) {
    function requireLogin() {
        if (!isset($_SESSION['user_id'])) {
            $_SESSION['error_message'] = "You must be logged in to access this page";
            header("Location: ../auth/login.php");
            exit();
        }
    }
}

// Check login status before including header
requireLogin();

// Check if bike_id is provided
$bike_id = isset($_GET['bike_id']) ? intval($_GET['bike_id']) : 0;

// Get bike details
$bike = null;
if ($bike_id > 0) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM bikes WHERE bike_id = ? AND status = 'available'");
        $stmt->execute([$bike_id]);
        $bike = $stmt->fetch();
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Error: " . $e->getMessage();
    }
}

// Handle form submission first, before any HTML output
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Helper function for sanitizing inputs
    if (!function_exists('sanitize')) {
        function sanitize($data) {
            return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
        }
    }

    $start_time = sanitize($_POST['start_time']);
    $end_time = sanitize($_POST['end_time']);
    $pickup_location = sanitize($_POST['pickup_location']);
    $dropoff_location = sanitize($_POST['dropoff_location']);
    $bike_id = intval($_POST['bike_id']);

    $errors = array();
    
    // Validate user session
    if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
        $_SESSION['error_message'] = "Your session has expired. Please log in again.";
        header("Location: ../auth/login.php");
        exit();
    }

    // Validate form data
    if (empty($start_time) || empty($end_time)) {
        $errors[] = "Start and end times are required";
    }

    if (empty($pickup_location)) {
        $errors[] = "Please select a pickup location on the map";
    }

    if (empty($dropoff_location)) {
        $errors[] = "Please select a dropoff location on the map";
    }

    // Check if bike is still available
    try {
        $stmt = $pdo->prepare("SELECT * FROM bikes WHERE bike_id = ? AND status = 'available'");
        $stmt->execute([$bike_id]);
        $bike = $stmt->fetch();
        
        if (!$bike) {
            $errors[] = "This bike is no longer available";
        }
    } catch (PDOException $e) {
        $errors[] = "Error: " . $e->getMessage();
    }

    // Create reservation if no errors
    if (empty($errors)) {
        try {
            // Begin transaction
            $pdo->beginTransaction();
            
            // Create reservation
            $stmt = $pdo->prepare("
                INSERT INTO reservations 
                (user_id, bike_id, start_time, end_time, pickup_location, dropoff_location, status) 
                VALUES (?, ?, ?, ?, ?, ?, 'pending')
            ");
            $stmt->execute([
                $_SESSION['user_id'],
                $bike_id,
                $start_time,
                $end_time,
                $pickup_location,
                $dropoff_location
            ]);
            
            $reservation_id = $pdo->lastInsertId();
            
            // Update bike status
            $stmt = $pdo->prepare("UPDATE bikes SET status = 'reserved' WHERE bike_id = ?");
            $stmt->execute([$bike_id]);
            
            // Calculate total hours and amount
            $start = new DateTime($start_time);
            $end = new DateTime($end_time);
            $diff = $start->diff($end);
            $hours = $diff->h + ($diff->days * 24);
            $total_amount = $hours * $bike['hourly_rate'];
            
            // Store session data for payment
            $_SESSION['reservation'] = [
                'reservation_id' => $reservation_id,
                'bike_name' => $bike['bike_name'],
                'start_time' => $start_time,
                'end_time' => $end_time,
                'total_hours' => $hours,
                'hourly_rate' => $bike['hourly_rate'],
                'total_amount' => $total_amount
            ];
            
            // Commit transaction
            $pdo->commit();
            
            // Redirect to payment page
            header("Location: payment.php");
            exit();
            
        } catch (PDOException $e) {
            // Rollback transaction on error
            $pdo->rollBack();
            $_SESSION['error_message'] = "Error creating reservation: " . $e->getMessage();
        }
    } else {
        $_SESSION['error_message'] = implode("<br>", $errors);
    }
}

// Get all available bikes for dropdown if no specific bike was selected
$available_bikes = [];
if (!$bike) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM bikes WHERE status = 'available'");
        $stmt->execute();
        $available_bikes = $stmt->fetchAll();
        
        if (count($available_bikes) > 0) {
            $bike = $available_bikes[0]; // Set first bike as default
            $bike_id = $bike['bike_id'];
        }
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Error fetching bikes: " . $e->getMessage();
    }
}

// If no bikes are available, redirect before any HTML output
if (!$bike) {
    $_SESSION['error_message'] = "No bikes are currently available for booking";
    header("Location: fleet.php");
    exit();
}

// Now that all redirects are complete, include the header which will start HTML output
include '../includes/header.php';
?>

<div class="bg-white p-8 rounded-lg shadow-md mb-8">
    <h1 class="text-3xl font-bold mb-6">Book a Bike</h1>
    
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <!-- Bike Details Section -->
        <div>
            <div class="bike-card bg-white rounded-lg shadow-lg overflow-hidden">
                <div class="relative">
                    <img src="<?php echo $bike['image_path'] ? '../' . $bike['image_path'] : '../assets/images/default-bike.jpg'; ?>" alt="<?php echo htmlspecialchars($bike['bike_name']); ?>" class="w-full h-64 object-cover">
                    <div class="absolute top-4 right-4">
                        <span class="badge-available text-white px-3 py-1 rounded-full text-sm font-semibold">
                            Available
                        </span>
                    </div>
                </div>
                <div class="p-6">
                    <h3 class="text-xl font-bold mb-2"><?php echo htmlspecialchars($bike['bike_name']); ?></h3>
                    <p class="text-gray-500 mb-2"><?php echo htmlspecialchars($bike['bike_type']); ?></p>
                    <p class="text-gray-700 mb-4"><?php echo htmlspecialchars($bike['specifications']); ?></p>
                    <div class="text-blue-600 font-bold text-xl">
                        ₹<?php echo number_format($bike['hourly_rate'], 2); ?>/hour
                    </div>
                </div>
            </div>
            
            <?php if (count($available_bikes) > 1): ?>
            <div class="mt-6">
                <h3 class="text-lg font-semibold mb-3">Choose Another Bike</h3>
                <form action="book.php" method="GET">
                    <div class="flex">
                        <select name="bike_id" class="form-input w-full border rounded-lg rounded-r-none px-4 py-2">
                            <?php foreach ($available_bikes as $available_bike): ?>
                            <option value="<?php echo $available_bike['bike_id']; ?>" <?php echo $available_bike['bike_id'] == $bike_id ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($available_bike['bike_name']); ?> - ₹<?php echo number_format($available_bike['hourly_rate'], 2); ?>/hour
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg rounded-l-none">
                            Select
                        </button>
                    </div>
                </form>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Booking Form Section -->
        <div>
            <form action="book.php" method="POST" id="booking-form">
                <input type="hidden" name="bike_id" value="<?php echo $bike_id; ?>">
                <input type="hidden" name="pickup_location" id="pickup_location">
                <input type="hidden" name="dropoff_location" id="dropoff_location">
                
                <div class="mb-6">
                    <h3 class="text-lg font-semibold mb-3">Rental Period</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="start_time" class="block text-gray-700 text-sm font-medium mb-2">Start Date & Time</label>
                            <input type="datetime-local" id="start_time" name="start_time" class="form-input w-full px-4 py-2 border rounded-lg" required>
                        </div>
                        <div>
                            <label for="end_time" class="block text-gray-700 text-sm font-medium mb-2">End Date & Time</label>
                            <input type="datetime-local" id="end_time" name="end_time" class="form-input w-full px-4 py-2 border rounded-lg" required>
                        </div>
                    </div>
                </div>
                
                <div class="mb-6">
                    <h3 class="text-lg font-semibold mb-3">Pickup & Drop-off Locations</h3>
                    <p class="text-sm text-gray-600 mb-2">We'll detect your current location. Click on the map to set your pickup (green) and drop-off (red) locations.</p>
                    
                    <div class="flex mb-3">
                        <button type="button" id="get-location" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg">
                            <i class="fas fa-map-marker-alt mr-2"></i>Use My Current Location
                        </button>
                        <div id="location-status" class="ml-3 flex items-center text-sm"></div>
                    </div>
                    
                    <!-- Map Container -->
                    <div id="map" class="h-72 w-full border rounded-lg mb-3"></div>
                    
                    <div class="flex items-center space-x-4 text-sm">
                        <div class="flex items-center">
                            <span class="w-4 h-4 bg-green-500 rounded-full inline-block mr-1"></span>
                            <span id="pickup-coords">Pickup: Not set</span>
                        </div>
                        <div class="flex items-center">
                            <span class="w-4 h-4 bg-red-500 rounded-full inline-block mr-1"></span>
                            <span id="dropoff-coords">Drop-off: Not set</span>
                        </div>
                    </div>
                </div>
                
                <div class="mt-8">
                    <button type="submit" class="btn-primary w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-6 rounded-lg text-lg">
                        <i class="fas fa-calendar-check mr-2"></i>Continue to Payment
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Leaflet CSS and JS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js"></script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize the map with default coordinates for India
        var map = L.map('map').setView([20.5937, 78.9629], 5);
        
        // Add the tile layer (OpenStreetMap)
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        }).addTo(map);
        
        var pickupMarker = null;
        var dropoffMarker = null;
        var userLocationMarker = null;
        
        // Get location button handler
        document.getElementById('get-location').addEventListener('click', function() {
            const statusElement = document.getElementById('location-status');
            statusElement.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Getting your location...';
            
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(
                    // Success callback
                    function(position) {
                        const userLat = position.coords.latitude;
                        const userLng = position.coords.longitude;
                        
                        // Update map to user's location
                        map.setView([userLat, userLng], 15);
                        
                        // Add or update user's location marker
                        if (userLocationMarker) {
                            userLocationMarker.setLatLng([userLat, userLng]);
                        } else {
                            userLocationMarker = L.marker([userLat, userLng], {
                                icon: L.divIcon({
                                    className: 'custom-div-icon',
                                    html: "<div style='background-color:#2563EB;width:12px;height:12px;border-radius:50%;border: 2px solid white;'></div>",
                                    iconSize: [12, 12]
                                })
                            }).addTo(map);
                        }
                        
                        // Set pickup location to user's current location
                        if (pickupMarker === null) {
                            pickupMarker = L.marker([userLat, userLng], { 
                                icon: L.divIcon({
                                    className: 'custom-div-icon',
                                    html: "<div style='background-color:#4CAF50;width:12px;height:12px;border-radius:50%;'></div>",
                                    iconSize: [12, 12]
                                })
                            }).addTo(map);
                            
                            document.getElementById('pickup_location').value = userLat + ',' + userLng;
                            document.getElementById('pickup-coords').textContent = 'Pickup: ' + userLat.toFixed(4) + ', ' + userLng.toFixed(4);
                        }
                        
                        statusElement.innerHTML = '<span class="text-green-600"><i class="fas fa-check-circle mr-1"></i>Location found!</span>';
                    },
                    // Error callback
                    function(error) {
                        console.error("Geolocation error:", error);
                        let errorMessage = "Unable to get your location.";
                        switch(error.code) {
                            case error.PERMISSION_DENIED:
                                errorMessage = "Location access denied. Please enable location services.";
                                break;
                            case error.POSITION_UNAVAILABLE:
                                errorMessage = "Location information is unavailable.";
                                break;
                            case error.TIMEOUT:
                                errorMessage = "Location request timed out.";
                                break;
                        }
                        statusElement.innerHTML = '<span class="text-red-600"><i class="fas fa-exclamation-circle mr-1"></i>' + errorMessage + '</span>';
                    }
                );
            } else {
                statusElement.innerHTML = '<span class="text-red-600"><i class="fas fa-exclamation-circle mr-1"></i>Geolocation is not supported by this browser.</span>';
            }
        });
        
        // Click handler for the map
        map.on('click', function(e) {
            var latlng = e.latlng;
            
            // If pickup not set, set pickup
            if (pickupMarker === null) {
                pickupMarker = L.marker(latlng, { 
                    icon: L.divIcon({
                        className: 'custom-div-icon',
                        html: "<div style='background-color:#4CAF50;width:12px;height:12px;border-radius:50%;'></div>",
                        iconSize: [12, 12]
                    })
                }).addTo(map);
                
                document.getElementById('pickup_location').value = latlng.lat + ',' + latlng.lng;
                document.getElementById('pickup-coords').textContent = 'Pickup: ' + latlng.lat.toFixed(4) + ', ' + latlng.lng.toFixed(4);
            } 
            // If dropoff not set, set dropoff
            else if (dropoffMarker === null) {
                dropoffMarker = L.marker(latlng, { 
                    icon: L.divIcon({
                        className: 'custom-div-icon',
                        html: "<div style='background-color:#FF5252;width:12px;height:12px;border-radius:50%;'></div>",
                        iconSize: [12, 12]
                    })
                }).addTo(map);
                
                document.getElementById('dropoff_location').value = latlng.lat + ',' + latlng.lng;
                document.getElementById('dropoff-coords').textContent = 'Drop-off: ' + latlng.lat.toFixed(4) + ', ' + latlng.lng.toFixed(4);
            }
        });
        
        // Set minimum date for start time (now)
        const now = new Date();
        now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
        document.getElementById('start_time').min = now.toISOString().slice(0, 16);
        
        // Update end time minimum based on start time
        document.getElementById('start_time').addEventListener('change', function() {
            const startTime = new Date(this.value);
            startTime.setHours(startTime.getHours() + 1);
            document.getElementById('end_time').min = startTime.toISOString().slice(0, 16);
            
            // Reset end time if it's before the new minimum
            const endTime = new Date(document.getElementById('end_time').value);
            if (endTime < startTime) {
                document.getElementById('end_time').value = startTime.toISOString().slice(0, 16);
            }
        });
        
        // Form validation
        document.getElementById('booking-form').addEventListener('submit', function(event) {
            if (!document.getElementById('pickup_location').value) {
                alert('Please select a pickup location on the map');
                event.preventDefault();
                return false;
            }
            
            if (!document.getElementById('dropoff_location').value) {
                alert('Please select a drop-off location on the map');
                event.preventDefault();
                return false;
            }
            
            if (!document.getElementById('start_time').value || !document.getElementById('end_time').value) {
                alert('Please select start and end times for your booking');
                event.preventDefault();
                return false;
            }
            
            return true;
        });
        
        // Try to get user's location automatically on page load
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(
                function(position) {
                    const userLat = position.coords.latitude;
                    const userLng = position.coords.longitude;
                    
                    // Update map to user's location
                    map.setView([userLat, userLng], 15);
                    
                    // Add user's location marker
                    userLocationMarker = L.marker([userLat, userLng], {
                        icon: L.divIcon({
                            className: 'custom-div-icon',
                            html: "<div style='background-color:#2563EB;width:12px;height:12px;border-radius:50%;border: 2px solid white;'></div>",
                            iconSize: [12, 12]
                        })
                    }).addTo(map);
                    
                    document.getElementById('location-status').innerHTML = 
                        '<span class="text-green-600"><i class="fas fa-check-circle mr-1"></i>Using your current location</span>';
                },
                function(error) {
                    console.log("Initial geolocation error:", error);
                }
            );
        }
    });
</script>

<?php include '../includes/footer.php'; ?>