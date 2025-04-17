</main>
    
    <!-- Footer -->
    <footer class="bg-purple-900 dark:bg-gray-800 text-white py-8">
        <div class="container mx-auto px-4">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <div>
                    <h3 class="text-lg font-semibold mb-4 text-purple-200">Velo Rapido</h3>
                    <p class="text-purple-100 dark:text-gray-300">
                        Experience premium bike rentals with Velo Rapido. Explore the city on two wheels with our high-quality bikes and excellent service.
                    </p>
                </div>
                
                <div>
                    <h3 class="text-lg font-semibold mb-4 text-purple-200">Quick Links</h3>
                    <ul class="space-y-2">
                        <li><a href="/velo-rapido/index.php" class="text-purple-200 dark:text-purple-300 hover:text-white">Home</a></li>
                        <li><a href="/velo-rapido/pages/fleet.php" class="text-purple-200 dark:text-purple-300 hover:text-white">Our Fleet</a></li>
                        <li><a href="/velo-rapido/auth/register.php" class="text-purple-200 dark:text-purple-300 hover:text-white">Register</a></li>
                        <li><a href="/velo-rapido/auth/login.php" class="text-purple-200 dark:text-purple-300 hover:text-white">Login</a></li>
                    </ul>
                </div>
                
                <div>
                    <h3 class="text-lg font-semibold mb-4 text-purple-200">Contact Us</h3>
                    <ul class="space-y-2 text-purple-100 dark:text-gray-300">
                        <li><i class="fas fa-map-marker-alt mr-2"></i> 123 Bike Street, Cycling City</li>
                        <li><i class="fas fa-phone mr-2"></i> (555) 123-4567</li>
                        <li><i class="fas fa-envelope mr-2"></i> info@velorapido.com</li>
                    </ul>
                    <div class="mt-4 flex space-x-4">
                        <a href="#" class="text-white hover:text-purple-300"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" class="text-white hover:text-purple-300"><i class="fab fa-twitter"></i></a>
                        <a href="#" class="text-white hover:text-purple-300"><i class="fab fa-instagram"></i></a>
                    </div>
                </div>
            </div>
            
            <div class="border-t border-purple-800 dark:border-gray-700 mt-8 pt-4 text-center text-purple-200 dark:text-gray-400">
                <p>&copy; <?php echo date('Y'); ?> Velo Rapido. All rights reserved.</p>
            </div>
        </div>
    </footer>
    
    <!-- JavaScript for mobile menu toggle -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Mobile menu toggle
            const menuButton = document.getElementById('menu-toggle');
            const mobileMenu = document.getElementById('mobile-menu');
            
            menuButton.addEventListener('click', function() {
                mobileMenu.classList.toggle('hidden');
            });
            
            // Alert dismiss functionality
            const dismissButtons = document.querySelectorAll('[role="alert"] svg');
            dismissButtons.forEach(button => {
                button.addEventListener('click', function() {
                    this.parentNode.parentNode.remove();
                });
            });
            
            // Sticky header scroll effect
            const nav = document.querySelector('nav');
            window.addEventListener('scroll', function() {
                if (window.scrollY > 10) {
                    nav.classList.add('scrolled');
                } else {
                    nav.classList.remove('scrolled');
                }
            });
        });
    </script>
</body>
</html>