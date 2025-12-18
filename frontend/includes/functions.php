<?php
// Include database connection
require_once 'db_connection.php';

// Get all products
function getAllProducts() {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT p.*, 
                   COALESCE(MIN(pv.preco_venda), p.preco_venda) as min_price,
                   COALESCE(MAX(pv.preco_venda), p.preco_venda) as max_price,
                   SUM(COALESCE(pv.estoque_atual, 0)) + COALESCE(p.estoque_atual, 0) as total_stock
            FROM produtos p
            LEFT JOIN produto_variacoes pv ON p.id_produto = pv.id_produto
            GROUP BY p.id_produto
            HAVING total_stock > 0
        ");
        $stmt->execute();
        
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Database error in getAllProducts: " . $e->getMessage());
        return [];
    }
}

// Get products by category
function getProductsByCategory($category) {
    global $pdo;
    
    try {
        // In your database, you might need to adjust this query based on how categories are stored
        // This is just a placeholder assuming there's a category field or relation
        $stmt = $pdo->prepare("
            SELECT p.*, 
                   COALESCE(MIN(pv.preco_venda), p.preco_venda) as min_price,
                   COALESCE(MAX(pv.preco_venda), p.preco_venda) as max_price,
                   SUM(COALESCE(pv.estoque_atual, 0)) + COALESCE(p.estoque_atual, 0) as total_stock
            FROM produtos p
            LEFT JOIN produto_variacoes pv ON p.id_produto = pv.id_produto
            WHERE p.descricao LIKE :category
            GROUP BY p.id_produto
            HAVING total_stock > 0
        ");
        $stmt->execute(['category' => '%' . $category . '%']);
        
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Database error in getProductsByCategory: " . $e->getMessage());
        return [];
    }
}

// Get product by ID
function getProductById($productId) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM produtos 
            WHERE id_produto = :id
        ");
        $stmt->execute(['id' => $productId]);
        
        $product = $stmt->fetch();
        
        if ($product) {
            // Get product variations
            $stmt = $pdo->prepare("
                SELECT * FROM produto_variacoes 
                WHERE id_produto = :id AND estoque_atual > 0
            ");
            $stmt->execute(['id' => $productId]);
            $variations = $stmt->fetchAll();
            
            $product['variations'] = $variations;
            
            // Get available colors and sizes from variations
            $colors = [];
            $sizes = [];
            foreach ($variations as $variation) {
                if (!empty($variation['cor']) && !in_array($variation['cor'], $colors)) {
                    $colors[] = $variation['cor'];
                }
                if (!empty($variation['tamanho']) && !in_array($variation['tamanho'], $sizes)) {
                    $sizes[] = $variation['tamanho'];
                }
            }
            
            $product['colors'] = $colors;
            $product['sizes'] = $sizes;
            
            // Set product images (using placeholder if no image is available)
            $product['images'] = [
                'assets/images/placeholder.png',
                'assets/images/placeholder.png',
                'assets/images/placeholder.png'
            ];
            
            // Add product details
            $product['details'] = [
                'material' => 'Informação não disponível',
                'fit' => 'Informação não disponível',
                'care' => 'Informação não disponível',
                'origin' => 'Informação não disponível'
            ];
        }
        
        return $product;
    } catch (PDOException $e) {
        error_log("Database error in getProductById: " . $e->getMessage());
        return null;
    }
}

// Get variation by ID
function getVariationById($variationId) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT pv.*, p.nome as produto_nome, p.descricao, p.foto
            FROM produto_variacoes pv
            JOIN produtos p ON pv.id_produto = p.id_produto
            WHERE pv.id_variacao = :id
        ");
        $stmt->execute(['id' => $variationId]);
        
        return $stmt->fetch();
    } catch (PDOException $e) {
        error_log("Database error in getVariationById: " . $e->getMessage());
        return null;
    }
}

// Get featured products
function getFeaturedProducts($limit = 4) {
    global $pdo;
    
    try {
        // In a real system, you might have a "featured" flag or use sales data
        // Here we're just getting products with stock
        $stmt = $pdo->prepare("
            SELECT p.*, 
                   COALESCE(MIN(pv.preco_venda), p.preco_venda) as min_price,
                   COALESCE(MAX(pv.preco_venda), p.preco_venda) as max_price,
                   SUM(COALESCE(pv.estoque_atual, 0)) + COALESCE(p.estoque_atual, 0) as total_stock
            FROM produtos p
            LEFT JOIN produto_variacoes pv ON p.id_produto = pv.id_produto
            GROUP BY p.id_produto
            HAVING total_stock > 0
            LIMIT :limit
        ");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Database error in getFeaturedProducts: " . $e->getMessage());
        return [];
    }
}

// Cart functions
function addToCart($productId, $variationId = null, $quantity = 1) {
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }
    
    $key = $variationId ? "v_$variationId" : "p_$productId";
    
    // Check if product already exists in cart
    if (isset($_SESSION['cart'][$key])) {
        $_SESSION['cart'][$key]['quantity'] += $quantity;
    } else {
        if ($variationId) {
            $variation = getVariationById($variationId);
            if ($variation) {
                $_SESSION['cart'][$key] = [
                    'id' => $variationId,
                    'type' => 'variation',
                    'product_id' => $variation['id_produto'],
                    'name' => $variation['produto_nome'] . ' - ' . $variation['cor'] . ' ' . $variation['tamanho'],
                    'price' => $variation['preco_venda'],
                    'image' => 'assets/images/placeholder.png',
                    'quantity' => $quantity,
                    'color' => $variation['cor'],
                    'size' => $variation['tamanho']
                ];
            }
        } else {
            $product = getProductById($productId);
            if ($product) {
                $_SESSION['cart'][$key] = [
                    'id' => $productId,
                    'type' => 'product',
                    'product_id' => $productId,
                    'name' => $product['nome'],
                    'price' => $product['preco_venda'],
                    'image' => 'assets/images/placeholder.png',
                    'quantity' => $quantity
                ];
            }
        }
    }
}

function removeFromCart($key) {
    if (isset($_SESSION['cart'][$key])) {
        unset($_SESSION['cart'][$key]);
    }
}

function updateCartQuantity($key, $quantity) {
    if (isset($_SESSION['cart'][$key])) {
        $_SESSION['cart'][$key]['quantity'] = $quantity;
    }
}

function clearCart() {
    unset($_SESSION['cart']);
}

function getCartItems() {
    return isset($_SESSION['cart']) ? $_SESSION['cart'] : [];
}

function getCartItemCount() {
    $count = 0;
    if (isset($_SESSION['cart'])) {
        foreach ($_SESSION['cart'] as $item) {
            $count += $item['quantity'];
        }
    }
    return $count;
}

function calculateSubtotal() {
    $subtotal = 0;
    if (isset($_SESSION['cart'])) {
        foreach ($_SESSION['cart'] as $item) {
            $subtotal += $item['price'] * $item['quantity'];
        }
    }
    return $subtotal;
}

// Check if product or variation has enough stock
function checkStock($type, $id, $quantity) {
    global $pdo;
    
    try {
        if ($type === 'product') {
            $stmt = $pdo->prepare("SELECT estoque_atual FROM produtos WHERE id_produto = :id");
        } else {
            $stmt = $pdo->prepare("SELECT estoque_atual FROM produto_variacoes WHERE id_variacao = :id");
        }
        
        $stmt->execute(['id' => $id]);
        $result = $stmt->fetch();
        
        if ($result && isset($result['estoque_atual'])) {
            return $result['estoque_atual'] >= $quantity;
        }
        
        return false;
    } catch (PDOException $e) {
        error_log("Database error in checkStock: " . $e->getMessage());
        return false;
    }
}

// Update stock after purchase
function updateStock($type, $id, $quantity) {
    global $pdo;
    
    try {
        if ($type === 'product') {
            $stmt = $pdo->prepare("
                UPDATE produtos 
                SET estoque_atual = estoque_atual - :quantity 
                WHERE id_produto = :id
            ");
        } else {
            $stmt = $pdo->prepare("
                UPDATE produto_variacoes 
                SET estoque_atual = estoque_atual - :quantity 
                WHERE id_variacao = :id
            ");
        }
        
        $stmt->execute([
            'id' => $id,
            'quantity' => $quantity
        ]);
        
        return true;
    } catch (PDOException $e) {
        error_log("Database error in updateStock: " . $e->getMessage());
        return false;
    }
}

// Process order
function processOrder($customerData, $cartItems) {
    global $pdo;
    
    try {
        // Start transaction
        $pdo->beginTransaction();
        
        // Get or create customer
        $customerId = 22; // Using the demo customer ID from your database
        
        // Create sale record
        $stmt = $pdo->prepare("
            INSERT INTO vendas (id_cliente, id_funcionario, total_venda, status, valor_total, desconto)
            VALUES (:customer_id, :employee_id, :total, 'finalizada', :total, 0)
        ");
        
        $total = calculateSubtotal();
        $stmt->execute([
            'customer_id' => $customerId,
            'employee_id' => 11, // Using the demo employee ID from your database
            'total' => $total
        ]);
        
        $saleId = $pdo->lastInsertId();
        
        // Add items to sale
        foreach ($cartItems as $item) {
            if ($item['type'] === 'variation') {
                $stmt = $pdo->prepare("
                    INSERT INTO itens_venda (id_venda, id_produto, id_variacao, quantidade, preco_unitario, total_item, nome_variacao)
                    VALUES (:sale_id, :product_id, :variation_id, :quantity, :price, :total, :variation_name)
                ");
                
                $stmt->execute([
                    'sale_id' => $saleId,
                    'product_id' => $item['product_id'],
                    'variation_id' => $item['id'],
                    'quantity' => $item['quantity'],
                    'price' => $item['price'],
                    'total' => $item['price'] * $item['quantity'],
                    'variation_name' => $item['name']
                ]);
                
                // Update stock
                updateStock('variation', $item['id'], $item['quantity']);
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO itens_venda (id_venda, id_produto, quantidade, preco_unitario, total_item)
                    VALUES (:sale_id, :product_id, :quantity, :price, :total)
                ");
                
                $stmt->execute([
                    'sale_id' => $saleId,
                    'product_id' => $item['id'],
                    'quantity' => $item['quantity'],
                    'price' => $item['price'],
                    'total' => $item['price'] * $item['quantity']
                ]);
                
                // Update stock
                updateStock('product', $item['id'], $item['quantity']);
            }
        }
        
        // Commit transaction
        $pdo->commit();
        
        return true;
    } catch (Exception $e) {
        // Rollback transaction on error
        $pdo->rollBack();
        error_log("Order processing error: " . $e->getMessage());
        return false;
    }
}
?>
