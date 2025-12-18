<?php
// Include database connection
require_once 'includes/db_connection.php';

// Product ID to update
$productId = 119; // Replace with your specific product ID
$imagePath = 'public/imgteste.png';

try {
    // Update the product's image path
    $stmt = $pdo->prepare("
        UPDATE produtos 
        SET foto = :imagePath 
        WHERE id_produto = :productId
    ");
    
    $stmt->execute([
        'imagePath' => $imagePath,
        'productId' => $productId
    ]);
    
    $rowsAffected = $stmt->rowCount();
    
    if ($rowsAffected > 0) {
        echo "Product image updated successfully!";
    } else {
        echo "No product found with ID: $productId";
    }
} catch (PDOException $e) {
    echo "Error updating product image: " . $e->getMessage();
}
?>

<p><a href="products.php">Return to products</a></p>
