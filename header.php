<?php
// Include config at the top of header.php
include 'config.php';

// Rest of your header.php code remains the same
if(isset($message)){
   foreach($message as $message){
      echo '
      <div class="message">
         <span>'.$message.'</span>
         <i class="fas fa-times" onclick="this.parentElement.remove();"></i>
      </div>
      ';
   }
}
?>

<header class="header">

   <div class="header-1">
      <div class="flex">
         <div class="share">
            <a href="#" class="fab fa-facebook-f"></a>
            <a href="#" class="fab fa-twitter"></a>
            <a href="#" class="fab fa-instagram"></a>
            <a href="#" class="fab fa-linkedin"></a>
         </div>
         <p> new <a href="login.php">login</a> | <a href="register.php">register</a> </p>
      </div>
   </div>

   <div class="header-2">
      <div class="flex">
         <a href="index.php" class="logo">BloomNest</a>

         <nav class="navbar">
            <a href="index.php">Home</a>
            <a href="about.php">About</a>
            <a href="shop.php">Shop</a>
            <a href="contact.php">Contact</a>
            <?php if (isset($_SESSION['id'])): ?>
               <a href="orders.php">Order</a>
               <a href="ordershistory.php">Order history</a>
               <a href="favorite.php">Favorites</a>
            <?php endif; ?>
         </nav>

         <div class="icons">
            <div id="menu-btn" class="fas fa-bars"></div>
            <a href="search_page.php" class="fas fa-search"></a>
            <?php
            if (isset($_SESSION['id'])) {
               echo '<a href="logout.php" id="log-out-btn" class="fa-solid fa-arrow-right-from-bracket" title="Log Out"></a>';
            } else {
               echo '<a href="login.php" id="log-in-btn" class="fa-solid fa-arrow-right-to-bracket" title="Log In"></a>';
            }
            ?>
            
            <?php
            $cart_rows_number = 0;
            if (isset($_SESSION['id'])) {
               $user_id = $_SESSION['id'];
               // Changed to object-oriented syntax
               $select_cart_number = $conn->query("SELECT * FROM `cart` WHERE user_id = '$user_id'") or die('query failed');
               $cart_rows_number = $select_cart_number->num_rows;
            } 
            ?>
            
            <?php if (isset($_SESSION['id'])): ?>
               <a href="cart.php"> 
               <i class="fas fa-shopping-cart"></i> 
               <span>(<?php echo $cart_rows_number; ?>)</span> 
               </a>
            <?php endif; ?>
         </div>
         
         <?php if (isset($_SESSION['id'])): ?>
            <div class="user-box">
               <p>username : <span><?php echo $_SESSION['username']; ?></span></p>
               <a href="logout.php" class="delete-btn">logout</a>
            </div>
         <?php endif; ?>
      </div>
   </div>

</header>