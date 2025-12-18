<?php
session_start();
include 'includes/functions.php';

// Check if cart is empty
$cartItems = getCartItems();
if (empty($cartItems) && !isset($_SESSION['order_complete'])) {
    header("Location: cart.php");
    exit;
}

// Handle form submission
$orderComplete = false;
if (isset($_POST['place_order'])) {
    // In a real application, you would process payment and save order to database
    // For this demo, we'll just simulate order completion
    $_SESSION['order_complete'] = true;
    clearCart();
    $orderComplete = true;
}

$subtotal = calculateSubtotal();
$pageTitle = "Checkout | StyleHub";
?>

<!DOCTYPE html>
<html lang="en">
<?php include 'includes/head.php'; ?>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container px-4 py-5">
        <?php if (isset($_SESSION['order_complete']) || $orderComplete): ?>
            <!-- Order Confirmation -->
            <div class="row justify-content-center">
                <div class="col-md-6">
                    <div class="card text-center">
                        <div class="card-body py-5">
                            <div class="mb-4">
                                <span class="bg-success text-white rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 80px; height: 80px;">
                                    <i class="bi bi-check-lg" style="font-size: 40px;"></i>
                                </span>
                            </div>
                            <h2 class="card-title mb-3">Order Confirmed!</h2>
                            <p class="card-text mb-4">Thank you for your purchase. Your order has been confirmed and will be shipped soon.</p>
                            <div class="bg-light p-3 rounded mb-4">
                                <p class="fw-bold mb-1">Order #<?php echo rand(10000, 99999); ?></p>
                                <p class="text-muted small mb-0">A confirmation email has been sent to your email address.</p>
                            </div>
                            <a href="index.php" class="btn btn-dark">Return to Home</a>
                        </div>
                    </div>
                </div>
            </div>
            <?php 
            // Clear the order complete flag if we're showing the confirmation
            if (isset($_SESSION['order_complete'])) {
                unset($_SESSION['order_complete']);
            }
            ?>
        <?php else: ?>
            <!-- Checkout Form -->
            <div class="mb-4">
                <a href="cart.php" class="text-decoration-none">
                    <i class="bi bi-arrow-left me-2"></i> Back to Cart
                </a>
            </div>
            
            <div class="row g-5">
                <div class="col-md-7 col-lg-8">
                    <h1 class="mb-4">Checkout</h1>
                    <form method="post" class="needs-validation" novalidate>
                        <!-- Contact Information -->
                        <div class="card mb-4">
                            <div class="card-header bg-light">
                                <h5 class="mb-0">Contact Information</h5>
                            </div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label for="firstName" class="form-label">First Name</label>
                                        <input type="text" class="form-control" id="firstName" name="firstName" required>
                                        <div class="invalid-feedback">
                                            Please enter your first name.
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="lastName" class="form-label">Last Name</label>
                                        <input type="text" class="form-control" id="lastName" name="lastName" required>
                                        <div class="invalid-feedback">
                                            Please enter your last name.
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <label for="email" class="form-label">Email</label>
                                        <input type="email" class="form-control" id="email" name="email" required>
                                        <div class="invalid-feedback">
                                            Please enter a valid email address.
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <label for="phone" class="form-label">Phone</label>
                                        <input type="tel" class="form-control" id="phone" name="phone" required>
                                        <div class="invalid-feedback">
                                            Please enter your phone number.
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Shipping Address -->
                        <div class="card mb-4">
                            <div class="card-header bg-light">
                                <h5 class="mb-0">Shipping Address</h5>
                            </div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-12">
                                        <label for="address" class="form-label">Address</label>
                                        <input type="text" class="form-control" id="address" name="address" required>
                                        <div class="invalid-feedback">
                                            Please enter your shipping address.
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <label for="apartment" class="form-label">Apartment, suite, etc. (optional)</label>
                                        <input type="text" class="form-control" id="apartment" name="apartment">
                                    </div>
                                    <div class="col-md-5">
                                        <label for="city" class="form-label">City</label>
                                        <input type="text" class="form-control" id="city" name="city" required>
                                        <div class="invalid-feedback">
                                            Please enter your city.
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <label for="state" class="form-label">State</label>
                                        <select class="form-select" id="state" name="state" required>
                                            <option value="">Choose...</option>
                                            <option value="CA">California</option>
                                            <option value="NY">New York</option>
                                            <option value="TX">Texas</option>
                                            <option value="FL">Florida</option>
                                        </select>
                                        <div class="invalid-feedback">
                                            Please select your state.
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <label for="zip" class="form-label">ZIP Code</label>
                                        <input type="text" class="form-control" id="zip" name="zip" required>
                                        <div class="invalid-feedback">
                                            Please enter your ZIP code.
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Payment Method -->
                        <div class="card mb-4">
                            <div class="card-header bg-light">
                                <h5 class="mb-0">Payment Method</h5>
                            </div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-12">
                                        <div class="form-check">
                                            <input id="credit" name="paymentMethod" type="radio" class="form-check-input" value="credit" checked required>
                                            <label class="form-check-label" for="credit">Credit card</label>
                                        </div>
                                        <div class="form-check">
                                            <input id="paypal" name="paymentMethod" type="radio" class="form-check-input" value="paypal" required>
                                            <label class="form-check-label" for="paypal">PayPal</label>
                                        </div>
                                        <div class="form-check">
                                            <input id="cod" name="paymentMethod" type="radio" class="form-check-input" value="cod" required>
                                            <label class="form-check-label" for="cod">Cash on Delivery</label>
                                        </div>
                                    </div>
                                    
                                    <div class="col-12 mt-4" id="credit-card-details">
                                        <div class="row g-3">
                                            <div class="col-12">
                                                <label for="cardName" class="form-label">Name on card</label>
                                                <input type="text" class="form-control" id="cardName" name="cardName">
                                                <small class="text-muted">Full name as displayed on card</small>
                                            </div>
                                            <div class="col-12">
                                                <label for="cardNumber" class="form-label">Card number</label>
                                                <div class="input-group">
                                                    <input type="text" class="form-control" id="cardNumber" name="cardNumber" placeholder="1234 5678 9012 3456">
                                                    <span class="input-group-text"><i class="bi bi-credit-card"></i></span>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <label for="expiry" class="form-label">Expiration</label>
                                                <input type="text" class="form-control" id="expiry" name="expiry" placeholder="MM/YY">
                                            </div>
                                            <div class="col-md-6">
                                                <label for="cvv" class="form-label">CVV</label>
                                                <input type="text" class="form-control" id="cvv" name="cvv" placeholder="123">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Shipping Method -->
                        <div class="card mb-4">
                            <div class="card-header bg-light">
                                <h5 class="mb-0">Shipping Method</h5>
                            </div>
                            <div class="card-body">
                                <div class="form-check mb-3">
                                    <input id="standard" name="shippingMethod" type="radio" class="form-check-input" value="standard" checked required>
                                    <label class="form-check-label" for="standard">
                                        <div class="d-flex justify-content-between">
                                            <span>Standard Shipping</span>
                                            <span>Free</span>
                                        </div>
                                        <small class="text-muted">Delivery in 5-7 business days</small>
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input id="express" name="shippingMethod" type="radio" class="form-check-input" value="express" required>
                                    <label class="form-check-label" for="express">
                                        <div class="d-flex justify-content-between">
                                            <span>Express Shipping</span>
                                            <span>$9.99</span>
                                        </div>
                                        <small class="text-muted">Delivery in 2-3 business days</small>
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <button class="btn btn-dark btn-lg w-100 mt-4" type="submit" name="place_order">Place Order</button>
                    </form>
                </div>
                
                <!-- Order Summary -->
                <div class="col-md-5 col-lg-4">
                    <div class="card">
                        <div class="card-header bg-dark text-white">
                            <h5 class="mb-0">Order Summary</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <?php foreach ($cartItems as $item): ?>
                                    <div class="d-flex justify-content-between mb-2">
                                        <div>
                                            <span class="text-muted"><?php echo $item['quantity']; ?> × </span>
                                            <span><?php echo $item['name']; ?></span>
                                        </div>
                                        <span>$<?php echo number_format($item['price'] * $item['quantity'], 2); ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <hr>
                            <div class="d-flex justify-content-between mb-2">
                                <span>Subtotal</span>
                                <span>$<?php echo number_format($subtotal, 2); ?></span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span>Shipping</span>
                                <span>Free</span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span>Tax</span>
                                <span>Calculated at checkout</span>
                            </div>
                            <hr>
                            <div class="d-flex justify-content-between mb-0">
                                <span class="fw-bold">Total</span>
                                <span class="fw-bold">$<?php echo number_format($subtotal, 2); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <?php include 'includes/footer.php'; ?>
    <?php include 'includes/scripts.php'; ?>
    
    <script>
        // Form validation
        (function () {
            'use strict'
            
            // Fetch all the forms we want to apply custom Bootstrap validation styles to
            var forms = document.querySelectorAll('.needs-validation')
            
            // Loop over them and prevent submission
            Array.prototype.slice.call(forms)
                .forEach(function (form) {
                    form.addEventListener('submit', function (event) {
                        if (!form.checkValidity()) {
                            event.preventDefault()
                            event.stopPropagation()
                        }
                        
                        form.classList.add('was-validated')
                    }, false)
                })
        })()
        
        // Toggle payment method details
        document.querySelectorAll('input[name="paymentMethod"]').forEach(input => {
            input.addEventListener('change', function() {
                const creditCardDetails = document.getElementById('credit-card-details');
                if (this.value === 'credit') {
                    creditCardDetails.style.display = 'block';
                } else {
                    creditCardDetails.style.display = 'none';
                }
            });
        });
    </script>
</body>
</html>
