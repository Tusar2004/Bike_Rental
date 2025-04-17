<?php
// Start session only if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once '../db/db.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header("Location: /velo-rapido/index.php");
    exit();
}

// Handle registration form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = sanitize($_POST['first_name']);
    $last_name = sanitize($_POST['last_name']);
    $email = sanitize($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    $errors = array();
    
    // Validate form data
    if (empty($first_name) || empty($last_name) || empty($email) || empty($password) || empty($confirm_password)) {
        $errors[] = "All fields are required";
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    }
    
    if (strlen($password) < 6) {
        $errors[] = "Password must be at least 6 characters long";
    }
    
    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match";
    }
    
    // Check if email already exists
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetchColumn() > 0) {
        $errors[] = "Email already in use";
    }
    
    // Register user if there are no errors
    if (empty($errors)) {
        try {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            $stmt = $pdo->prepare("INSERT INTO users (first_name, last_name, email, password) VALUES (?, ?, ?, ?)");
            if ($stmt->execute([$first_name, $last_name, $email, $hashed_password])) {
                $_SESSION['flash_message'] = "Registration successful! You can now log in.";
                header("Location: login.php");
                exit();
            } else {
                $errors[] = "Registration failed. Please try again.";
            }
        } catch (PDOException $e) {
            $errors[] = "Error: " . $e->getMessage();
        }
    }
    
    // Store errors in session
    if (!empty($errors)) {
        $_SESSION['error_message'] = implode("<br>", $errors);
    }
}
?>

<?php include '../includes/header.php'; ?>

<div class="flex justify-center items-center my-8">
    <div class="w-full max-w-md">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg overflow-hidden">
            <div class="bg-gradient-to-r from-purple-600 to-purple-800 dark:from-purple-700 dark:to-purple-900 px-6 py-8 text-white">
                <h2 class="text-2xl font-bold text-center">Create an Account</h2>
                <p class="text-center mt-2">Join Velo Rapido to start booking bikes</p>
            </div>
            
            <div class="p-6">
                <form action="register.php" method="POST" class="space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="first_name" class="block text-gray-700 dark:text-gray-300 text-sm font-medium mb-2">First Name</label>
                            <input type="text" id="first_name" name="first_name" class="form-input w-full px-4 py-2 border rounded-lg text-gray-700 dark:text-gray-200 dark:bg-gray-700 dark:border-gray-600 focus:outline-none focus:border-purple-500 dark:focus:border-purple-400" value="<?php echo isset($_POST['first_name']) ? htmlspecialchars($_POST['first_name']) : ''; ?>" required>
                        </div>
                        
                        <div>
                            <label for="last_name" class="block text-gray-700 dark:text-gray-300 text-sm font-medium mb-2">Last Name</label>
                            <input type="text" id="last_name" name="last_name" class="form-input w-full px-4 py-2 border rounded-lg text-gray-700 dark:text-gray-200 dark:bg-gray-700 dark:border-gray-600 focus:outline-none focus:border-purple-500 dark:focus:border-purple-400" value="<?php echo isset($_POST['last_name']) ? htmlspecialchars($_POST['last_name']) : ''; ?>" required>
                        </div>
                    </div>
                    
                    <div>
                        <label for="email" class="block text-gray-700 dark:text-gray-300 text-sm font-medium mb-2">Email Address</label>
                        <input type="email" id="email" name="email" class="form-input w-full px-4 py-2 border rounded-lg text-gray-700 dark:text-gray-200 dark:bg-gray-700 dark:border-gray-600 focus:outline-none focus:border-purple-500 dark:focus:border-purple-400" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
                    </div>
                    
                    <div>
                        <label for="password" class="block text-gray-700 dark:text-gray-300 text-sm font-medium mb-2">Password</label>
                        <input type="password" id="password" name="password" class="form-input w-full px-4 py-2 border rounded-lg text-gray-700 dark:text-gray-200 dark:bg-gray-700 dark:border-gray-600 focus:outline-none focus:border-purple-500 dark:focus:border-purple-400" required>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Must be at least 6 characters</p>
                    </div>
                    
                    <div>
                        <label for="confirm_password" class="block text-gray-700 dark:text-gray-300 text-sm font-medium mb-2">Confirm Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" class="form-input w-full px-4 py-2 border rounded-lg text-gray-700 dark:text-gray-200 dark:bg-gray-700 dark:border-gray-600 focus:outline-none focus:border-purple-500 dark:focus:border-purple-400" required>
                    </div>
                    
                    <div>
                        <button type="submit" class="btn-primary w-full bg-gradient-to-r from-purple-500 to-purple-700 hover:from-purple-600 hover:to-purple-800 text-white font-bold py-2 px-4 rounded-lg focus:outline-none focus:shadow-outline">
                            <i class="fas fa-user-plus mr-2"></i>Create Account
                        </button>
                    </div>
                </form>
                
                <div class="mt-6 text-center">
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        Already have an account? 
                        <a href="login.php" class="font-medium text-purple-600 dark:text-purple-400 hover:text-purple-500 dark:hover:text-purple-300">Log in here</a>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>