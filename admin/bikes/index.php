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

// Determine which action to take based on GET or POST parameters
$action = isset($_GET['action']) ? sanitize($_GET['action']) : 'list';

// Initialize variables
$bikes = [];
$bike_types = [];
$errors = [];

// Handle AJAX requests
if (isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');
    $response = ['success' => false, 'message' => ''];
    
    if ($_POST['ajax_action'] === 'delete_bike' && isset($_POST['bike_id'])) {
        $bike_id = intval($_POST['bike_id']);
        
        try {
            // Check if the bike is currently reserved
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as count FROM reservations 
                WHERE bike_id = ? AND status IN ('pending', 'confirmed')
            ");
            $stmt->execute([$bike_id]);
            $reserved = $stmt->fetch()['count'] > 0;
            
            if ($reserved) {
                $response['message'] = "Cannot delete a bike that has active reservations";
            } else {
                // Delete the bike
                $stmt = $pdo->prepare("DELETE FROM bikes WHERE bike_id = ?");
                if ($stmt->execute([$bike_id])) {
                    $response['success'] = true;
                    $response['message'] = "Bike deleted successfully";
                } else {
                    $response['message'] = "Failed to delete bike";
                }
            }
        } catch (PDOException $e) {
            $response['message'] = "Error: " . $e->getMessage();
        }
    } elseif ($_POST['ajax_action'] === 'update_status' && isset($_POST['bike_id']) && isset($_POST['status'])) {
        $bike_id = intval($_POST['bike_id']);
        $status = sanitize($_POST['status']);
        
        try {
            // Check if status is valid
            if (!in_array($status, ['available', 'maintenance'])) {
                $response['message'] = "Invalid status selected";
            } 
            // Check if bike is reserved and trying to set to available
            elseif ($status === 'available') {
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) as count FROM reservations 
                    WHERE bike_id = ? AND status IN ('pending', 'confirmed')
                ");
                $stmt->execute([$bike_id]);
                $reserved = $stmt->fetch()['count'] > 0;
                
                if ($reserved) {
                    $response['message'] = "Cannot set status to available: bike has active reservations";
                } else {
                    // Update status
                    $stmt = $pdo->prepare("UPDATE bikes SET status = ? WHERE bike_id = ?");
                    if ($stmt->execute([$status, $bike_id])) {
                        $response['success'] = true;
                        $response['message'] = "Bike status updated successfully";
                    } else {
                        $response['message'] = "Failed to update bike status";
                    }
                }
            } else {
                // Update status
                $stmt = $pdo->prepare("UPDATE bikes SET status = ? WHERE bike_id = ?");
                if ($stmt->execute([$status, $bike_id])) {
                    $response['success'] = true;
                    $response['message'] = "Bike status updated successfully";
                } else {
                    $response['message'] = "Failed to update bike status";
                }
            }
        } catch (PDOException $e) {
            $response['message'] = "Error: " . $e->getMessage();
        }
    }
    
    echo json_encode($response);
    exit;
}

/********************************
 * ADD BIKE FUNCTIONALITY
 ********************************/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'add') {
    $bike_name = sanitize($_POST['bike_name']);
    $bike_type = sanitize($_POST['bike_type']);
    $specifications = sanitize($_POST['specifications']);
    $hourly_rate = floatval($_POST['hourly_rate']);
    $status = sanitize($_POST['status']);
    
    // Validate form data
    if (empty($bike_name)) {
        $errors[] = "Bike name is required";
    }
    
    if (empty($bike_type)) {
        $errors[] = "Bike type is required";
    }
    
    if ($hourly_rate <= 0) {
        $errors[] = "Hourly rate must be greater than zero";
    }
    
    // Process image upload if provided
    $image_path = '';
    if (isset($_FILES['bike_image']) && $_FILES['bike_image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../../assets/images/bikes/';
        
        // Create directory if it doesn't exist
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        // Generate unique filename
        $file_ext = pathinfo($_FILES['bike_image']['name'], PATHINFO_EXTENSION);
        $file_name = 'bike_' . time() . '.' . $file_ext;
        $target_file = $upload_dir . $file_name;
        
        // Check if file is an actual image
        $check = getimagesize($_FILES['bike_image']['tmp_name']);
        if ($check === false) {
            $errors[] = "File is not an image";
        } else {
            // Check file size (max 5MB)
            if ($_FILES['bike_image']['size'] > 5000000) {
                $errors[] = "File is too large (max 5MB)";
            } else {
                // Allow certain file formats
                $allowed_extensions = ["jpg", "jpeg", "png", "gif"];
                if (!in_array(strtolower($file_ext), $allowed_extensions)) {
                    $errors[] = "Only JPG, JPEG, PNG & GIF files are allowed";
                } else {
                    // Upload file
                    if (move_uploaded_file($_FILES['bike_image']['tmp_name'], $target_file)) {
                        $image_path = 'assets/images/bikes/' . $file_name;
                    } else {
                        $errors[] = "Failed to upload image";
                    }
                }
            }
        }
    }
    
    // Insert bike into database if no errors
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO bikes (bike_name, bike_type, specifications, image_path, hourly_rate, status)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            
            if ($stmt->execute([$bike_name, $bike_type, $specifications, $image_path, $hourly_rate, $status])) {
                $_SESSION['flash_message'] = "Bike added successfully!";
                header("Location: index.php");
                exit();
            } else {
                $errors[] = "Failed to add bike to database";
            }
        } catch (PDOException $e) {
            $errors[] = "Database error: " . $e->getMessage();
        }
    }
    
    // If there are errors, store them in session
    if (!empty($errors)) {
        $_SESSION['error_message'] = implode("<br>", $errors);
    }
}

/********************************
 * UPDATE BIKE IMAGE FUNCTIONALITY
 ********************************/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_image']) && isset($_POST['bike_id'])) {
    $bike_id = intval($_POST['bike_id']);
    $errors = [];
    
    // Get bike details
    try {
        $stmt = $pdo->prepare("SELECT bike_name FROM bikes WHERE bike_id = ?");
        $stmt->execute([$bike_id]);
        $bike = $stmt->fetch();
        
        if (!$bike) {
            $_SESSION['error_message'] = "Bike not found";
            header("Location: index.php");
            exit();
        }
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Error: " . $e->getMessage();
        header("Location: index.php");
        exit();
    }
    
    // Process image upload if provided
    if (isset($_FILES['bike_image']) && $_FILES['bike_image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../../assets/images/bikes/';
        
        // Create directory if it doesn't exist
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        // Generate unique filename
        $file_ext = pathinfo($_FILES['bike_image']['name'], PATHINFO_EXTENSION);
        $file_name = 'bike_' . $bike_id . '_' . time() . '.' . $file_ext;
        $target_file = $upload_dir . $file_name;
        
        // Check if file is an actual image
        $check = getimagesize($_FILES['bike_image']['tmp_name']);
        if ($check === false) {
            $errors[] = "File is not an image";
        } else {
            // Check file size (max 5MB)
            if ($_FILES['bike_image']['size'] > 5000000) {
                $errors[] = "File is too large (max 5MB)";
            } else {
                // Allow certain file formats
                $allowed_extensions = ["jpg", "jpeg", "png", "gif"];
                if (!in_array(strtolower($file_ext), $allowed_extensions)) {
                    $errors[] = "Only JPG, JPEG, PNG & GIF files are allowed";
                } else {
                    // Upload file
                    if (move_uploaded_file($_FILES['bike_image']['tmp_name'], $target_file)) {
                        $image_path = 'assets/images/bikes/' . $file_name;
                        
                        // Update image path in database
                        try {
                            $stmt = $pdo->prepare("UPDATE bikes SET image_path = ? WHERE bike_id = ?");
                            if ($stmt->execute([$image_path, $bike_id])) {
                                $_SESSION['flash_message'] = "Image updated successfully for " . htmlspecialchars($bike['bike_name']);
                                header("Location: index.php?action=images");
                                exit();
                            } else {
                                $errors[] = "Failed to update image in database";
                            }
                        } catch (PDOException $e) {
                            $errors[] = "Database error: " . $e->getMessage();
                        }
                    } else {
                        $errors[] = "Failed to upload image";
                    }
                }
            }
        }
    } else {
        $errors[] = "Please select an image to upload";
    }
    
    // If there are errors, store them in session
    if (!empty($errors)) {
        $_SESSION['error_message'] = implode("<br>", $errors);
    }
}

/********************************
 * UPDATE BIKE STATUS FUNCTIONALITY
 ********************************/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status']) && isset($_POST['bike_id']) && isset($_POST['status'])) {
    $bike_id = intval($_POST['bike_id']);
    $status = sanitize($_POST['status']);
    
    try {
        // Check if status is valid
        if (!in_array($status, ['available', 'maintenance'])) {
            $_SESSION['error_message'] = "Invalid status selected";
        } 
        // Check if bike is reserved and trying to set to available
        elseif ($status === 'available') {
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as count FROM reservations 
                WHERE bike_id = ? AND status IN ('pending', 'confirmed')
            ");
            $stmt->execute([$bike_id]);
            $reserved = $stmt->fetch()['count'] > 0;
            
            if ($reserved) {
                $_SESSION['error_message'] = "Cannot set status to available: bike has active reservations";
                header("Location: index.php");
                exit();
            }
        }
        
        // Update status
        $stmt = $pdo->prepare("UPDATE bikes SET status = ? WHERE bike_id = ?");
        if ($stmt->execute([$status, $bike_id])) {
            $_SESSION['flash_message'] = "Bike status updated successfully";
        } else {
            $_SESSION['error_message'] = "Failed to update bike status";
        }
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Error: " . $e->getMessage();
    }
    
    // Redirect to refresh the page
    header("Location: index.php");
    exit();
}

/********************************
 * DELETE BIKE FUNCTIONALITY
 ********************************/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_bike']) && isset($_POST['bike_id'])) {
    $bike_id = intval($_POST['bike_id']);
    
    try {
        // Check if the bike is currently reserved
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count FROM reservations 
            WHERE bike_id = ? AND status IN ('pending', 'confirmed')
        ");
        $stmt->execute([$bike_id]);
        $reserved = $stmt->fetch()['count'] > 0;
        
        if ($reserved) {
            $_SESSION['error_message'] = "Cannot delete a bike that has active reservations";
        } else {
            // Delete the bike
            $stmt = $pdo->prepare("DELETE FROM bikes WHERE bike_id = ?");
            if ($stmt->execute([$bike_id])) {
                $_SESSION['flash_message'] = "Bike deleted successfully";
            } else {
                $_SESSION['error_message'] = "Failed to delete bike";
            }
        }
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Error: " . $e->getMessage();
    }
    
    // Redirect to refresh the page
    header("Location: index.php");
    exit();
}

/********************************
 * FETCH BIKE DATA
 ********************************/
// Get bikes with filter options
$status_filter = isset($_GET['status']) ? sanitize($_GET['status']) : '';
$type_filter = isset($_GET['type']) ? sanitize($_GET['type']) : '';

// Build query based on filters
$query = "SELECT * FROM bikes WHERE 1=1";
$params = [];

if (!empty($status_filter)) {
    $query .= " AND status = ?";
    $params[] = $status_filter;
}

if (!empty($type_filter)) {
    $query .= " AND bike_type = ?";
    $params[] = $type_filter;
}

$query .= " ORDER BY bike_id DESC";

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $bikes = $stmt->fetchAll();
    
    // Get bike types for filter
    $stmt = $pdo->prepare("SELECT DISTINCT bike_type FROM bikes ORDER BY bike_type");
    $stmt->execute();
    $bike_types = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $_SESSION['error_message'] = "Error fetching bikes: " . $e->getMessage();
    $bikes = [];
    $bike_types = [];
}

/********************************
 * DISPLAY BIKE DETAILS
 ********************************/
if ($action === 'view' && isset($_GET['id'])) {
    $bike_id = intval($_GET['id']);
    
    try {
        // Get bike details
        $stmt = $pdo->prepare("
            SELECT b.*, 
                   (SELECT COUNT(*) FROM reservations r WHERE r.bike_id = b.bike_id) AS total_reservations,
                   (SELECT COUNT(*) FROM damages d WHERE d.bike_id = b.bike_id) AS total_damages,
                   (SELECT COUNT(*) FROM maintenance m WHERE m.bike_id = b.bike_id) AS maintenance_count
            FROM bikes b
            WHERE b.bike_id = ?
        ");
        $stmt->execute([$bike_id]);
        $bike = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$bike) {
            $_SESSION['error_message'] = "Bike not found";
            header("Location: index.php");
            exit();
        }
        
        // Get reservation history
        $stmt = $pdo->prepare("
            SELECT r.*, u.first_name, u.last_name, u.email
            FROM reservations r
            JOIN users u ON r.user_id = u.user_id
            WHERE r.bike_id = ?
            ORDER BY r.reservation_date DESC
            LIMIT 10
        ");
        $stmt->execute([$bike_id]);
        $reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get maintenance history
        $stmt = $pdo->prepare("
            SELECT * FROM maintenance
            WHERE bike_id = ?
            ORDER BY start_date DESC
            LIMIT 10
        ");
        $stmt->execute([$bike_id]);
        $maintenance_records = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get damage reports
        $stmt = $pdo->prepare("
            SELECT d.*, u.first_name, u.last_name
            FROM damages d
            LEFT JOIN users u ON d.user_id = u.user_id
            WHERE d.bike_id = ?
            ORDER BY d.report_date DESC
            LIMIT 10
        ");
        $stmt->execute([$bike_id]);
        $damages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Include header
        $page_title = "Bike Details: " . htmlspecialchars($bike['bike_name']);
        include '../../includes/header.php';
        
        ?>
        <div class="container mt-4">
            <h1><?php echo htmlspecialchars($bike['bike_name']); ?> Details</h1>
            
            <div class="row mt-4">
                <div class="col-md-4">
                    <div class="card">
                        <img src="<?php echo !empty($bike['image_path']) ? '../../' . $bike['image_path'] : '../../assets/images/default-bike.jpg'; ?>" 
                             class="card-img-top" alt="<?php echo htmlspecialchars($bike['bike_name']); ?>">
                        <div class="card-body">
                            <h5 class="card-title"><?php echo htmlspecialchars($bike['bike_name']); ?></h5>
                            <p class="card-text">
                                <strong>Type:</strong> <?php echo htmlspecialchars($bike['bike_type']); ?><br>
                                <strong>Status:</strong> <span class="badge bg-<?php echo $bike['status'] === 'available' ? 'success' : 'warning'; ?>">
                                    <?php echo ucfirst(htmlspecialchars($bike['status'])); ?>
                                </span><br>
                                <strong>Hourly Rate:</strong> $<?php echo number_format($bike['hourly_rate'], 2); ?><br>
                                <strong>Total Reservations:</strong> <?php echo $bike['total_reservations']; ?><br>
                                <strong>Maintenance Count:</strong> <?php echo $bike['maintenance_count']; ?><br>
                                <strong>Damage Reports:</strong> <?php echo $bike['total_damages']; ?><br>
                            </p>
                        </div>
                        <div class="card-footer">
                            <a href="index.php" class="btn btn-secondary">Back to Bikes</a>
                            <a href="../manage-bike-images.php?id=<?php echo $bike['bike_id']; ?>" class="btn btn-primary">Manage Images</a>
                        </div>
                    </div>
                    
                    <div class="card mt-3">
                        <div class="card-header">
                            <h5>Specifications</h5>
                        </div>
                        <div class="card-body">
                            <p><?php echo nl2br(htmlspecialchars($bike['specifications'])); ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-8">
                    <ul class="nav nav-tabs" id="bikeDetailTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="reservations-tab" data-bs-toggle="tab" 
                                    data-bs-target="#reservations" type="button" role="tab" 
                                    aria-controls="reservations" aria-selected="true">Reservations</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="maintenance-tab" data-bs-toggle="tab" 
                                    data-bs-target="#maintenance" type="button" role="tab" 
                                    aria-controls="maintenance" aria-selected="false">Maintenance</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="damages-tab" data-bs-toggle="tab" 
                                    data-bs-target="#damages" type="button" role="tab" 
                                    aria-controls="damages" aria-selected="false">Damages</button>
                        </li>
                    </ul>
                    
                    <div class="tab-content p-3 border border-top-0 rounded-bottom" id="bikeDetailTabsContent">
                        <!-- Reservations Tab -->
                        <div class="tab-pane fade show active" id="reservations" role="tabpanel" aria-labelledby="reservations-tab">
                            <?php if (empty($reservations)): ?>
                                <p class="text-muted">No reservation history found for this bike.</p>
                            <?php else: ?>
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>User</th>
                                            <th>Dates</th>
                                            <th>Hours</th>
                                            <th>Status</th>
                                            <th>Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($reservations as $res): ?>
                                            <tr>
                                                <td><?php echo $res['reservation_id']; ?></td>
                                                <td><?php echo htmlspecialchars($res['first_name'] . ' ' . $res['last_name']); ?><br>
                                                    <small><?php echo htmlspecialchars($res['email']); ?></small>
                                                </td>
                                                <td>
                                                    <?php echo date('M d, Y', strtotime($res['reservation_date'])); ?><br>
                                                    <small><?php echo date('h:i A', strtotime($res['start_time'])); ?> - 
                                                    <?php echo date('h:i A', strtotime($res['end_time'])); ?></small>
                                                </td>
                                                <td><?php echo $res['duration_hours']; ?></td>
                                                <td><span class="badge bg-<?php 
                                                    echo $res['status'] === 'confirmed' ? 'success' : 
                                                        ($res['status'] === 'pending' ? 'warning' : 
                                                        ($res['status'] === 'completed' ? 'info' : 'danger')); 
                                                    ?>">
                                                    <?php echo ucfirst(htmlspecialchars($res['status'])); ?>
                                                </span></td>
                                                <td>$<?php echo number_format($res['total_amount'], 2); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                                <?php if (count($reservations) === 10): ?>
                                    <p class="text-muted">Showing the 10 most recent reservations.</p>
                                    <a href="../reports/reservations.php?bike_id=<?php echo $bike_id; ?>" class="btn btn-sm btn-outline-primary">
                                        View Complete History
                                    </a>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Maintenance Tab -->
                        <div class="tab-pane fade" id="maintenance" role="tabpanel" aria-labelledby="maintenance-tab">
                            <?php if (empty($maintenance_records)): ?>
                                <p class="text-muted">No maintenance history found for this bike.</p>
                                <a href="../schedule-maintenance.php?bike_id=<?php echo $bike_id; ?>" class="btn btn-primary">
                                    Schedule Maintenance
                                </a>
                            <?php else: ?>
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Start Date</th>
                                            <th>End Date</th>
                                            <th>Type</th>
                                            <th>Description</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($maintenance_records as $record): ?>
                                            <tr>
                                                <td><?php echo $record['maintenance_id']; ?></td>
                                                <td><?php echo date('M d, Y', strtotime($record['start_date'])); ?></td>
                                                <td><?php echo $record['end_date'] ? date('M d, Y', strtotime($record['end_date'])) : 'In Progress'; ?></td>
                                                <td><?php echo htmlspecialchars($record['maintenance_type']); ?></td>
                                                <td><?php echo htmlspecialchars($record['description']); ?></td>
                                                <td><span class="badge bg-<?php 
                                                    echo $record['end_date'] ? 'success' : 'warning'; 
                                                    ?>">
                                                    <?php echo $record['end_date'] ? 'Completed' : 'In Progress'; ?>
                                                </span></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                                <div class="mt-3">
                                    <a href="../schedule-maintenance.php?bike_id=<?php echo $bike_id; ?>" class="btn btn-primary">
                                        Schedule Maintenance
                                    </a>
                                    <?php if (count($maintenance_records) === 10): ?>
                                        <a href="../maintenance/index.php?bike_id=<?php echo $bike_id; ?>" class="btn btn-outline-primary">
                                            View Complete History
                                        </a>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Damages Tab -->
                        <div class="tab-pane fade" id="damages" role="tabpanel" aria-labelledby="damages-tab">
                            <?php if (empty($damages)): ?>
                                <p class="text-muted">No damage reports found for this bike.</p>
                            <?php else: ?>
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Reported By</th>
                                            <th>Date</th>
                                            <th>Description</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($damages as $damage): ?>
                                            <tr>
                                                <td><?php echo $damage['damage_id']; ?></td>
                                                <td><?php echo $damage['user_id'] ? htmlspecialchars($damage['first_name'] . ' ' . $damage['last_name']) : 'Admin'; ?></td>
                                                <td><?php echo date('M d, Y', strtotime($damage['report_date'])); ?></td>
                                                <td><?php echo htmlspecialchars($damage['description']); ?></td>
                                                <td><span class="badge bg-<?php 
                                                    echo $damage['status'] === 'fixed' ? 'success' : 'danger'; 
                                                    ?>">
                                                    <?php echo ucfirst(htmlspecialchars($damage['status'])); ?>
                                                </span></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                                <?php if (count($damages) === 10): ?>
                                    <p class="text-muted">Showing the 10 most recent damage reports.</p>
                                    <a href="../reports/damages.php?bike_id=<?php echo $bike_id; ?>" class="btn btn-sm btn-outline-primary">
                                        View All Damage Reports
                                    </a>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
        
        // Include footer
        include '../../includes/footer.php';
        exit(); // Stop execution after displaying bike details
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Error: " . $e->getMessage();
        header("Location: index.php");
        exit();
    }
}

// Now that all redirects are complete, include the header which will start HTML output
include '../../includes/header.php';
?>

<div class="bg-white dark:bg-gray-800 p-8 rounded-lg shadow-md mb-8">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-6">
        <h1 class="text-3xl font-bold dark:text-white">Bike Management</h1>
        <div class="mt-4 md:mt-0 flex flex-col md:flex-row gap-2">
            <?php if ($action !== 'add'): ?>
                <a href="index.php?action=add" class="bg-gradient-to-r from-purple-500 to-purple-700 hover:from-purple-600 hover:to-purple-800 text-white font-bold py-2 px-4 rounded-lg">
                    <i class="fas fa-plus mr-2"></i>Add New Bike
                </a>
            <?php endif; ?>
            
            <?php if ($action !== 'list'): ?>
                <a href="index.php" class="bg-gradient-to-r from-gray-500 to-gray-700 hover:from-gray-600 hover:to-gray-800 text-white font-bold py-2 px-4 rounded-lg">
                    <i class="fas fa-list mr-2"></i>View All Bikes
                </a>
            <?php endif; ?>
            
            <?php if ($action !== 'images'): ?>
                <a href="index.php?action=images" class="bg-gradient-to-r from-blue-500 to-blue-700 hover:from-blue-600 hover:to-blue-800 text-white font-bold py-2 px-4 rounded-lg">
                    <i class="fas fa-images mr-2"></i>Manage Images
                </a>
            <?php endif; ?>
        </div>
    </div>
    
    <?php if ($action === 'add'): ?>
    <!-- ADD NEW BIKE FORM -->
    <div class="mb-6">
        <h2 class="text-xl font-semibold mb-4 dark:text-white">Add New Bike</h2>
        <form action="index.php?action=add" method="POST" enctype="multipart/form-data" class="space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="bike_name" class="block text-gray-700 dark:text-gray-300 text-sm font-medium mb-2">Bike Name</label>
                    <input type="text" id="bike_name" name="bike_name" class="form-input w-full px-4 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600 dark:text-white" 
                           value="<?php echo isset($_POST['bike_name']) ? htmlspecialchars($_POST['bike_name']) : ''; ?>" required>
                </div>
                
                <div>
                    <label for="bike_type" class="block text-gray-700 dark:text-gray-300 text-sm font-medium mb-2">Bike Type</label>
                    <select id="bike_type" name="bike_type" class="form-input w-full px-4 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600 dark:text-white" required>
                        <option value="">Select Type</option>
                        <option value="Mountain Bike" <?php echo (isset($_POST['bike_type']) && $_POST['bike_type'] === 'Mountain Bike') ? 'selected' : ''; ?>>Mountain Bike</option>
                        <option value="Road Bike" <?php echo (isset($_POST['bike_type']) && $_POST['bike_type'] === 'Road Bike') ? 'selected' : ''; ?>>Road Bike</option>
                        <option value="City Bike" <?php echo (isset($_POST['bike_type']) && $_POST['bike_type'] === 'City Bike') ? 'selected' : ''; ?>>City Bike</option>
                        <option value="E-Bike" <?php echo (isset($_POST['bike_type']) && $_POST['bike_type'] === 'E-Bike') ? 'selected' : ''; ?>>E-Bike</option>
                        <option value="Kids Bike" <?php echo (isset($_POST['bike_type']) && $_POST['bike_type'] === 'Kids Bike') ? 'selected' : ''; ?>>Kids Bike</option>
                        <option value="Tandem Bike" <?php echo (isset($_POST['bike_type']) && $_POST['bike_type'] === 'Tandem Bike') ? 'selected' : ''; ?>>Tandem Bike</option>
                        <option value="Scooty" <?php echo (isset($_POST['bike_type']) && $_POST['bike_type'] === 'Scooty') ? 'selected' : ''; ?>>Scooty</option>
                        <option value="Motorcycle" <?php echo (isset($_POST['bike_type']) && $_POST['bike_type'] === 'Motorcycle') ? 'selected' : ''; ?>>Motorcycle</option>
                    </select>
                </div>
                
                <div>
                    <label for="hourly_rate" class="block text-gray-700 dark:text-gray-300 text-sm font-medium mb-2">Hourly Rate (₹)</label>
                    <input type="number" id="hourly_rate" name="hourly_rate" step="0.01" min="0" class="form-input w-full px-4 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600 dark:text-white" 
                           value="<?php echo isset($_POST['hourly_rate']) ? htmlspecialchars($_POST['hourly_rate']) : ''; ?>" required>
                </div>
                
                <div>
                    <label for="status" class="block text-gray-700 dark:text-gray-300 text-sm font-medium mb-2">Status</label>
                    <select id="status" name="status" class="form-input w-full px-4 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600 dark:text-white" required>
                        <option value="available" <?php echo (isset($_POST['status']) && $_POST['status'] === 'available') ? 'selected' : ''; ?>>Available</option>
                        <option value="maintenance" <?php echo (isset($_POST['status']) && $_POST['status'] === 'maintenance') ? 'selected' : ''; ?>>Maintenance</option>
                    </select>
                </div>
            </div>
            
            <div>
                <label for="specifications" class="block text-gray-700 dark:text-gray-300 text-sm font-medium mb-2">Specifications</label>
                <textarea id="specifications" name="specifications" rows="4" class="form-input w-full px-4 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600 dark:text-white" 
                          placeholder="Enter bike specifications..."><?php echo isset($_POST['specifications']) ? htmlspecialchars($_POST['specifications']) : ''; ?></textarea>
            </div>
            
            <div>
                <label class="block text-gray-700 dark:text-gray-300 text-sm font-medium mb-2">Bike Image</label>
                <div class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 dark:border-gray-600 border-dashed rounded-md dark:bg-gray-700">
                    <div class="space-y-1 text-center">
                        <svg class="mx-auto h-12 w-12 text-gray-400 dark:text-gray-500" stroke="currentColor" fill="none" viewBox="0 0 48 48" aria-hidden="true">
                            <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                        <div class="flex text-sm text-gray-600 dark:text-gray-400">
                            <label for="bike_image" class="relative cursor-pointer bg-white dark:bg-transparent rounded-md font-medium text-purple-600 dark:text-purple-400 hover:text-purple-500 dark:hover:text-purple-300 focus-within:outline-none">
                                <span>Upload a file</span>
                                <input id="bike_image" name="bike_image" type="file" class="sr-only" accept="image/*">
                            </label>
                            <p class="pl-1 dark:text-gray-400">or drag and drop</p>
                        </div>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            PNG, JPG, GIF up to 5MB
                        </p>
                    </div>
                </div>
                <div id="image_preview" class="mt-3 hidden">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Selected image:</p>
                    <div class="mt-1 relative">
                        <img id="preview_img" src="#" alt="Preview" class="max-h-32 rounded border dark:border-gray-600">
                        <button type="button" id="remove_image" class="absolute top-0 right-0 bg-red-500 text-white rounded-full p-1 transform translate-x-1/2 -translate-y-1/2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
            
            <div class="pt-4">
                <button type="submit" class="w-full bg-gradient-to-r from-purple-500 to-purple-700 hover:from-purple-600 hover:to-purple-800 text-white font-bold py-2 px-4 rounded-lg">
                    <i class="fas fa-plus-circle mr-2"></i>Add Bike to Fleet
                </button>
            </div>
        </form>
    </div>
    <?php elseif ($action === 'images'): ?>
    <!-- MANAGE BIKE IMAGES -->
    <div class="mb-6">
        <h2 class="text-xl font-semibold mb-4 dark:text-white">Manage Bike Images</h2>
        <p class="text-gray-600 dark:text-gray-400 mb-4">Upload images for bikes in your fleet. Recommended image size: 800x600 pixels.</p>
        
        <?php if (empty($bikes)): ?>
            <div class="bg-gray-50 dark:bg-gray-700 p-6 text-center rounded-lg">
                <p class="text-gray-600 dark:text-gray-400">No bikes found in the database.</p>
                <a href="index.php?action=add" class="mt-4 inline-block bg-purple-600 hover:bg-purple-700 text-white font-bold py-2 px-4 rounded">
                    Add New Bike
                </a>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php foreach ($bikes as $bike): ?>
                    <div class="bg-gray-50 dark:bg-gray-700 rounded-lg overflow-hidden border border-gray-200 dark:border-gray-600">
                        <!-- Bike Image -->
                        <div class="h-48 overflow-hidden bg-gray-100 dark:bg-gray-800 relative">
                            <img src="<?php echo $bike['image_path'] ? '../../' . $bike['image_path'] : '../../assets/images/default-bike.jpg'; ?>" 
                                 alt="<?php echo htmlspecialchars($bike['bike_name']); ?>"
                                 class="w-full h-full object-cover">
                            
                            <?php if ($bike['image_path']): ?>
                                <div class="absolute top-2 right-2 bg-green-500 text-white rounded-full p-1 text-xs">
                                    <i class="fas fa-check"></i> Has Image
                                </div>
                            <?php else: ?>
                                <div class="absolute top-2 right-2 bg-yellow-500 text-white rounded-full p-1 text-xs">
                                    <i class="fas fa-exclamation-triangle"></i> Default Image
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Bike Details and Upload Form -->
                        <div class="p-4">
                            <h3 class="text-lg font-semibold dark:text-white mb-1">
                                <?php echo htmlspecialchars($bike['bike_name']); ?>
                            </h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400 mb-2">
                                <?php echo htmlspecialchars($bike['bike_type']); ?> - ID: <?php echo $bike['bike_id']; ?>
                            </p>
                            
                            <form action="index.php?action=images" method="POST" enctype="multipart/form-data" class="mt-3">
                                <input type="hidden" name="bike_id" value="<?php echo $bike['bike_id']; ?>">
                                
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Update Image:
                                </label>
                                
                                <div class="flex">
                                    <input type="file" name="bike_image" 
                                           class="form-input w-full border-r-0 rounded-r-none border dark:bg-gray-700 dark:border-gray-600 dark:text-white text-sm" 
                                           accept="image/*" required>
                                           
                                    <button type="submit" name="update_image" 
                                            class="bg-purple-600 hover:bg-purple-700 text-white py-1 px-4 rounded-r text-sm">
                                        Upload
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    <?php else: ?>
    <!-- MANAGE BIKES (DEFAULT VIEW) -->
    <!-- Filter options -->
    <div class="mb-6">
        <form action="index.php" method="GET" class="flex flex-wrap gap-4 bg-gray-800 p-6 rounded-lg shadow-md">
            <div class="flex-1 min-w-[200px]">
                <label for="status" class="block text-gray-300 text-sm font-medium mb-2">Filter by Status</label>
                <select name="status" id="status" class="form-input w-full border rounded-lg px-4 py-2 bg-gray-700 border-gray-600 text-white" onchange="this.form.submit()">
                    <option value="">All Status</option>
                    <option value="available" <?php echo $status_filter === 'available' ? 'selected' : ''; ?>>Available</option>
                    <option value="reserved" <?php echo $status_filter === 'reserved' ? 'selected' : ''; ?>>Reserved</option>
                    <option value="maintenance" <?php echo $status_filter === 'maintenance' ? 'selected' : ''; ?>>Maintenance</option>
                </select>
            </div>
            
            <div class="flex-1 min-w-[200px]">
                <label for="type" class="block text-gray-300 text-sm font-medium mb-2">Filter by Type</label>
                <select name="type" id="type" class="form-input w-full border rounded-lg px-4 py-2 bg-gray-700 border-gray-600 text-white" onchange="this.form.submit()">
                    <option value="">All Types</option>
                    <?php foreach ($bike_types as $type): ?>
                        <option value="<?php echo htmlspecialchars($type); ?>" <?php echo $type_filter === $type ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($type); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <?php if (!empty($status_filter) || !empty($type_filter)): ?>
                <div class="w-full">
                    <a href="index.php" class="text-purple-400 hover:text-purple-300 text-sm">
                        <i class="fas fa-times-circle mr-1"></i>Clear Filters
                    </a>
                </div>
            <?php endif; ?>
        </form>
    </div>
    
    <!-- Bikes table -->
    <?php if (empty($bikes)): ?>
        <div class="bg-gray-50 dark:bg-gray-700 p-6 text-center rounded-lg border border-gray-200 dark:border-gray-600">
            <p class="text-gray-600 dark:text-gray-400">No bikes found matching your criteria.</p>
        </div>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-700">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">ID</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Image</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Bike Details</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Status</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Hourly Rate</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                    <?php foreach ($bikes as $bike): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300">
                                #<?php echo $bike['bike_id']; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="h-16 w-16 rounded-md overflow-hidden bg-gray-100 dark:bg-gray-700">
                                    <img src="<?php echo $bike['image_path'] ? '../../' . $bike['image_path'] : '../../assets/images/default-bike.jpg'; ?>" 
                                         alt="<?php echo htmlspecialchars($bike['bike_name']); ?>"
                                         class="h-full w-full object-cover">
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm font-medium text-gray-900 dark:text-white"><?php echo htmlspecialchars($bike['bike_name']); ?></div>
                                <div class="text-sm text-gray-500 dark:text-gray-400"><?php echo htmlspecialchars($bike['bike_type']); ?></div>
                                <div class="text-xs text-gray-500 dark:text-gray-400 mt-1 max-w-xs truncate"><?php echo htmlspecialchars($bike['specifications']); ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php if ($bike['status'] === 'available'): ?>
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-100">
                                        Available
                                    </span>
                                <?php elseif ($bike['status'] === 'reserved'): ?>
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800 dark:bg-yellow-800 dark:text-yellow-100">
                                        Reserved
                                    </span>
                                <?php else: ?>
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800 dark:bg-red-800 dark:text-red-100">
                                        Maintenance
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                ₹<?php echo number_format($bike['hourly_rate'], 2); ?>/hour
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm space-x-1">
                                <a href="index.php?action=view&id=<?php echo $bike['bike_id']; ?>" class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300">View</a>
                                <a href="../bikes/edit.php?id=<?php echo $bike['bike_id']; ?>" class="text-purple-600 hover:text-purple-900 dark:text-purple-400 dark:hover:text-purple-300">Edit</a>
                                
                                <!-- Status Update Options -->
                                <?php if ($bike['status'] !== 'reserved'): ?>
                                    <div class="dropdown inline-block relative">
                                        <button class="text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-300 ml-2">Status <i class="fas fa-caret-down"></i></button>
                                        <div class="dropdown-menu absolute hidden bg-white dark:bg-gray-700 border rounded-md shadow-lg py-1 z-10 w-32">
                                            <?php if ($bike['status'] !== 'available'): ?>
                                                <form action="index.php" method="POST" class="block">
                                                    <input type="hidden" name="bike_id" value="<?php echo $bike['bike_id']; ?>">
                                                    <input type="hidden" name="status" value="available">
                                                    <button type="submit" name="update_status" class="px-4 py-1 hover:bg-gray-100 dark:hover:bg-gray-600 text-green-600 dark:text-green-400 w-full text-left text-sm">
                                                        <i class="fas fa-check-circle mr-1"></i> Available
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            
                                            <?php if ($bike['status'] !== 'maintenance'): ?>
                                                <form action="index.php" method="POST" class="block">
                                                    <input type="hidden" name="bike_id" value="<?php echo $bike['bike_id']; ?>">
                                                    <input type="hidden" name="status" value="maintenance">
                                                    <button type="submit" name="update_status" class="px-4 py-1 hover:bg-gray-100 dark:hover:bg-gray-600 text-red-600 dark:text-red-400 w-full text-left text-sm">
                                                        <i class="fas fa-tools mr-1"></i> Maintenance
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <!-- Delete Option -->
                                <?php if ($bike['status'] !== 'reserved'): ?>
                                    <form action="index.php" method="POST" class="inline-block" onsubmit="return confirm('Are you sure you want to delete this bike? This action cannot be undone.');">
                                        <input type="hidden" name="bike_id" value="<?php echo $bike['bike_id']; ?>">
                                        <button type="submit" name="delete_bike" class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300 ml-2">Delete</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
    <?php endif; ?>
</div>

<style>
    /* Dropdown styling */
    .dropdown:hover .dropdown-menu {
        display: block;
    }
    
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

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Image preview functionality for Add Bike form
        const fileInput = document.getElementById('bike_image');
        if (fileInput) {
            const previewContainer = document.getElementById('image_preview');
            const previewImage = document.getElementById('preview_img');
            const removeButton = document.getElementById('remove_image');
            
            fileInput.addEventListener('change', function() {
                const file = this.files[0];
                
                if (file) {
                    const reader = new FileReader();
                    
                    reader.onload = function(e) {
                        previewImage.src = e.target.result;
                        previewContainer.classList.remove('hidden');
                    };
                    
                    reader.readAsDataURL(file);
                }
            });
            
            if (removeButton) {
                removeButton.addEventListener('click', function() {
                    fileInput.value = '';
                    previewContainer.classList.add('hidden');
                });
            }
        }
    });
</script>

<?php include '../../includes/footer.php'; ?>