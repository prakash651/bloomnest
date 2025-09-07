<?php
include 'config.php';
error_reporting(0);
session_start();

class Product {
    public $id;
    public $name;
    public $price;
    public $image;
    public $stock;
    public $quantity;

    public function __construct($id, $name, $price, $image, $stock, $quantity = 1) {
        $this->id = $id;
        $this->name = $name;
        $this->price = $price;
        $this->image = $image;
        $this->stock = $stock;
        $this->quantity = $quantity;
    }
}

class CartManager {
    private $conn;
    
    public function __construct($connection) {
        $this->conn = $connection;
    }
    
    public function addToCart(Product $product, $user_id) {
        // Check if product is already in cart
        $check_cart = mysqli_query($this->conn, 
            "SELECT * FROM `cart` WHERE name = '{$product->name}' AND user_id = '$user_id'");
        
        if (mysqli_num_rows($check_cart) > 0) {
            return 'Already added to cart!';
        }
        
        // Ensure user does not add more than the available stock
        if ($product->quantity > $product->stock) {
            return 'Cannot add more than available stock!';
        }
        
        // Insert into cart
        $insert_query = mysqli_query($this->conn, 
            "INSERT INTO `cart`(user_id, name, price, quantity, image) 
             VALUES('$user_id', '{$product->name}', '{$product->price}', '{$product->quantity}', '{$product->image}')");
        
        return $insert_query ? 'Product added to cart!' : 'Failed to add product to cart!';
    }
}

class FavoritesManager {
    private $conn;
    
    public function __construct($connection) {
        $this->conn = $connection;
    }
    
    public function toggleFavorite($user_id, $product_id) {
        // Check if the product is already in favorites
        $check_favorites = mysqli_query($this->conn, 
            "SELECT * FROM `favorites` WHERE user_id = '$user_id' AND product_id = '$product_id'");
        
        if (mysqli_num_rows($check_favorites) > 0) {
            // Remove from favorites
            mysqli_query($this->conn, 
                "DELETE FROM `favorites` WHERE user_id = '$user_id' AND product_id = '$product_id'");
            return 'Product removed from favorites!';
        } else {
            // Add to favorites
            mysqli_query($this->conn, 
                "INSERT INTO `favorites`(user_id, product_id) VALUES('$user_id', '$product_id')");
            return 'Product added to favorites!';
        }
    }
    
    public function isFavorite($user_id, $product_id) {
        $check_favorite = mysqli_query($this->conn, 
            "SELECT * FROM `favorites` WHERE user_id = '$user_id' AND product_id = '$product_id'");
        return mysqli_num_rows($check_favorite) > 0;
    }
}

class ProductManager {
    private $conn;
    
    public function __construct($connection) {
        $this->conn = $connection;
    }
    
    public function getAllProducts() {
        $query = "SELECT * FROM `products` ORDER BY id DESC";
        $result = mysqli_query($this->conn, $query);
        
        $products = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $products[] = new Product(
                $row['id'], 
                $row['name'], 
                $row['price'], 
                $row['image'], 
                $row['stocks']
            );
        }
        
        return $products;
    }
}

class ShopController {
    private $cartManager;
    private $favoritesManager;
    private $productManager;
    
    public function __construct(CartManager $cartManager, FavoritesManager $favoritesManager, ProductManager $productManager) {
        $this->cartManager = $cartManager;
        $this->favoritesManager = $favoritesManager;
        $this->productManager = $productManager;
    }
    
    public function handleRequest() {
        $message = [];
        
        // Process add to cart
        if (isset($_POST['add_to_cart'])) {
            if (isset($_SESSION['id'])) {
                $user_id = $_SESSION['id'];
                $product = new Product(
                    0,
                    $_POST['product_name'],
                    $_POST['product_price'],
                    $_POST['product_image'],
                    $_POST['product_stock'],
                    $_POST['product_quantity']
                );
                
                $message[] = $this->cartManager->addToCart($product, $user_id);
            } else {
                header('location:login.php');
                exit();
            }
        }
        
        // Process add to favorites
        if (isset($_POST['add_to_favorites'])) {
            if (isset($_SESSION['id'])) {
                $user_id = $_SESSION['id'];
                $product_id = $_POST['product_id'];
                
                $message[] = $this->favoritesManager->toggleFavorite($user_id, $product_id);
            } else {
                header('location:login.php');
                exit();
            }
        }
        
        // Get all products
        $products = $this->productManager->getAllProducts();
        
        return [
            'message' => $message,
            'products' => $products
        ];
    }
}

// Initialize objects
$cartManager = new CartManager($conn);
$favoritesManager = new FavoritesManager($conn);
$productManager = new ProductManager($conn);
$controller = new ShopController($cartManager, $favoritesManager, $productManager);

// Handle request
$result = $controller->handleRequest();
extract($result);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shop - BloomNest</title>
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
        
        .heading {
            background: linear-gradient(rgba(0,0,0,0.5), rgba(0,0,0,0.5)), url('images/shop-bg.jpg') no-repeat;
            background-size: cover;
            background-position: center;
            padding: 4rem 2rem;
            text-align: center;
            min-height: 30vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }
        
        .heading h3 {
            font-family: 'Playfair Display', serif;
            font-size: 4rem;
            color: var(--white);
            margin-bottom: 1rem;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }
        
        .heading p {
            font-size: 1.8rem;
            color: var(--white);
        }
        
        .heading p a {
            color: var(--secondary);
        }
        
        .heading p a:hover {
            text-decoration: underline;
        }
        
        .message {
            position: sticky;
            top: 0;
            max-width: 1200px;
            margin: 2rem auto;
            background-color: var(--light);
            padding: 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-radius: 0.5rem;
            box-shadow: var(--box-shadow);
            z-index: 10000;
            border-left: 4px solid var(--success);
        }
        
        .message span {
            font-size: 1.6rem;
            color: var(--dark);
        }
        
        .message i {
            cursor: pointer;
            color: #888;
            font-size: 1.8rem;
        }
        
        .message i:hover {
            color: var(--dark);
        }
        
        .title {
            font-family: 'Playfair Display', serif;
            font-size: 3.5rem;
            color: var(--dark);
            text-align: center;
            margin: 4rem 0 3rem;
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
        
        .products {
            padding: 5rem 2rem;
            background: var(--light-bg);
        }
        
        .products .box-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(30rem, 1fr));
            gap: 3rem;
            max-width: 1200px;
            margin: 0 auto;
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
            color: var(--primary);
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
        
        .btn {
            display: inline-block;
            margin-top: 1rem;
            padding: 1.2rem 3rem;
            cursor: pointer;
            color: var(--white);
            font-size: 1.6rem;
            border-radius: 50px;
            text-transform: uppercase;
            font-weight: 500;
            text-align: center;
            box-shadow: var(--box-shadow);
            background: linear-gradient(45deg, var(--primary), var(--accent));
            width: 100%;
        }
        
        .btn:hover {
            background: linear-gradient(45deg, var(--accent), var(--primary));
            transform: translateY(-3px);
            box-shadow: 0 1rem 2rem rgba(0,0,0,0.2);
        }
        
        .empty {
            grid-column: 1 / -1;
            text-align: center;
            font-size: 2rem;
            color: #888;
            padding: 4rem;
        }
        
        @media (max-width: 768px) {
            .title {
                font-size: 2.8rem;
            }
            
            .products .box-container {
                grid-template-columns: repeat(auto-fit, minmax(25rem, 1fr));
                gap: 2rem;
            }
            
            .heading h3 {
                font-size: 3rem;
            }
        }
        
        @media (max-width: 450px) {
            .heading h3 {
                font-size: 2.5rem;
            }
            
            .heading p {
                font-size: 1.6rem;
            }
            
            .title {
                font-size: 2.5rem;
            }
            
            .products .box-container {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>

   <?php include 'header.php'; ?>

   <div class="heading">
      <h3>Our Shop</h3>
      <p> <a href="index.php">home</a> / shop </p>
   </div>

   <?php if (!empty($message)): ?>
      <?php foreach ($message as $msg): ?>
         <div class="message">
            <span><?php echo htmlspecialchars($msg); ?></span>
            <i class="fas fa-times" onclick="this.parentElement.remove();"></i>
         </div>
      <?php endforeach; ?>
   <?php endif; ?>

   <section class="products">
      <h1 class="title">All Products</h1>
      <div class="box-container">
         <?php if (!empty($products)): ?>
            <?php foreach ($products as $product): ?>
               <?php
               $is_favorite = false;
               if (isset($_SESSION['id'])) {
                  $is_favorite = $favoritesManager->isFavorite($_SESSION['id'], $product->id);
               }
               ?>
               <form action="" method="post" class="box">
                  <img class="image" src="uploaded_img/<?php echo htmlspecialchars($product->image); ?>" alt="<?php echo htmlspecialchars($product->name); ?>">
                  <div class="heart-container">
                     <button type="submit" name="add_to_favorites" class="heart-btn">
                        <i class="<?php echo $is_favorite ? 'fa-solid fa-heart' : 'fa-regular fa-heart'; ?>"></i>
                     </button>
                  </div>
                  <div class="name"><?php echo htmlspecialchars($product->name); ?></div>
                  <div class="price">Rs. <?php echo htmlspecialchars($product->price); ?>/-</div>
                  <div class="stock">Stock: <?php echo htmlspecialchars($product->stock); ?></div>
                  <input type="number" min="1" max="<?php echo $product->stock; ?>" name="product_quantity" value="1" class="qty">
                  <input type="hidden" name="product_id" value="<?php echo $product->id; ?>">
                  <input type="hidden" name="product_name" value="<?php echo htmlspecialchars($product->name); ?>">
                  <input type="hidden" name="product_price" value="<?php echo htmlspecialchars($product->price); ?>">
                  <input type="hidden" name="product_image" value="<?php echo htmlspecialchars($product->image); ?>">
                  <input type="hidden" name="product_stock" value="<?php echo $product->stock; ?>">
                  <input type="submit" value="Add to Cart" name="add_to_cart" class="btn">
               </form>
            <?php endforeach; ?>
         <?php else: ?>
            <p class="empty">No products found!</p>
         <?php endif; ?>
      </div>
   </section>

   <?php include 'footer.php'; ?>
   <script src="js/script.js"></script>
   <script>
        document.addEventListener("DOMContentLoaded", function () {
            // Add animation to product boxes on scroll
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
        });
    </script>
</body>
</html>