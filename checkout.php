<?php
include 'config.php';
session_start();

class CheckoutManager {
    private $conn;
    private $user_id;
    private $messages = [];
    
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
    
    public function processOrder($postData) {
        $name = mysqli_real_escape_string($this->conn, $postData['name']);
        $number = $postData['number'];
        $email = mysqli_real_escape_string($this->conn, $postData['email']);
        $method = mysqli_real_escape_string($this->conn, $postData['method']);
        $address = mysqli_real_escape_string($this->conn, '  ' . $postData['city'] . ', ' . $postData['district']);
        $placed_on = date('d-M-Y');
        $payment_status = "Pending";
        $grand_total = $postData['grand_total'];
        $selected_items = explode(',', $postData['selected_items']);
        
        // Redirect to eSewa if selected
        if ($method == "esewa") {
            header('location:esewa.php?amount=' . $grand_total);
            exit();
        }
        
        $cart_total = 0;
        $cart_products = [];
        
        if (count($selected_items) > 0) {
            foreach ($selected_items as $item_id) {
                $cart_item = $this->getCartItem($item_id);
                if ($cart_item) {
                    $cart_products[] = $cart_item['name'] . ' (' . $cart_item['quantity'] . ') ';
                    $sub_total = ($cart_item['price'] * $cart_item['quantity']);
                    $cart_total += $sub_total;
                }
            }
        }
        
        $total_products = implode(', ', $cart_products);
        
        if ($cart_total == 0) {
            $this->messages[] = 'No items selected for the order!';
            return false;
        }
        
        // Check if order already exists
        if ($this->orderExists($name, $number, $email, $method, $address, $total_products, $cart_total)) {
            $this->messages[] = 'Order already placed!';
            return false;
        }
        
        // Insert the order
        if ($this->insertOrder($name, $number, $email, $method, $address, $total_products, $cart_total, $placed_on, $payment_status)) {
            // Update stock and remove items from cart
            foreach ($selected_items as $item_id) {
                $cart_item = $this->getCartItem($item_id);
                if ($cart_item) {
                    $this->updateProductStock($cart_item['name'], $cart_item['quantity']);
                    $this->removeFromCart($item_id);
                }
            }
            
            $this->messages[] = 'Order placed successfully!';
            return true;
        }
        
        return false;
    }
    
    private function getCartItem($item_id) {
        $query = "SELECT * FROM `cart` WHERE user_id = ? AND id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("ii", $this->user_id, $item_id);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }
    
    private function orderExists($name, $number, $email, $method, $address, $total_products, $total_price) {
        $query = "SELECT * FROM `orders` WHERE name = ? AND number = ? AND email = ? AND method = ? AND address = ? AND total_products = ? AND total_price = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("sissssd", $name, $number, $email, $method, $address, $total_products, $total_price);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->num_rows > 0;
    }
    
    private function insertOrder($name, $number, $email, $method, $address, $total_products, $total_price, $placed_on, $payment_status) {
        $query = "INSERT INTO `orders`(user_id, name, number, email, method, address, total_products, total_price, placed_on, payment_status) VALUES(?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("issssssdss", $this->user_id, $name, $number, $email, $method, $address, $total_products, $total_price, $placed_on, $payment_status);
        return $stmt->execute();
    }
    
    private function updateProductStock($product_name, $quantity) {
        $query = "UPDATE `products` SET stocks = stocks - ? WHERE name = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("is", $quantity, $product_name);
        return $stmt->execute();
    }
    
    private function removeFromCart($item_id) {
        $query = "DELETE FROM `cart` WHERE user_id = ? AND id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("ii", $this->user_id, $item_id);
        return $stmt->execute();
    }
    
    public function getCartItems() {
        $cart_items = [];
        $grand_total = 0;
        
        if (!$this->user_id) {
            return ['items' => $cart_items, 'grand_total' => $grand_total];
        }
        
        $query = "SELECT *, (price * quantity) as total_price FROM `cart` WHERE user_id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $this->user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($item = $result->fetch_assoc()) {
            $cart_items[] = [
                'id' => $item['id'],
                'name' => $item['name'],
                'price' => $item['price'],
                'quantity' => $item['quantity'],
                'image' => $item['image'],
                'total_price' => $item['total_price']
            ];
            $grand_total += $item['total_price'];
        }
        
        return ['items' => $cart_items, 'grand_total' => $grand_total];
    }
    
    public function getMessages() {
        return $this->messages;
    }
}

// Initialize CheckoutManager
$checkoutManager = new CheckoutManager($conn);

// Check if user is logged in
if (!$checkoutManager->isUserLoggedIn()) {
    $checkoutManager->redirectToLogin();
}

// Handle order submission
if (isset($_POST['order_btn'])) {
    $checkoutManager->processOrder($_POST);
}

// Get cart items
$cart_data = $checkoutManager->getCartItems();
$cart_items = $cart_data['items'];
$grand_total = $cart_data['grand_total'];

// Get messages
$messages = $checkoutManager->getMessages();

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout</title>

    <!-- Font Awesome CDN Link -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <!-- Custom CSS File Link -->
    <link rel="stylesheet" href="css/style.css">

    <style>
        .cart-items-container {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            margin-top: 20px;
        }

        .cart-item {
            background-color: #f7f7f7;
            border: 1px solid #ddd;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            padding: 15px;
            margin: 10px;
            width: 250px;
            text-align: center;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .cart-item input[type="checkbox"] {
            margin-right: 10px;
            transform: scale(1.5);
        }

        .cart-item label {
            font-size: 16px;
            font-weight: bold;
            margin-top: 10px;
            cursor: pointer;
        }

        .cart-item .item-price {
            font-size: 14px;
            color: #555;
            margin-top: 5px;
        }

        .cart-item img {
            max-width: 100px;
            margin-bottom: 10px;
            align-self: center;
        }

        #grand-total {
            font-size: 22px;
            color: #e74c3c;
            font-weight: bold;
            text-align: center;
            margin-top: 20px;
        }

        .checkout-container {
            text-align: center;
            margin-top: 30px;
        }

        .checkout-form {
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        
        .message {
            text-align: center;
            padding: 10px;
            margin: 10px 0;
            border-radius: 5px;
        }
        
        .message p {
            color: #2ecc71;
            font-weight: bold;
        }
        
        .message.error p {
            color: #e74c3c;
        }
    </style>
</head>

<body>

    <?php include 'header.php'; ?>

    <div class="heading">
        <h3>Checkout</h3>
        <p> <a href="home.php">Home</a> / Checkout </p>
    </div>

    <!-- Display messages -->
    <?php if (!empty($messages)): ?>
        <div class="message <?php echo strpos($messages[0], 'successfully') !== false ? '' : 'error'; ?>">
            <?php foreach ($messages as $msg): ?>
                <p><?php echo $msg; ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Display Order Section -->
    <section class="display-order">
        <div class="cart-items-container">
            <?php if (!empty($cart_items)): ?>
                <?php foreach ($cart_items as $item): ?>
                    <div class="cart-item">
                        <label>
                            <input type="checkbox" name="cart_items[]" value="<?php echo $item['id']; ?>" checked onclick="updateTotal()">
                            <?php echo $item['name']; ?>
                            <span>(<?php echo 'Rs.' . $item['price'] . '/-' . ' x ' . $item['quantity']; ?>)</span>
                        </label>
                        <input type="hidden" class="item-price" value="<?php echo $item['total_price']; ?>">
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="empty">Your cart is empty</p>
            <?php endif; ?>
            <div class="grand-total">Grand Total: <span id="grand-total">Rs.<?php echo $grand_total; ?>/-</span></div>
        </div>
    </section>

    <!-- Checkout Section -->
    <section class="checkout">
        <form action="" method="post" onsubmit="return validateForm()">
            <h3>Place Your Order</h3>
            <div class="flex">
                <div class="inputBox">
                    <span>Your Name :</span>
                    <input type="text" name="name" id="name" required placeholder="Enter your Name">
                </div>
                <div class="inputBox">
                    <span>Your Number :</span>
                    <input type="number" name="number" id="number" required placeholder="Enter your Number">
                </div>
                <div class="inputBox">
                    <span>Your E-mail :</span>
                    <input type="email" name="email" id="email" required placeholder="Enter your email">
                </div>
                <div class="inputBox">
                    <span>Payment Method :</span>
                    <select name="method" id="method">
                        <option value="cash on delivery">Cash on Delivery</option>
                        <option value="esewa">e-Sewa</option>
                    </select>
                </div>

                <div class="inputBox">
                    <span>District :</span>
                    <select name="district" id="district" required onchange="updateCities()">
                        <option value="">Select District</option>
                        <option value="Bhaktapur">Bhaktapur</option>
                        <option value="Chitwan">Chitwan</option>
                        <option value="Dhading">Dhading</option>
                        <option value="Dolakha">Dolakha</option>
                        <option value="Kathmandu">Kathmandu</option>
                        <option value="Lalitpur">Lalitpur</option>
                        <option value="Makwanpur">Makwanpur</option>
                        <option value="Nuwakot">Nuwakot</option>
                        <option value="Rasuwa">Rasuwa</option>
                        <option value="Sindhuli">Sindhuli</option>
                        <option value="Sindhupalchok">Sindhupalchok</option>
                    </select>
                </div>
                <div class="inputBox">
                    <span>City :</span>
                    <select name="city" id="city" required>
                        <option value="">Select City</option>
                    </select>
                </div>
            </div>

            <input type="hidden" value="<?= $grand_total ?>" name="grand_total">
            <input type="hidden" name="selected_items" id="selected_items">
            <input type="submit" value="Order Now" class="btn" name="order_btn">
        </form>

        <script>
            function updateTotal() {
                const checkboxes = document.querySelectorAll('input[name="cart_items[]"]');
                let grandTotal = 0;

                checkboxes.forEach(function(checkbox, index) {
                    if (checkbox.checked) {
                        const price = document.querySelectorAll('.item-price')[index].value;
                        grandTotal += parseFloat(price);
                    }
                });

                document.getElementById('grand-total').innerText = 'Rs.' + grandTotal + '/-';
            }

            function validateForm() {
                // Get form inputs
                const name = document.getElementById("name").value;
                const number = document.getElementById("number").value;
                const email = document.getElementById("email").value;
                const city = document.getElementById("city").value;
                const district = document.getElementById("district").value;

                // Regular expressions for validation
                const nameRegex = /^[a-zA-Z\s]+$/;
                const numberRegex = /^(97|98)[0-9]{8}$/;
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

                // Validate name
                if (!name.match(nameRegex)) {
                    alert("Please enter a valid name.");
                    return false;
                }

                // Validate phone number
                if (!number.match(numberRegex)) {
                    alert("Please enter a valid phone number (10 digits) starting with 97 or 98.");
                    return false;
                }

                // Validate email
                if (!email.match(emailRegex)) {
                    alert("Please enter a valid email address.");
                    return false;
                }

                // Validate city and district
                if (city.trim() === "" || district.trim() === "") {
                    alert("Please enter your city and district.");
                    return false;
                }

                // Validate that at least one cart item is selected
                const checkboxes = document.querySelectorAll('input[name="cart_items[]"]:checked');
                const selectedItems = Array.from(checkboxes).map(checkbox => checkbox.value);

                if (selectedItems.length === 0) {
                    alert('Please select at least one item to proceed with the order.');
                    return false;
                }

                document.getElementById('selected_items').value = selectedItems.join(',');

                return true;
            }

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
                }
            }
        </script>
    </section>

    <?php include 'footer.php'; ?>

    <!-- Custom JS File Link -->
    <script src="js/script.js"></script>

</body>

</html>