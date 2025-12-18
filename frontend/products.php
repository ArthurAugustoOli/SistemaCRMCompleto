<?php
session_start();
include 'includes/functions.php';

// Handle category filtering
$category = isset($_GET['category']) ? $_GET['category'] : null;

if ($category) {
    $products = getProductsByCategory($category);
} else {
    $products = getAllProducts();
}

// Debug
// echo "<pre>";
// print_r($products);
// echo "</pre>";

$pageTitle = "All Products | StyleHub";
?>

<!DOCTYPE html>
<html lang="en">
<?php include 'includes/head.php'; ?>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container px-4 py-5">
        <div class="text-center mb-5">
            <h1 class="display-5 fw-bold">
                <?php echo $category ? ucfirst($category) . "'s Collection" : "All Products"; ?>
            </h1>
            <p class="text-muted col-md-8 mx-auto">Browse our complete collection of premium clothing</p>
        </div>
        
        <!-- Filter options -->
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="d-flex flex-wrap gap-2 justify-content-center">
                    <a href="products.php" class="btn <?php echo !$category ? 'btn-dark' : 'btn-outline-dark'; ?>">All</a>
                    <a href="products.php?category=men" class="btn <?php echo $category === 'men' ? 'btn-dark' : 'btn-outline-dark'; ?>">Men</a>
                    <a href="products.php?category=women" class="btn <?php echo $category === 'women' ? 'btn-dark' : 'btn-outline-dark'; ?>">Women</a>
                    <a href="products.php?category=accessories" class="btn <?php echo $category === 'accessories' ? 'btn-dark' : 'btn-outline-dark'; ?>">Accessories</a>
                </div>
            </div>
        </div>
        
        <div class="row g-4">
            <?php if (empty($products)): ?>
                <div class="col-12 text-center py-5">
                    <h3>No products found</h3>
                    <p>Try a different category or check back later for new arrivals.</p>
                </div>
            <?php else: ?>
                <?php foreach ($products as $product): ?>
                    <div class="col-md-6 col-lg-3">
                        <?php include 'includes/product-card.php'; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <?php include 'includes/footer.php'; ?>
    <?php include 'includes/scripts.php'; ?>
</body>
</html>
