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

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize($_POST['email']);
    $password = $_POST['password'];
    
    if (empty($email) || empty($password)) {
        $_SESSION['error_message'] = "All fields are required";
    } else {
        try {
            // Check if user exists
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password'])) {
                // Login successful
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['first_name'] = $user['first_name'];
                $_SESSION['last_name'] = $user['last_name'];
                $_SESSION['user_role'] = $user['role'];
                
                $_SESSION['flash_message'] = "Login successful! Welcome back, " . $user['first_name'];
                
                // Redirect based on user role
                if ($user['role'] === 'admin') {
                    header("Location: /velo-rapido/admin/dashboard.php");
                } else {
                    header("Location: /velo-rapido/index.php");
                }
                exit();
            } else {
                $_SESSION['error_message'] = "Invalid email or password";
            }
        } catch (PDOException $e) {
            $_SESSION['error_message'] = "Error: " . $e->getMessage();
        }
    }
}
?>

<?php include '../includes/header.php'; ?>

<div class="flex justify-center items-center my-8">
    <div class="w-full max-w-md">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg overflow-hidden">
            <div class="bg-gradient-to-r from-purple-600 to-purple-800 dark:from-purple-700 dark:to-purple-900 px-6 py-8 text-white">
                <h2 class="text-2xl font-bold text-center">Welcome Back!</h2>
                <p class="text-center mt-2">Log in to access your Velo Rapido account</p>
            </div>
            
            <div class="p-6">
                <form action="login.php" method="POST" class="space-y-4">
                    <div>
                        <label for="email" class="block text-gray-700 dark:text-gray-300 text-sm font-medium mb-2">Email Address</label>
                        <input type="email" id="email" name="email" class="form-input w-full px-4 py-2 border rounded-lg text-gray-700 dark:text-gray-200 dark:bg-gray-700 dark:border-gray-600 focus:outline-none focus:border-purple-500 dark:focus:border-purple-400" required>
                    </div>
                    
                    <div>
                        <label for="password" class="block text-gray-700 dark:text-gray-300 text-sm font-medium mb-2">Password</label>
                        <input type="password" id="password" name="password" class="form-input w-full px-4 py-2 border rounded-lg text-gray-700 dark:text-gray-200 dark:bg-gray-700 dark:border-gray-600 focus:outline-none focus:border-purple-500 dark:focus:border-purple-400" required>
                    </div>
                    
                    <div>
                        <button type="submit" class="btn-primary w-full bg-gradient-to-r from-purple-500 to-purple-700 hover:from-purple-600 hover:to-purple-800 text-white font-bold py-2 px-4 rounded-lg focus:outline-none focus:shadow-outline">
                            <i class="fas fa-sign-in-alt mr-2"></i>Log In
                        </button>
                    </div>
                </form>
                
                <div class="mt-6 text-center">
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        Don't have an account? 
                        <a href="register.php" class="font-medium text-purple-600 dark:text-purple-400 hover:text-purple-500 dark:hover:text-purple-300">Sign up here</a>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>