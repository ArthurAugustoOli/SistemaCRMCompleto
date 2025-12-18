<div class="card h-100 border-0 shadow-sm product-card">
    <a href="product-detail.php?id=<?php echo $product['id_produto']; ?>" class="text-decoration-none">
        <div class="position-relative">
            <img src="<?php echo isset($product['foto']) && $product['foto'] ? $product['foto'] : 'public/imgteste.png'; ?>" class="card-img-top" alt="<?php echo number_format(floatval($product['preco_venda']), 2); ?>" style="height: 250px; object-fit: cover;">
            <?php if (isset($product['total_stock']) && $product['total_stock'] < 5): ?>
                <span class="position-absolute top-0 end-0 bg-danger text-white px-2 py-1 m-2 rounded">Low Stock</span>
            <?php endif; ?>
        </div>
    </a>
    <div class="card-body">
        <a href="product-detail.php?id=<?php echo $product['id_produto']; ?>" class="text-decoration-none text-dark">
            <h5 class="card-title"><?php echo number_format(floatval($product['preco_venda']), 2); ?></h5>
        </a>
        <?php if (isset($product['min_price']) && isset($product['max_price']) && $product['min_price'] != $product['max_price']): ?>
            <p class="card-text fw-bold">$<?php echo number_format(floatval($product['min_price']), 2); ?> - $<?php echo number_format(floatval($product['max_price']), 2); ?></p>
        <?php else: ?>
            <p class="card-text fw-bold">$<?php echo number_format(floatval($product['preco_venda']), 2); ?></p>
        <?php endif; ?>
    </div>
    <div class="card-footer bg-white border-top-0">
        <a href="product-detail.php?id=<?php echo $product['id_produto']; ?>" class="btn btn-dark w-100">
            <i class="bi bi-eye me-2"></i> View Details
        </a>
    </div>
</div>
