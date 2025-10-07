<?php
include 'config.php';
session_start();
error_reporting(1);

class HomePage {
    private $conn;
    
    public function __construct($connection) {
        $this->conn = $connection;
    }
    
    public function handlePaymentStatus() {
        // Handle eSewa payment success
        if (isset($_GET["payment"]) && $_GET["payment"] == "success") {
            $this->completeEsewaPayment();
        }
        
        // Also keep the existing data parameter handling for backward compatibility
        if (isset($_GET["data"])) {
            $response_encoded = $_GET["data"];
            $response = json_decode(base64_decode($response_encoded), true);
            $status = $response["status"] ?? '';
            
            if ($status == "COMPLETE") {
                $this->completeEsewaPayment();
            }
        }
    }
    
    private function completeEsewaPayment() {
        // Check if there's a pending order in session
        if (isset($_SESSION['pending_order'])) {
            $pending_order = $_SESSION['pending_order'];
            $order_id = $pending_order['order_id'];
            $selected_items = $pending_order['selected_items'];
            $user_id = $pending_order['user_id'];
            
            // Update order status to Completed
            if ($this->updateOrderStatus($order_id, 'Completed')) {
                // Update stock and clear cart items
                $this->updateStockAndClearCart($selected_items, $user_id);
                
                // Clear pending order from session
                unset($_SESSION['pending_order']);
                
                // Show success message
                $this->showMessage("Payment successful! Your order has been completed.");
                return true;
            }
        }
        
        $this->showMessage("Payment processed but order completion failed. Please contact support.");
        return false;
    }
    
    private function updateOrderStatus($order_id, $status) {
        $stmt = $this->conn->prepare("UPDATE orders SET payment_status = ? WHERE id = ?");
        $stmt->bind_param("si", $status, $order_id);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }
    
    private function updateStockAndClearCart($selected_items, $user_id) {
        foreach ($selected_items as $item_id) {
            // Get cart item details
            $cart_item = $this->getCartItem($item_id, $user_id);
            if ($cart_item) {
                // Update product stock
                $this->updateProductStock($cart_item['name'], $cart_item['quantity']);
                // Remove from cart
                $this->removeFromCart($item_id, $user_id);
            }
        }
    }
    
    private function getCartItem($item_id, $user_id) {
        $stmt = $this->conn->prepare("SELECT * FROM cart WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $item_id, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $item = $result->fetch_assoc();
        $stmt->close();
        return $item;
    }
    
    private function updateProductStock($product_name, $quantity) {
        $stmt = $this->conn->prepare("UPDATE products SET stocks = stocks - ? WHERE name = ?");
        $stmt->bind_param("is", $quantity, $product_name);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }
    
    private function removeFromCart($item_id, $user_id) {
        $stmt = $this->conn->prepare("DELETE FROM cart WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $item_id, $user_id);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }
    
    private function showMessage($text) {
        echo "<script>alert('$text');</script>";
    }
    
    public function handleAddToCart() {
        if (isset($_POST['add_to_cart']) && isset($_SESSION['id'])) {
            $user_id = $_SESSION['id'];
            $product_name = $this->sanitizeInput($_POST['product_name']);
            $product_price = $this->sanitizeInput($_POST['product_price']);
            $product_image = $this->sanitizeInput($_POST['product_image']);
            $product_quantity = intval($_POST['product_quantity']);
            $product_stock = intval($_POST['product_stock']);
            
            if ($product_quantity > $product_stock) {
                return 'Cannot add more than available stock!';
            }
            
            if ($this->isProductInCart($user_id, $product_name)) {
                return 'Already added to cart!';
            }
            
            if ($this->addToCart($user_id, $product_name, $product_price, $product_quantity, $product_image)) {
                return 'Product added to cart!';
            }
            
            return 'Failed to add product to cart!';
        }
        return null;
    }
    
    public function handleAddToFavorites() {
        if (isset($_POST['add_to_favorites']) && isset($_SESSION['id'])) {
            $user_id = $_SESSION['id'];
            $product_id = intval($_POST['product_id']);
            
            if ($this->isProductInFavorites($user_id, $product_id)) {
                $this->removeFromFavorites($user_id, $product_id);
                return 'Product removed from favorites!';
            } else {
                $this->addToFavorites($user_id, $product_id);
                return 'Product added to favorites!';
            }
        }
        return null;
    }
    
    private function isProductInCart($user_id, $product_name) {
        $stmt = $this->conn->prepare("SELECT * FROM `cart` WHERE name = ? AND user_id = ?");
        $stmt->bind_param("si", $product_name, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $exists = $result->num_rows > 0;
        $stmt->close();
        return $exists;
    }
    
    private function addToCart($user_id, $name, $price, $quantity, $image) {
        $stmt = $this->conn->prepare("INSERT INTO `cart`(user_id, name, price, quantity, image) VALUES(?, ?, ?, ?, ?)");
        $stmt->bind_param("issis", $user_id, $name, $price, $quantity, $image);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }
    
    private function isProductInFavorites($user_id, $product_id) {
        $stmt = $this->conn->prepare("SELECT * FROM `favorites` WHERE user_id = ? AND product_id = ?");
        $stmt->bind_param("ii", $user_id, $product_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $exists = $result->num_rows > 0;
        $stmt->close();
        return $exists;
    }
    
    private function addToFavorites($user_id, $product_id) {
        $stmt = $this->conn->prepare("INSERT INTO `favorites`(user_id, product_id) VALUES(?, ?)");
        $stmt->bind_param("ii", $user_id, $product_id);
        $stmt->execute();
        $stmt->close();
    }
    
    private function removeFromFavorites($user_id, $product_id) {
        $stmt = $this->conn->prepare("DELETE FROM `favorites` WHERE user_id = ? AND product_id = ?");
        $stmt->bind_param("ii", $user_id, $product_id);
        $stmt->execute();
        $stmt->close();
    }
    
    public function getCategories() {
        $result = $this->conn->query("SELECT * FROM categories");
        $categories = [];
        while ($row = $result->fetch_assoc()) {
            $categories[] = $row;
        }
        return $categories;
    }
    
    public function getProducts($category = '') {
        $query = "SELECT * FROM `products` WHERE 1";
        $params = [];
        $types = "";
        
        if (!empty($category)) {
            // Convert category to integer to ensure type safety
            $category = intval($category);
            if ($category > 0) {
                $query .= " AND category = ?";
                $params[] = $category;
                $types .= "i";
            }
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
    
    public function isProductFavorite($user_id, $product_id) {
        if (!$user_id) return false;
        
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
    
    public function render() {
        $this->handlePaymentStatus();
        $cartMessage = $this->handleAddToCart();
        $favoriteMessage = $this->handleAddToFavorites();
        
        // Get and validate category parameter
        $selected_category = $_GET['category'] ?? '';
        if (!empty($selected_category) && !ctype_digit($selected_category)) {
            $selected_category = ''; // Reset if not a valid integer
        }
        
        $products = $this->getProducts($selected_category);
        $categories = $this->getCategories();
        $user_id = $_SESSION['id'] ?? null;
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta http-equiv="X-UA-Compatible" content="IE=edge">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Home - BloomNest</title>
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
            <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&family=Montserrat:wght@300;400;500;600&display=swap">
            <link rel="stylesheet" href="css/style.css">
            <style>
                :root {
                    --primary: #8e44ad;
                    --secondary: #f39c12;
                    --accent: #e84393;
                    --light: #f9f7fe;
                    --dark: #2c3e50;
                    --success: #27ae60;
                    --pink: #e84393;
                    --light-pink: #fd79a8;
                    --white: #fff;
                    --black: #333;
                    --light-bg: #f5f5f5;
                    --border: 0.1rem solid rgba(0,0,0,0.1);
                    --box-shadow: 0 0.5rem 1.5rem rgba(0,0,0,0.1);
                    --transition: all 0.3s ease;
                }
                
                * {
                    font-family: 'Montserrat', sans-serif;
                    margin: 0;
                    padding: 0;
                    box-sizing: border-box;
                    outline: none;
                    border: none;
                    text-decoration: none;
                    text-transform: capitalize;
                    transition: var(--transition);
                }
                
                .message {
                    position: sticky;
                    top: 0;
                    max-width: 1200px;
                    margin: 0 auto;
                    background-color: var(--light);
                    padding: 1.5rem;
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    border-radius: 0.5rem;
                    box-shadow: var(--box-shadow);
                    z-index: 10000;
                    border-left: 4px solid var(--success);
                    margin-top: 2rem;
                }
                
                .home {
                    background: linear-gradient(rgba(0,0,0,0.5), rgba(0,0,0,0.5)), url('images/hero-bg.jpg') no-repeat;
                    background-size: cover;
                    background-position: center;
                    padding: 4rem 2rem;
                    text-align: center;
                    min-height: 80vh;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                }
                
                .home .content {
                    max-width: 800px;
                }
                
                .home .content h3 {
                    font-family: 'Playfair Display', serif;
                    font-size: 4.5rem;
                    color: var(--white);
                    margin-bottom: 1.5rem;
                    text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
                }
                
                .home .content p {
                    font-size: 1.8rem;
                    color: var(--white);
                    padding: 1rem 0;
                    line-height: 1.8;
                    margin-bottom: 2rem;
                }
                
                .btn, .white-btn, .option-btn {
                    display: inline-block;
                    margin-top: 1rem;
                    padding: 1rem 3rem;
                    cursor: pointer;
                    color: var(--white);
                    font-size: 1.8rem;
                    border-radius: 50px;
                    text-transform: uppercase;
                    font-weight: 500;
                    text-align: center;
                    box-shadow: var(--box-shadow);
                }
                
                .btn {
                    background: linear-gradient(45deg, var(--primary), var(--accent));
                    width: 100%;
                }
                
                .btn:hover {
                    background: linear-gradient(45deg, var(--accent), var(--primary));
                    transform: translateY(-3px);
                    box-shadow: 0 1rem 2rem rgba(0,0,0,0.2);
                }
                
                .white-btn {
                    background: var(--white);
                    color: var(--primary);
                }
                
                .white-btn:hover {
                    background: var(--primary);
                    color: var(--white);
                }
                
                .option-btn {
                    background: linear-gradient(45deg, var(--secondary), var(--accent));
                    padding: 1rem 2.5rem;
                }
                
                .option-btn:hover {
                    transform: translateY(-3px);
                    box-shadow: 0 1rem 2rem rgba(0,0,0,0.2);
                }
                
                .title {
                    font-family: 'Playfair Display', serif;
                    font-size: 3.5rem;
                    color: var(--dark);
                    text-align: center;
                    margin-bottom: 3rem;
                    position: relative;
                    padding-bottom: 1rem;
                }
                
                .title::after {
                    content: '';
                    position: absolute;
                    bottom: 0;
                    left: 50%;
                    transform: translateX(-50%);
                    width: 100px;
                    height: 4px;
                    background: linear-gradient(to right, var(--primary), var(--accent));
                    border-radius: 2px;
                }
                
                .filter {
                    text-align: center;
                    margin: 4rem 0 2rem;
                    text-transform: uppercase;
                    color: var(--dark);
                    font-size: 2.2rem;
                    font-weight: 600;
                    letter-spacing: 1px;
                }
                
                .filter-options {
                    margin-bottom: 3rem;
                    display: flex;
                    justify-content: center;
                }
                
                .filter-options select {
                    padding: 12px 25px;
                    font-size: 16px;
                    border: 2px solid #e0e0e0;
                    border-radius: 50px;
                    outline: none;
                    background: var(--white);
                    box-shadow: var(--box-shadow);
                    cursor: pointer;
                    appearance: none;
                    background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%238e44ad' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
                    background-repeat: no-repeat;
                    background-position: right 15px center;
                    background-size: 16px;
                    padding-right: 45px;
                }
                
                .filter-options select:hover, .filter-options select:focus {
                    border-color: var(--primary);
                    box-shadow: 0 0 0 3px rgba(142, 68, 173, 0.1);
                }
                
                .products {
                    padding: 5rem 2rem;
                    background: var(--light-bg);
                }
                
                .products .box-container {
                    display: grid;
                    grid-template-columns: repeat(auto-fit, minmax(30rem, 1fr));
                    gap: 3rem;
                    justify-content: center;
                    margin: 0 auto;
                    max-width: 1200px;
                    padding: 0 2rem;
                }
                
                .products .box-container .box {
                    position: relative;
                    background: var(--white);
                    box-shadow: var(--box-shadow);
                    border-radius: 1rem;
                    padding: 2.5rem;
                    overflow: hidden;
                    display: flex;
                    flex-direction: column;
                    transition: var(--transition);
                    border: var(--border);
                }
                
                .products .box-container .box:hover {
                    transform: translateY(-10px);
                    box-shadow: 0 1.5rem 3rem rgba(0,0,0,0.15);
                }
                
                .products .box-container .box .image {
                    height: 22rem;
                    width: 100%;
                    object-fit: contain;
                    margin-bottom: 2rem;
                    padding: 1rem;
                }
                
                .products .box-container .box .name {
                    font-size: 2.2rem;
                    color: var(--dark);
                    margin: 0.5rem 0;
                    font-weight: 600;
                }
                
                .products .box-container .box .price {
                    font-size: 2.4rem;
                    color: white;
                    margin: 0.5rem 0;
                    font-weight: 700;
                }
                
                .products .box-container .box .stock {
                    font-size: 1.5rem;
                    color: #666;
                    margin-bottom: 1.5rem;
                    padding: 0.5rem 1rem;
                    background: #f8f9fa;
                    border-radius: 50px;
                    display: inline-block;
                    align-self: flex-start;
                }
                
                .products .box-container .box .qty {
                    padding: 1.2rem;
                    font-size: 1.6rem;
                    color: var(--black);
                    background: #f8f9fa;
                    border-radius: 0.5rem;
                    margin: 1rem 0;
                    border: 1px solid #e9ecef;
                    width: 100%;
                }
                
                .heart-container {
                    position: absolute;
                    top: 20px;
                    right: 20px;
                    z-index: 10;
                }
                
                .heart-btn {
                    background: var(--white);
                    width: 40px;
                    height: 40px;
                    border-radius: 50%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                    border: none;
                    cursor: pointer;
                    transition: var(--transition);
                }
                
                .heart-btn:hover {
                    background: #ffeaea;
                    transform: scale(1.1);
                }
                
                .heart-btn i {
                    color: #ff6666;
                    font-size: 18px;
                }
                
                .heart-btn:hover i {
                    color: #ff3333;
                }
                
                .empty {
                    grid-column: 1 / -1;
                    text-align: center;
                    font-size: 2rem;
                    color: #888;
                    padding: 4rem;
                }
                
                .load-more {
                    margin-top: 4rem;
                    text-align: center;
                }
                
                .about {
                    padding: 8rem 2rem;
                }
                
                .about .flex {
                    display: flex;
                    align-items: center;
                    flex-wrap: wrap;
                    gap: 5rem;
                }
                
                .about .flex .image {
                    flex: 1 1 40rem;
                    border-radius: 2rem;
                    overflow: hidden;
                    box-shadow: var(--box-shadow);
                }
                
                .about .flex .image img {
                    width: 100%;
                    height: 100%;
                    object-fit: cover;
                }
                
                .about .flex .content {
                    flex: 1 1 40rem;
                }
                
                .about .flex .content h3 {
                    font-family: 'Playfair Display', serif;
                    font-size: 3.5rem;
                    color: var(--dark);
                    margin-bottom: 2rem;
                }
                
                .about .flex .content p {
                    font-size: 1.7rem;
                    color: #666;
                    line-height: 1.8;
                    padding: 1rem 0;
                }
                
                .home-contact {
                    background: linear-gradient(rgba(0,0,0,0.7), rgba(0,0,0,0.7)), url('images/contact-bg.jpg') no-repeat;
                    background-size: cover;
                    background-position: center;
                    padding: 6rem 2rem;
                    text-align: center;
                }
                
                .home-contact .content h3 {
                    font-family: 'Playfair Display', serif;
                    font-size: 3.5rem;
                    color: var(--white);
                    margin-bottom: 2rem;
                }
                
                .home-contact .content p {
                    font-size: 1.8rem;
                    color: var(--white);
                    max-width: 800px;
                    margin: 0 auto;
                    line-height: 1.8;
                    margin-bottom: 3rem;
                }
                
                @media (max-width: 768px) {
                    .home .content h3 {
                        font-size: 3rem;
                    }
                    
                    .title {
                        font-size: 2.8rem;
                    }
                    
                    .about .flex {
                        gap: 3rem;
                    }
                    
                    .about .flex .content h3 {
                        font-size: 2.8rem;
                    }
                    
                    .products .box-container {
                        grid-template-columns: repeat(auto-fit, minmax(25rem, 1fr));
                        gap: 2rem;
                    }
                }
                
                @media (max-width: 450px) {
                    .home .content h3 {
                        font-size: 2.5rem;
                    }
                    
                    .home .content p {
                        font-size: 1.6rem;
                    }
                    
                    .filter-options select {
                        width: 90%;
                    }
                    
                    .title {
                        font-size: 2.5rem;
                    }
                }
            </style>
        </head>
        <body>
            <?php include_once 'header.php'; ?>
            
            <?php if (isset($cartMessage)): ?>
                <div class="message">
                    <span><?php echo $cartMessage; ?></span>
                    <i class="fas fa-times" onclick="this.parentElement.remove();"></i>
                </div>
            <?php endif; ?>
            
            <?php if (isset($favoriteMessage)): ?>
                <div class="message">
                    <span><?php echo $favoriteMessage; ?></span>
                    <i class="fas fa-times" onclick="this.parentElement.remove();"></i>
                </div>
            <?php endif; ?>

            <section class="home">
                <div class="content">
                    <h3>Hand Picked Flower Items to Your Door</h3>
                    <p>Discover a world of flowers, handpicked just for you. Enjoy curated selections delivered straight to your doorstep. Start your floral adventure today!</p>
                    <a href="about.php" class="white-btn">Discover More</a>
                </div>
            </section>

            <section class="products">
                <h1 class="title">Latest Products</h1>
                
                <h2 class="filter">Filter by Category</h2>
                <div class="filter-options">
                    <select id="category-filter">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo $category['id']; ?>" <?php echo $selected_category == $category['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($category['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="box-container" id="products-grid">
                    <?php if (!empty($products)): ?>
                        <?php foreach ($products as $product): ?>
                            <?php
                            $is_favorite = $this->isProductFavorite($user_id, $product['id']);
                            $available_stock = $product['stocks'];
                            ?>
                            <form action="" method="post" class="box">
                                <img class="image" src="uploaded_img/<?php echo htmlspecialchars($product['image']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>">
                                <div class="heart-container">
                                    <button type="submit" name="add_to_favorites" class="heart-btn">
                                        <i class="<?php echo $is_favorite ? 'fa-solid fa-heart' : 'fa-regular fa-heart'; ?>"></i>
                                    </button>
                                </div>
                                <div class="name"><?php echo htmlspecialchars($product['name']); ?></div>
                                <div class="price">Rs. <?php echo htmlspecialchars($product['price']); ?>/-</div>
                                <div class="stock">Stock: <?php echo htmlspecialchars($available_stock); ?></div>
                                <input type="number" min="1" max="<?php echo $available_stock; ?>" name="product_quantity" value="1" class="qty">
                                <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                <input type="hidden" name="product_name" value="<?php echo htmlspecialchars($product['name']); ?>">
                                <input type="hidden" name="product_price" value="<?php echo htmlspecialchars($product['price']); ?>">
                                <input type="hidden" name="product_image" value="<?php echo htmlspecialchars($product['image']); ?>">
                                <input type="hidden" name="product_stock" value="<?php echo $available_stock; ?>">
                                <input type="submit" value="Add to Cart" name="add_to_cart" class="btn">
                            </form>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="empty">No products found!</p>
                    <?php endif; ?>
                </div>

                <div class="load-more">
                    <a href="shop.php" class="option-btn">Load More</a>
                </div>
            </section>

            <section class="about">
                <div class="flex">
                    <div class="image">
                        <img src="images/about-img.jpg" alt="About BloomNest">
                    </div>
                    <div class="content">
                        <h3>About Us</h3>
                        <p>At BloomNest, we believe in the power of flowers to transform lives. Our curated selection ensures that you receive the best in floral arrangements, handpicked by our team of experts with years of experience in floristry.</p>
                        <p>We source our flowers from ethical growers who prioritize sustainable practices, ensuring that every bouquet is not only beautiful but also environmentally conscious.</p>
                        <a href="about.php" class="btn">Read More</a>
                    </div>
                </div>
            </section>

            <section class="home-contact">
                <div class="content">
                    <h3>Have Any Questions?</h3>
                    <p>At BloomNest, we're here to bring beauty to your moments! Whether you're searching for the perfect bouquet, need help with your order, or simply have a question about our flowers, our dedicated team is ready to assist you.</p>
                    <a href="contact.php" class="white-btn">Contact Us</a>
                </div>
            </section>

            <?php include 'footer.php'; ?>
            <script src="script/script.js"></script>
            <script>
                document.addEventListener("DOMContentLoaded", function () {
                    const categoryFilter = document.getElementById('category-filter');
                    const productsGrid = document.getElementById('products-grid');
                    
                    categoryFilter.addEventListener('change', function() {
                        const category = this.value;
                        
                        // Show loading indicator
                        productsGrid.innerHTML = '<div class="empty">Loading products...</div>';
                        
                        // Create form data for POST request
                        const formData = new FormData();
                        formData.append('category', category);

                        // Create AJAX request to fetch_products.php
                        fetch('fetch_products.php', {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => {
                            if (!response.ok) {
                                throw new Error('Network response was not ok');
                            }
                            return response.text();
                        })
                        .then(data => {
                            productsGrid.innerHTML = data;
                            initProductAnimations();
                        })
                        .catch(error => {
                            productsGrid.innerHTML = '<div class="empty">Error loading products: ' + error.message + '</div>';
                            console.error('Error:', error);
                        });
                    });
                    
                    // Initialize product animations
                    function initProductAnimations() {
                        const productBoxes = document.querySelectorAll('.products .box');
                        
                        const observer = new IntersectionObserver((entries) => {
                            entries.forEach(entry => {
                                if (entry.isIntersecting) {
                                    entry.target.style.opacity = 1;
                                    entry.target.style.transform = 'translateY(0)';
                                }
                            });
                        }, { threshold: 0.1 });
                        
                        productBoxes.forEach(box => {
                            box.style.opacity = 0;
                            box.style.transform = 'translateY(50px)';
                            box.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
                            observer.observe(box);
                        });
                    }
                    
                    // Handle form submissions with AJAX
                    document.addEventListener('click', function(e) {
                        // Handle add to cart form submissions
                        if (e.target.matches('input[name="add_to_cart"]')) {
                            e.preventDefault();
                            
                            const form = e.target.closest('form');
                            const formData = new FormData(form);
                            
                            fetch('index.php', {
                                method: 'POST',
                                body: formData
                            })
                            .then(response => response.text())
                            .then(data => {
                                // Show success message
                                showMessage('Product added to cart!');
                            })
                            .catch(error => {
                                showMessage('Error adding product to cart');
                                console.error('Error:', error);
                            });
                        }
                        
                        // Handle add to favorites form submissions
                        if (e.target.matches('.heart-btn') || e.target.closest('.heart-btn')) {
                            e.preventDefault();
                            
                            const btn = e.target.closest('.heart-btn');
                            const form = btn.closest('form');
                            const formData = new FormData(form);
                            formData.append('add_to_favorites', '1');
                            
                            fetch('index.php', {
                                method: 'POST',
                                body: formData
                            })
                            .then(response => response.text())
                            .then(data => {
                                // Toggle heart icon
                                const icon = btn.querySelector('i');
                                if (icon.classList.contains('fa-regular')) {
                                    icon.classList.remove('fa-regular');
                                    icon.classList.add('fa-solid');
                                    showMessage('Product added to favorites!');
                                } else {
                                    icon.classList.remove('fa-solid');
                                    icon.classList.add('fa-regular');
                                    showMessage('Product removed from favorites!');
                                }
                            })
                            .catch(error => {
                                showMessage('Error updating favorites');
                                console.error('Error:', error);
                            });
                        }
                    });
                    
                    // Function to show messages
                    function showMessage(text) {
                        // Remove existing messages
                        const existingMessages = document.querySelectorAll('.message');
                        existingMessages.forEach(msg => msg.remove());
                        
                        // Create new message
                        const message = document.createElement('div');
                        message.className = 'message';
                        message.innerHTML = `
                            <span>${text}</span>
                            <i class="fas fa-times" onclick="this.parentElement.remove();"></i>
                        `;
                        
                        // Add to page
                        document.body.appendChild(message);
                        
                        // Auto remove after 3 seconds
                        setTimeout(() => {
                            if (message.parentElement) {
                                message.remove();
                            }
                        }, 3000);
                    }
                    
                    // Initialize animations on page load
                    initProductAnimations();
                });
            </script>
        </body>
        </html>
        <?php
    }
}

// Usage
try {
    $homePage = new HomePage($conn);
    $homePage->render();
} catch (Exception $e) {
    error_log("Home page error: " . $e->getMessage());
    echo "<div class='error'>An error occurred. Please try again later.</div>";
    echo "<div class='error'>Error: " . htmlspecialchars($e->getMessage()) . "</div>";
}
?>