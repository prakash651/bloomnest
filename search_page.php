<?php
include 'config.php';
session_start();
error_reporting(0);

class Product {
    public $id;
    public $name;
    public $price;
    public $image;
    public $quantity;
    public $match_score;

    public function __construct($id, $name, $price, $image, $quantity = 1, $match_score = 0) {
        $this->id = $id;
        $this->name = $name;
        $this->price = $price;
        $this->image = $image;
        $this->quantity = $quantity;
        $this->match_score = $match_score;
    }
}

class CartManager {
    private $conn;
    private $user_id;
    
    public function __construct($connection, $user_id) {
        $this->conn = $connection;
        $this->user_id = $user_id;
    }
    
    public function addToCart(Product $product) {
        // Check if product is already in cart
        $check_cart = mysqli_query($this->conn, 
            "SELECT * FROM `cart` WHERE name = '{$product->name}' AND user_id = '{$this->user_id}'");
        
        if (mysqli_num_rows($check_cart) > 0) {
            return 'already added to cart!';
        }
        
        // Insert into cart
        $insert_query = mysqli_query($this->conn, 
            "INSERT INTO `cart`(user_id, name, price, quantity, image) 
             VALUES('{$this->user_id}', '{$product->name}', '{$product->price}', '{$product->quantity}', '{$product->image}')");
        
        return $insert_query ? 'product added to cart!' : 'Failed to add product to cart!';
    }
}

class SearchManager {
    private $conn;
    
    public function __construct($connection) {
        $this->conn = $connection;
    }
    
    public function getAllProducts() {
        $query = "SELECT * FROM `products`";
        $result = mysqli_query($this->conn, $query);
        
        $products = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $products[] = new Product($row['id'], $row['name'], $row['price'], $row['image']);
        }
        
        return $products;
    }
    
    public function getTraditionalSearchResults($search_term) {
        $search_term = mysqli_real_escape_string($this->conn, $search_term);
        $query = "SELECT * FROM `products` WHERE name LIKE '%$search_term%'";
        $result = mysqli_query($this->conn, $query);
        
        $products = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $products[] = new Product($row['id'], $row['name'], $row['price'], $row['image']);
        }
        
        return $products;
    }
    
    public function customSearch($search_term, $products) {
        $results = [];
        $search_term = strtolower(trim($search_term));
        
        if (empty($search_term)) {
            return $results;
        }
        
        foreach ($products as $product) {
            $product_name = strtolower($product->name);
            
            // Exact match (highest priority)
            if ($product_name === $search_term) {
                $product->match_score = 100;
                $results[] = $product;
                continue;
            }
            
            // Contains match
            if (strpos($product_name, $search_term) !== false) {
                $product->match_score = 80;
                $results[] = $product;
                continue;
            }
            
            // Word boundary match (starts with search term)
            $words = explode(' ', $product_name);
            foreach ($words as $word) {
                if (strpos($word, $search_term) === 0) {
                    $product->match_score = 70;
                    $results[] = $product;
                    continue 2;
                }
            }
            
            // Custom similarity calculation
            $similarity = $this->calculateSimilarity($search_term, $product_name);
            
            if ($similarity >= 60) {
                $product->match_score = $similarity;
                $results[] = $product;
            }
        }
        
        // Sort results by match score (highest first)
        usort($results, function($a, $b) {
            return $b->match_score - $a->match_score;
        });
        
        return $results;
    }
    
    private function calculateSimilarity($str1, $str2) {
        $len1 = strlen($str1);
        $len2 = strlen($str2);
        
        if (abs($len1 - $len2) > 5) {
            return 0;
        }
        
        $common_chars = 0;
        $max_len = max($len1, $len2);
        
        for ($i = 0; $i < $max_len; $i++) {
            if ($i < $len1 && $i < $len2 && $str1[$i] === $str2[$i]) {
                $common_chars++;
            }
        }
        
        $similarity = ($common_chars / $max_len) * 100;
        
        if ($len1 > 0 && $len2 > 0 && $str1[0] === $str2[0]) {
            $similarity += 10;
        }
        
        return min(100, $similarity);
    }
}

class SearchController {
    private $searchManager;
    private $cartManager;
    
    public function __construct(SearchManager $searchManager, CartManager $cartManager) {
        $this->searchManager = $searchManager;
        $this->cartManager = $cartManager;
    }
    
    public function handleRequest() {
        $message = [];
        $search_results = [];
        $search_performed = false;
        $search_method = 'traditional';
        
        // Process add to cart
        if (isset($_POST['add_to_cart'])) {
            $product = new Product(
                0,
                $_POST['product_name'],
                $_POST['product_price'],
                $_POST['product_image'],
                $_POST['product_quantity']
            );
            
            $message[] = $this->cartManager->addToCart($product);
        }
        
        // Process search
        if (isset($_POST['submit']) && !empty($_POST['search'])) {
            $search_term = $_POST['search'];
            $search_performed = true;
            
            // Use custom search algorithm
            $all_products = $this->searchManager->getAllProducts();
            $search_results = $this->searchManager->customSearch($search_term, $all_products);
            
            // If custom search returns few results, fall back to traditional search
            if (count($search_results) < 3) {
                $traditional_results = $this->searchManager->getTraditionalSearchResults($search_term);
                if (count($traditional_results) > count($search_results)) {
                    $search_results = $traditional_results;
                    $search_method = 'traditional';
                }
            } else {
                $search_method = 'custom';
            }
        }
        
        return [
            'message' => $message,
            'search_results' => $search_results,
            'search_performed' => $search_performed,
            'search_method' => $search_method
        ];
    }
}

// Initialize objects
$user_id = isset($_SESSION['id']) ? $_SESSION['id'] : 0;
$searchManager = new SearchManager($conn);
$cartManager = new CartManager($conn, $user_id);
$controller = new SearchController($searchManager, $cartManager);

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
   <title>Advanced Product Search</title>
   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
   <link rel="stylesheet" href="css/style.css">
   
   <style>
        :root {
            --portrait-width: 280px;
            --portrait-height: 380px;
            --image-height: 200px;
        }
        
        .search-info {
            text-align: center;
            margin: 20px 0;
            color: #666;
            font-style: italic;
            font-size: 1.6rem;
            width: 100%;
        }
        
        .match-score {
            background: #ff7eb9;
            color: white;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 1.2rem;
            margin-left: 10px;
        }
        
        .message {
            position: fixed;
            top: 20px;
            right: 20px;
            background: #7fc96b;
            color: white;
            padding: 15px 20px;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            z-index: 1000;
            animation: slideIn 0.3s ease;
        }
        
        @keyframes slideIn {
            from { transform: translateX(100px); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        
        .products .box-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(var(--portrait-width), 1fr));
            gap: 2rem;
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
            justify-content: center;
        }
        
        .products .box {
            background: #fff;
            box-shadow: 0 0.5rem 1.5rem rgba(0,0,0,0.1);
            border-radius: 1rem;
            border: 0.1rem solid rgba(0,0,0,0.1);
            padding: 1.5rem;
            text-align: center;
            position: relative;
            overflow: hidden;
            height: var(--portrait-height);
            display: flex;
            flex-direction: column;
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .products .box:hover {
            transform: translateY(-5px);
            box-shadow: 0 1rem 2rem rgba(0,0,0,0.15);
        }
        
        .products .box .image-container {
            height: var(--image-height);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1.5rem;
            overflow: hidden;
            border-radius: 0.8rem;
            background: #f9f9f9;
            padding: 1rem;
        }
        
        .products .box .image {
            max-height: 100%;
            max-width: 100%;
            width: auto;
            height: auto;
            object-fit: contain;
        }
        
        .products .box .name {
            font-size: 1.6rem;
            color: #333;
            margin: 0.5rem 0;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-wrap: wrap;
            min-height: 4rem;
            line-height: 1.4;
        }
        
        .products .box .price {
            font-size: 2rem;
            color: var(--pink);
            margin: 0.5rem 0;
            font-weight: bold;
        }
        
        .products .box .qty {
            width: 100%;
            padding: 1rem;
            font-size: 1.6rem;
            color: #333;
            border: 0.1rem solid rgba(0,0,0,0.1);
            border-radius: 0.5rem;
            margin: 1rem 0;
            text-align: center;
        }
        
        .products .box .btn {
            display: block;
            width: 100%;
            cursor: pointer;
            margin-top: auto;
            padding: 1rem;
            font-size: 1.6rem;
            background: var(--pink);
            color: #fff;
            border: none;
            border-radius: 0.5rem;
            transition: background 0.3s;
        }
        
        .products .box .btn:hover {
            background: #e0528f;
        }
        
        .empty {
            text-align: center;
            color: #6c757d;
            font-size: 2rem;
            grid-column: 1 / -1;
            padding: 40px 0;
        }
        
        .search-form {
            max-width: 600px;
            margin: 0 auto;
            padding: 2rem;
        }
        
        .search-form form {
            display: flex;
            gap: 1rem;
        }
        
        .search-form .box {
            flex: 1;
            padding: 1.2rem 1.4rem;
            font-size: 1.8rem;
            color: #333;
            border: 0.1rem solid rgba(0,0,0,0.1);
            border-radius: 0.5rem;
        }
        
        .search-form .btn {
            padding: 1.2rem 3rem;
            font-size: 1.8rem;
            background: var(--pink);
            color: #fff;
            border: none;
            border-radius: 0.5rem;
            cursor: pointer;
        }
        
        @media (max-width: 768px) {
            :root {
                --portrait-width: 240px;
                --portrait-height: 360px;
                --image-height: 180px;
            }
            
            .search-form form {
                flex-direction: column;
            }
            
            .products .box-container {
                grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
                padding: 1.5rem;
                gap: 1.5rem;
            }
            
            .products .box {
                padding: 1.2rem;
            }
            
            .products .box .name {
                font-size: 1.4rem;
            }
            
            .products .box .price {
                font-size: 1.8rem;
            }
        }
        
        @media (max-width: 576px) {
            .products .box-container {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 400px) {
            .products .box-container {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
   
<?php include 'header.php'; ?>

<div class="heading">
   <h3>Advanced Product Search</h3>
   <p> <a href="index.php">home</a> / search </p>
</div>

<?php if (!empty($message)): ?>
    <?php foreach ($message as $msg): ?>
        <div class="message"><?php echo htmlspecialchars($msg); ?></div>
    <?php endforeach; ?>
    <script>
        setTimeout(() => {
            document.querySelectorAll('.message').forEach(msg => msg.remove());
        }, 3000);
    </script>
<?php endif; ?>

<section class="search-form">
   <form action="" method="post">
      <input type="text" name="search" placeholder="search products..." class="box" 
             value="<?php echo isset($_POST['search']) ? htmlspecialchars($_POST['search']) : ''; ?>">
      <input type="submit" name="submit" value="search" class="btn">
   </form>
</section>

<section class="products" style="padding-top: 0;">
   <div class="box-container">
   <?php
   if ($search_performed) {
       if (!empty($search_results)) {
           echo '<div class="search-info">';
           echo '</div>';
           
           foreach ($search_results as $product) {
   ?>
   <form action="" method="post" class="box">
      <div class="image-container">
          <img src="uploaded_img/<?php echo htmlspecialchars($product->image); ?>" alt="<?php echo htmlspecialchars($product->name); ?>" class="image">
      </div>
      <div class="name">
          <?php echo htmlspecialchars($product->name); ?>
          <?php if ($search_method === 'custom' && $product->match_score > 0): ?>
              <span class="match-score"><?php echo round($product->match_score); ?>% match</span>
          <?php endif; ?>
      </div>
      <div class="price">Rs. <?php echo number_format($product->price, 2); ?>/-</div>
      <input type="number" class="qty" name="product_quantity" min="1" value="1">
      <input type="hidden" name="product_name" value="<?php echo htmlspecialchars($product->name); ?>">
      <input type="hidden" name="product_price" value="<?php echo htmlspecialchars($product->price); ?>">
      <input type="hidden" name="product_image" value="<?php echo htmlspecialchars($product->image); ?>">
      <input type="submit" class="btn" value="add to cart" name="add_to_cart">
   </form>
   <?php
           }
       } else {
           echo '<p class="empty">No products found matching your search!</p>';
           echo '<div class="search-info">Try different keywords or check spelling</div>';
       }
   } else {
       echo '<p class="empty">Enter a search term to find products!</p>';
   }
   ?>
   </div>
</section>

<?php include 'footer.php'; ?>

<script src="js/script.js"></script>

</body>
</html>