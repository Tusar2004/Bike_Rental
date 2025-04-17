<?php
// Start processing before including the header file
session_start();
require_once '../../db/db.php';

// Function to ensure user is admin
if (!function_exists('requireAdmin')) {
    function requireAdmin() {
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
            $_SESSION['error_message'] = "You must be an administrator to access this page";
            header("Location: ../../auth/login.php");
            exit();
        }
    }
}

// Function to sanitize input
if (!function_exists('sanitize')) {
    function sanitize($data) {
        return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
    }
}

// Require admin privileges to access this page
requireAdmin();

// Handle reservation actions
if (isset($_POST['action']) && isset($_POST['reservation_id'])) {
    $reservation_id = intval($_POST['reservation_id']);
    $action = $_POST['action'];
    
    try {
        if ($action === 'confirm') {
            // Update reservation status to confirmed
            $stmt = $pdo->prepare("UPDATE reservations SET status = 'confirmed' WHERE reservation_id = ?");
            if ($stmt->execute([$reservation_id])) {
                $_SESSION['flash_message'] = "Reservation confirmed successfully";
            } else {
                $_SESSION['error_message'] = "Failed to confirm reservation";
            }
        } elseif ($action === 'complete') {
            // Update reservation status to completed
            $stmt = $pdo->prepare("UPDATE reservations SET status = 'completed' WHERE reservation_id = ?");
            
            if ($stmt->execute([$reservation_id])) {
                // Get bike_id to update its status
                $stmt = $pdo->prepare("SELECT bike_id FROM reservations WHERE reservation_id = ?");
                $stmt->execute([$reservation_id]);
                $bike = $stmt->fetch();
                
                if ($bike) {
                    // Update bike status to available
                    $stmt = $pdo->prepare("UPDATE bikes SET status = 'available' WHERE bike_id = ?");
                    $stmt->execute([$bike['bike_id']]);
                }
                
                $_SESSION['flash_message'] = "Reservation marked as completed";
            } else {
                $_SESSION['error_message'] = "Failed to complete reservation";
            }
        } elseif ($action === 'cancel') {
            // Update reservation status to cancelled
            $stmt = $pdo->prepare("UPDATE reservations SET status = 'cancelled' WHERE reservation_id = ?");
            
            if ($stmt->execute([$reservation_id])) {
                // Get bike_id to update its status
                $stmt = $pdo->prepare("SELECT bike_id FROM reservations WHERE reservation_id = ?");
                $stmt->execute([$reservation_id]);
                $bike = $stmt->fetch();
                
                if ($bike) {
                    // Update bike status to available
                    $stmt = $pdo->prepare("UPDATE bikes SET status = 'available' WHERE bike_id = ?");
                    $stmt->execute([$bike['bike_id']]);
                }
                
                $_SESSION['flash_message'] = "Reservation cancelled successfully";
            } else {
                $_SESSION['error_message'] = "Failed to cancel reservation";
            }
        }
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Error: " . $e->getMessage();
    }
    
    // Redirect to refresh the page
    header("Location: reservations.php");
    exit();
}

// Set up pagination and filtering
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;

$status_filter = isset($_GET['status']) ? sanitize($_GET['status']) : '';
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$date_filter = isset($_GET['date_filter']) ? sanitize($_GET['date_filter']) : '';

// Build the query with filters
$query = "
    SELECT r.*, u.first_name, u.last_name, u.email, b.bike_name, b.bike_type, b.image_path, 
           COALESCE(p.amount, 0) as total_amount
    FROM reservations r
    JOIN users u ON r.user_id = u.user_id
    JOIN bikes b ON r.bike_id = b.bike_id
    LEFT JOIN payments p ON r.reservation_id = p.reservation_id
    WHERE 1=1
";

$count_query = "SELECT COUNT(*) FROM reservations r WHERE 1=1";
$params = [];
$count_params = [];

if ($status_filter) {
    $query .= " AND r.status = ?";
    $count_query .= " AND r.status = ?";
    $params[] = $status_filter;
    $count_params[] = $status_filter;
}

if ($search) {
    $query .= " AND (b.bike_name LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)";
    $count_query .= " AND r.reservation_id IN (
        SELECT r2.reservation_id FROM reservations r2
        JOIN users u2 ON r2.user_id = u2.user_id
        JOIN bikes b2 ON r2.bike_id = b2.bike_id
        WHERE b2.bike_name LIKE ? OR u2.first_name LIKE ? OR u2.last_name LIKE ? OR u2.email LIKE ?
    )";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
    $count_params = array_merge($count_params, [$search_param, $search_param, $search_param, $search_param]);
}

if ($date_filter === 'today') {
    $query .= " AND DATE(r.start_time) = CURDATE()";
    $count_query .= " AND DATE(r.start_time) = CURDATE()";
} elseif ($date_filter === 'tomorrow') {
    $query .= " AND DATE(r.start_time) = DATE_ADD(CURDATE(), INTERVAL 1 DAY)";
    $count_query .= " AND DATE(r.start_time) = DATE_ADD(CURDATE(), INTERVAL 1 DAY)";
} elseif ($date_filter === 'this_week') {
    $query .= " AND YEARWEEK(r.start_time, 1) = YEARWEEK(CURDATE(), 1)";
    $count_query .= " AND YEARWEEK(r.start_time, 1) = YEARWEEK(CURDATE(), 1)";
}

$query .= " ORDER BY r.start_time DESC LIMIT ? OFFSET ?";
$params[] = $perPage;
$params[] = $offset;

try {
    // Get total count for pagination
    $stmt = $pdo->prepare($count_query);
    $stmt->execute($count_params);
    $total_reservations = $stmt->fetchColumn();
    $totalPages = ceil($total_reservations / $perPage);
    
    // Get reservations
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $reservations = $stmt->fetchAll();
} catch (PDOException $e) {
    $_SESSION['error_message'] = "Error fetching reservations: " . $e->getMessage();
    $reservations = [];
    $totalPages = 0;
}

// Now that all redirects are complete, include the header which will start HTML output
include '../../includes/header.php';
?>

<div class="bg-white dark:bg-gray-800 p-8 rounded-lg shadow-md mb-8">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold dark:text-white">All Reservations</h1>
        <div class="flex items-center gap-4">
            <a href="../dashboard.php" class="text-purple-600 hover:text-purple-800 dark:text-purple-400 dark:hover:text-purple-300">
                <i class="fas fa-arrow-left mr-1"></i>Back to Dashboard
            </a>
            <form action="" method="GET" class="flex items-center">
                <div class="relative">
                    <input type="text" name="search" placeholder="Search reservations..." 
                           class="pl-10 pr-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                           value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                    <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                        <i class="fas fa-search text-gray-400"></i>
                    </div>
                </div>
                <button type="submit" class="ml-2 bg-gradient-to-r from-purple-500 to-purple-700 hover:from-purple-600 hover:to-purple-800 text-white px-4 py-2 rounded-md">
                    Search
                </button>
                <?php if (isset($_GET['search'])): ?>
                    <a href="reservations.php" class="ml-2 text-purple-600 hover:text-purple-800 dark:text-purple-400 dark:hover:text-purple-300">Clear</a>
                <?php endif; ?>
            </form>
        </div>
    </div>
    
    <!-- Filter Options -->
    <div class="flex flex-wrap justify-between mb-6">
        <!-- Status Filter Tabs -->
        <div class="mb-4 sm:mb-0">
            <div class="border-b border-gray-200 dark:border-gray-700">
                <ul class="flex flex-wrap -mb-px text-sm font-medium text-center">
                    <li class="mr-2">
                        <a href="reservations.php" class="inline-block p-4 <?php echo !$status_filter ? 'text-purple-600 dark:text-purple-400 border-b-2 border-purple-600 dark:border-purple-400 rounded-t-lg active' : 'text-gray-500 dark:text-gray-400 border-b-2 border-transparent rounded-t-lg hover:text-gray-600 dark:hover:text-gray-300 hover:border-gray-300 dark:hover:border-gray-600'; ?>">
                            All Reservations
                        </a>
                    </li>
                    <li class="mr-2">
                        <a href="reservations.php?status=pending" class="inline-block p-4 <?php echo $status_filter === 'pending' ? 'text-purple-600 dark:text-purple-400 border-b-2 border-purple-600 dark:border-purple-400 rounded-t-lg active' : 'text-gray-500 dark:text-gray-400 border-b-2 border-transparent rounded-t-lg hover:text-gray-600 dark:hover:text-gray-300 hover:border-gray-300 dark:hover:border-gray-600'; ?>">
                            Pending
                        </a>
                    </li>
                    <li class="mr-2">
                        <a href="reservations.php?status=confirmed" class="inline-block p-4 <?php echo $status_filter === 'confirmed' ? 'text-purple-600 dark:text-purple-400 border-b-2 border-purple-600 dark:border-purple-400 rounded-t-lg active' : 'text-gray-500 dark:text-gray-400 border-b-2 border-transparent rounded-t-lg hover:text-gray-600 dark:hover:text-gray-300 hover:border-gray-300 dark:hover:border-gray-600'; ?>">
                            Confirmed
                        </a>
                    </li>
                    <li class="mr-2">
                        <a href="reservations.php?status=completed" class="inline-block p-4 <?php echo $status_filter === 'completed' ? 'text-purple-600 dark:text-purple-400 border-b-2 border-purple-600 dark:border-purple-400 rounded-t-lg active' : 'text-gray-500 dark:text-gray-400 border-b-2 border-transparent rounded-t-lg hover:text-gray-600 dark:hover:text-gray-300 hover:border-gray-300 dark:hover:border-gray-600'; ?>">
                            Completed
                        </a>
                    </li>
                    <li class="mr-2">
                        <a href="reservations.php?status=cancelled" class="inline-block p-4 <?php echo $status_filter === 'cancelled' ? 'text-purple-600 dark:text-purple-400 border-b-2 border-purple-600 dark:border-purple-400 rounded-t-lg active' : 'text-gray-500 dark:text-gray-400 border-b-2 border-transparent rounded-t-lg hover:text-gray-600 dark:hover:text-gray-300 hover:border-gray-300 dark:hover:border-gray-600'; ?>">
                            Cancelled
                        </a>
                    </li>
                </ul>
            </div>
        </div>
        
        <!-- Date Filter -->
        <div class="flex items-center">
            <span class="mr-2 text-gray-600 dark:text-gray-400">Filter by date:</span>
            <select id="date-filter" class="bg-gray-50 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 text-gray-900 dark:text-white text-sm rounded-lg focus:ring-purple-500 focus:border-purple-500 p-2.5" onchange="window.location.href=this.value">
                <option value="reservations.php<?php echo $status_filter ? '?status=' . $status_filter : ''; ?>" <?php echo !$date_filter ? 'selected' : ''; ?>>All Dates</option>
                <option value="reservations.php?date_filter=today<?php echo $status_filter ? '&status=' . $status_filter : ''; ?>" <?php echo $date_filter === 'today' ? 'selected' : ''; ?>>Today</option>
                <option value="reservations.php?date_filter=tomorrow<?php echo $status_filter ? '&status=' . $status_filter : ''; ?>" <?php echo $date_filter === 'tomorrow' ? 'selected' : ''; ?>>Tomorrow</option>
                <option value="reservations.php?date_filter=this_week<?php echo $status_filter ? '&status=' . $status_filter : ''; ?>" <?php echo $date_filter === 'this_week' ? 'selected' : ''; ?>>This Week</option>
            </select>
        </div>
    </div>
    
    <!-- Reservations Table -->
    <?php if (empty($reservations)): ?>
        <div class="bg-gray-50 dark:bg-gray-700 p-6 text-center rounded-lg">
            <p class="text-gray-600 dark:text-gray-400">No reservations found.</p>
        </div>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="min-w-full bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden">
                <thead class="bg-gray-100 dark:bg-gray-700">
                    <tr>
                        <th class="py-3 px-4 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Reservation ID</th>
                        <th class="py-3 px-4 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">User</th>
                        <th class="py-3 px-4 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Bike</th>
                        <th class="py-3 px-4 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Duration</th>
                        <th class="py-3 px-4 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Status</th>
                        <th class="py-3 px-4 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Amount</th>
                        <th class="py-3 px-4 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    <?php foreach ($reservations as $reservation): ?>
                        <tr class="hover:bg-purple-50 dark:hover:bg-purple-900/10 transition-colors duration-150">
                            <td class="py-3 px-4 text-sm text-gray-900 dark:text-white">#<?php echo $reservation['reservation_id']; ?></td>
                            <td class="py-3 px-4">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0 h-8 w-8 bg-purple-100 dark:bg-purple-900 rounded-full flex items-center justify-center">
                                        <i class="fas fa-user text-purple-500 dark:text-purple-300"></i>
                                    </div>
                                    <div class="ml-3">
                                        <div class="text-sm font-medium text-gray-900 dark:text-white">
                                            <?php echo htmlspecialchars($reservation['first_name'] . ' ' . $reservation['last_name']); ?>
                                        </div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">
                                            <?php echo htmlspecialchars($reservation['email']); ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="py-3 px-4">
                                <div class="flex items-center">
                                    <div class="h-10 w-10 flex-shrink-0 rounded-md overflow-hidden bg-gray-100 dark:bg-gray-700">
                                        <img src="<?php echo $reservation['image_path'] ? '../../' . $reservation['image_path'] : '../../assets/images/default-bike.jpg'; ?>" 
                                             alt="<?php echo htmlspecialchars($reservation['bike_name']); ?>"
                                             class="h-full w-full object-cover">
                                    </div>
                                    <div class="ml-3">
                                        <div class="text-sm font-medium text-gray-900 dark:text-white">
                                            <?php echo htmlspecialchars($reservation['bike_name']); ?>
                                        </div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">
                                            <?php echo htmlspecialchars($reservation['bike_type']); ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="py-3 px-4">
                                <div class="text-sm text-gray-900 dark:text-white">
                                    <?php 
                                        $start_time = new DateTime($reservation['start_time']);
                                        $end_time = new DateTime($reservation['end_time']);
                                        echo $start_time->format('M j, g:i A');
                                    ?>
                                </div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">
                                    to <?php echo $end_time->format('M j, g:i A'); ?>
                                </div>
                                <div class="text-xs text-purple-600 dark:text-purple-400 font-medium mt-1">
                                    <?php 
                                        $interval = $start_time->diff($end_time);
                                        $hours = $interval->h + ($interval->days * 24);
                                        echo $hours . ' hour' . ($hours != 1 ? 's' : '');
                                    ?>
                                </div>
                            </td>
                            <td class="py-3 px-4">
                                <?php if ($reservation['status'] === 'pending'): ?>
                                    <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 dark:bg-yellow-800 text-yellow-800 dark:text-yellow-200">
                                        Pending
                                    </span>
                                <?php elseif ($reservation['status'] === 'confirmed'): ?>
                                    <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 dark:bg-green-800 text-green-800 dark:text-green-200">
                                        Confirmed
                                    </span>
                                <?php elseif ($reservation['status'] === 'completed'): ?>
                                    <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 dark:bg-blue-800 text-blue-800 dark:text-blue-200">
                                        Completed
                                    </span>
                                <?php else: ?>
                                    <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 dark:bg-red-800 text-red-800 dark:text-red-200">
                                        Cancelled
                                    </span>
                                <?php endif; ?>
                                <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                    <?php 
                                        $created_at = new DateTime($reservation['created_at']);
                                        echo 'Created: ' . $created_at->format('M j, Y');
                                    ?>
                                </div>
                            </td>
                            <td class="py-3 px-4 text-sm font-medium text-gray-900 dark:text-white">
                                ₹<?php echo number_format($reservation['total_amount'], 2); ?>
                            </td>
                            <td class="py-3 px-4 text-sm">
                                <div class="flex flex-col space-y-2">
                                    <?php if ($reservation['status'] === 'pending'): ?>
                                        <form action="reservations.php" method="POST">
                                            <input type="hidden" name="reservation_id" value="<?php echo $reservation['reservation_id']; ?>">
                                            <input type="hidden" name="action" value="confirm">
                                            <button type="submit" class="px-3 py-1 bg-gradient-to-r from-green-500 to-green-600 hover:from-green-600 hover:to-green-700 text-white text-xs rounded-md w-full transition-all duration-150">
                                                <i class="fas fa-check mr-1"></i>Confirm
                                            </button>
                                        </form>
                                        
                                        <form action="reservations.php" method="POST" onsubmit="return confirm('Are you sure you want to cancel this reservation?');">
                                            <input type="hidden" name="reservation_id" value="<?php echo $reservation['reservation_id']; ?>">
                                            <input type="hidden" name="action" value="cancel">
                                            <button type="submit" class="px-3 py-1 bg-gradient-to-r from-red-500 to-red-600 hover:from-red-600 hover:to-red-700 text-white text-xs rounded-md w-full transition-all duration-150">
                                                <i class="fas fa-times mr-1"></i>Cancel
                                            </button>
                                        </form>
                                    <?php elseif ($reservation['status'] === 'confirmed'): ?>
                                        <form action="reservations.php" method="POST">
                                            <input type="hidden" name="reservation_id" value="<?php echo $reservation['reservation_id']; ?>">
                                            <input type="hidden" name="action" value="complete">
                                            <button type="submit" class="px-3 py-1 bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 text-white text-xs rounded-md w-full transition-all duration-150">
                                                <i class="fas fa-check-circle mr-1"></i>Complete
                                            </button>
                                        </form>
                                        
                                        <form action="reservations.php" method="POST" onsubmit="return confirm('Are you sure you want to cancel this reservation?');">
                                            <input type="hidden" name="reservation_id" value="<?php echo $reservation['reservation_id']; ?>">
                                            <input type="hidden" name="action" value="cancel">
                                            <button type="submit" class="px-3 py-1 bg-gradient-to-r from-red-500 to-red-600 hover:from-red-600 hover:to-red-700 text-white text-xs rounded-md w-full transition-all duration-150">
                                                <i class="fas fa-times mr-1"></i>Cancel
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    
                                    <a href="#" class="px-3 py-1 bg-gradient-to-r from-purple-500 to-purple-700 hover:from-purple-600 hover:to-purple-800 text-white text-xs rounded-md text-center transition-all duration-150">
                                        <i class="fas fa-eye mr-1"></i>Details
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <div class="flex justify-center mt-6">
                <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo ($page - 1); ?><?php echo $status_filter ? '&status=' . urlencode($status_filter) : ''; ?><?php echo $date_filter ? '&date_filter=' . urlencode($date_filter) : ''; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>" 
                           class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white dark:bg-gray-700 dark:border-gray-600 text-sm font-medium text-gray-500 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600">
                            <span class="sr-only">Previous</span>
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    <?php endif; ?>
                    
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <a href="?page=<?php echo $i; ?><?php echo $status_filter ? '&status=' . urlencode($status_filter) : ''; ?><?php echo $date_filter ? '&date_filter=' . urlencode($date_filter) : ''; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>" 
                           class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white dark:bg-gray-700 dark:border-gray-600 text-sm font-medium <?php echo $i === $page ? 'text-purple-600 bg-purple-50 dark:bg-purple-900 dark:text-purple-300' : 'text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600'; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?php echo ($page + 1); ?><?php echo $status_filter ? '&status=' . urlencode($status_filter) : ''; ?><?php echo $date_filter ? '&date_filter=' . urlencode($date_filter) : ''; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>" 
                           class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white dark:bg-gray-700 dark:border-gray-600 text-sm font-medium text-gray-500 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600">
                            <span class="sr-only">Next</span>
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php endif; ?>
                </nav>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php include '../../includes/footer.php'; ?>