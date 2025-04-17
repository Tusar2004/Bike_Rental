<?php
include 'includes/header.php';

// Get featured bikes (newest 4 bikes)
try {
    $stmt = $pdo->prepare("
        SELECT * FROM bikes 
        WHERE status = 'available' 
        ORDER BY created_at DESC 
        LIMIT 4
    ");
    $stmt->execute();
    $featured_bikes = $stmt->fetchAll();
} catch (PDOException $e) {
    $_SESSION['error_message'] = "Error retrieving bikes: " . $e->getMessage();
    $featured_bikes = [];
}
?>

<!-- Hero Section -->
<section class="relative bg-purple-900 dark:bg-gray-900 text-white py-16">
    <div class="absolute inset-0 overflow-hidden">
        <img src="assets/images/hero-bg.jpg" alt="Cycling background" class="w-full h-full object-cover opacity-20">
    </div>
    <div class="container mx-auto px-4 relative">
        <div class="max-w-2xl">
            <h1 class="text-4xl md:text-5xl font-bold mb-4">Premium Bike Rental for Your Adventures</h1>
            <p class="text-xl mb-8">Explore the city or conquer trails with our high-quality bikes. Easy booking, affordable rates, and top-notch service.</p>
            <div class="flex flex-wrap gap-4">
                <a href="pages/fleet.php" class="bg-gradient-to-r from-purple-400 to-purple-600 text-white hover:from-purple-500 hover:to-purple-700 font-bold py-3 px-6 rounded-lg text-lg">
                    Browse Our Fleet
                </a>
                <?php if (!isLoggedIn()): ?>
                <a href="auth/register.php" class="bg-transparent border-2 border-purple-300 hover:bg-purple-800 hover:border-purple-800 text-white font-bold py-3 px-6 rounded-lg text-lg transition">
                    Sign Up Now
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<!-- How It Works -->
<section class="py-16 bg-white dark:bg-gray-800">
    <div class="container mx-auto px-4">
        <h2 class="text-3xl font-bold text-center mb-12 dark:text-white">How It Works</h2>
        
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            <div class="text-center">
                <div class="inline-block p-4 bg-purple-100 dark:bg-purple-900/40 rounded-full mb-4">
                    <i class="fas fa-search text-purple-600 dark:text-purple-400 text-3xl"></i>
                </div>
                <h3 class="text-xl font-semibold mb-3 dark:text-white">1. Choose Your Bike</h3>
                <p class="text-gray-600 dark:text-gray-300">Browse our diverse fleet and select the perfect bike for your needs.</p>
            </div>
            
            <div class="text-center">
                <div class="inline-block p-4 bg-purple-100 dark:bg-purple-900/40 rounded-full mb-4">
                    <i class="far fa-calendar-alt text-purple-600 dark:text-purple-400 text-3xl"></i>
                </div>
                <h3 class="text-xl font-semibold mb-3 dark:text-white">2. Book Your Ride</h3>
                <p class="text-gray-600 dark:text-gray-300">Select your preferred dates, location, and complete the booking process.</p>
            </div>
            
            <div class="text-center">
                <div class="inline-block p-4 bg-purple-100 dark:bg-purple-900/40 rounded-full mb-4">
                    <i class="fas fa-bicycle text-purple-600 dark:text-purple-400 text-3xl"></i>
                </div>
                <h3 class="text-xl font-semibold mb-3 dark:text-white">3. Enjoy the Ride</h3>
                <p class="text-gray-600 dark:text-gray-300">Pick up your bike and start your adventure with confidence.</p>
            </div>
        </div>
    </div>
</section>

<!-- Featured Bikes -->
<section class="py-16 bg-gray-100 dark:bg-gray-900">
    <div class="container mx-auto px-4">
        <div class="flex justify-between items-center mb-8">
            <h2 class="text-3xl font-bold dark:text-white">Featured Bikes</h2>
            <a href="pages/fleet.php" class="text-purple-600 dark:text-purple-400 hover:text-purple-800 dark:hover:text-purple-300 flex items-center">
                View All Bikes <i class="fas fa-arrow-right ml-2"></i>
            </a>
        </div>
        
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
            <?php foreach ($featured_bikes as $bike): ?>
            <div class="bg-white dark:bg-gray-800 rounded-lg overflow-hidden shadow-lg transition transform hover:-translate-y-1">
                <div class="h-48 overflow-hidden">
                    <img src="<?php echo $bike['image_path'] ?: 'assets/images/default-bike.jpg'; ?>" 
                         alt="<?php echo htmlspecialchars($bike['bike_name']); ?>"
                         class="w-full h-full object-cover">
                </div>
                <div class="p-6">
                    <div class="flex justify-between items-start mb-2">
                        <h3 class="text-xl font-semibold dark:text-white"><?php echo htmlspecialchars($bike['bike_name']); ?></h3>
                        <span class="bg-purple-100 dark:bg-purple-900/40 text-purple-800 dark:text-purple-300 text-xs px-2 py-1 rounded-full"><?php echo htmlspecialchars($bike['bike_type']); ?></span>
                    </div>
                    <p class="text-gray-600 dark:text-gray-300 line-clamp-3 mb-4"><?php echo htmlspecialchars(substr($bike['specifications'], 0, 120)) . (strlen($bike['specifications']) > 120 ? '...' : ''); ?></p>
                    <div class="flex justify-between items-center">
                        <p class="text-lg font-bold text-purple-600 dark:text-purple-400">₹<?php echo number_format($bike['hourly_rate'], 2); ?>/hr</p>
                        <a href="pages/book.php?bike_id=<?php echo $bike['bike_id']; ?>" class="bg-gradient-to-r from-purple-500 to-purple-700 hover:from-purple-600 hover:to-purple-800 text-white font-bold py-2 px-4 rounded">
                            Book Now
                        </a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- Features -->
<section class="py-16 bg-white dark:bg-gray-800">
    <div class="container mx-auto px-4">
        <h2 class="text-3xl font-bold text-center mb-12 dark:text-white">Why Choose Velo Rapido</h2>
        
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">
            <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-6 dark:bg-gray-700/50">
                <div class="text-purple-600 dark:text-purple-400 mb-4">
                    <i class="fas fa-check-circle text-3xl"></i>
                </div>
                <h3 class="text-xl font-semibold mb-3 dark:text-white">Quality Bikes</h3>
                <p class="text-gray-600 dark:text-gray-300">We maintain our bikes to the highest standards for your safety and enjoyment.</p>
            </div>
            
            <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-6 dark:bg-gray-700/50">
                <div class="text-purple-600 dark:text-purple-400 mb-4">
                    <i class="fas fa-map-marker-alt text-3xl"></i>
                </div>
                <h3 class="text-xl font-semibold mb-3 dark:text-white">Convenient Locations</h3>
                <p class="text-gray-600 dark:text-gray-300">Multiple pickup and drop-off points throughout the city for your convenience.</p>
            </div>
            
            <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-6 dark:bg-gray-700/50">
                <div class="text-purple-600 dark:text-purple-400 mb-4">
                    <i class="fas fa-dollar-sign text-3xl"></i>
                </div>
                <h3 class="text-xl font-semibold mb-3 dark:text-white">Competitive Pricing</h3>
                <p class="text-gray-600 dark:text-gray-300">Affordable rates with special discounts for longer rentals and loyal customers.</p>
            </div>
            
            <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-6 dark:bg-gray-700/50">
                <div class="text-purple-600 dark:text-purple-400 mb-4">
                    <i class="fas fa-headset text-3xl"></i>
                </div>
                <h3 class="text-xl font-semibold mb-3 dark:text-white">24/7 Support</h3>
                <p class="text-gray-600 dark:text-gray-300">Our team is always available to assist you with any questions or issues.</p>
            </div>
        </div>
    </div>
</section>

<!-- Testimonial Section -->
<section class="py-16 bg-purple-900 dark:bg-gray-900 text-white">
    <div class="container mx-auto px-4">
        <h2 class="text-3xl font-bold text-center mb-12">What Our Customers Say</h2>
        
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            <div class="bg-purple-800/70 dark:bg-gray-800 p-6 rounded-lg relative">
                <div class="text-purple-300 dark:text-purple-400 mb-4 text-4xl opacity-30 absolute top-3 left-3">
                    <i class="fas fa-quote-left"></i>
                </div>
                <div class="relative">
                    <p class="mb-4">The bikes were in excellent condition and the booking process was super easy. Will definitely use Velo Rapido again on my next trip!</p>
                    <div class="flex items-center">
                        <div class="text-purple-300 dark:text-purple-400 mr-2">
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                        </div>
                        <p class="font-semibold">- Sarah Johnson</p>
                    </div>
                </div>
            </div>
            
            <div class="bg-purple-800/70 dark:bg-gray-800 p-6 rounded-lg relative">
                <div class="text-purple-300 dark:text-purple-400 mb-4 text-4xl opacity-30 absolute top-3 left-3">
                    <i class="fas fa-quote-left"></i>
                </div>
                <div class="relative">
                    <p class="mb-4">Great customer service! When we had a flat tire, they responded quickly and replaced the bike within an hour. Very impressed!</p>
                    <div class="flex items-center">
                        <div class="text-purple-300 dark:text-purple-400 mr-2">
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star-half-alt"></i>
                        </div>
                        <p class="font-semibold">- Mark Davis</p>
                    </div>
                </div>
            </div>
            
            <div class="bg-purple-800/70 dark:bg-gray-800 p-6 rounded-lg relative">
                <div class="text-purple-300 dark:text-purple-400 mb-4 text-4xl opacity-30 absolute top-3 left-3">
                    <i class="fas fa-quote-left"></i>
                </div>
                <div class="relative">
                    <p class="mb-4">We rented e-bikes for a family trip and had an amazing experience. The bikes were perfect for exploring the city without getting exhausted.</p>
                    <div class="flex items-center">
                        <div class="text-purple-300 dark:text-purple-400 mr-2">
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                        </div>
                        <p class="font-semibold">- Lisa Wong</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- CTA Section -->
<section class="py-16 bg-white dark:bg-gray-800">
    <div class="container mx-auto px-4 text-center">
        <h2 class="text-3xl font-bold mb-4 dark:text-white">Ready for Your Next Adventure?</h2>
        <p class="text-xl text-gray-600 dark:text-gray-300 mb-8 max-w-2xl mx-auto">Experience the freedom of exploring on two wheels with our premium bike rental service.</p>
        <a href="pages/fleet.php" class="bg-gradient-to-r from-purple-500 to-purple-700 hover:from-purple-600 hover:to-purple-800 text-white font-bold py-3 px-8 rounded-lg text-lg inline-block">
            Book Your Bike Now
        </a>
    </div>
</section>

<?php include 'includes/footer.php'; ?>