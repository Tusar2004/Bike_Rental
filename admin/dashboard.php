<?php
// Start processing before including the header file
session_start();
require_once '../db/db.php';

// Function to ensure user is admin
if (!function_exists('requireAdmin')) {
    function requireAdmin() {
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
            $_SESSION['error_message'] = "You must be an administrator to access this page";
            header("Location: ../auth/login.php");
            exit();
        }
    }
}

// Require admin privileges to access this page
requireAdmin();

// Get dashboard statistics
try {
    // Total bikes
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM bikes");
    $stmt->execute();
    $total_bikes = $stmt->fetch()['total'];
    
    // Available bikes
    $stmt = $pdo->prepare("SELECT COUNT(*) as available FROM bikes WHERE status = 'available'");
    $stmt->execute();
    $available_bikes = $stmt->fetch()['available'];
    
    // Reserved bikes
    $stmt = $pdo->prepare("SELECT COUNT(*) as reserved FROM bikes WHERE status = 'reserved'");
    $stmt->execute();
    $reserved_bikes = $stmt->fetch()['reserved'];
    
    // Bikes in maintenance
    $stmt = $pdo->prepare("SELECT COUNT(*) as maintenance FROM bikes WHERE status = 'maintenance'");
    $stmt->execute();
    $maintenance_bikes = $stmt->fetch()['maintenance'];
    
    // Total users
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM users WHERE role = 'user'");
    $stmt->execute();
    $total_users = $stmt->fetch()['total'];
    
    // Active reservations
    $stmt = $pdo->prepare("SELECT COUNT(*) as active FROM reservations WHERE status IN ('pending', 'confirmed')");
    $stmt->execute();
    $active_reservations = $stmt->fetch()['active'];
    
    // Total reservations
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM reservations");
    $stmt->execute();
    $total_reservations = $stmt->fetch()['total'];
    
    // Pending damage reports
    $stmt = $pdo->prepare("SELECT COUNT(*) as pending FROM damages WHERE status = 'reported'");
    $stmt->execute();
    $pending_damages = $stmt->fetch()['pending'];
    
    // Recent reservations
    $stmt = $pdo->prepare("
        SELECT r.*, u.first_name, u.last_name, u.email, b.bike_name
        FROM reservations r
        JOIN users u ON r.user_id = u.user_id
        JOIN bikes b ON r.bike_id = b.bike_id
        ORDER BY r.created_at DESC
        LIMIT 5
    ");
    $stmt->execute();
    $recent_reservations = $stmt->fetchAll();
    
    // Recent damage reports
    $stmt = $pdo->prepare("
        SELECT d.*, u.first_name, u.last_name, u.email, b.bike_name
        FROM damages d
        JOIN users u ON d.user_id = u.user_id
        JOIN bikes b ON d.bike_id = b.bike_id
        ORDER BY d.reported_at DESC
        LIMIT 5
    ");
    $stmt->execute();
    $recent_damages = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $_SESSION['error_message'] = "Error: " . $e->getMessage();
}

// Now that all redirects are complete, include the header which will start HTML output
include '../includes/header.php';
?>

<div class="bg-white dark:bg-gray-800 p-8 rounded-lg shadow-md mb-8">
    <h1 class="text-3xl font-bold mb-8 dark:text-white">Admin Dashboard</h1>
    
    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <!-- Bike Status -->
        <div class="bg-purple-50/70 dark:bg-purple-900/20 p-6 rounded-lg border border-purple-100 dark:border-purple-800">
            <h3 class="text-lg font-semibold text-purple-800 dark:text-purple-200 mb-2">Bike Fleet</h3>
            <div class="text-3xl font-bold text-purple-600 dark:text-purple-300 mb-4"><?php echo $total_bikes; ?></div>
            <div class="flex flex-col space-y-2 text-sm">
                <div class="flex justify-between items-center">
                    <span class="dark:text-gray-300">Available:</span>
                    <span class="font-medium text-green-600 dark:text-green-400"><?php echo $available_bikes; ?></span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="dark:text-gray-300">Reserved:</span>
                    <span class="font-medium text-yellow-600 dark:text-yellow-400"><?php echo $reserved_bikes; ?></span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="dark:text-gray-300">Maintenance:</span>
                    <span class="font-medium text-red-600 dark:text-red-400"><?php echo $maintenance_bikes; ?></span>
                </div>
            </div>
            <div class="mt-4 flex space-x-2">
                <a href="bikes/index.php" class="inline-block text-purple-600 dark:text-purple-400 hover:text-purple-800 dark:hover:text-purple-300 text-sm font-medium">
                    Manage Bikes <i class="fas fa-arrow-right ml-1"></i>
                </a>
                <a href="bikes/index.php?action=images" class="inline-block text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 text-sm font-medium">
                    <i class="fas fa-images mr-1"></i> Images
                </a>
            </div>
        </div>
        
        <!-- Users -->
        <div class="bg-purple-50/70 dark:bg-purple-900/20 p-6 rounded-lg border border-purple-100 dark:border-purple-800">
            <h3 class="text-lg font-semibold text-purple-800 dark:text-purple-200 mb-2">Users</h3>
            <div class="text-3xl font-bold text-purple-600 dark:text-purple-300 mb-4"><?php echo $total_users; ?></div>
            <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">Registered customer accounts</p>
            <a href="users/index.php" class="mt-4 inline-block text-purple-600 dark:text-purple-400 hover:text-purple-800 dark:hover:text-purple-300 text-sm font-medium">
                View All Users <i class="fas fa-arrow-right ml-1"></i>
            </a>
        </div>
        
        <!-- Reservations -->
        <div class="bg-purple-50/50 dark:bg-purple-900/15 p-6 rounded-lg border border-purple-100 dark:border-purple-800">
            <h3 class="text-lg font-semibold text-purple-800 dark:text-purple-200 mb-2">Reservations</h3>
            <div class="text-3xl font-bold text-purple-600 dark:text-purple-300 mb-4"><?php echo $total_reservations; ?></div>
            <div class="flex justify-between items-center text-sm">
                <span class="dark:text-gray-300">Active Bookings:</span>
                <span class="font-medium dark:text-gray-200"><?php echo $active_reservations; ?></span>
            </div>
            <a href="reports/reservations.php" class="mt-4 inline-block text-purple-600 dark:text-purple-400 hover:text-purple-800 dark:hover:text-purple-300 text-sm font-medium">
                View All Reservations <i class="fas fa-arrow-right ml-1"></i>
            </a>
        </div>
        
        <!-- Damage Reports -->
        <div class="bg-purple-50/30 dark:bg-purple-900/10 p-6 rounded-lg border border-purple-100 dark:border-purple-800">
            <h3 class="text-lg font-semibold text-purple-800 dark:text-purple-200 mb-2">Damage Reports</h3>
            <div class="text-3xl font-bold text-purple-600 dark:text-purple-300 mb-4"><?php echo $pending_damages; ?></div>
            <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">Pending damage reports requiring review</p>
            <a href="reports/damages.php" class="mt-4 inline-block text-purple-600 dark:text-purple-400 hover:text-purple-800 dark:hover:text-purple-300 text-sm font-medium">
                View Damage Reports <i class="fas fa-arrow-right ml-1"></i>
            </a>
        </div>
    </div>
    
    <!-- Quick Actions -->
    <div class="mb-8">
        <h2 class="text-xl font-semibold mb-4 dark:text-white">Quick Actions</h2>
        <div class="flex flex-wrap gap-4">
            <a href="bikes/index.php?action=add" class="px-4 py-2 bg-gradient-to-r from-purple-500 to-purple-700 hover:from-purple-600 hover:to-purple-800 text-white rounded-md">
                <i class="fas fa-plus mr-2"></i>Add New Bike
            </a>
            <a href="maintenance/index.php?action=schedule" class="px-4 py-2 bg-gradient-to-r from-purple-400 to-purple-600 hover:from-purple-500 hover:to-purple-700 text-white rounded-md">
                <i class="fas fa-tools mr-2"></i>Schedule Maintenance
            </a>
            <a href="reports/damages.php" class="px-4 py-2 bg-gradient-to-r from-purple-600 to-purple-800 hover:from-purple-700 hover:to-purple-900 text-white rounded-md">
                <i class="fas fa-exclamation-triangle mr-2"></i>Review Damage Reports
            </a>
        </div>
    </div>
    
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <!-- Recent Reservations -->
        <div>
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-xl font-semibold dark:text-white">Recent Reservations</h2>
                <a href="#" class="text-purple-600 dark:text-purple-400 hover:text-purple-800 dark:hover:text-purple-300 text-sm">View All</a>
            </div>
            <div class="bg-gray-50 dark:bg-gray-700 rounded-lg border border-gray-200 dark:border-gray-600">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-600">
                        <thead class="bg-gray-100 dark:bg-gray-800">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">User</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Bike</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Status</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Created</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-600">
                            <?php if (empty($recent_reservations)): ?>
                                <tr>
                                    <td colspan="4" class="px-6 py-4 text-center text-gray-500 dark:text-gray-400">No recent reservations found.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($recent_reservations as $reservation): ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm font-medium text-gray-900 dark:text-gray-200">
                                                <?php echo htmlspecialchars($reservation['first_name'] . ' ' . $reservation['last_name']); ?>
                                            </div>
                                            <div class="text-sm text-gray-500 dark:text-gray-400">
                                                <?php echo htmlspecialchars($reservation['email']); ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                            <?php echo htmlspecialchars($reservation['bike_name']); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <?php if ($reservation['status'] === 'pending'): ?>
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 dark:bg-yellow-800 text-yellow-800 dark:text-yellow-200">
                                                    Pending
                                                </span>
                                            <?php elseif ($reservation['status'] === 'confirmed'): ?>
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 dark:bg-green-800 text-green-800 dark:text-green-200">
                                                    Confirmed
                                                </span>
                                            <?php elseif ($reservation['status'] === 'completed'): ?>
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 dark:bg-blue-800 text-blue-800 dark:text-blue-200">
                                                    Completed
                                                </span>
                                            <?php else: ?>
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 dark:bg-red-800 text-red-800 dark:text-red-200">
                                                    Cancelled
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                            <?php 
                                                $created_at = new DateTime($reservation['created_at']);
                                                echo $created_at->format('M j, Y g:i A');
                                            ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Recent Damage Reports -->
        <div>
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-xl font-semibold dark:text-white">Recent Damage Reports</h2>
                <a href="#" class="text-purple-600 dark:text-purple-400 hover:text-purple-800 dark:hover:text-purple-300 text-sm">View All</a>
            </div>
            <div class="bg-gray-50 dark:bg-gray-700 rounded-lg border border-gray-200 dark:border-gray-600">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-600">
                        <thead class="bg-gray-100 dark:bg-gray-800">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Bike</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Reported By</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Status</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Reported</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-600">
                            <?php if (empty($recent_damages)): ?>
                                <tr>
                                    <td colspan="4" class="px-6 py-4 text-center text-gray-500 dark:text-gray-400">No recent damage reports found.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($recent_damages as $damage): ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                            <?php echo htmlspecialchars($damage['bike_name']); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm font-medium text-gray-900 dark:text-gray-200">
                                                <?php echo htmlspecialchars($damage['first_name'] . ' ' . $damage['last_name']); ?>
                                            </div>
                                            <div class="text-sm text-gray-500 dark:text-gray-400">
                                                <?php echo htmlspecialchars($damage['email']); ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <?php if ($damage['status'] === 'reported'): ?>
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 dark:bg-yellow-800 text-yellow-800 dark:text-yellow-200">
                                                    Reported
                                                </span>
                                            <?php elseif ($damage['status'] === 'under_review'): ?>
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 dark:bg-blue-800 text-blue-800 dark:text-blue-200">
                                                    Under Review
                                                </span>
                                            <?php else: ?>
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 dark:bg-green-800 text-green-800 dark:text-green-200">
                                                    Resolved
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                            <?php 
                                                $reported_at = new DateTime($damage['reported_at']);
                                                echo $reported_at->format('M j, Y g:i A');
                                            ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>