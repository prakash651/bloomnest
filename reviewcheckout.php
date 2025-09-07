<?php
include 'config.php';
session_start();

class CheckoutProcessor {
    private $conn;
    private $user_id;
    private $user_data = [];
    private $order_data = [];
    private $errors = [];
    
    public function __construct($db_connection) {
        $this->conn = $db_connection;
        $this->user_id = $_SESSION['id'] ?? null;
        
        if (!$this->user_id) {
            header('location:login.php');
            exit();
        }
    }
    
    public function loadUserData() {
        $user_details_query = mysqli_query($this->conn, "SELECT * FROM `user_details` WHERE id = '{$this->user_id}'") or die('query failed');
        
        if (mysqli_num_rows($user_details_query) > 0) {
            $this->user_data = mysqli_fetch_assoc($user_details_query);
            
            // Split address into city and district
            $address_parts = explode(', ', $this->user_data['address'] ?? '');
            $this->user_data['city'] = trim($address_parts[0] ?? '');
            $this->user_data['district'] = trim($address_parts[1] ?? '');
        }
    }
    
    public function loadOrderData() {
        if (isset($_GET['order_id']) && isset($_GET['products']) && isset($_GET['total_price'])) {
            $this->order_data = [
                'order_id' => $_GET['order_id'],
                'products' => $_GET['products'],
                'total_price' => $_GET['total_price'],
                'name' => $_GET['name'] ?? $this->user_data['name'] ?? '',
                'email' => $_GET['email'] ?? $this->user_data['email'] ?? '',
                'address' => $_GET['address'] ?? $this->user_data['address'] ?? '',
                'phone' => $_GET['phone'] ?? $this->user_data['number'] ?? ''
            ];
            
            // Split address into city and district
            $address_parts = explode(', ', $this->order_data['address']);
            $this->order_data['city'] = trim($address_parts[0] ?? '');
            $this->order_data['district'] = trim($address_parts[1] ?? '');
        }
    }
    
    public function processOrder() {
        if (isset($_POST['order_btn'])) {
            $name = mysqli_real_escape_string($this->conn, $_POST['name']);
            $email = mysqli_real_escape_string($this->conn, $_POST['email']);
            $city = mysqli_real_escape_string($this->conn, $_POST['city']);
            $district = mysqli_real_escape_string($this->conn, $_POST['district']);
            $phone = mysqli_real_escape_string($this->conn, $_POST['phone']);
            $payment_method = mysqli_real_escape_string($this->conn, $_POST['method']);
            $selected_items = mysqli_real_escape_string($this->conn, $_POST['selected_items']);
            $total_price = mysqli_real_escape_string($this->conn, $_POST['total_price']);
            
            // Validate inputs
            if (!$this->validateInputs($name, $email, $phone, $city, $district)) {
                return false;
            }
            
            // Concatenate city and district to form the address
            $address = $city . ', ' . $district;
            
            // Insert the order into the orders table
            $place_order = mysqli_query($this->conn, "INSERT INTO `orders`(user_id, placed_on, name, number, email, address, method, total_products, total_price, payment_status) VALUES('{$this->user_id}', NOW(), '$name', '$phone', '$email', '$address', '$payment_method', '$selected_items', '$total_price', 'Pending')") or die('query failed');
            
            if ($place_order) {
                if ($payment_method == "esewa") {
                    header('location:esewa.php?amount=' . $total_price);
                    exit();
                } else {
                    // Redirect or show success message
                    echo '<script>alert("Your order has been placed successfully!");</script>';
                    header('location:orders.php'); // Redirect to orders page after placing the order
                    exit();
                }
            } else {
                $this->errors[] = "Failed to place the order.";
                return false;
            }
        }
        return true;
    }
    
    private function validateInputs($name, $email, $phone, $city, $district) {
        $nameRegex = '/^[a-zA-Z\s]+$/';
        $numberRegex = '/^(97|98)[0-9]{8}$/';
        $emailRegex = '/^[^\s@]+@[^\s@]+\.[^\s@]+$/';
        
        if (!preg_match($nameRegex, $name)) {
            $this->errors[] = "Please enter a valid name.";
        }
        
        if (!preg_match($emailRegex, $email)) {
            $this->errors[] = "Please enter a valid email address.";
        }
        
        if (!preg_match($numberRegex, $phone)) {
            $this->errors[] = "Please enter a valid phone number (10 digits) starting with 97 or 98.";
        }
        
        if (empty($city)) {
            $this->errors[] = "Please select a city.";
        }
        
        if (empty($district)) {
            $this->errors[] = "Please select a district.";
        }
        
        return empty($this->errors);
    }
    
    public function displayErrors() {
        if (!empty($this->errors)) {
            foreach ($this->errors as $error) {
                echo '<script>alert("' . $error . '");</script>';
            }
        }
    }
    
    public function getUserData() {
        return $this->user_data;
    }
    
    public function getOrderData() {
        return $this->order_data;
    }
    
    public function getPrefilledValue($field) {
        if (!empty($this->order_data)) {
            return $this->order_data[$field] ?? '';
        } else {
            return $this->user_data[$field] ?? '';
        }
    }
    
    public function getProducts() {
        return $this->order_data['products'] ?? '';
    }
    
    public function getTotalPrice() {
        return $this->order_data['total_price'] ?? 0;
    }
}

// Initialize and process checkout
$checkout = new CheckoutProcessor($conn);
$checkout->loadUserData();
$checkout->loadOrderData();
$checkout->processOrder();
$checkout->displayErrors();

// Get data for the view
$user_name = $checkout->getPrefilledValue('name');
$user_email = $checkout->getPrefilledValue('email');
$user_phone = $checkout->getPrefilledValue('number');
$user_city = $checkout->getPrefilledValue('city');
$user_district = $checkout->getPrefilledValue('district');
$products = $checkout->getProducts();
$total_price = $checkout->getTotalPrice();
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
   <style>
       .order-summary {
           background-color: #f9f9f9;
           padding: 20px;
           border-radius: 5px;
           margin: 20px 0;
           text-align: center;
       }
       
       .order-summary p {
           margin: 10px 0;
           font-size: 18px;
       }
       
       .checkout {
           padding: 20px;
       }
       
       .checkout .flex {
           display: flex;
           flex-wrap: wrap;
           gap: 20px;
       }
       
       .checkout .inputBox {
           flex: 1 1 300px;
       }
       
       .checkout .inputBox span {
           display: block;
           margin-bottom: 5px;
           font-weight: bold;
       }
       
       .checkout .inputBox input,
       .checkout .inputBox select {
           width: 100%;
           padding: 10px;
           border: 1px solid #ddd;
           border-radius: 5px;
       }
       
       .btn {
           display: block;
           width: 100%;
           padding: 12px;
           background-color: #4CAF50;
           color: white;
           border: none;
           border-radius: 5px;
           cursor: pointer;
           font-size: 18px;
           margin-top: 20px;
       }
       
       .btn:hover {
           background-color: #45a049;
       }
   </style>
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
                   const option = document.createElement("option");
                   option.value = city;
                   option.textContent = city;
                   citySelect.appendChild(option);
               });

               // Select the user's city
               const userCity = "<?php echo htmlspecialchars($user_city); ?>";
               if (userCity) {
                   const userCityOption = Array.from(citySelect.options).find(option => option.value === userCity);
                   if (userCityOption) {
                       userCityOption.selected = true;
                   }
               }
           }
       }

       // Call updateCities on page load to pre-select city
       window.onload = function() {
           document.getElementById('district').value = "<?php echo htmlspecialchars($user_district); ?>"; // Set selected district
           updateCities(); // Call function to populate cities and set selected city
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