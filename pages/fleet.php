<?php
// Start processing before including the header file
session_start();
require_once '../db/db.php';

// Function to check if user is admin - include if not available
if (!function_exists('isAdmin')) {
    function isAdmin() {
        return isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
    }
}

// Function to check if user is logged in - include if not available
if (!function_exists('isLoggedIn')) {
    function isLoggedIn() {
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }
}

// Now that all potential redirects are handled, include the header which will start HTML output
include '../includes/header.php';
?>

<div class="bg-white dark:bg-gray-800 p-8 rounded-lg shadow-md mb-8">
    <h1 class="text-3xl font-bold mb-6 dark:text-white">Our Bike Collection</h1>
    
    <!-- Filter options -->
    <div class="mb-8">
        <h2 class="text-xl font-semibold mb-4 dark:text-white">Filter Bikes</h2>
        <form action="" method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label for="bike_type" class="block text-gray-700 dark:text-gray-300 text-sm font-medium mb-2">Vehicle Type</label>
                <select name="bike_type" id="bike_type" class="form-input w-full border rounded-lg px-4 py-2 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                    <option value="">All Types</option>
                    <option value="Mountain Bike" <?php echo (isset($_GET['bike_type']) && $_GET['bike_type'] === 'Mountain Bike') ? 'selected' : ''; ?>>Mountain Bike</option>
                    <option value="City Bike" <?php echo (isset($_GET['bike_type']) && $_GET['bike_type'] === 'City Bike') ? 'selected' : ''; ?>>City Bike</option>
                    <option value="E-Bike" <?php echo (isset($_GET['bike_type']) && $_GET['bike_type'] === 'E-Bike') ? 'selected' : ''; ?>>E-Bike</option>
                    <option value="Scooty" <?php echo (isset($_GET['bike_type']) && $_GET['bike_type'] === 'Scooty') ? 'selected' : ''; ?>>Scooty</option>
                    <option value="Motorcycle" <?php echo (isset($_GET['bike_type']) && $_GET['bike_type'] === 'Motorcycle') ? 'selected' : ''; ?>>Motorcycle</option>
                </select>
            </div>
            
            <div>
                <label for="min_rate" class="block text-gray-700 dark:text-gray-300 text-sm font-medium mb-2">Min Rate (₹)</label>
                <input type="number" name="min_rate" id="min_rate" min="0" step="1" value="<?php echo isset($_GET['min_rate']) ? htmlspecialchars($_GET['min_rate']) : ''; ?>" class="form-input w-full border rounded-lg px-4 py-2 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
            </div>
            
            <div>
                <label for="max_rate" class="block text-gray-700 dark:text-gray-300 text-sm font-medium mb-2">Max Rate (₹)</label>
                <input type="number" name="max_rate" id="max_rate" min="0" step="1" value="<?php echo isset($_GET['max_rate']) ? htmlspecialchars($_GET['max_rate']) : ''; ?>" class="form-input w-full border rounded-lg px-4 py-2 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
            </div>
            
            <div class="flex items-end">
                <button type="submit" class="bg-gradient-to-r from-purple-500 to-purple-700 hover:from-purple-600 hover:to-purple-800 text-white font-bold py-2 px-4 rounded-lg w-full">
                    <i class="fas fa-filter mr-2"></i>Apply Filters
                </button>
            </div>
        </form>
    </div>
    
    <?php
    // Build the query based on filters
    $query = "SELECT * FROM bikes WHERE 1=1";
    $params = array();
    
    // We're removing the availability filter to show all bikes to users
    // Users will still see the availability status badge on each bike
    
    // Apply bike type filter
    if (isset($_GET['bike_type']) && !empty($_GET['bike_type'])) {
        $query .= " AND bike_type = ?";
        $params[] = $_GET['bike_type'];
    }
    
    // Apply min rate filter
    if (isset($_GET['min_rate']) && is_numeric($_GET['min_rate'])) {
        $query .= " AND hourly_rate >= ?";
        $params[] = $_GET['min_rate'];
    }
    
    // Apply max rate filter
    if (isset($_GET['max_rate']) && is_numeric($_GET['max_rate'])) {
        $query .= " AND hourly_rate <= ?";
        $params[] = $_GET['max_rate'];
    }
    
    // Execute the query
    try {
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $bikes = $stmt->fetchAll();
    } catch (PDOException $e) {
        echo "Error: " . $e->getMessage();
        $bikes = [];
    }
    ?>
    
    <!-- Bikes Display -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
        <?php if (count($bikes) > 0): ?>
            <?php foreach ($bikes as $bike): ?>
                <div class="bike-card bg-white dark:bg-gray-700 rounded-lg shadow-lg overflow-hidden">
                    <div class="relative">
                        <img src="<?php echo $bike['image_path'] ? '../' . $bike['image_path'] : '../assets/images/default-bike.jpg'; ?>" alt="<?php echo htmlspecialchars($bike['bike_name']); ?>" class="w-full h-64 object-cover">
                        <div class="image-overlay"></div>
                        <div class="absolute top-4 right-4">
                            <?php if ($bike['status'] === 'available'): ?>
                                <span class="badge-available text-white px-3 py-1 rounded-full text-sm font-semibold">
                                    Available
                                </span>
                            <?php elseif ($bike['status'] === 'reserved'): ?>
                                <span class="badge-reserved text-white px-3 py-1 rounded-full text-sm font-semibold">
                                    Reserved
                                </span>
                            <?php else: ?>
                                <span class="badge-maintenance text-white px-3 py-1 rounded-full text-sm font-semibold">
                                    Maintenance
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="p-6">
                        <h3 class="text-xl font-bold mb-2 dark:text-white"><?php echo htmlspecialchars($bike['bike_name']); ?></h3>
                        <p class="text-gray-500 dark:text-gray-300 mb-2"><?php echo htmlspecialchars($bike['bike_type']); ?></p>
                        <p class="text-gray-700 dark:text-gray-300 mb-4"><?php echo htmlspecialchars($bike['specifications']); ?></p>
                        <div class="flex justify-between items-center">
                            <span class="text-purple-600 dark:text-purple-400 font-bold">₹<?php echo number_format($bike['hourly_rate'], 2); ?>/hour</span>
                            <?php if ($bike['status'] === 'available' && isLoggedIn()): ?>
                                <a href="book.php?bike_id=<?php echo $bike['bike_id']; ?>" class="bg-gradient-to-r from-purple-500 to-purple-700 hover:from-purple-600 hover:to-purple-800 text-white font-bold py-2 px-4 rounded">
                                    Book Now
                                </a>
                            <?php elseif (!isLoggedIn()): ?>
                                <a href="../auth/login.php" class="bg-gradient-to-r from-purple-500 to-purple-700 hover:from-purple-600 hover:to-purple-800 text-white font-bold py-2 px-4 rounded">
                                    Login to Book
                                </a>
                            <?php else: ?>
                                <button disabled class="bg-gray-400 dark:bg-gray-600 text-white font-bold py-2 px-4 rounded cursor-not-allowed">
                                    Unavailable
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="col-span-3 text-center py-8">
                <p class="text-gray-500 dark:text-gray-400">No vehicles matching your filters were found.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>