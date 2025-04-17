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

// Process any user actions before including header
if (isset($_POST['action']) && isset($_POST['user_id'])) {
    $user_id = intval($_POST['user_id']);
    $action = $_POST['action'];
    
    try {
        if ($action === 'delete') {
            // Check if user has any active reservations
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as count FROM reservations 
                WHERE user_id = ? AND status IN ('pending', 'confirmed')
            ");
            $stmt->execute([$user_id]);
            $has_active_reservations = $stmt->fetch()['count'] > 0;
            
            if ($has_active_reservations) {
                $_SESSION['error_message'] = "Cannot delete user with active reservations";
            } else {
                // Delete user
                $stmt = $pdo->prepare("DELETE FROM users WHERE user_id = ?");
                if ($stmt->execute([$user_id])) {
                    $_SESSION['flash_message'] = "User deleted successfully";
                } else {
                    $_SESSION['error_message'] = "Failed to delete user";
                }
            }
        } elseif ($action === 'toggle_admin') {
            // Toggle admin status
            $stmt = $pdo->prepare("
                UPDATE users 
                SET role = CASE WHEN role = 'admin' THEN 'user' ELSE 'admin' END 
                WHERE user_id = ?
            ");
            if ($stmt->execute([$user_id])) {
                $_SESSION['flash_message'] = "User role updated successfully";
            } else {
                $_SESSION['error_message'] = "Failed to update user role";
            }
        }
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Error: " . $e->getMessage();
    }
    
    // Redirect to refresh the page
    header("Location: index.php");
    exit();
}

// Process user enable/disable actions via GET
if (isset($_GET['action']) && isset($_GET['id'])) {
    $user_id = intval($_GET['id']);
    $action = $_GET['action'];
    
    try {
        if ($action === 'disable') {
            $stmt = $pdo->prepare("UPDATE users SET status = 'disabled' WHERE user_id = ?");
            if ($stmt->execute([$user_id])) {
                $_SESSION['flash_message'] = "User has been disabled";
            } else {
                $_SESSION['error_message'] = "Failed to disable user";
            }
        } elseif ($action === 'enable') {
            $stmt = $pdo->prepare("UPDATE users SET status = 'active' WHERE user_id = ?");
            if ($stmt->execute([$user_id])) {
                $_SESSION['flash_message'] = "User has been enabled";
            } else {
                $_SESSION['error_message'] = "Failed to enable user";
            }
        } elseif ($action === 'delete') {
            // Check if user has any active reservations
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as count FROM reservations 
                WHERE user_id = ? AND status IN ('pending', 'confirmed')
            ");
            $stmt->execute([$user_id]);
            $has_active_reservations = $stmt->fetch()['count'] > 0;
            
            if ($has_active_reservations) {
                $_SESSION['error_message'] = "Cannot delete user with active reservations";
            } else {
                // Delete user
                $stmt = $pdo->prepare("DELETE FROM users WHERE user_id = ?");
                if ($stmt->execute([$user_id])) {
                    $_SESSION['flash_message'] = "User deleted successfully";
                } else {
                    $_SESSION['error_message'] = "Failed to delete user";
                }
            }
        }
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Error: " . $e->getMessage();
    }
    
    // Redirect to refresh the page without the action parameters
    header("Location: index.php" . (isset($_GET['page']) ? "?page=" . intval($_GET['page']) : ""));
    exit();
}

// Get users data with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';

try {
    // Base query for counting and fetching
    $baseQuery = "FROM users WHERE role = 'user'";
    $params = [];
    
    // Add search condition if search is provided
    if (!empty($search)) {
        $baseQuery .= " AND (first_name LIKE ? OR last_name LIKE ? OR email LIKE ?)";
        $searchTerm = "%$search%";
        $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
    }
    
    // Count total number of users
    $stmt = $pdo->prepare("SELECT COUNT(*) as total $baseQuery");
    $stmt->execute($params);
    $total_users = $stmt->fetch()['total'];
    
    // Calculate total pages
    $totalPages = ceil($total_users / $perPage);
    
    // Fetch paginated user list
    $query = "
        SELECT *, 
            (SELECT COUNT(*) FROM reservations WHERE user_id = users.user_id) as total_rentals,
            (SELECT COUNT(*) FROM reservations WHERE user_id = users.user_id AND status IN ('pending', 'confirmed')) as active_rentals
        $baseQuery
        ORDER BY created_at DESC
        LIMIT ? OFFSET ?
    ";
    
    $stmt = $pdo->prepare($query);
    $params[] = $perPage;
    $params[] = $offset;
    $stmt->execute($params);
    $users = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $_SESSION['error_message'] = "Error: " . $e->getMessage();
    $users = [];
    $totalPages = 0;
}

// Now that all redirects are complete, include the header which will start HTML output
include '../../includes/header.php';
?>

<div class="bg-white dark:bg-gray-800 p-8 rounded-lg shadow-md mb-8">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold dark:text-white">Manage Users</h1>
        <div class="flex items-center gap-4">
            <a href="../dashboard.php" class="text-purple-600 hover:text-purple-800 dark:text-purple-400 dark:hover:text-purple-300">
                <i class="fas fa-arrow-left mr-1"></i>Back to Dashboard
            </a>
            <form action="" method="GET" class="flex items-center">
                <div class="relative">
                    <input type="text" name="search" placeholder="Search users..." 
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
                    <a href="index.php" class="ml-2 text-purple-600 hover:text-purple-800 dark:text-purple-400 dark:hover:text-purple-300">Clear</a>
                <?php endif; ?>
            </form>
        </div>
    </div>
    
    <div class="mb-6">
        <div class="bg-gray-50 dark:bg-gray-700 rounded-lg border border-gray-200 dark:border-gray-600">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-100 dark:bg-gray-700">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">User</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Contact</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Registration Date</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Status</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Rentals</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                        <?php if (empty($users)): ?>
                            <tr>
                                <td colspan="6" class="px-6 py-4 text-center text-gray-500 dark:text-gray-400">No users found.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0 h-10 w-10 bg-gray-200 dark:bg-gray-600 rounded-full flex items-center justify-center">
                                                <i class="fas fa-user text-gray-500 dark:text-gray-300"></i>
                                            </div>
                                            <div class="ml-4">
                                                <div class="text-sm font-medium text-gray-900 dark:text-white">
                                                    <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                                                </div>
                                                <div class="text-sm text-gray-500 dark:text-gray-400">
                                                    ID: <?php echo $user['user_id']; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900 dark:text-white"><?php echo htmlspecialchars($user['email']); ?></div>
                                        <?php if (!empty($user['phone'])): ?>
                                            <div class="text-sm text-gray-500 dark:text-gray-400"><?php echo htmlspecialchars($user['phone']); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                        <?php 
                                            $created_at = new DateTime($user['created_at']);
                                            echo $created_at->format('M j, Y');
                                        ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php 
                                        // Set default status to 'disabled' if not defined
                                        $userStatus = isset($user['status']) ? $user['status'] : 'disabled';
                                        if ($userStatus === 'active'): 
                                        ?>
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-100">
                                                Active
                                            </span>
                                        <?php else: ?>
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800 dark:bg-red-800 dark:text-red-100">
                                                Disabled
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                        <div class="flex flex-col">
                                            <span>Total: <?php echo $user['total_rentals']; ?></span>
                                            <span>Active: <?php echo $user['active_rentals']; ?></span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <div class="flex space-x-2">
                                            <?php 
                                            // Use the same userStatus variable
                                            if ($userStatus === 'active'): 
                                            ?>
                                                <a href="?action=disable&id=<?php echo $user['user_id']; ?>" 
                                                   class="text-purple-600 hover:text-purple-900 dark:text-purple-400 dark:hover:text-purple-300" 
                                                   onclick="return confirm('Are you sure you want to disable this user?')">
                                                    Disable
                                                </a>
                                            <?php else: ?>
                                                <a href="?action=enable&id=<?php echo $user['user_id']; ?>" 
                                                   class="text-green-600 hover:text-green-900 dark:text-green-400 dark:hover:text-green-300">
                                                    Enable
                                                </a>
                                            <?php endif; ?>
                                            <a href="?action=delete&id=<?php echo $user['user_id']; ?>" 
                                               class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300" 
                                               onclick="return confirm('Are you sure you want to delete this user? This action cannot be undone.')">
                                                Delete
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <?php if ($totalPages > 1): ?>
        <div class="flex justify-center mt-4">
            <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo ($page - 1); ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white dark:bg-gray-700 dark:border-gray-600 text-sm font-medium text-gray-500 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600">
                        <span class="sr-only">Previous</span>
                        <i class="fas fa-chevron-left"></i>
                    </a>
                <?php endif; ?>
                
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <a href="?page=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" 
                       class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white dark:bg-gray-700 dark:border-gray-600 text-sm font-medium <?php echo $i === $page ? 'text-purple-600 bg-purple-50 dark:bg-purple-900 dark:text-purple-300' : 'text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600'; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>
                
                <?php if ($page < $totalPages): ?>
                    <a href="?page=<?php echo ($page + 1); ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white dark:bg-gray-700 dark:border-gray-600 text-sm font-medium text-gray-500 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600">
                        <span class="sr-only">Next</span>
                        <i class="fas fa-chevron-right"></i>
                    </a>
                <?php endif; ?>
            </nav>
        </div>
    <?php endif; ?>
</div>

<style>
    /* Status badge styling */
    .badge-available {
        background: linear-gradient(to right, #10B981, #059669);
    }
    .badge-reserved {
        background: linear-gradient(to right, #F59E0B, #D97706);
    }
    .badge-maintenance {
        background: linear-gradient(to right, #EF4444, #DC2626);
    }
</style>

<?php include '../../includes/footer.php'; ?>