<?php
include 'config.php';
error_reporting(0);
session_start();

class FavoriteManager {
    private $conn;
    private $user_id;
    
    public function __construct($connection) {
        $this->conn = $connection;
        $this->user_id = $_SESSION['id'] ?? null;
    }
    
    public function isUserLoggedIn() {
        return isset($this->user_id);
    }
    
    public function redirectToLogin() {
        header('location:login.php');
        exit();
    }
    
    public function handleDeleteFavorite() {
        if (isset($_GET['delete_favorite'])) {
            $delete_id = $this->sanitizeInput($_GET['delete_favorite']);
            $this->deleteFavorite($delete_id);
            header('location:favorite.php');
            exit();
        }
    }
    
    public function handleAddToCart() {
        if (isset($_POST['add_to_cart'])) {
            if (!$this->isUserLoggedIn()) {
                $this->redirectToLogin();
            }
            
            $product_name = $this->sanitizeInput($_POST['product_name']);
            $product_price = $this->sanitizeInput($_POST['product_price']);
            $product_image = $this->sanitizeInput($_POST['product_image']);
            $product_quantity = $this->sanitizeInput($_POST['product_quantity']);
            
            return $this->addToCart($product_name, $product_price, $product_quantity, $product_image);
        }
        return null;
    }
    
    private function deleteFavorite($product_id) {
        $stmt = $this->conn->prepare("DELETE FROM `favorites` WHERE product_id = ? AND user_id = ?");
        $stmt->bind_param("ii", $product_id, $this->user_id);
        $stmt->execute();
        $stmt->close();
    }
    
    private function addToCart($name, $price, $quantity, $image) {
        // Check if product already in cart
        $stmt = $this->conn->prepare("SELECT * FROM `cart` WHERE name = ? AND user_id = ?");
        $stmt->bind_param("si", $name, $this->user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            return 'already added to cart!';
        } else {
            // Insert into cart
            $insert_stmt = $this->conn->prepare("INSERT INTO `cart`(user_id, name, price, quantity, image) VALUES(?, ?, ?, ?, ?)");
            $insert_stmt->bind_param("issis", $this->user_id, $name, $price, $quantity, $image);
            $insert_stmt->execute();
            $insert_stmt->close();
            $stmt->close();
            return 'product added to cart!';
         }
      }
    
    public function getFavorites() {
        $stmt = $this->conn->prepare("SELECT p.* FROM `favorites` f INNER JOIN `products` p ON f.product_id = p.id WHERE f.user_id = ?");
        $stmt->bind_param("i", $this->user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();
        return $result;
    }
    
    private function sanitizeInput($input) {
        return htmlspecialchars(strip_tags(trim($input)));
    }
}

// Initialize and process requests
$favoriteManager = new FavoriteManager($conn);

// Check if user is logged in
if (!$favoriteManager->isUserLoggedIn()) {
    $favoriteManager->redirectToLogin();
}

// Handle operations
$favoriteManager->handleDeleteFavorite();
$message = $favoriteManager->handleAddToCart();
?>

<!DOCTYPE html>
<html lang="en">

<head>
   <meta charset="UTF-8">
   <meta http-equiv="X-UA-Compatible" content="IE=edge">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>Favorites</title>

   <!-- font awesome cdn link  -->
   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

   <!-- custom css file link  -->
   <link rel="stylesheet" href="css/style.css">
</head>

<body>

   <?php include 'header.php'; ?>

   <div class="heading">
      <h3>Favorites</h3>
      <p> <a href="index.php">home</a> / favorites </p>
   </div>

   <?php if (isset($message)): ?>
      <div class="message">
         <span><?php echo $message; ?></span>
         <i class="fas fa-times" onclick="this.parentElement.remove();"></i>
      </div>
   <?php endif; ?>

   <section class="shopping-cart">
      <h1 class="title">Favorite Products</h1>

      <div class="box-container">
         <?php
         $favorites = $favoriteManager->getFavorites();
         if ($favorites->num_rows > 0) {
            while ($fetch_favorite = $favorites->fetch_assoc()) {
               ?>
               <div class="box">
                  <a href="favorite.php?delete_favorite=<?php echo $fetch_favorite['id']; ?>" class="fas fa-times"
                     onclick="return confirm('Remove this from favorites?');"></a>
                  <img src="./uploaded_img/<?php echo $fetch_favorite['image']; ?>" alt="">
                  <div class="name"><?php echo $fetch_favorite['name']; ?></div>
                  <div class="price">Rs.<?php echo $fetch_favorite['price']; ?>/-</div>
                  <form action="" method="post">
                     <input type="number" min="1" name="product_quantity" value="1" class="qty">
                     <input type="hidden" name="product_name" value="<?php echo $fetch_favorite['name']; ?>">
                     <input type="hidden" name="product_price" value="<?php echo $fetch_favorite['price']; ?>">
                     <input type="hidden" name="product_image" value="<?php echo $fetch_favorite['image']; ?>">
                     <input type="submit" name="add_to_cart" value="Add to Cart" class="option-btn">
                  </form>
               </div>
               <?php
            }
         } else {
            echo '<p class="empty">Your favorites list is empty</p>';
         }
         ?>
      </div>

   </section>

   <?php include 'footer.php'; ?>

   <!-- custom js file link  -->
   <script src="js/script.js"></script>

</body>

</html>