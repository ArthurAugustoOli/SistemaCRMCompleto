<header class="sticky-top bg-white border-bottom">
    <nav class="navbar navbar-expand-lg navbar-light">
        <div class="container px-4">
            <a class="navbar-brand fw-bold" href="index.php">StyleHub</a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="products.php">All Products</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="products.php?category=men">Men</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="products.php?category=women">Women</a>
                    </li>
                </ul>
                
                <div class="d-flex align-items-center">
                    <a href="#" class="btn btn-outline-dark me-2" data-bs-toggle="modal" data-bs-target="#searchModal">
                        <i class="bi bi-search"></i>
                    </a>
                    <a href="#" class="btn btn-outline-dark me-2">
                        <i class="bi bi-person"></i>
                    </a>
                    <a href="cart.php" class="btn btn-outline-dark position-relative">
                        <i class="bi bi-cart"></i>
                        <?php if (getCartItemCount() > 0): ?>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-dark">
                                <?php echo getCartItemCount(); ?>
                            </span>
                        <?php endif; ?>
                    </a>
                </div>
            </div>
        </div>
    </nav>
    
    <!-- Search Modal -->
    <div class="modal fade" id="searchModal" tabindex="-1" aria-labelledby="searchModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="searchModalLabel">Search Products</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form action="products.php" method="get">
                        <div class="input-group">
                            <input type="text" class="form-control" name="search" placeholder="Search for products...">
                            <button class="btn btn-dark" type="submit">Search</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</header>
