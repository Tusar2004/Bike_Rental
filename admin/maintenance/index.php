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

// Determine which action to take based on POST parameters
$action = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['schedule_maintenance'])) {
        $action = 'schedule';
    } elseif (isset($_POST['start_maintenance'])) {
        $action = 'start';
    } elseif (isset($_POST['complete_maintenance'])) {
        $action = 'complete';
    } elseif (isset($_POST['delete_maintenance'])) {
        $action = 'delete';
    }
}

/********************************
 * HANDLE SCHEDULE MAINTENANCE
 ********************************/
if ($action === 'schedule') {
    $bike_id = intval($_POST['bike_id']);
    $start_date = sanitize($_POST['start_date']);
    $end_date = sanitize($_POST['end_date']);
    $maintenance_type = sanitize($_POST['maintenance_type']);
    $description = sanitize($_POST['description']);
    
    // Validation
    $errors = [];
    if (empty($bike_id)) {
        $errors[] = "Please select a bike";
    }
    if (empty($start_date)) {
        $errors[] = "Start date is required";
    }
    if (empty($end_date)) {
        $errors[] = "End date is required";
    }
    if (empty($maintenance_type)) {
        $errors[] = "Maintenance type is required";
    }
    
    // Check if end date is after start date
    if ($start_date && $end_date) {
        $start = new DateTime($start_date);
        $end = new DateTime($end_date);
        if ($end < $start) {
            $errors[] = "End date must be after start date";
        }
    }
    
    if (empty($errors)) {
        try {
            // Begin transaction
            $pdo->beginTransaction();
            
            // Insert maintenance record with the maintenance_type included
            $stmt = $pdo->prepare("
                INSERT INTO maintenance (bike_id, start_date, end_date, description, status, maintenance_type)
                VALUES (?, ?, ?, ?, 'scheduled', ?)
            ");
            $stmt->execute([$bike_id, $start_date, $end_date, $description, $maintenance_type]);
            
            // Update bike status to 'maintenance'
            $stmt = $pdo->prepare("UPDATE bikes SET status = 'maintenance' WHERE bike_id = ?");
            $stmt->execute([$bike_id]);
            
            $pdo->commit();
            
            $_SESSION['flash_message'] = "Maintenance scheduled successfully";
        } catch (PDOException $e) {
            $pdo->rollBack();
            $_SESSION['error_message'] = "Error: " . $e->getMessage();
        }
    } else {
        $_SESSION['error_message'] = implode("<br>", $errors);
    }
}

/********************************
 * HANDLE START MAINTENANCE
 ********************************/
if ($action === 'start') {
    $maintenance_id = intval($_POST['maintenance_id']);
    
    try {
        // Update maintenance status
        $stmt = $pdo->prepare("UPDATE maintenance SET status = 'in_progress' WHERE maintenance_id = ?");
        $stmt->execute([$maintenance_id]);
        
        if ($stmt->rowCount() > 0) {
            $_SESSION['flash_message'] = "Maintenance has been started";
        } else {
            $_SESSION['error_message'] = "Unable to find maintenance record";
        }
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Database error: " . $e->getMessage();
    }
}

/********************************
 * HANDLE COMPLETE MAINTENANCE
 ********************************/
if ($action === 'complete') {
    $maintenance_id = intval($_POST['maintenance_id']);
    
    try {
        // Begin transaction
        $pdo->beginTransaction();
        
        // Get the bike_id from the maintenance record
        $stmt = $pdo->prepare("SELECT bike_id FROM maintenance WHERE maintenance_id = ?");
        $stmt->execute([$maintenance_id]);
        $maintenance = $stmt->fetch();
        
        if (!$maintenance) {
            throw new PDOException("Maintenance record not found");
        }
        
        $bike_id = $maintenance['bike_id'];
        
        // Update maintenance status to completed with end_date
        $stmt = $pdo->prepare("UPDATE maintenance SET status = 'completed', end_date = NOW() WHERE maintenance_id = ?");
        $stmt->execute([$maintenance_id]);
        
        // Update bike status back to 'available'
        $stmt = $pdo->prepare("UPDATE bikes SET status = 'available' WHERE bike_id = ?");
        $stmt->execute([$bike_id]);
        
        $pdo->commit();
        
        $_SESSION['flash_message'] = "Maintenance completed successfully. Bike is now available for rental.";
    } catch (PDOException $e) {
        $pdo->rollBack();
        $_SESSION['error_message'] = "Error: " . $e->getMessage();
    }
}

/********************************
 * HANDLE DELETE MAINTENANCE
 ********************************/
if ($action === 'delete') {
    $maintenance_id = intval($_POST['maintenance_id']);
    
    try {
        // Begin transaction
        $pdo->beginTransaction();
        
        // Get the bike_id and status from the maintenance record
        $stmt = $pdo->prepare("SELECT bike_id, status FROM maintenance WHERE maintenance_id = ?");
        $stmt->execute([$maintenance_id]);
        $maintenance = $stmt->fetch();
        
        if (!$maintenance) {
            throw new PDOException("Maintenance record not found");
        }
        
        $bike_id = $maintenance['bike_id'];
        
        // Delete the maintenance record
        $stmt = $pdo->prepare("DELETE FROM maintenance WHERE maintenance_id = ?");
        $stmt->execute([$maintenance_id]);
        
        // If maintenance was scheduled or in progress, update bike status back to 'available'
        if ($maintenance['status'] !== 'completed') {
            $stmt = $pdo->prepare("UPDATE bikes SET status = 'available' WHERE bike_id = ?");
            $stmt->execute([$bike_id]);
        }
        
        $pdo->commit();
        
        $_SESSION['flash_message'] = "Maintenance record deleted successfully.";
    } catch (PDOException $e) {
        $pdo->rollBack();
        $_SESSION['error_message'] = "Error: " . $e->getMessage();
    }
}

/********************************
 * FETCH DATA FOR THE PAGE
 ********************************/
// Get list of bikes for dropdown
try {
    $stmt = $pdo->prepare("SELECT bike_id, bike_name, bike_type, status FROM bikes ORDER BY bike_name");
    $stmt->execute();
    $bikes = $stmt->fetchAll();
} catch (PDOException $e) {
    $_SESSION['error_message'] = "Error: " . $e->getMessage();
    $bikes = [];
}

// Get upcoming maintenance schedule
try {
    $stmt = $pdo->prepare("
        SELECT m.*, b.bike_name, b.bike_type 
        FROM maintenance m
        JOIN bikes b ON m.bike_id = b.bike_id
        WHERE m.status IN ('scheduled', 'in_progress')
        ORDER BY m.start_date
    ");
    $stmt->execute();
    $scheduled_maintenance = $stmt->fetchAll();
} catch (PDOException $e) {
    $_SESSION['error_message'] = "Error: " . $e->getMessage();
    $scheduled_maintenance = [];
}

// Get maintenance history
try {
    $stmt = $pdo->prepare("
        SELECT m.*, b.bike_name, b.bike_type 
        FROM maintenance m
        JOIN bikes b ON m.bike_id = b.bike_id
        WHERE m.status = 'completed'
        ORDER BY m.end_date DESC
        LIMIT 10
    ");
    $stmt->execute();
    $maintenance_history = $stmt->fetchAll();
} catch (PDOException $e) {
    $_SESSION['error_message'] = "Error: " . $e->getMessage();
    $maintenance_history = [];
}

// Now that all redirects are complete, include the header which will start HTML output
include '../../includes/header.php';
?>

<div class="bg-white dark:bg-gray-800 p-8 rounded-lg shadow-md mb-8">
    <div class="flex justify-between items-center mb-8">
        <h1 class="text-3xl font-bold dark:text-white">Maintenance Management</h1>
        <a href="../dashboard.php" class="text-purple-600 hover:text-purple-800 dark:text-purple-400 dark:hover:text-purple-300">
            <i class="fas fa-arrow-left mr-1"></i>Back to Dashboard
        </a>
    </div>
    
    <!-- Schedule Maintenance Form -->
    <div class="bg-gray-800 text-white p-6 rounded-lg shadow-md mb-8">
        <h2 class="text-xl font-semibold mb-4 text-white">Schedule New Maintenance</h2>
        <form action="index.php" method="POST" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="bike_id" class="block text-gray-300 text-sm font-medium mb-2">Select Bike</label>
                    <select id="bike_id" name="bike_id" class="form-select block w-full mt-1 rounded-md border-gray-600 bg-gray-700 text-white shadow-sm h-10">
                        <option value="">-- Select a Bike --</option>
                        <?php foreach ($bikes as $bike): ?>
                            <option value="<?php echo $bike['bike_id']; ?>" <?php echo (isset($_POST['bike_id']) && $_POST['bike_id'] == $bike['bike_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($bike['bike_name'] . ' (' . $bike['bike_type'] . ')'); ?> 
                                - <?php echo ucfirst($bike['status']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label for="maintenance_type" class="block text-gray-300 text-sm font-medium mb-2">Maintenance Type</label>
                    <select id="maintenance_type" name="maintenance_type" class="form-select block w-full mt-1 rounded-md border-gray-600 bg-gray-700 text-white shadow-sm h-10">
                        <option value="">-- Select Type --</option>
                        <option value="routine" <?php echo (isset($_POST['maintenance_type']) && $_POST['maintenance_type'] == 'routine') ? 'selected' : ''; ?>>Routine Maintenance</option>
                        <option value="damage_repair" <?php echo (isset($_POST['maintenance_type']) && $_POST['maintenance_type'] == 'damage_repair') ? 'selected' : ''; ?>>Damage Repair</option>
                        <option value="safety_check" <?php echo (isset($_POST['maintenance_type']) && $_POST['maintenance_type'] == 'safety_check') ? 'selected' : ''; ?>>Safety Check</option>
                        <option value="other" <?php echo (isset($_POST['maintenance_type']) && $_POST['maintenance_type'] == 'other') ? 'selected' : ''; ?>>Other</option>
                    </select>
                </div>
                
                <div>
                    <label for="start_date" class="block text-gray-300 text-sm font-medium mb-2">Start Date</label>
                    <input type="datetime-local" id="start_date" name="start_date" class="form-input block w-full mt-1 rounded-md border-gray-600 bg-gray-700 text-white shadow-sm h-10"
                        value="<?php echo isset($_POST['start_date']) ? $_POST['start_date'] : ''; ?>">
                </div>
                
                <div>
                    <label for="end_date" class="block text-gray-300 text-sm font-medium mb-2">End Date</label>
                    <input type="datetime-local" id="end_date" name="end_date" class="form-input block w-full mt-1 rounded-md border-gray-600 bg-gray-700 text-white shadow-sm h-10"
                        value="<?php echo isset($_POST['end_date']) ? $_POST['end_date'] : ''; ?>">
                </div>
            </div>
            
            <div>
                <label for="description" class="block text-gray-300 text-sm font-medium mb-2">Description</label>
                <textarea id="description" name="description" rows="4" class="form-textarea block w-full mt-1 rounded-md border-gray-600 bg-gray-700 text-white shadow-sm"
                    placeholder="Describe the maintenance to be performed..."><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
            </div>
            
            <div class="flex justify-end">
                <button type="submit" name="schedule_maintenance" class="bg-purple-600 hover:bg-purple-700 text-white font-bold py-2 px-6 rounded-lg focus:outline-none focus:shadow-outline">
                    Schedule Maintenance
                </button>
            </div>
        </form>
    </div>
    
    <!-- Upcoming Maintenance Schedule -->
    <div class="mb-8">
        <h2 class="text-xl font-semibold mb-4 dark:text-white">Upcoming Maintenance</h2>
        <div class="overflow-x-auto bg-gray-50 dark:bg-gray-700 rounded-lg border border-gray-200 dark:border-gray-600">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-100 dark:bg-gray-700">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Bike</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Type</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Start Date</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">End Date</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Status</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                    <?php if (empty($scheduled_maintenance)): ?>
                        <tr>
                            <td colspan="6" class="px-6 py-4 text-center text-gray-500 dark:text-gray-400">No maintenance scheduled.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($scheduled_maintenance as $maintenance): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900 dark:text-white">
                                        <?php echo htmlspecialchars($maintenance['bike_name']); ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                    <?php 
                                        $type_labels = [
                                            'routine' => 'Routine Maintenance',
                                            'damage_repair' => 'Damage Repair',
                                            'safety_check' => 'Safety Check',
                                            'other' => 'Other'
                                        ];
                                        echo isset($type_labels[$maintenance['maintenance_type']]) ? $type_labels[$maintenance['maintenance_type']] : ucfirst($maintenance['maintenance_type']);
                                    ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                    <?php 
                                        $start_date = new DateTime($maintenance['start_date']);
                                        echo $start_date->format('M j, Y g:i A');
                                    ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                    <?php 
                                        $end_date = new DateTime($maintenance['end_date']);
                                        echo $end_date->format('M j, Y g:i A');
                                    ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php if ($maintenance['status'] === 'scheduled'): ?>
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800 dark:bg-blue-800 dark:text-blue-100">
                                            Scheduled
                                        </span>
                                    <?php elseif ($maintenance['status'] === 'in_progress'): ?>
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800 dark:bg-yellow-800 dark:text-yellow-100">
                                            In Progress
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                    <div class="flex space-x-2">
                                        <!-- Complete Maintenance -->
                                        <form action="index.php" method="POST" onsubmit="return confirm('Are you sure you want to mark this maintenance as complete?');">
                                            <input type="hidden" name="maintenance_id" value="<?php echo $maintenance['maintenance_id']; ?>">
                                            <button type="submit" name="complete_maintenance" class="text-green-600 hover:text-green-800 dark:text-green-400 dark:hover:text-green-300" title="Mark as Complete">
                                                <i class="fas fa-check"></i>
                                            </button>
                                        </form>
                                        
                                        <!-- Start Maintenance -->
                                        <?php if ($maintenance['status'] === 'scheduled'): ?>
                                        <form action="index.php" method="POST">
                                            <input type="hidden" name="maintenance_id" value="<?php echo $maintenance['maintenance_id']; ?>">
                                            <button type="submit" name="start_maintenance" class="text-purple-600 hover:text-purple-800 dark:text-purple-400 dark:hover:text-purple-300" title="Start Maintenance">
                                                <i class="fas fa-play"></i>
                                            </button>
                                        </form>
                                        <?php endif; ?>
                                        
                                        <!-- Delete Scheduled Maintenance -->
                                        <form action="index.php" method="POST" onsubmit="return confirm('Are you sure you want to delete this maintenance record?');">
                                            <input type="hidden" name="maintenance_id" value="<?php echo $maintenance['maintenance_id']; ?>">
                                            <button type="submit" name="delete_maintenance" class="text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300" title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Maintenance History -->
    <div>
        <h2 class="text-xl font-semibold mb-4 dark:text-white">Maintenance History</h2>
        <div class="overflow-x-auto bg-gray-50 dark:bg-gray-700 rounded-lg border border-gray-200 dark:border-gray-600">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-100 dark:bg-gray-700">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Bike</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Type</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Date Range</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Description</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                    <?php if (empty($maintenance_history)): ?>
                        <tr>
                            <td colspan="4" class="px-6 py-4 text-center text-gray-500 dark:text-gray-400">No maintenance history found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($maintenance_history as $history): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900 dark:text-white">
                                        <?php echo htmlspecialchars($history['bike_name']); ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                    <?php 
                                        $type_labels = [
                                            'routine' => 'Routine Maintenance',
                                            'damage_repair' => 'Damage Repair',
                                            'safety_check' => 'Safety Check',
                                            'other' => 'Other'
                                        ];
                                        echo isset($type_labels[$history['maintenance_type']]) ? $type_labels[$history['maintenance_type']] : ucfirst($history['maintenance_type']);
                                    ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                    <?php 
                                        $start_date = new DateTime($history['start_date']);
                                        $end_date = new DateTime($history['end_date']);
                                        echo $start_date->format('M j, Y') . ' - ' . $end_date->format('M j, Y');
                                    ?>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                    <?php echo htmlspecialchars(substr($history['description'], 0, 50)) . (strlen($history['description']) > 50 ? '...' : ''); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
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