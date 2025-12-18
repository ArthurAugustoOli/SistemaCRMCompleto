<?php
session_start();
include 'includes/functions.php';
$pageTitle = "StyleHub | Premium Clothing Brand";

// Get featured products
$featuredProducts = getFeaturedProducts(4);

// Debug
// echo "<pre>";
// print_r($featuredProducts);
// echo "</pre>";
?>

<!DOCTYPE html>
<html lang="en">
<?php include 'includes/head.php'; ?>
<body>
    <?php include 'includes/header.php'; ?>
    
    <!-- Hero Section -->
    <div class="position-relative">
        <div class="position-absolute top-0 start-0 end-0 bottom-0 bg-dark opacity-50"></div>
        <div class="position-relative d-flex align-items-center justify-content-start" style="background-image: url('public/imagem123.png');
    background-position: center;
    width: 100%;
    height: 70vh;">
            <div class="container px-4">
                <div class="col-lg-6 text-white">
                    <h1 class="display-4 fw-bold">SUA FRASE AQUI!</h1>
                    <p class="lead">frase de impacto aqui!frase de impacto aqui!frase de impacto aqui!frase de impacto aqui!frase de impacto aqui!frase de impacto aqui!</p>
                    <div class="d-flex flex-wrap gap-3 mt-4">
                        <a href="products.php" class="btn btn-light btn-lg">Compre Agora</a>
                        <a href="products.php?category=new" class="btn btn-outline-light btn-lg">Suas Coleções</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="container px-4 py-5">
        <!-- Category Section -->
        <?php include 'includes/category-section.php'; ?>
        
        <!-- Featured Products -->
        <section class="py-5">
            <div class="text-center mb-5">
                <h2 class="display-6 fw-bold">Produtos Recomendados</h2>
                <p class="text-muted col-md-8 mx-auto">Os produtos mais vendidos serão exibidos aqui!</p>
            </div>
            
            <div class="row g-4">
                <?php if (empty($featuredProducts)): ?>
                    <div class="col-12 text-center">
                        <p>No products available at the moment.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($featuredProducts as $product): ?>
                        <div class="col-md-6 col-lg-3">
                            <?php include 'includes/product-card.php'; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>
        
        <!-- CTA Section -->
        <div class="text-center py-5">
            <h2 class="display-6 fw-bold">Ready to upgrade your style?</h2>
            <p class="text-muted col-md-8 mx-auto">Discover our latest collections and find your perfect fit.</p>
            <a href="products.php" class="btn btn-dark btn-lg mt-3">
                <i class="bi bi-bag me-2"></i> Shop Now
            </a>
        </div>
    </div>
    
    <?php include 'includes/footer.php'; ?>
    <?php include 'includes/scripts.php'; ?>
</body>
</html>
