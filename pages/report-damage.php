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

// Check if reservation ID is provided
if (!isset($_GET['reservation_id'])) {
    $_SESSION['error_message'] = "Reservation ID is required";
    header("Location: dashboard.php");
    exit();
}

$reservation_id = intval($_GET['reservation_id']);

// Get reservation and bike details
try {
    $stmt = $pdo->prepare("
        SELECT r.*, b.bike_id, b.bike_name, b.bike_type, b.image_path
        FROM reservations r
        JOIN bikes b ON r.bike_id = b.bike_id
        WHERE r.reservation_id = ? AND r.user_id = ?
    ");
    $stmt->execute([$reservation_id, $_SESSION['user_id']]);
    $reservation = $stmt->fetch();
    
    // Check if reservation exists and belongs to the user
    if (!$reservation) {
        $_SESSION['error_message'] = "Invalid reservation or you don't have permission to report damage for this bike";
        header("Location: dashboard.php");
        exit();
    }
} catch (PDOException $e) {
    $_SESSION['error_message'] = "Error retrieving reservation: " . $e->getMessage();
    header("Location: dashboard.php");
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $description = sanitize($_POST['description']);
    $bike_id = intval($reservation['bike_id']);
    $user_id = $_SESSION['user_id'];
    
    // Validate form data
    if (empty($description)) {
        $_SESSION['error_message'] = "Please provide a description of the damage";
    } else {
        // Process image upload if provided
        $image_path = '';
        if (isset($_FILES['damage_image']) && $_FILES['damage_image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../assets/images/damages/';
            
            // Create directory if it doesn't exist
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            // Generate unique filename
            $file_ext = pathinfo($_FILES['damage_image']['name'], PATHINFO_EXTENSION);
            $file_name = 'damage_' . time() . '.' . $file_ext;
            $target_file = $upload_dir . $file_name;
            
            // Check if file is an actual image
            $check = getimagesize($_FILES['damage_image']['tmp_name']);
            if ($check === false) {
                $_SESSION['error_message'] = "File is not an image";
            } else {
                // Check file size (max 5MB)
                if ($_FILES['damage_image']['size'] > 5000000) {
                    $_SESSION['error_message'] = "File is too large (max 5MB)";
                } else {
                    // Allow certain file formats
                    $allowed_extensions = array("jpg", "jpeg", "png", "gif");
                    if (!in_array(strtolower($file_ext), $allowed_extensions)) {
                        $_SESSION['error_message'] = "Only JPG, JPEG, PNG & GIF files are allowed";
                    } else {
                        // Upload file
                        if (move_uploaded_file($_FILES['damage_image']['tmp_name'], $target_file)) {
                            $image_path = 'assets/images/damages/' . $file_name;
                        } else {
                            $_SESSION['error_message'] = "Failed to upload image";
                        }
                    }
                }
            }
        }
        
        // Insert damage report if no upload errors
        if (!isset($_SESSION['error_message'])) {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO damages (bike_id, user_id, description, image_path, status)
                    VALUES (?, ?, ?, ?, 'reported')
                ");
                $stmt->execute([$bike_id, $user_id, $description, $image_path]);
                
                // Set bike status to maintenance
                $stmt = $pdo->prepare("UPDATE bikes SET status = 'maintenance' WHERE bike_id = ?");
                $stmt->execute([$bike_id]);
                
                $_SESSION['flash_message'] = "Damage report submitted successfully. Thank you for reporting the issue.";
                header("Location: dashboard.php");
                exit();
            } catch (PDOException $e) {
                $_SESSION['error_message'] = "Error submitting damage report: " . $e->getMessage();
            }
        }
    }
}

// Now that all redirects are complete, include the header which will start HTML output
include '../includes/header.php';
?>

<div class="bg-white p-8 rounded-lg shadow-md max-w-3xl mx-auto mb-8">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold">Report Damage</h1>
        <a href="dashboard.php" class="text-blue-600 hover:text-blue-800">
            <i class="fas fa-arrow-left mr-1"></i>Back to Dashboard
        </a>
    </div>
    
    <div class="border-b pb-6 mb-6">
        <h2 class="text-xl font-semibold mb-4">Bike Information</h2>
        <div class="flex flex-col md:flex-row">
            <div class="md:w-1/3 mb-4 md:mb-0">
                <div class="h-40 w-40 rounded-md overflow-hidden bg-gray-200">
                    <img src="<?php echo $reservation['image_path'] ? '../' . $reservation['image_path'] : '../assets/images/default-bike.jpg'; ?>" 
                         alt="<?php echo htmlspecialchars($reservation['bike_name']); ?>"
                         class="h-full w-full object-cover">
                </div>
            </div>
            <div class="md:w-2/3 md:pl-6">
                <p class="text-lg font-semibold"><?php echo htmlspecialchars($reservation['bike_name']); ?></p>
                <p class="text-sm text-gray-600 mb-2"><?php echo htmlspecialchars($reservation['bike_type']); ?></p>
                
                <div class="grid grid-cols-2 gap-4 mt-4">
                    <div>
                        <p class="text-sm text-gray-500">Reservation ID</p>
                        <p class="font-medium">#<?php echo $reservation['reservation_id']; ?></p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Rental Period</p>
                        <p class="font-medium">
                            <?php 
                                $start = new DateTime($reservation['start_time']);
                                $end = new DateTime($reservation['end_time']);
                                echo $start->format('M j, Y g:i A') . ' - ' . $end->format('M j, Y g:i A');
                            ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <form action="report-damage.php?reservation_id=<?php echo $reservation_id; ?>" method="POST" enctype="multipart/form-data" class="space-y-6">
        <div>
            <label for="description" class="block text-gray-700 text-sm font-medium mb-2">Damage Description</label>
            <textarea id="description" name="description" rows="5" class="form-input w-full px-4 py-2 border rounded-lg" 
                placeholder="Please describe the damage or issue in detail..." required><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
            <p class="text-sm text-gray-500 mt-1">Provide as many details as possible about the damage or issue with the bike.</p>
        </div>
        
        <div>
            <label class="block text-gray-700 text-sm font-medium mb-2">Upload Images (Optional)</label>
            <div class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-md">
                <div class="space-y-1 text-center">
                    <svg class="mx-auto h-12 w-12 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48" aria-hidden="true">
                        <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                    <div class="flex text-sm text-gray-600">
                        <label for="damage_image" class="relative cursor-pointer bg-white rounded-md font-medium text-blue-600 hover:text-blue-500 focus-within:outline-none">
                            <span>Upload a file</span>
                            <input id="damage_image" name="damage_image" type="file" class="sr-only" accept="image/*">
                        </label>
                        <p class="pl-1">or drag and drop</p>
                    </div>
                    <p class="text-xs text-gray-500">
                        PNG, JPG, GIF up to 5MB
                    </p>
                </div>
            </div>
            <div id="image_preview" class="mt-3 hidden">
                <p class="text-sm text-gray-500">Selected image:</p>
                <div class="mt-1 relative">
                    <img id="preview_img" src="#" alt="Preview" class="max-h-32 rounded border">
                    <button type="button" id="remove_image" class="absolute top-0 right-0 bg-red-500 text-white rounded-full p-1 transform translate-x-1/2 -translate-y-1/2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
            </div>
        </div>
        
        <div class="border-t pt-6">
            <p class="text-gray-600 mb-4">
                <i class="fas fa-info-circle text-blue-500 mr-1"></i>
                By submitting this report, you acknowledge that the damage occurred during your rental period. Our team will evaluate the damage and determine if any additional charges apply according to our terms of service.
            </p>
            <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg">
                Submit Damage Report
            </button>
        </div>
    </form>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const fileInput = document.getElementById('damage_image');
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
        
        removeButton.addEventListener('click', function() {
            fileInput.value = '';
            previewContainer.classList.add('hidden');
        });
    });
</script>

<?php include '../includes/footer.php'; ?>