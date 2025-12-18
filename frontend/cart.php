<?php
session_start();
include 'includes/functions.php';

// Handle cart actions
if (isset($_POST['action'])) {
    $action = $_POST['action'];
    $productId = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
    
    if ($action === 'remove' && $productId) {
        removeFromCart($productId);
    } else if ($action === 'update' && $productId) {
        $quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 1;
        updateCartQuantity($productId, $quantity);
    } else if ($action === 'clear') {
        clearCart();
    }
    
    // Redirect to prevent form resubmission
    header("Location: cart.php");
    exit;
}

$cartItems = getCartItems();
$subtotal = calculateSubtotal();
$pageTitle = "Shopping Cart | StyleHub";
?>

<!DOCTYPE html>
<html lang="en">
<?php include 'includes/head.php'; ?>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container px-4 py-5">
        <h1 class="fw-bold mb-4">Shopping Cart</h1>
        
        <?php if (empty($cartItems)): ?>
            <div class="text-center py-5">
                <i class="bi bi-bag display-1 text-muted mb-3"></i>
                <h2 class="mb-3">Your cart is empty</h2>
                <p class="text-muted mb-4">Looks like you haven't added anything to your cart yet.</p>
                <a href="products.php" class="btn btn-dark">Continue Shopping</a>
            </div>
        <?php else: ?>
            <div class="row g-4">
                <!-- Cart Items -->
                <div class="col-lg-8">
                    <div class="card mb-4">
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-borderless align-middle mb-0">
                                    <thead class="bg-light">
                                        <tr>
                                            <th scope="col" class="py-3 ps-4">Product</th>
                                            <th scope="col" class="py-3 text-center">Price</th>
                                            <th scope="col" class="py-3 text-center">Quantity</th>
                                            <th scope="col" class="py-3 text-center">Total</th>
                                            <th scope="col" class="py-3 text-center">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($cartItems as $item): ?>
                                            <tr>
                                                <td class="py-3 ps-4">
                                                    <div class="d-flex align-items-center">
                                                        <img src="<?php echo $item['image']; ?>" alt="<?php echo $item['name']; ?>" class="img-fluid rounded" style="width: 80px; height: 80px; object-fit: cover;">
                                                        <div class="ms-3">
                                                            <h6 class="fw-bold mb-1"><?php echo $item['name']; ?></h6>
                                                            <p class="text-muted small mb-0">
                                                                Size: <?php echo $item['selectedSize']; ?>, 
                                                                Color: <?php echo $item['selectedColor']; ?>
                                                            </p>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="py-3 text-center">$<?php echo number_format($item['price'], 2); ?></td>
                                                <td class="py-3 text-center">
                                                    <form method="post" class="d-inline">
                                                        <input type="hidden" name="action" value="update">
                                                        <input type="hidden" name="product_id" value="<?php echo $item['id']; ?>">
                                                        <div class="input-group input-group-sm" style="width: 100px; margin: 0 auto;">
                                                            <button type="button" class="btn btn-outline-dark quantity-btn" data-action="decrease" data-product-id="<?php echo $item['id']; ?>">
                                                                <i class="bi bi-dash"></i>
                                                            </button>
                                                            <input type="number" class="form-control text-center quantity-input" name="quantity" value="<?php echo $item['quantity']; ?>" min="1" max="10" data-product-id="<?php echo $item['id']; ?>">
                                                            <button type="button" class="btn btn-outline-dark quantity-btn" data-action="increase" data-product-id="<?php echo $item['id']; ?>">
                                                                <i class="bi bi-plus"></i>
                                                            </button>
                                                        </div>
                                                    </form>
                                                </td>
                                                <td class="py-3 text-center fw-bold">
                                                    $<?php echo number_format($item['price'] * $item['quantity'], 2); ?>
                                                </td>
                                                <td class="py-3 text-center">
                                                    <form method="post" class="d-inline">
                                                        <input type="hidden" name="action" value="remove">
                                                        <input type="hidden" name="product_id" value="<?php echo $item['id']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-between">
                        <form method="post">
                            <input type="hidden" name="action" value="clear">
                            <button type="submit" class="btn btn-outline-dark">Clear Cart</button>
                        </form>
                        <a href="products.php" class="btn btn-outline-dark">Continue Shopping</a>
                    </div>
                </div>
                
                <!-- Order Summary -->
                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header bg-dark text-white">
                            <h5 class="mb-0">Order Summary</h5>
                        </div>
                        <div class="card-body">
                            <div class="d-flex justify-content-between mb-3">
                                <span>Subtotal</span>
                                <span>$<?php echo number_format($subtotal, 2); ?></span>
                            </div>
                            <div class="d-flex justify-content-between mb-3">
                                <span>Shipping</span>
                                <span>Calculated at checkout</span>
                            </div>
                            <div class="d-flex justify-content-between mb-3">
                                <span>Tax</span>
                                <span>Calculated at checkout</span>
                            </div>
                            <hr>
                            <div class="d-flex justify-content-between mb-4">
                                <span class="fw-bold">Total</span>
                                <span class="fw-bold">$<?php echo number_format($subtotal, 2); ?></span>
                            </div>
                            <a href="checkout.php" class="btn btn-dark w-100">Proceed to Checkout</a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <?php include 'includes/footer.php'; ?>
    <?php include 'includes/scripts.php'; ?>
    
    <script>
        // Quantity buttons functionality
        document.querySelectorAll('.quantity-btn').forEach(button => {
            button.addEventListener('click', function() {
                const productId = this.dataset.productId;
                const input = document.querySelector(`.quantity-input[data-product-id="${productId}"]`);
                const currentValue = parseInt(input.value);
                const form = input.closest('form');
                
                if (this.dataset.action === 'increase') {
                    if (currentValue < 10) {
                        input.value = currentValue + 1;
                        form.submit();
                    }
                } else if (this.dataset.action === 'decrease') {
                    if (currentValue > 1) {
                        input.value = currentValue - 1;
                        form.submit();
                    }
                }
            });
        });
    </script>
</body>
</html>
