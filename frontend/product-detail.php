<?php
session_start();
include 'includes/functions.php';

// Get product ID from URL
$productId = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Find the product
$product = null;
foreach ($products as $p) {
    if ($p['id'] === $productId) {
        $product = $p;
        break;
    }
}

// Handle add to cart
if (isset($_POST['add_to_cart'])) {
    $quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 1;
    $size = isset($_POST['size']) ? $_POST['size'] : '';
    $color = isset($_POST['color']) ? $_POST['color'] : '';
    
    if ($product && $size && $color) {
        addToCart($product, $quantity, $size, $color);
        header("Location: cart.php");
        exit;
    }
}

$pageTitle = $product ? $product['name'] . " | StyleHub" : "Product Not Found | StyleHub";
?>

<!DOCTYPE html>
<html lang="en">
<?php include 'includes/head.php'; ?>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container px-4 py-5">
        <!-- Breadcrumb -->
        <nav aria-label="breadcrumb" class="mb-4">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none">Home</a></li>
                <li class="breadcrumb-item"><a href="products.php" class="text-decoration-none">Products</a></li>
                <?php if ($product): ?>
                    <li class="breadcrumb-item active" aria-current="page"><?php echo $product['name']; ?></li>
                <?php else: ?>
                    <li class="breadcrumb-item active" aria-current="page">Product Not Found</li>
                <?php endif; ?>
            </ol>
        </nav>
        
        <?php if ($product): ?>
            <div class="row g-5">
                <!-- Product Images -->
                <div class="col-md-6">
                    <div class="position-relative mb-3">
                        <img src="<?php echo $product['image']; ?>" alt="<?php echo $product['name']; ?>" class="img-fluid rounded border">
                        <?php if ($product['isNew']): ?>
                            <span class="position-absolute top-0 end-0 bg-dark text-white px-3 py-1 m-2 rounded">New</span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="row g-2">
                        <?php foreach ($product['images'] as $index => $image): ?>
                            <div class="col-3">
                                <img src="<?php echo $image; ?>" alt="<?php echo $product['name']; ?>" class="img-fluid rounded border cursor-pointer">
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- Product Details -->
                <div class="col-md-6">
                    <h1 class="fw-bold"><?php echo $product['name']; ?></h1>
                    <h3 class="fw-bold mb-3">$<?php echo number_format($product['price'], 2); ?></h3>
                    
                    <p class="text-muted mb-4"><?php echo $product['description']; ?></p>
                    
                    <form method="post" action="">
                        <!-- Size Selection -->
                        <div class="mb-4">
                            <h5 class="fw-bold mb-2">Size</h5>
                            <div class="d-flex flex-wrap gap-2">
                                <?php foreach ($product['sizes'] as $size): ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="size" id="size-<?php echo $size; ?>" value="<?php echo $size; ?>" required>
                                        <label class="form-check-label btn btn-outline-dark" for="size-<?php echo $size; ?>">
                                            <?php echo $size; ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <!-- Color Selection -->
                        <div class="mb-4">
                            <h5 class="fw-bold mb-2">Color</h5>
                            <div class="d-flex flex-wrap gap-2">
                                <?php foreach ($product['colors'] as $color): ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="color" id="color-<?php echo $color; ?>" value="<?php echo $color; ?>" required>
                                        <label class="form-check-label btn btn-outline-dark" for="color-<?php echo $color; ?>">
                                            <?php echo $color; ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <!-- Quantity -->
                        <div class="mb-4">
                            <h5 class="fw-bold mb-2">Quantity</h5>
                            <div class="input-group" style="width: 150px;">
                                <button type="button" class="btn btn-outline-dark quantity-btn" data-action="decrease">
                                    <i class="bi bi-dash"></i>
                                </button>
                                <input type="number" class="form-control text-center" name="quantity" value="1" min="1" max="10" id="quantity-input">
                                <button type="button" class="btn btn-outline-dark quantity-btn" data-action="increase">
                                    <i class="bi bi-plus"></i>
                                </button>
                            </div>
                        </div>
                        
                        <!-- Add to Cart Button -->
                        <button type="submit" name="add_to_cart" class="btn btn-dark btn-lg w-100 mb-4">
                            <i class="bi bi-cart me-2"></i> Add to Cart
                        </button>
                    </form>
                    
                    <!-- Product Details Tabs -->
                    <ul class="nav nav-tabs" id="productTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="details-tab" data-bs-toggle="tab" data-bs-target="#details" type="button" role="tab" aria-controls="details" aria-selected="true">Details</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="shipping-tab" data-bs-toggle="tab" data-bs-target="#shipping" type="button" role="tab" aria-controls="shipping" aria-selected="false">Shipping & Returns</button>
                        </li>
                    </ul>
                    
                    <div class="tab-content p-3 border border-top-0 rounded-bottom" id="productTabsContent">
                        <div class="tab-pane fade show active" id="details" role="tabpanel" aria-labelledby="details-tab">
                            <div class="row g-3">
                                <div class="col-6">
                                    <h6 class="fw-bold">Material</h6>
                                    <p class="text-muted"><?php echo $product['details']['material']; ?></p>
                                </div>
                                <div class="col-6">
                                    <h6 class="fw-bold">Fit</h6>
                                    <p class="text-muted"><?php echo $product['details']['fit']; ?></p>
                                </div>
                                <div class="col-6">
                                    <h6 class="fw-bold">Care</h6>
                                    <p class="text-muted"><?php echo $product['details']['care']; ?></p>
                                </div>
                                <div class="col-6">
                                    <h6 class="fw-bold">Origin</h6>
                                    <p class="text-muted"><?php echo $product['details']['origin']; ?></p>
                                </div>
                            </div>
                        </div>
                        <div class="tab-pane fade" id="shipping" role="tabpanel" aria-labelledby="shipping-tab">
                            <div class="mb-3">
                                <h6 class="fw-bold">Shipping</h6>
                                <p class="text-muted">Free standard shipping on all orders over $100. Delivery within 3-5 business days.</p>
                            </div>
                            <div>
                                <h6 class="fw-bold">Returns</h6>
                                <p class="text-muted">We accept returns within 30 days of delivery. Items must be unworn, unwashed, and with the original tags attached.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="text-center py-5">
                <h2 class="mb-4">Product Not Found</h2>
                <p>The product you are looking for does not exist.</p>
                <a href="products.php" class="btn btn-dark mt-3">Back to Products</a>
            </div>
        <?php endif; ?>
    </div>
    
    <?php include 'includes/footer.php'; ?>
    <?php include 'includes/scripts.php'; ?>
    
    <script>
        // Quantity buttons functionality
        document.querySelectorAll('.quantity-btn').forEach(button => {
            button.addEventListener('click', function() {
                const input = document.getElementById('quantity-input');
                const currentValue = parseInt(input.value);
                
                if (this.dataset.action === 'increase') {
                    if (currentValue < 10) {
                        input.value = currentValue + 1;
                    }
                } else if (this.dataset.action === 'decrease') {
                    if (currentValue > 1) {
                        input.value = currentValue - 1;
                    }
                }
            });
        });
    </script>
</body>
</html>
