<?php
include 'config.php';
session_start();

class ProductFilter {
    private $conn;
    private $category;
    
    public function __construct($connection) {
        $this->conn = $connection;
        $this->category = isset($_POST['category']) ? $this->sanitizeInput($_POST['category']) : '';
    }
    
    public function displayProducts() {
        try {
            $products = $this->getFilteredProducts();
            
            if (empty($products)) {
                echo '<p class="empty">No products found!</p>';
                return;
            }
            
            foreach ($products as $product) {
                $this->renderProductBox($product);
            }
            
        } catch (Exception $e) {
            echo '<p class="empty">Error loading products: ' . $e->getMessage() . '</p>';
        }
    }
    
    private function getFilteredProducts() {
        $query = "SELECT * FROM products WHERE 1";
        $params = [];
        $types = "";
        
        // Filter by category if selected
        if (!empty($this->category)) {
            $query .= " AND category = ?";
            $params[] = $this->category;
            $types .= "s";
        }
        
        $query .= " ORDER BY id DESC LIMIT 6";
        
        $stmt = $this->conn->prepare($query);
        
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        $products = [];
        
        while ($row = $result->fetch_assoc()) {
            $products[] = $row;
        }
        
        $stmt->close();
        return $products;
    }
    
    private function renderProductBox($product) {
        $available_stock = $product['stocks'];
        $is_favorite = $this->isProductFavorite($product['id']);
        ?>
        <form action="" method="post" class="box">
            <img class="image" src="uploaded_img/<?php echo htmlspecialchars($product['image']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>">
            <div class="heart-container">
                <button type="submit" name="add_to_favorites" class="heart-btn">
                    <i class="<?php echo $is_favorite ? 'fa-solid fa-heart' : 'fa-regular fa-heart'; ?>"></i>
                </button>
            </div>
            <div class="name"><?php echo htmlspecialchars($product['name']); ?></div>
            <div class="price">Rs.<?php echo htmlspecialchars($product['price']); ?>/-</div>
            <div class="stock">Stock: <?php echo htmlspecialchars($available_stock); ?></div>
            <input type="number" min="1" max="<?php echo $available_stock; ?>" name="product_quantity" value="1" class="qty">
            <input type="hidden" name="product_id" value="<?php echo htmlspecialchars($product['id']); ?>">
            <input type="hidden" name="product_name" value="<?php echo htmlspecialchars($product['name']); ?>">
            <input type="hidden" name="product_price" value="<?php echo htmlspecialchars($product['price']); ?>">
            <input type="hidden" name="product_image" value="<?php echo htmlspecialchars($product['image']); ?>">
            <input type="hidden" name="product_stock" value="<?php echo htmlspecialchars($available_stock); ?>">

            <input type="submit" value="Add to Cart" name="add_to_cart" class="btn">
        </form>
        <?php
    }
    
    private function isProductFavorite($product_id) {
        if (!isset($_SESSION['id'])) return false;
        
        $user_id = $_SESSION['id'];
        $stmt = $this->conn->prepare("SELECT * FROM `favorites` WHERE user_id = ? AND product_id = ?");
        $stmt->bind_param("ii", $user_id, $product_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $is_favorite = $result->num_rows > 0;
        $stmt->close();
        return $is_favorite;
    }
    
    private function sanitizeInput($input) {
        return htmlspecialchars(strip_tags(trim($input)));
    }
}

// Usage
try {
    $productFilter = new ProductFilter($conn);
    $productFilter->displayProducts();
} catch (Exception $e) {
    echo '<p class="empty">Unable to load products. Please try again later.</p>';
    // Log the error for debugging: error_log($e->getMessage());
}
?>