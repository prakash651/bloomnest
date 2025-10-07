<?php
include 'config.php';
session_start();

$user_id = $_SESSION['id'];

if (!isset($user_id)) {
    header('location:login.php');
}

// Function to update stock from products string (for reorders)
function updateStockFromProductsString($products_string, $conn) {
    // Parse products string like "Rose (2), Lily (3), Tulip (1)"
    $products = explode(', ', $products_string);
    
    foreach ($products as $product) {
        // Extract product name and quantity using regex
        if (preg_match('/(.+)\s+\((\d+)\)/', $product, $matches)) {
            $product_name = trim($matches[1]);
            $quantity = intval($matches[2]);
            
            // Update product stock
            mysqli_query($conn, "UPDATE `products` SET stocks = stocks - $quantity WHERE name = '$product_name'");
        }
    }
}

// Check if the order data is provided (reordering case)
if (isset($_GET['order_id']) && isset($_GET['products']) && isset($_GET['total_price'])) {
    $order_id = $_GET['order_id'];
    $products = $_GET['products'];
    $total_price = $_GET['total_price'];
    $user_name = $_GET['name'];    // Pre-fill name from the order
    $user_email = $_GET['email'];  // Pre-fill email from the order
    $user_address = $_GET['address'];  // Pre-fill address from the order
    $user_phone = $_GET['phone'];
    
    // Split address into city and district
    $address_parts = explode(', ', $user_address);
    $user_city = trim($address_parts[0]);
    $user_district = trim($address_parts[1] ?? '');
} else {
    // Fetch user details for pre-filling the form
    $user_details_query = mysqli_query($conn, "SELECT * FROM `user_details` WHERE id = '$user_id'") or die('query failed');
    if (mysqli_num_rows($user_details_query) > 0) {
        $user_details = mysqli_fetch_assoc($user_details_query);
        $user_name = $user_details['name'];
        $user_email = $user_details['email'];
        $user_address = $user_details['address'];
        $user_phone = $user_details['number'];
        
        // Split address into city and district
        $address_parts = explode(', ', $user_address);
        $user_city = trim($address_parts[0]);
        $user_district = trim($address_parts[1] ?? '');
    } else {
        $user_name = '';
        $user_email = '';
        $user_address = '';
        $user_phone = '';
        $user_city = '';
        $user_district = '';
    }
    $products = ''; // If no reorder, leave products empty
    $total_price = 0;
}

if (isset($_POST['order_btn'])) {
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $city = mysqli_real_escape_string($conn, $_POST['city']);
    $district = mysqli_real_escape_string($conn, $_POST['district']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
    $payment_method = mysqli_real_escape_string($conn, $_POST['method']);
    $selected_items = mysqli_real_escape_string($conn, $_POST['selected_items']);
    $total_price = mysqli_real_escape_string($conn, $_POST['total_price']);
    
    // Concatenate city and district to form the address
    $address = $city . ', ' . $district;
    
    // Insert the order into the orders table
    $place_order = mysqli_query($conn, "INSERT INTO `orders`(user_id, placed_on, name, number, email, address, method, total_products, total_price, payment_status) VALUES('$user_id', NOW(), '$name', '$phone', '$email', '$address', '$payment_method', '$selected_items', '$total_price', 'Pending')") or die('query failed');
    
    if ($place_order) {
        $order_id = mysqli_insert_id($conn); // Get the inserted order ID
        
        if ($payment_method == "esewa") {
            // For reorders, we don't have actual cart items to clear, so we'll handle it differently
            // Store minimal order info in session - index.php will handle stock updates based on order data
            $_SESSION['pending_order'] = [
                'order_id' => $order_id,
                'selected_items' => [], // Empty array since we don't have cart items for reorders
                'user_id' => $user_id,
                'is_reorder' => true, // Flag to indicate this is a reorder
                'products_data' => $selected_items // Store the product string for reference
            ];
            header('location:esewa.php?amount=' . $total_price);
            exit();
        } else {
            // For cash on delivery with reorders, we need to update stock but can't clear cart (no cart items)
            // Parse the products string to get product names and quantities for stock update
            updateStockFromProductsString($selected_items, $conn); // FIXED: Removed $this->
            
            // Update payment status to completed for cash on delivery
            mysqli_query($conn, "UPDATE `orders` SET payment_status = 'Completed' WHERE id = '$order_id'");
            
            // Redirect or show success message
            echo '<script>alert("Your order has been placed successfully!");</script>';
            header('location:orders.php'); // Redirect to orders page after placing the order
            exit();
        }
    } else {
        echo '<script>alert("Failed to place the order.");</script>';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
   <meta charset="UTF-8">
   <meta http-equiv="X-UA-Compatible" content="IE=edge">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>Review Checkout</title>
   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
   <link rel="stylesheet" href="css/style.css">
</head>
<body>

<?php include 'header.php'; ?>

<div class="heading">
   <h3>Review Your Order</h3>
   <p><a href="index.php">Home</a> / Review Checkout</p>
   <h1>Your Order Summary</h1>
   <div class="order-summary">
      <p>Products: <strong><?php echo htmlspecialchars($products); ?></strong></p>
      <p>Total Price: <strong>Rs.<?php echo htmlspecialchars($total_price); ?>/-</strong></p>
   </div>
</div>

<section class="checkout">
   <!-- Checkout form -->
   <form action="" method="post" onsubmit="return validateForm()">
      <h3>Place Your Order</h3>
      <div class="flex">
         <div class="inputBox">
            <span>Your Name :</span>
            <input type="text" name="name" id="name" required value="<?php echo htmlspecialchars($user_name); ?>" placeholder="Enter your Name">
         </div>
         <div class="inputBox">
            <span>Your E-mail :</span>
            <input type="email" name="email" id="email" required value="<?php echo htmlspecialchars($user_email); ?>" placeholder="Enter your email">
         </div>
         <div class="inputBox">
            <span>Select District :</span>
            <select name="district" id="district" onchange="updateCities()" required>
               <option value="">Select District</option>
               <option value="Bhaktapur" <?php echo ($user_district === 'Bhaktapur') ? 'selected' : ''; ?>>Bhaktapur</option>
               <option value="Chitwan" <?php echo ($user_district === 'Chitwan') ? 'selected' : ''; ?>>Chitwan</option>
               <option value="Dhading" <?php echo ($user_district === 'Dhading') ? 'selected' : ''; ?>>Dhading</option>
               <option value="Dolakha" <?php echo ($user_district === 'Dolakha') ? 'selected' : ''; ?>>Dolakha</option>
               <option value="Kathmandu" <?php echo ($user_district === 'Kathmandu') ? 'selected' : ''; ?>>Kathmandu</option>
               <option value="Lalitpur" <?php echo ($user_district === 'Lalitpur') ? 'selected' : ''; ?>>Lalitpur</option>
               <option value="Makwanpur" <?php echo ($user_district === 'Makwanpur') ? 'selected' : ''; ?>>Makwanpur</option>
               <option value="Nuwakot" <?php echo ($user_district === 'Nuwakot') ? 'selected' : ''; ?>>Nuwakot</option>
               <option value="Rasuwa" <?php echo ($user_district === 'Rasuwa') ? 'selected' : ''; ?>>Rasuwa</option>
               <option value="Sindhuli" <?php echo ($user_district === 'Sindhuli') ? 'selected' : ''; ?>>Sindhuli</option>
               <option value="Sindhupalchok" <?php echo ($user_district === 'Sindhupalchok') ? 'selected' : ''; ?>>Sindhupalchok</option>
            </select>
         </div>
         <div class="inputBox">
            <span>Select City :</span>
            <select name="city" id="city" required>
               <option value="">Select City</option>
            </select>
         </div>
         <div class="inputBox">
            <span>Your Phone Number :</span>
            <input type="text" name="phone" id="number" required value="<?php echo htmlspecialchars($user_phone); ?>" placeholder="Enter your Phone Number">
         </div>
         <div class="inputBox">
            <span>Payment Method :</span>
            <select name="method" id="method">
               <option value="cash on delivery">Cash on Delivery</option>
               <option value="esewa">e-Sewa</option>
            </select>
         </div>
      </div>

      <!-- Hidden fields to pass order details -->
      <input type="hidden" name="selected_items" value="<?php echo htmlspecialchars($products); ?>">
      <input type="hidden" name="total_price" value="<?php echo htmlspecialchars($total_price); ?>">
      <input type="submit" value="Order Now" class="btn" name="order_btn">
   </form>

   <script>
       const cities = {
           Bhaktapur: ["Bhaktapur", "Suryabinayak", "Changunarayan", "Nagadesh", "Sallaghari"],
           Chitwan: ["Bharatpur", "Khairahani", "Ratnanagar", "Madi", "Meghauli", "Rapti"],
           Dhading: ["Dhading Besi", "Gajuri", "Khadichaur", "Thakre", "Arukharka"],
           Dolakha: ["Charikot", "Bhimeshwor", "Dolakha", "Kshamawati", "Suri"],
           Kathmandu: ["Baneshwor", "Maitighar", "Teku", "Kalimati", "Nagarjun", "Swayambhu", "Taudaha"],
           Lalitpur: ["Lalitpur", "Lagankhel", "Pulchowk", "Jawalkhel", "Sanepa"],
           Makwanpur: ["Hetauda", "Makwanpur Gadhi", "Bharta", "Thaha", "Manahari"],
           Nuwakot: ["Bidur", "Nuwakot", "Tadi", "Kharanitar", "Rudi"],
           Rasuwa: ["Dhunche", "Syafru Besi", "Bhorle", "Ramche", "Bhotechaur"],
           Sindhuli: ["Sindhuli", "Kamalamai", "Kuntabesi", "Duwakot", "Gajuri"],
           Sindhupalchok: ["Chautara", "Barabise", "Melamchi", "Dolakha", "Panchpokhari"]
       };

       function updateCities() {
           const districtSelect = document.getElementById("district");
           const citySelect = document.getElementById("city");
           const selectedDistrict = districtSelect.value;

           // Clear previous options
           citySelect.innerHTML = '<option value="">Select City</option>';

           if (selectedDistrict) {
               // Populate cities based on selected district
               cities[selectedDistrict].forEach(city => {
                   const option = new Option(city, city);
                   citySelect.add(option);
               });

               // Select the user's city
               const userCity = "<?php echo htmlspecialchars($user_city); ?>";
               if (userCity) {
                   citySelect.value = userCity;
               }
           }
       }

       // Call updateCities on page load to pre-select city
       window.onload = function() {
           const userDistrict = "<?php echo htmlspecialchars($user_district); ?>";
           if (userDistrict) {
               document.getElementById('district').value = userDistrict;
               updateCities();
           }
       };

       function validateForm() {
           const name = document.getElementById('name').value.trim();
           const email = document.getElementById('email').value.trim();
           const phone = document.getElementById('number').value.trim();
           const district = document.getElementById('district').value;
           const city = document.getElementById('city').value;

           const nameRegex = /^[a-zA-Z\s]+$/;
           const numberRegex = /^(97|98)[0-9]{8}$/;
           const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

           if (!name.match(nameRegex)) {
               alert("Please enter a valid name.");
               return false;
           }

           if (!email.match(emailRegex)) {
               alert("Please enter a valid email address.");
               return false;
           }

           if (phone === "") {
               alert("Phone number is required");
               return false;
           }

           if (!phone.match(numberRegex)) {
               alert("Please enter a valid phone number (10 digits) starting with 97 or 98.");
               return false;
           }

           if (district === "") {
               alert("Please select a district");
               return false;
           }

           if (city === "") {
               alert("Please select a city");
               return false;
           }

           return true;
       }
   </script>
</section>

<?php include 'footer.php'; ?>

</body>
</html>