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

// Handle damage report actions
if (isset($_POST['action']) && isset($_POST['damage_id'])) {
    $damage_id = intval($_POST['damage_id']);
    $action = $_POST['action'];
    
    try {
        if ($action === 'review') {
            // Update damage status to under_review
            $stmt = $pdo->prepare("UPDATE damages SET status = 'under_review' WHERE damage_id = ?");
            if ($stmt->execute([$damage_id])) {
                $_SESSION['flash_message'] = "Damage report marked as under review";
            } else {
                $_SESSION['error_message'] = "Failed to update damage report status";
            }
        } elseif ($action === 'resolve') {
            // Update damage status to resolved
            $stmt = $pdo->prepare("UPDATE damages SET status = 'resolved' WHERE damage_id = ?");
            
            if ($stmt->execute([$damage_id])) {
                $_SESSION['flash_message'] = "Damage report marked as resolved";
            } else {
                $_SESSION['error_message'] = "Failed to update damage report status";
            }
        } elseif ($action === 'schedule_maintenance') {
            // Get the bike_id from the damage report
            $stmt = $pdo->prepare("SELECT bike_id FROM damages WHERE damage_id = ?");
            $stmt->execute([$damage_id]);
            $damage = $stmt->fetch();
            
            if ($damage) {
                // Mark damage as resolved
                $stmt = $pdo->prepare("UPDATE damages SET status = 'resolved' WHERE damage_id = ?");
                $stmt->execute([$damage_id]);
                
                // Redirect to schedule maintenance page with bike_id
                $_SESSION['flash_message'] = "Redirecting to schedule maintenance";
                header("Location: ../maintenance/schedule-maintenance.php?bike_id=" . $damage['bike_id']);
                exit();
            } else {
                $_SESSION['error_message'] = "Damage report not found";
            }
        }
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Error: " . $e->getMessage();
    }
    
    // Redirect to refresh the page
    header("Location: damages.php");
    exit();
}

// Set up pagination and filtering
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;

$status_filter = isset($_GET['status']) ? sanitize($_GET['status']) : '';
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';

// Build the query with filters
$query = "
    SELECT d.*, u.first_name, u.last_name, u.email, b.bike_name, b.bike_type, b.image_path
    FROM damages d
    JOIN users u ON d.user_id = u.user_id
    JOIN bikes b ON d.bike_id = b.bike_id
    WHERE 1=1
";

$count_query = "SELECT COUNT(*) FROM damages d WHERE 1=1";
$params = [];
$count_params = [];

if ($status_filter) {
    $query .= " AND d.status = ?";
    $count_query .= " AND d.status = ?";
    $params[] = $status_filter;
    $count_params[] = $status_filter;
}

if ($search) {
    $query .= " AND (b.bike_name LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? OR d.description LIKE ?)";
    $count_query .= " AND (b.bike_name LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? OR d.description LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
    $count_params = array_merge($count_params, [$search_param, $search_param, $search_param, $search_param]);
}

$query .= " ORDER BY d.reported_at DESC LIMIT ? OFFSET ?";
$params[] = $perPage;
$params[] = $offset;

try {
    // Get total count for pagination
    $stmt = $pdo->prepare($count_query);
    $stmt->execute($count_params);
    $total_damages = $stmt->fetchColumn();
    $totalPages = ceil($total_damages / $perPage);
    
    // Get damage reports
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $damages = $stmt->fetchAll();
} catch (PDOException $e) {
    $_SESSION['error_message'] = "Error fetching damage reports: " . $e->getMessage();
    $damages = [];
    $totalPages = 0;
}

// Now that all redirects are complete, include the header which will start HTML output
include '../../includes/header.php';
?>

<div class="bg-white dark:bg-gray-800 p-8 rounded-lg shadow-md mb-8">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold dark:text-white">Damage Reports</h1>
        <div class="flex items-center gap-4">
            <a href="../dashboard.php" class="text-purple-600 hover:text-purple-800 dark:text-purple-400 dark:hover:text-purple-300">
                <i class="fas fa-arrow-left mr-1"></i>Back to Dashboard
            </a>
            <form action="" method="GET" class="flex items-center">
                <div class="relative">
                    <input type="text" name="search" placeholder="Search damages..." 
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
                    <a href="damages.php" class="ml-2 text-purple-600 hover:text-purple-800 dark:text-purple-400 dark:hover:text-purple-300">Clear</a>
                <?php endif; ?>
            </form>
        </div>
    </div>
    
    <!-- Status Filter Tabs -->
    <div class="mb-6">
        <div class="border-b border-gray-200 dark:border-gray-700">
            <ul class="flex flex-wrap -mb-px text-sm font-medium text-center">
                <li class="mr-2">
                    <a href="damages.php" class="inline-block p-4 <?php echo !$status_filter ? 'text-purple-600 dark:text-purple-400 border-b-2 border-purple-600 dark:border-purple-400 rounded-t-lg active' : 'text-gray-500 dark:text-gray-400 border-b-2 border-transparent rounded-t-lg hover:text-gray-600 dark:hover:text-gray-300 hover:border-gray-300 dark:hover:border-gray-600'; ?>">
                        All Reports
                    </a>
                </li>
                <li class="mr-2">
                    <a href="damages.php?status=reported" class="inline-block p-4 <?php echo $status_filter === 'reported' ? 'text-purple-600 dark:text-purple-400 border-b-2 border-purple-600 dark:border-purple-400 rounded-t-lg active' : 'text-gray-500 dark:text-gray-400 border-b-2 border-transparent rounded-t-lg hover:text-gray-600 dark:hover:text-gray-300 hover:border-gray-300 dark:hover:border-gray-600'; ?>">
                        Reported
                    </a>
                </li>
                <li class="mr-2">
                    <a href="damages.php?status=under_review" class="inline-block p-4 <?php echo $status_filter === 'under_review' ? 'text-purple-600 dark:text-purple-400 border-b-2 border-purple-600 dark:border-purple-400 rounded-t-lg active' : 'text-gray-500 dark:text-gray-400 border-b-2 border-transparent rounded-t-lg hover:text-gray-600 dark:hover:text-gray-300 hover:border-gray-300 dark:hover:border-gray-600'; ?>">
                        Under Review
                    </a>
                </li>
                <li class="mr-2">
                    <a href="damages.php?status=resolved" class="inline-block p-4 <?php echo $status_filter === 'resolved' ? 'text-purple-600 dark:text-purple-400 border-b-2 border-purple-600 dark:border-purple-400 rounded-t-lg active' : 'text-gray-500 dark:text-gray-400 border-b-2 border-transparent rounded-t-lg hover:text-gray-600 dark:hover:text-gray-300 hover:border-gray-300 dark:hover:border-gray-600'; ?>">
                        Resolved
                    </a>
                </li>
            </ul>
        </div>
    </div>
    
    <!-- Damage Reports List -->
    <?php if (empty($damages)): ?>
        <div class="bg-gray-50 dark:bg-gray-700 p-6 text-center rounded-lg">
            <p class="text-gray-600 dark:text-gray-400">No damage reports found.</p>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 gap-6">
            <?php foreach ($damages as $damage): ?>
                <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg border border-gray-200 dark:border-gray-600">
                    <div class="flex flex-col md:flex-row">
                        <!-- Bike Image -->
                        <div class="md:w-1/6 mb-4 md:mb-0">
                            <div class="h-32 w-32 mx-auto md:mx-0 rounded-md overflow-hidden bg-gray-100 dark:bg-gray-800">
                                <img src="<?php echo $damage['image_path'] ? '../../' . $damage['image_path'] : '../../assets/images/default-bike.jpg'; ?>" 
                                     alt="<?php echo htmlspecialchars($damage['bike_name']); ?>"
                                     class="w-full h-full object-cover">
                            </div>
                        </div>
                        
                        <!-- Damage Details -->
                        <div class="md:w-3/6 md:px-4">
                            <div class="flex justify-between mb-2">
                                <div>
                                    <h3 class="text-lg font-semibold dark:text-white"><?php echo htmlspecialchars($damage['bike_name']); ?></h3>
                                    <p class="text-sm text-gray-500 dark:text-gray-400"><?php echo htmlspecialchars($damage['bike_type']); ?></p>
                                </div>
                                
                                <div>
                                    <?php if ($damage['status'] === 'reported'): ?>
                                        <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 dark:bg-yellow-800 text-yellow-800 dark:text-yellow-200">
                                            Reported
                                        </span>
                                    <?php elseif ($damage['status'] === 'under_review'): ?>
                                        <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 dark:bg-blue-800 text-blue-800 dark:text-blue-200">
                                            Under Review
                                        </span>
                                    <?php else: ?>
                                        <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 dark:bg-green-800 text-green-800 dark:text-green-200">
                                            Resolved
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="mb-2">
                                <span class="text-sm text-gray-500 dark:text-gray-400">Reported by:</span>
                                <span class="text-sm font-medium text-gray-800 dark:text-gray-200">
                                    <?php echo htmlspecialchars($damage['first_name'] . ' ' . $damage['last_name']); ?>
                                    (<?php echo htmlspecialchars($damage['email']); ?>)
                                </span>
                            </div>
                            
                            <div class="mb-2">
                                <span class="text-sm text-gray-500 dark:text-gray-400">Reported on:</span>
                                <span class="text-sm font-medium text-gray-800 dark:text-gray-200">
                                    <?php 
                                        $reported_at = new DateTime($damage['reported_at']);
                                        echo $reported_at->format('M j, Y g:i A'); 
                                    ?>
                                </span>
                            </div>
                            
                            <div class="bg-white dark:bg-gray-800 p-3 rounded-lg border border-gray-200 dark:border-gray-600 mt-2">
                                <h4 class="font-medium text-gray-700 dark:text-gray-300 mb-1">Damage Description:</h4>
                                <p class="text-gray-600 dark:text-gray-400"><?php echo nl2br(htmlspecialchars($damage['description'])); ?></p>
                            </div>
                            
                            <!-- Damage photos if available -->
                            <?php if (!empty($damage['photo_path'])): ?>
                                <div class="mt-3">
                                    <h4 class="font-medium text-gray-700 dark:text-gray-300 mb-1">Damage Photos:</h4>
                                    <div class="grid grid-cols-2 gap-2 mt-2">
                                        <div class="rounded-md overflow-hidden border border-gray-200 dark:border-gray-600">
                                            <a href="../../<?php echo $damage['photo_path']; ?>" target="_blank">
                                                <img src="../../<?php echo $damage['photo_path']; ?>" alt="Damage Photo" class="w-full h-24 object-cover">
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Actions -->
                        <div class="md:w-2/6 md:pl-4 mt-4 md:mt-0 border-t pt-4 md:pt-0 md:border-t-0 md:border-l md:border-gray-200 md:dark:border-gray-600 md:pl-6">
                            <h4 class="font-medium text-gray-700 dark:text-gray-300 mb-3">Actions:</h4>
                            <div class="space-y-2">
                                <?php if ($damage['status'] === 'reported'): ?>
                                    <form action="damages.php" method="POST">
                                        <input type="hidden" name="damage_id" value="<?php echo $damage['damage_id']; ?>">
                                        <input type="hidden" name="action" value="review">
                                        <button type="submit" class="w-full bg-blue-500 hover:bg-blue-600 text-white font-medium py-2 px-4 rounded">
                                            <i class="fas fa-eye mr-2"></i>Mark as Under Review
                                        </button>
                                    </form>
                                <?php endif; ?>
                                
                                <?php if ($damage['status'] !== 'resolved'): ?>
                                    <form action="damages.php" method="POST">
                                        <input type="hidden" name="damage_id" value="<?php echo $damage['damage_id']; ?>">
                                        <input type="hidden" name="action" value="resolve">
                                        <button type="submit" class="w-full bg-green-500 hover:bg-green-600 text-white font-medium py-2 px-4 rounded">
                                            <i class="fas fa-check mr-2"></i>Mark as Resolved
                                        </button>
                                    </form>
                                    
                                    <form action="damages.php" method="POST">
                                        <input type="hidden" name="damage_id" value="<?php echo $damage['damage_id']; ?>">
                                        <input type="hidden" name="action" value="schedule_maintenance">
                                        <button type="submit" class="w-full bg-purple-500 hover:bg-purple-600 text-white font-medium py-2 px-4 rounded">
                                            <i class="fas fa-tools mr-2"></i>Schedule Maintenance
                                        </button>
                                    </form>
                                <?php endif; ?>
                                
                                <a href="<?php echo isset($damage['reservation_id']) ? '../../pages/report-damage.php?reservation_id=' . $damage['reservation_id'] : '#'; ?>" 
                                   class="block text-center text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200 mt-2">
                                    <i class="fas fa-external-link-alt mr-2"></i>View Reservation Details
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <div class="flex justify-center mt-6">
                <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo ($page - 1); ?><?php echo $status_filter ? '&status=' . urlencode($status_filter) : ''; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>" 
                           class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white dark:bg-gray-700 dark:border-gray-600 text-sm font-medium text-gray-500 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600">
                            <span class="sr-only">Previous</span>
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    <?php endif; ?>
                    
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <a href="?page=<?php echo $i; ?><?php echo $status_filter ? '&status=' . urlencode($status_filter) : ''; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>" 
                           class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white dark:bg-gray-700 dark:border-gray-600 text-sm font-medium <?php echo $i === $page ? 'text-purple-600 bg-purple-50 dark:bg-purple-900 dark:text-purple-300' : 'text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600'; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?php echo ($page + 1); ?><?php echo $status_filter ? '&status=' . urlencode($status_filter) : ''; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>" 
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