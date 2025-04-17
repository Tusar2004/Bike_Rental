<?php
// Start session only if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Include database connection
require_once __DIR__ . '/../db/db.php';

// Check if the current page is in the admin directory
$isAdminPage = strpos($_SERVER['PHP_SELF'], '/admin/') !== false;
?>
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Velo Rapido - Premium Bike Rentals</title>
    
    <!-- Tailwind CSS via CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        // Configure Tailwind with dark mode
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        lavender: {
                            100: '#F5F3FF',
                            200: '#EDE9FE',
                            300: '#DDD6FE',
                            400: '#C4B5FD',
                            500: '#A78BFA',
                            600: '#9F7AEA',
                            700: '#8B5CF6',
                            800: '#7C3AED',
                            900: '#6D28D9'
                        }
                    }
                }
            }
        }
    </script>
    
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="/velo-rapido/assets/css/style.css">
</head>
<body class="bg-gray-100 dark:bg-gray-900 min-h-screen flex flex-col">
    <!-- Navigation -->
    <nav class="bg-gradient-to-r from-purple-200 via-purple-300 to-purple-200 dark:from-purple-900 dark:via-purple-800 dark:to-purple-900 text-gray-800 dark:text-white shadow-lg fixed top-0 left-0 right-0 z-50">
        <div class="container mx-auto px-6 md:px-12">
            <div class="flex justify-between items-center py-4">
                <!-- Logo -->
                <a href="/velo-rapido/index.php" class="flex items-center space-x-3">
                    <i class="fas fa-bicycle text-2xl text-purple-700 dark:text-purple-400"></i>
                    <span class="text-xl font-bold text-purple-800 dark:text-purple-300">Velo Rapido</span>
                </a>
                
                <!-- Desktop Menu -->
                <div class="hidden md:flex space-x-6">
                    <?php if ($isAdminPage): ?>
                        <!-- Admin Navigation -->
                        <a href="/velo-rapido/admin/dashboard.php" class="hover:text-purple-600 dark:hover:text-purple-300">Dashboard</a>
                        <a href="/velo-rapido/admin/bikes/index.php" class="hover:text-purple-600 dark:hover:text-purple-300">Manage Bikes</a>
                        <a href="/velo-rapido/admin/maintenance/index.php" class="hover:text-purple-600 dark:hover:text-purple-300">Maintenance</a>
                        <a href="/velo-rapido/admin/users/index.php" class="hover:text-purple-600 dark:hover:text-purple-300">Users</a>
                        <a href="/velo-rapido/admin/reports/damages.php" class="hover:text-purple-600 dark:hover:text-purple-300">Damage Reports</a>
                        <a href="/velo-rapido/index.php" class="hover:text-purple-600 dark:hover:text-purple-300">Back to Site</a>
                        <a href="/velo-rapido/auth/logout.php" class="hover:text-purple-600 dark:hover:text-purple-300">Logout</a>
                    <?php else: ?>
                        <!-- Regular Navigation -->
                        <a href="/velo-rapido/index.php" class="hover:text-purple-600 dark:hover:text-purple-300">Home</a>
                        <a href="/velo-rapido/pages/fleet.php" class="hover:text-purple-600 dark:hover:text-purple-300">Our Fleet</a>
                        
                        <?php if (isLoggedIn()): ?>
                            <a href="/velo-rapido/pages/dashboard.php" class="hover:text-purple-600 dark:hover:text-purple-300">My Rentals</a>
                            
                            <?php if (isAdmin()): ?>
                                <a href="/velo-rapido/admin/dashboard.php" class="hover:text-purple-600 dark:hover:text-purple-300">Admin</a>
                            <?php endif; ?>
                            
                            <a href="/velo-rapido/auth/logout.php" class="hover:text-purple-600 dark:hover:text-purple-300">Logout</a>
                        <?php else: ?>
                            <a href="/velo-rapido/auth/login.php" class="hover:text-purple-600 dark:hover:text-purple-300">Login</a>
                            <a href="/velo-rapido/auth/register.php" class="hover:text-purple-600 dark:hover:text-purple-300">Register</a>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
                
                <!-- Mobile Menu Button -->
                <div class="md:hidden">
                    <button id="menu-toggle" class="focus:outline-none">
                        <i class="fas fa-bars text-xl text-purple-700 dark:text-purple-400"></i>
                    </button>
                </div>
            </div>
            
            <!-- Mobile Menu -->
            <div id="mobile-menu" class="md:hidden hidden pb-4">
                <?php if ($isAdminPage): ?>
                    <!-- Admin Navigation -->
                    <a href="/velo-rapido/admin/dashboard.php" class="block py-2 hover:text-purple-600 dark:hover:text-purple-300">Dashboard</a>
                    <a href="/velo-rapido/admin/bikes/index.php" class="block py-2 hover:text-purple-600 dark:hover:text-purple-300">Manage Bikes</a>
                    <a href="/velo-rapido/admin/maintenance/index.php" class="block py-2 hover:text-purple-600 dark:hover:text-purple-300">Maintenance</a>
                    <a href="/velo-rapido/admin/users/index.php" class="block py-2 hover:text-purple-600 dark:hover:text-purple-300">Users</a>
                    <a href="/velo-rapido/admin/reports/damages.php" class="block py-2 hover:text-purple-600 dark:hover:text-purple-300">Damage Reports</a>
                    <a href="/velo-rapido/index.php" class="block py-2 hover:text-purple-600 dark:hover:text-purple-300">Back to Site</a>
                    <a href="/velo-rapido/auth/logout.php" class="block py-2 hover:text-purple-600 dark:hover:text-purple-300">Logout</a>
                <?php else: ?>
                    <!-- Regular Navigation -->
                    <a href="/velo-rapido/index.php" class="block py-2 hover:text-purple-600 dark:hover:text-purple-300">Home</a>
                    <a href="/velo-rapido/pages/fleet.php" class="block py-2 hover:text-purple-600 dark:hover:text-purple-300">Our Fleet</a>
                    
                    <?php if (isLoggedIn()): ?>
                        <a href="/velo-rapido/pages/dashboard.php" class="block py-2 hover:text-purple-600 dark:hover:text-purple-300">My Rentals</a>
                        
                        <?php if (isAdmin()): ?>
                            <a href="/velo-rapido/admin/dashboard.php" class="block py-2 hover:text-purple-600 dark:hover:text-purple-300">Admin</a>
                        <?php endif; ?>
                        
                        <a href="/velo-rapido/auth/logout.php" class="block py-2 hover:text-purple-600 dark:hover:text-purple-300">Logout</a>
                    <?php else: ?>
                        <a href="/velo-rapido/auth/login.php" class="block py-2 hover:text-purple-600 dark:hover:text-purple-300">Login</a>
                        <a href="/velo-rapido/auth/register.php" class="block py-2 hover:text-purple-600 dark:hover:text-purple-300">Register</a>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </nav>
    
    <!-- Flash messages -->
    <div class="pt-20"> <!-- Added padding to prevent content from hiding under the fixed header -->
    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="bg-red-100 dark:bg-red-900/30 border border-red-400 dark:border-red-700 text-red-700 dark:text-red-300 px-4 py-3 rounded relative mt-4 mx-auto container" role="alert">
            <span class="block sm:inline"><?php echo $_SESSION['error_message']; ?></span>
            <span class="absolute top-0 bottom-0 right-0 px-4 py-3">
                <svg class="fill-current h-6 w-6 text-red-500" role="button" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                    <title>Close</title>
                    <path d="M14.348 14.849a1.2 1.2 0 0 1-1.697 0L10 11.819l-2.651 3.029a1.2 1.2 0 1 1-1.697-1.697l2.758-3.15-2.759-3.152a1.2 1.2 0 1 1 1.697-1.697L10 8.183l2.651-3.031a1.2 1.2 0 1 1 1.697 1.697l-2.758 3.152 2.758 3.15a1.2 1.2 0 0 1 0 1.698z"/>
                </svg>
            </span>
        </div>
        <?php unset($_SESSION['error_message']); ?>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['flash_message'])): ?>
        <div class="bg-green-100 dark:bg-green-900/30 border border-green-400 dark:border-green-700 text-green-700 dark:text-green-300 px-4 py-3 rounded relative mt-4 mx-auto container" role="alert">
            <span class="block sm:inline"><?php echo $_SESSION['flash_message']; ?></span>
            <span class="absolute top-0 bottom-0 right-0 px-4 py-3">
                <svg class="fill-current h-6 w-6 text-green-500" role="button" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                    <title>Close</title>
                    <path d="M14.348 14.849a1.2 1.2 0 0 1-1.697 0L10 11.819l-2.651 3.029a1.2 1.2 0 1 1-1.697-1.697l2.758-3.15-2.759-3.152a1.2 1.2 0 1 1 1.697-1.697L10 8.183l2.651-3.031a1.2 1.2 0 1 1 1.697 1.697l-2.758 3.152 2.758 3.15a1.2 1.2 0 0 1 0 1.698z"/>
                </svg>
            </span>
        </div>
        <?php unset($_SESSION['flash_message']); ?>
    <?php endif; ?>
    </div>
    
    <!-- Main content -->
    <main class="flex-grow container mx-auto px-4 py-8 pt-4">