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

// Check login status before including header
requireLogin();

// Check if reservation exists in session
if (!isset($_SESSION['reservation']) || !isset($_SESSION['reservation']['reservation_id'])) {
    $_SESSION['error_message'] = "No active reservation found. Please start the booking process again.";
    header("Location: fleet.php");
    exit();
}

$reservation = $_SESSION['reservation'];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $payment_method = sanitize($_POST['payment_method']);
    $reservation_id = intval($_SESSION['reservation']['reservation_id']);
    $amount = floatval($_SESSION['reservation']['total_amount']);
    
    // Basic validation
    if (!in_array($payment_method, ['cod', 'card', 'upi'])) {
        $_SESSION['error_message'] = "Invalid payment method selected";
    } else {
        try {
            // Begin transaction
            $pdo->beginTransaction();
            
            // Insert payment record
            $stmt = $pdo->prepare("
                INSERT INTO payments 
                (reservation_id, amount, payment_method, payment_status) 
                VALUES (?, ?, ?, 'completed')
            ");
            $stmt->execute([$reservation_id, $amount, $payment_method]);
            
            // Update reservation status
            $stmt = $pdo->prepare("UPDATE reservations SET status = 'confirmed' WHERE reservation_id = ?");
            $stmt->execute([$reservation_id]);
            
            // Commit transaction
            $pdo->commit();
            
            // Clear reservation from session
            unset($_SESSION['reservation']);
            
            // Set success message and redirect
            $_SESSION['flash_message'] = "Payment successful! Your booking has been confirmed.";
            header("Location: dashboard.php");
            exit();
            
        } catch (PDOException $e) {
            // Rollback transaction on error
            $pdo->rollBack();
            $_SESSION['error_message'] = "Payment error: " . $e->getMessage();
        }
    }
}

// Format dates
$start_datetime = new DateTime($reservation['start_time']);
$end_datetime = new DateTime($reservation['end_time']);

// Now that all redirects are complete, include the header which will start HTML output
include '../includes/header.php';
?>

<div class="bg-white p-8 rounded-lg shadow-md mb-8 max-w-2xl mx-auto">
    <h1 class="text-3xl font-bold mb-6">Complete Your Payment</h1>
    
    <div class="mb-8 bg-gradient-to-r from-purple-50 to-indigo-50 dark:from-purple-900/20 dark:to-indigo-900/20 rounded-lg border border-purple-100 dark:border-purple-800 overflow-hidden shadow-sm">
        <div class="border-b border-purple-100 dark:border-purple-800 bg-gradient-to-r from-purple-600 to-purple-800 dark:from-purple-700 dark:to-purple-900 px-6 py-4">
            <h2 class="text-xl font-semibold text-white">Booking Summary</h2>
        </div>
        
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <p class="text-gray-600 dark:text-gray-400">Bike:</p>
                    <p class="font-medium text-gray-900 dark:text-white"><?php echo htmlspecialchars($reservation['bike_name']); ?></p>
                </div>
                
                <div>
                    <p class="text-gray-600 dark:text-gray-400">Duration:</p>
                    <p class="font-medium text-gray-900 dark:text-white"><?php echo $reservation['total_hours']; ?> hour(s)</p>
                </div>
                
                <div>
                    <p class="text-gray-600 dark:text-gray-400">Start Time:</p>
                    <p class="font-medium text-gray-900 dark:text-white"><?php echo $start_datetime->format('M j, Y g:i A'); ?></p>
                </div>
                
                <div>
                    <p class="text-gray-600 dark:text-gray-400">End Time:</p>
                    <p class="font-medium text-gray-900 dark:text-white"><?php echo $end_datetime->format('M j, Y g:i A'); ?></p>
                </div>
            </div>
            
            <div class="mt-6 pt-6 border-t border-purple-100 dark:border-purple-800 bg-purple-50/50 dark:bg-purple-900/10 p-4 rounded-lg">
                <div class="flex justify-between items-center mb-2">
                    <span class="text-gray-600 dark:text-gray-400">Rate:</span>
                    <span class="text-gray-900 dark:text-white">₹<?php echo number_format($reservation['hourly_rate'], 2); ?>/hour</span>
                </div>
                <div class="flex justify-between items-center mb-2">
                    <span class="text-gray-600 dark:text-gray-400">Hours:</span>
                    <span class="text-gray-900 dark:text-white"><?php echo $reservation['total_hours']; ?></span>
                </div>
                <div class="flex justify-between items-center font-bold text-lg">
                    <span class="text-gray-900 dark:text-white">Total Amount:</span>
                    <span class="text-purple-600 dark:text-purple-400">₹<?php echo number_format($reservation['total_amount'], 2); ?></span>
                </div>
            </div>
        </div>
    </div>
    
    <form action="payment.php" method="POST" id="payment-form" class="space-y-6">
        <div>
            <div class="border-b border-purple-100 dark:border-purple-800 bg-gradient-to-r from-purple-600 to-purple-800 dark:from-purple-700 dark:to-purple-900 px-6 py-4 rounded-t-lg">
                <h2 class="text-xl font-semibold text-white">Payment Method</h2>
            </div>
            
            <div class="space-y-4 p-6 bg-gradient-to-r from-purple-50 to-indigo-50 dark:from-purple-900/20 dark:to-indigo-900/20 rounded-b-lg border border-purple-100 dark:border-purple-800 border-t-0">
                <div class="flex items-center p-4 border border-purple-100 dark:border-purple-800 rounded-lg bg-white dark:bg-gray-800">
                    <input id="upi" name="payment_method" type="radio" value="upi" class="h-5 w-5 text-purple-600" checked>
                    <label for="upi" class="ml-3 flex flex-col">
                        <span class="text-gray-900 dark:text-white font-medium">UPI Payment</span>
                        <span class="text-gray-500 dark:text-gray-400 text-sm">Pay using GooglePay, PhonePe, Paytm, or any UPI app</span>
                    </label>
                </div>
                
                <div class="flex items-center p-4 border border-purple-100 dark:border-purple-800 rounded-lg bg-white dark:bg-gray-800">
                    <input id="cod" name="payment_method" type="radio" value="cod" class="h-5 w-5 text-purple-600">
                    <label for="cod" class="ml-3 flex flex-col">
                        <span class="text-gray-900 dark:text-white font-medium">Cash on Delivery</span>
                        <span class="text-gray-500 dark:text-gray-400 text-sm">Pay when you pick up the bike</span>
                    </label>
                </div>
                
                <div class="flex items-center p-4 border border-purple-100 dark:border-purple-800 rounded-lg bg-white dark:bg-gray-800">
                    <input id="card" name="payment_method" type="radio" value="card" class="h-5 w-5 text-purple-600">
                    <label for="card" class="ml-3 flex flex-col">
                        <span class="text-gray-900 dark:text-white font-medium">Credit/Debit Card</span>
                        <span class="text-gray-500 dark:text-gray-400 text-sm">Secure online payment</span>
                    </label>
                </div>
            </div>
        </div>
        
        <!-- UPI details section -->
        <div id="upi-details" class="space-y-4 border-t border-purple-100 dark:border-purple-800 pt-6">
            <div>
                <label for="upi_id" class="block text-gray-700 dark:text-gray-300 text-sm font-medium mb-2">UPI ID</label>
                <input type="text" id="upi_id" placeholder="example@upi" class="form-input w-full px-4 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600 dark:text-white">
            </div>
            
            <div class="bg-yellow-50 dark:bg-yellow-900/20 p-4 rounded-lg border border-yellow-100 dark:border-yellow-900/30">
                <p class="text-sm text-yellow-800 dark:text-yellow-300">
                    <i class="fas fa-info-circle mr-2"></i>
                    For demo purposes, no actual payment will be processed. In a real application, you would be redirected to your UPI app to complete the payment.
                </p>
            </div>
        </div>
        
        <!-- Card details section (shown/hidden based on selection) -->
        <div id="card-details" class="hidden space-y-4 border-t border-purple-100 dark:border-purple-800 pt-6">
            <div>
                <label for="card_number" class="block text-gray-700 dark:text-gray-300 text-sm font-medium mb-2">Card Number</label>
                <input type="text" id="card_number" placeholder="1234 5678 9012 3456" class="form-input w-full px-4 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600 dark:text-white">
            </div>
            
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label for="expiry" class="block text-gray-700 dark:text-gray-300 text-sm font-medium mb-2">Expiry Date</label>
                    <input type="text" id="expiry" placeholder="MM/YY" class="form-input w-full px-4 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                </div>
                
                <div>
                    <label for="cvv" class="block text-gray-700 dark:text-gray-300 text-sm font-medium mb-2">CVV</label>
                    <input type="text" id="cvv" placeholder="123" class="form-input w-full px-4 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                </div>
            </div>
            
            <div>
                <label for="card_name" class="block text-gray-700 dark:text-gray-300 text-sm font-medium mb-2">Name on Card</label>
                <input type="text" id="card_name" class="form-input w-full px-4 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600 dark:text-white">
            </div>
        </div>
        
        <div class="pt-4">
            <button type="submit" class="btn-primary w-full bg-gradient-to-r from-purple-500 to-purple-700 hover:from-purple-600 hover:to-purple-800 text-white font-bold py-3 px-6 rounded-lg text-lg">
                <i class="fas fa-lock mr-2"></i>Complete Payment
            </button>
        </div>
    </form>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const upiRadio = document.getElementById('upi');
        const cardRadio = document.getElementById('card');
        const codRadio = document.getElementById('cod');
        const cardDetails = document.getElementById('card-details');
        const upiDetails = document.getElementById('upi-details');
        
        // Toggle payment details visibility
        function togglePaymentDetails() {
            if (cardRadio.checked) {
                cardDetails.classList.remove('hidden');
                upiDetails.classList.add('hidden');
            } else if (upiRadio.checked) {
                upiDetails.classList.remove('hidden');
                cardDetails.classList.add('hidden');
            } else {
                cardDetails.classList.add('hidden');
                upiDetails.classList.add('hidden');
            }
        }
        
        // Initial state
        togglePaymentDetails();
        
        // Event listeners for radio buttons
        upiRadio.addEventListener('change', togglePaymentDetails);
        cardRadio.addEventListener('change', togglePaymentDetails);
        codRadio.addEventListener('change', togglePaymentDetails);
        
        // Form validation (only if card payment or UPI is selected)
        document.getElementById('payment-form').addEventListener('submit', function(event) {
            if (cardRadio.checked) {
                const cardNumber = document.getElementById('card_number').value.trim();
                const expiry = document.getElementById('expiry').value.trim();
                const cvv = document.getElementById('cvv').value.trim();
                const cardName = document.getElementById('card_name').value.trim();
                
                if (!cardNumber || !expiry || !cvv || !cardName) {
                    alert('Please fill out all card details');
                    event.preventDefault();
                    return false;
                }
            } else if (upiRadio.checked) {
                const upiId = document.getElementById('upi_id').value.trim();
                
                if (!upiId) {
                    alert('Please enter your UPI ID');
                    event.preventDefault();
                    return false;
                }
                
                if (!upiId.includes('@')) {
                    alert('Please enter a valid UPI ID (e.g. name@upi)');
                    event.preventDefault();
                    return false;
                }
            }
            
            return true;
        });
    });
</script>

<?php include '../includes/footer.php'; ?>