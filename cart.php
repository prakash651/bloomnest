<?php
include 'config.php';
error_reporting(0);
session_start();

class CartManager {
    private $conn;
    private $user_id;
    private $messages = [];
    private $warnings = [];

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

    public function updateCartItem($cart_id, $cart_quantity) {
        // Get the product name from the cart
        $cart_item = $this->getCartItem($cart_id);
        if (!$cart_item) return false;

        $product_name = $cart_item['name'];
        
        // Get the available stock
        $available_stock = $this->getProductStock($product_name);
        
        // Check if the requested quantity exceeds available stock
        if ($cart_quantity > $available_stock) {
            $cart_quantity = $available_stock;
            $this->messages[] = "Updated quantity for $product_name to available stock ($available_stock).";
            $this->warnings[] = "Warning: $product_name quantity exceeds available stock. Adjusted to $available_stock.";
        }

        return $this->updateCartQuantity($cart_id, $cart_quantity);
    }

    private function getCartItem($cart_id) {
        $query = "SELECT name FROM `cart` WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $cart_id);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }

    private function getProductStock($product_name) {
        $query = "SELECT stocks FROM `products` WHERE name = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("s", $product_name);
        $stmt->execute();
        $result = $stmt->get_result();
        $product_info = $result->fetch_assoc();
        return $product_info['stocks'] ?? 0;
    }

    private function updateCartQuantity($cart_id, $quantity) {
        $query = "UPDATE `cart` SET quantity = ? WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("ii", $quantity, $cart_id);
        return $stmt->execute();
    }

    public function deleteCartItem($cart_id) {
        $query = "DELETE FROM `cart` WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $cart_id);
        return $stmt->execute();
    }

    public function deleteAllCartItems() {
        $query = "DELETE FROM `cart` WHERE user_id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $this->user_id);
        return $stmt->execute();
    }

    public function getCartItems() {
        $cart_items = [];
        $grand_total = 0;
        
        if (!$this->user_id) return ['items' => $cart_items, 'grand_total' => $grand_total];
        
        $query = "SELECT * FROM `cart` WHERE user_id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $this->user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($fetch_cart = $result->fetch_assoc()) {
            $product_name = $fetch_cart['name'];
            $available_stock = $this->getProductStock($product_name);
            
            // Display warning if cart quantity exceeds available stock
            if ($fetch_cart['quantity'] > $available_stock) {
                $this->warnings[] = "Warning: $product_name quantity exceeds available stock ($available_stock). Please adjust the quantity.";
            }
            
            $sub_total = $fetch_cart['quantity'] * $fetch_cart['price'];
            $grand_total += $sub_total;
            
            $cart_items[] = [
                'id' => $fetch_cart['id'],
                'name' => $fetch_cart['name'],
                'price' => $fetch_cart['price'],
                'quantity' => $fetch_cart['quantity'],
                'image' => $fetch_cart['image'],
                'sub_total' => $sub_total,
                'available_stock' => $available_stock
            ];
        }
        
        return ['items' => $cart_items, 'grand_total' => $grand_total];
    }

    public function getMessages() {
        return $this->messages;
    }

    public function getWarnings() {
        return $this->warnings;
    }

    public function hasWarnings() {
        return !empty($this->warnings);
    }
}

// Initialize CartManager
$cartManager = new CartManager($conn);

// Check if user is logged in
if (!$cartManager->isUserLoggedIn()) {
    $cartManager->redirectToLogin();
}

// Handle cart updates
if (isset($_POST['update_cart'])) {
    $cart_id = $_POST['cart_id'];
    $cart_quantity = $_POST['cart_quantity'];
    $cartManager->updateCartItem($cart_id, $cart_quantity);
}

// Handle item deletion
if (isset($_GET['delete'])) {
    $delete_id = $_GET['delete'];
    $cartManager->deleteCartItem($delete_id);
    header('location:cart.php');
    exit();
}

// Handle delete all items
if (isset($_GET['delete_all'])) {
    $cartManager->deleteAllCartItems();
    header('location:cart.php');
    exit();
}

// Get cart items and total
$cart_data = $cartManager->getCartItems();
$cart_items = $cart_data['items'];
$grand_total = $cart_data['grand_total'];

// Get messages and warnings
$messages = $cartManager->getMessages();
$warnings = $cartManager->getWarnings();
$has_warnings = $cartManager->hasWarnings();

?>

<!DOCTYPE html>
<html lang="en">

<head>
   <meta charset="UTF-8">
   <meta http-equiv="X-UA-Compatible" content="IE=edge">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>cart</title>
   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
   <link rel="stylesheet" href="css/style.css">
   <style>
      .stock {
         font-size: 15px;
         color: #666;
         margin: 5px 0;
      }
      
      .warning-message {
         color: #e74c3c;
         background-color: #fde8e6;
         padding: 10px;
         border-radius: 5px;
         margin: 10px 0;
         border-left: 4px solid #e74c3c;
      }
      
      .disabled {
         opacity: 0.6;
         pointer-events: none;
      }
      
      /* Fix for product image sizing */
      .shopping-cart .box-container .box img {
         max-width: 80%;
         height: 220px;
         object-fit: contain;
         display: block;
         margin: 0 auto;
      }
      
      .shopping-cart .box-container .box {
         text-align: center;
         padding: 20px;
         position: relative;
         overflow: hidden; /* Prevent any overflow */
      }
   </style>
</head>

<body>

   <?php include 'header.php'; ?>

   <div class="heading">
      <h3>shopping cart</h3>
      <p> <a href="index.php">home</a> / cart </p>
   </div>

   <section class="shopping-cart">
      <h1 class="title">Products Added</h1>

      <div class="box-container">
         <?php if (!empty($cart_items)): ?>
            <?php foreach ($cart_items as $item): ?>
               <div class="box">
                  <a href="cart.php?delete=<?php echo $item['id']; ?>" class="fas fa-times"
                     onclick="return confirm('delete this from cart?');"></a>
                  <img src="./uploaded_img/<?php echo $item['image']; ?>" alt="<?php echo $item['name']; ?>">
                  <div class="name"><?php echo $item['name']; ?></div>
                  <div class="price">Rs.<?php echo $item['price']; ?>/-</div>
                  <div class="stock">Stock: <?php echo $item['available_stock']; ?></div>
                  <form action="" method="post">
                     <input type="hidden" name="cart_id" value="<?php echo $item['id']; ?>">
                     <input type="number" min="1" name="cart_quantity" value="<?php echo $item['quantity']; ?>" 
                            max="<?php echo $item['available_stock']; ?>">
                     <input type="submit" name="update_cart" value="update" class="option-btn">
                  </form>
                  <div class="sub-total"> sub total : <span>Rs.<?php echo $item['sub_total']; ?>/-</span> </div>
               </div>
            <?php endforeach; ?>
         <?php else: ?>
            <p class="empty">your cart is empty</p>
         <?php endif; ?>
      </div>

      <!-- Display messages if any -->
      <?php if (!empty($messages)): ?>
         <div class="message">
            <?php foreach ($messages as $msg): ?>
               <p><?php echo $msg; ?></p>
            <?php endforeach; ?>
         </div>
      <?php endif; ?>

      <!-- Display warnings if any -->
      <?php if (!empty($warnings)): ?>
         <div class="warning-message">
            <?php foreach ($warnings as $warn): ?>
               <p><?php echo $warn; ?></p>
            <?php endforeach; ?>
         </div>
      <?php endif; ?>

      <?php if (!empty($cart_items)): ?>
         <div style="margin-top: 2rem; text-align:center;">
            <a href="cart.php?delete_all" class="delete-btn"
               onclick="return confirm('delete all from cart?');">delete all</a>
         </div>

         <div class="cart-total">
            <p>Grand Total : <span>Rs.<?php echo $grand_total; ?>/-</span></p>
            <div class="flex">
               <a href="shop.php" class="option-btn">Continue Shopping</a>
               <a href="checkout.php" class="btn <?php echo $has_warnings ? 'disabled' : ''; ?>">
                  Proceed to Checkout
               </a>
            </div>
         </div>
      <?php endif; ?>
   </section>

   <?php include 'footer.php'; ?>

   <script src="js/script.js"></script>

</body>

</html>