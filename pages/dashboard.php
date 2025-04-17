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

// Function to sanitize input
if (!function_exists('sanitize')) {
    function sanitize($data) {
        return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
    }
}

// Require user to be logged in
requireLogin();

// Get user reservations
$user_id = $_SESSION['user_id'];

// Handle reservation cancellation
if (isset($_POST['cancel_reservation']) && isset($_POST['reservation_id'])) {
    $reservation_id = intval($_POST['reservation_id']);
    
    try {
        // Check if reservation belongs to user and can be cancelled
        $stmt = $pdo->prepare("
            SELECT * FROM reservations 
            WHERE reservation_id = ? AND user_id = ? AND status IN ('pending', 'confirmed')
        ");
        $stmt->execute([$reservation_id, $user_id]);
        $reservation = $stmt->fetch();
        
        if (!$reservation) {
            $_SESSION['error_message'] = "Invalid reservation or it cannot be cancelled";
        } else {
            // Begin transaction
            $pdo->beginTransaction();
            
            // Update reservation status
            $stmt = $pdo->prepare("UPDATE reservations SET status = 'cancelled' WHERE reservation_id = ?");
            $stmt->execute([$reservation_id]);
            
            // Update bike status if it was reserved
            if ($reservation['status'] === 'confirmed') {
                $stmt = $pdo->prepare("UPDATE bikes SET status = 'available' WHERE bike_id = ?");
                $stmt->execute([$reservation['bike_id']]);
            }
            
            // Commit transaction
            $pdo->commit();
            
            $_SESSION['flash_message'] = "Reservation cancelled successfully";
        }
    } catch (PDOException $e) {
        // Rollback transaction on error
        $pdo->rollBack();
        $_SESSION['error_message'] = "Error: " . $e->getMessage();
    }
    
    // Redirect to refresh the page
    header("Location: dashboard.php");
    exit();
}

// Get user's reservations with bike details and payment status
try {
    $stmt = $pdo->prepare("
        SELECT r.*, b.bike_name, b.bike_type, b.image_path, b.hourly_rate, 
               p.payment_status, p.payment_method
        FROM reservations r
        JOIN bikes b ON r.bike_id = b.bike_id
        LEFT JOIN payments p ON r.reservation_id = p.reservation_id
        WHERE r.user_id = ?
        ORDER BY r.start_time DESC
    ");
    $stmt->execute([$user_id]);
    $reservations = $stmt->fetchAll();
} catch (PDOException $e) {
    $_SESSION['error_message'] = "Error retrieving reservations: " . $e->getMessage();
    $reservations = [];
}

// Get user information
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
} catch (PDOException $e) {
    $_SESSION['error_message'] = "Error retrieving user information: " . $e->getMessage();
    $user = [];
}

// Now that all redirects are complete, include the header which will start HTML output
include '../includes/header.php';
?>

<div class="bg-white dark:bg-gray-800 p-8 rounded-lg shadow-md mb-8">
    <h1 class="text-3xl font-bold mb-6 dark:text-white">My Dashboard</h1>
    
    <!-- User Profile Summary -->
    <div class="bg-gray-50 dark:bg-gray-700 p-6 rounded-lg border border-gray-200 dark:border-gray-600 mb-8">
        <div class="flex flex-col md:flex-row justify-between">
            <div>
                <h2 class="text-xl font-semibold mb-2 dark:text-white">Welcome, <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h2>
                <p class="text-gray-600 dark:text-gray-300"><?php echo htmlspecialchars($user['email']); ?></p>
            </div>
            <div class="mt-4 md:mt-0">
                <a href="book.php" class="bg-gradient-to-r from-purple-500 to-purple-700 hover:from-purple-600 hover:to-purple-800 text-white font-bold py-2 px-4 rounded-lg">
                    <i class="fas fa-bicycle mr-2"></i>Rent a Bike
                </a>
            </div>
        </div>
    </div>
    
    <!-- Tabs Navigation -->
    <div class="border-b border-gray-200 dark:border-gray-700 mb-6">
        <ul class="flex flex-wrap -mb-px text-sm font-medium text-center text-gray-500 dark:text-gray-400">
            <li class="mr-2">
                <a href="#active" class="inline-block p-4 text-purple-600 dark:text-purple-400 border-b-2 border-purple-600 dark:border-purple-400 rounded-t-lg active" aria-current="page">
                    Active Reservations
                </a>
            </li>
            <li class="mr-2">
                <a href="#history" class="inline-block p-4 hover:text-gray-600 dark:hover:text-gray-300 hover:border-gray-300 dark:hover:border-gray-600 border-b-2 border-transparent rounded-t-lg">
                    Reservation History
                </a>
            </li>
        </ul>
    </div>
    
    <!-- Active Reservations Section -->
    <div id="active-reservations" class="mb-8">
        <h2 class="text-xl font-semibold mb-4 dark:text-white">Active Reservations</h2>
        
        <?php 
        $activeFound = false;
        foreach ($reservations as $reservation): 
            if ($reservation['status'] === 'pending' || $reservation['status'] === 'confirmed'):
                $activeFound = true;
                
                // Calculate rental duration and cost
                $start = new DateTime($reservation['start_time']);
                $end = new DateTime($reservation['end_time']);
                $interval = $start->diff($end);
                $hours = $interval->h + ($interval->days * 24);
                $total_cost = $hours * $reservation['hourly_rate'];
        ?>
            <div class="bg-white dark:bg-gray-700 border dark:border-gray-600 rounded-lg shadow-sm mb-4 overflow-hidden">
                <div class="flex flex-col md:flex-row">
                    <!-- Bike image -->
                    <div class="md:w-1/4 p-4">
                        <div class="h-48 rounded-md overflow-hidden bg-gray-100 dark:bg-gray-800">
                            <img src="<?php echo $reservation['image_path'] ? '../' . $reservation['image_path'] : '../assets/images/default-bike.jpg'; ?>" 
                                 alt="<?php echo htmlspecialchars($reservation['bike_name']); ?>"
                                 class="h-full w-full object-cover">
                        </div>
                    </div>
                    
                    <!-- Reservation details -->
                    <div class="md:w-1/2 p-4">
                        <h3 class="text-lg font-semibold dark:text-white"><?php echo htmlspecialchars($reservation['bike_name']); ?></h3>
                        <p class="text-gray-500 dark:text-gray-400 text-sm"><?php echo htmlspecialchars($reservation['bike_type']); ?></p>
                        
                        <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <p class="text-sm text-gray-500 dark:text-gray-400">Reservation #</p>
                                <p class="font-medium dark:text-gray-200"><?php echo $reservation['reservation_id']; ?></p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500 dark:text-gray-400">Status</p>
                                <?php if ($reservation['status'] === 'pending'): ?>
                                    <span class="inline-block px-2 py-1 text-xs font-semibold rounded-full bg-yellow-100 dark:bg-yellow-900/50 text-yellow-800 dark:text-yellow-300">
                                        Pending
                                    </span>
                                <?php elseif ($reservation['status'] === 'confirmed'): ?>
                                    <span class="inline-block px-2 py-1 text-xs font-semibold rounded-full bg-green-100 dark:bg-green-900/50 text-green-800 dark:text-green-300">
                                        Confirmed
                                    </span>
                                <?php endif; ?>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500 dark:text-gray-400">Pickup</p>
                                <p class="font-medium dark:text-gray-200">
                                    <?php echo $start->format('M j, Y g:i A'); ?><br>
                                    <?php echo htmlspecialchars($reservation['pickup_location']); ?>
                                </p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500 dark:text-gray-400">Drop-off</p>
                                <p class="font-medium dark:text-gray-200">
                                    <?php echo $end->format('M j, Y g:i A'); ?><br>
                                    <?php echo htmlspecialchars($reservation['dropoff_location']); ?>
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Actions and payment -->
                    <div class="md:w-1/4 bg-gray-50 dark:bg-gray-800 p-4 flex flex-col justify-between">
                        <div>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Total Cost</p>
                            <p class="text-lg font-semibold text-purple-600 dark:text-purple-400">₹<?php echo number_format($total_cost, 2); ?></p>
                            
                            <?php if (isset($reservation['payment_status'])): ?>
                                <div class="mt-2">
                                    <p class="text-sm text-gray-500 dark:text-gray-400">Payment</p>
                                    <?php if ($reservation['payment_status'] === 'completed'): ?>
                                        <span class="inline-block px-2 py-1 text-xs font-semibold rounded-full bg-green-100 dark:bg-green-900/50 text-green-800 dark:text-green-300">
                                            Paid
                                        </span>
                                    <?php elseif ($reservation['payment_status'] === 'pending'): ?>
                                        <span class="inline-block px-2 py-1 text-xs font-semibold rounded-full bg-yellow-100 dark:bg-yellow-900/50 text-yellow-800 dark:text-yellow-300">
                                            Pending
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-block px-2 py-1 text-xs font-semibold rounded-full bg-red-100 dark:bg-red-900/50 text-red-800 dark:text-red-300">
                                            <?php echo ucfirst($reservation['payment_status']); ?>
                                        </span>
                                    <?php endif; ?>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Via <?php echo ucfirst($reservation['payment_method']); ?></p>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="mt-4 space-y-2">
                            <?php if ($reservation['status'] === 'pending' && (!isset($reservation['payment_status']) || $reservation['payment_status'] !== 'completed')): ?>
                                <a href="payment.php?id=<?php echo $reservation['reservation_id']; ?>" class="block text-center bg-gradient-to-r from-purple-500 to-purple-700 hover:from-purple-600 hover:to-purple-800 text-white font-semibold py-2 px-4 rounded">
                                    Complete Payment
                                </a>
                            <?php endif; ?>
                            
                            <?php if ($reservation['status'] === 'pending' || $reservation['status'] === 'confirmed'): ?>
                                <form action="dashboard.php" method="POST" onsubmit="return confirm('Are you sure you want to cancel this reservation?');">
                                    <input type="hidden" name="reservation_id" value="<?php echo $reservation['reservation_id']; ?>">
                                    <button type="submit" name="cancel_reservation" class="w-full text-red-500 dark:text-red-400 hover:text-red-700 dark:hover:text-red-300 font-semibold py-2 px-4 border border-red-500 dark:border-red-400 hover:border-red-700 dark:hover:border-red-300 rounded">
                                        Cancel Reservation
                                    </button>
                                </form>
                            <?php endif; ?>
                            
                            <?php if ($reservation['status'] === 'confirmed'): ?>
                                <a href="report-damage.php?reservation_id=<?php echo $reservation['reservation_id']; ?>" class="block text-center text-gray-700 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white font-semibold py-2 px-4 border border-gray-300 dark:border-gray-500 hover:border-gray-500 dark:hover:border-gray-400 rounded">
                                    <i class="fas fa-exclamation-triangle mr-1"></i> Report Damage
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php 
            endif; 
        endforeach;
        
        if (!$activeFound):
        ?>
            <div class="bg-gray-50 dark:bg-gray-700 p-6 text-center rounded-lg border border-gray-200 dark:border-gray-600">
                <p class="text-gray-600 dark:text-gray-300">No active reservations found.</p>
                <a href="fleet.php" class="mt-4 inline-block text-purple-600 dark:text-purple-400 hover:text-purple-800 dark:hover:text-purple-300">
                    Browse available bikes <i class="fas fa-arrow-right ml-1"></i>
                </a>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Reservation History Section -->
    <div id="reservation-history" class="hidden">
        <h2 class="text-xl font-semibold mb-4 dark:text-white">Reservation History</h2>
        
        <?php 
        $historyFound = false;
        foreach ($reservations as $reservation): 
            if ($reservation['status'] === 'completed' || $reservation['status'] === 'cancelled'):
                $historyFound = true;
                
                // Calculate rental duration and cost
                $start = new DateTime($reservation['start_time']);
                $end = new DateTime($reservation['end_time']);
                $interval = $start->diff($end);
                $hours = $interval->h + ($interval->days * 24);
                $total_cost = $hours * $reservation['hourly_rate'];
        ?>
            <div class="bg-white dark:bg-gray-700 border dark:border-gray-600 rounded-lg shadow-sm mb-4 overflow-hidden">
                <div class="flex flex-col md:flex-row">
                    <!-- Bike image -->
                    <div class="md:w-1/4 p-4">
                        <div class="h-32 rounded-md overflow-hidden bg-gray-100 dark:bg-gray-800">
                            <img src="<?php echo $reservation['image_path'] ? '../' . $reservation['image_path'] : '../assets/images/default-bike.jpg'; ?>" 
                                 alt="<?php echo htmlspecialchars($reservation['bike_name']); ?>"
                                 class="h-full w-full object-cover">
                        </div>
                    </div>
                    
                    <!-- Reservation details -->
                    <div class="md:w-1/2 p-4">
                        <h3 class="text-lg font-semibold dark:text-white"><?php echo htmlspecialchars($reservation['bike_name']); ?></h3>
                        <p class="text-gray-500 dark:text-gray-400 text-sm"><?php echo htmlspecialchars($reservation['bike_type']); ?></p>
                        
                        <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <p class="text-sm text-gray-500 dark:text-gray-400">Reservation #</p>
                                <p class="font-medium dark:text-gray-200"><?php echo $reservation['reservation_id']; ?></p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500 dark:text-gray-400">Status</p>
                                <?php if ($reservation['status'] === 'completed'): ?>
                                    <span class="inline-block px-2 py-1 text-xs font-semibold rounded-full bg-purple-100 dark:bg-purple-900/50 text-purple-800 dark:text-purple-300">
                                        Completed
                                    </span>
                                <?php else: ?>
                                    <span class="inline-block px-2 py-1 text-xs font-semibold rounded-full bg-red-100 dark:bg-red-900/50 text-red-800 dark:text-red-300">
                                        Cancelled
                                    </span>
                                <?php endif; ?>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500 dark:text-gray-400">Rental Period</p>
                                <p class="font-medium dark:text-gray-200">
                                    <?php echo $start->format('M j, Y g:i A'); ?> - 
                                    <?php echo $end->format('M j, Y g:i A'); ?>
                                </p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500 dark:text-gray-400">Cost</p>
                                <p class="font-medium dark:text-gray-200">₹<?php echo number_format($total_cost, 2); ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Payment info -->
                    <div class="md:w-1/4 bg-gray-50 dark:bg-gray-800 p-4">
                        <div>
                            <?php if ($reservation['status'] === 'completed'): ?>
                                <div class="text-center mb-4">
                                    <i class="fas fa-check-circle text-green-500 dark:text-green-400 text-4xl"></i>
                                    <p class="text-sm text-gray-600 dark:text-gray-300 mt-2">Thank you for renting with us!</p>
                                </div>
                                
                                <a href="#" class="block text-center bg-gradient-to-r from-purple-500 to-purple-700 hover:from-purple-600 hover:to-purple-800 text-white font-semibold py-2 px-4 rounded">
                                    <i class="fas fa-star mr-1"></i> Rate Your Experience
                                </a>
                            <?php else: ?>
                                <div class="text-center mb-4">
                                    <i class="fas fa-times-circle text-red-500 dark:text-red-400 text-4xl"></i>
                                    <p class="text-sm text-gray-600 dark:text-gray-300 mt-2">This reservation was cancelled.</p>
                                </div>
                                
                                <a href="fleet.php" class="block text-center bg-gradient-to-r from-purple-500 to-purple-700 hover:from-purple-600 hover:to-purple-800 text-white font-semibold py-2 px-4 rounded">
                                    Book Again
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php 
            endif; 
        endforeach;
        
        if (!$historyFound):
        ?>
            <div class="bg-gray-50 dark:bg-gray-700 p-6 text-center rounded-lg border border-gray-200 dark:border-gray-600">
                <p class="text-gray-600 dark:text-gray-300">No reservation history found.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Tab switching functionality
        const tabs = document.querySelectorAll('ul.flex li a');
        const activeTab = document.getElementById('active-reservations');
        const historyTab = document.getElementById('reservation-history');
        
        tabs.forEach(tab => {
            tab.addEventListener('click', function(e) {
                e.preventDefault();
                
                // Update active tab styling
                tabs.forEach(t => {
                    t.classList.remove('text-purple-600', 'dark:text-purple-400', 'border-purple-600', 'dark:border-purple-400');
                    t.classList.add('hover:text-gray-600', 'dark:hover:text-gray-300', 'hover:border-gray-300', 'dark:hover:border-gray-600', 'border-transparent');
                });
                this.classList.add('text-purple-600', 'dark:text-purple-400', 'border-purple-600', 'dark:border-purple-400');
                this.classList.remove('hover:text-gray-600', 'dark:hover:text-gray-300', 'hover:border-gray-300', 'dark:hover:border-gray-600', 'border-transparent');
                
                // Show correct content
                if (this.getAttribute('href') === '#active') {
                    activeTab.classList.remove('hidden');
                    historyTab.classList.add('hidden');
                } else {
                    activeTab.classList.add('hidden');
                    historyTab.classList.remove('hidden');
                }
            });
        });
    });
</script>

<?php include '../includes/footer.php'; ?>