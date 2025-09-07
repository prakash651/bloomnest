<?php
include 'config.php';
session_start();
error_reporting(0);

$user_id = $_SESSION['id'];

if (!isset($user_id)) {
   header('location:login.php');
   exit;
}

// OOP Classes
class OrderManager {
    private $conn;
    
    public function __construct($connection) {
        $this->conn = $connection;
    }
    
    private function updateStock($order_items) {
        $items = explode(',', $order_items);
        
        foreach ($items as $item) {
            preg_match('/(.+?)\s\((\d+)\)/', trim($item), $matches);
            if (count($matches) == 3) {
                $product_name = $matches[1]; 
                $quantity = (int)$matches[2]; 
                
                mysqli_query($this->conn, "UPDATE `products` SET `stocks` = `stocks` + $quantity WHERE `name` = '$product_name'") or die('query failed');
            }
        }
    }
    
    public function deleteOrder($order_id, $user_id) {
        $order_query = mysqli_query($this->conn, "SELECT total_products FROM `orders` WHERE id = '$order_id' AND user_id = '$user_id'") or die('query failed');
        
        if (mysqli_num_rows($order_query) > 0) {
            $order = mysqli_fetch_assoc($order_query);
            $this->updateStock($order['total_products']);
            
            mysqli_query($this->conn, "DELETE FROM `orders` WHERE id = '$order_id' AND user_id = '$user_id'") or die('query failed');
            return true;
        }
        return false;
    }
    
    public function deleteAllOrders($user_id) {
        $orders_query = mysqli_query($this->conn, "SELECT total_products FROM `orders` WHERE user_id = '$user_id'") or die('query failed');
        
        while ($order = mysqli_fetch_assoc($orders_query)) {
            $this->updateStock($order['total_products']);
        }
        
        mysqli_query($this->conn, "DELETE FROM `orders` WHERE user_id = '$user_id'") or die('query failed');
        return true;
    }
    
    public function getUserOrders($user_id) {
        $result = mysqli_query($this->conn, "SELECT * FROM `orders` WHERE user_id = '$user_id' and payment_status = 'Pending'");
        if ($result === false) {
            // Handle query error
            die("Query failed: " . mysqli_error($this->conn));
        }
        return $result;
    }
}

class ComplaintManager {
    private $conn;
    
    public function __construct($connection) {
        $this->conn = $connection;
    }
    
    public function submitComplaint($user_id, $name, $email, $number, $message) {
        $name = mysqli_real_escape_string($this->conn, $name);
        $email = mysqli_real_escape_string($this->conn, $email);
        $number = mysqli_real_escape_string($this->conn, $number);
        $message = mysqli_real_escape_string($this->conn, $message);
        
        $query = "INSERT INTO `message` (user_id, name, email, number, message) VALUES ('$user_id', '$name', '$email', '$number', '$message')";
        return mysqli_query($this->conn, $query) or die('Query Failed');
    }
}

// Initialize managers
$orderManager = new OrderManager($conn);
$complaintManager = new ComplaintManager($conn);

// Process requests
if (isset($_GET['delete'])) {
    if ($orderManager->deleteOrder($_GET['delete'], $user_id)) {
        header('location:orders.php');
        exit;
    }
}

if (isset($_POST['delete_all'])) {
    if ($orderManager->deleteAllOrders($user_id)) {
        header('location:orders.php');
        exit;
    }
}

if (isset($_POST['submit_complaint'])) {
    if ($complaintManager->submitComplaint(
        $_POST['user_id'], 
        $_POST['name'], 
        $_POST['email'], 
        $_POST['number'], 
        $_POST['message']
    )) {
        header('Location: orders.php');
        exit;
    }
}

// Get user orders
$order_query = $orderManager->getUserOrders($user_id);
?>

<!DOCTYPE html>
<html lang="en">
<head>
   <meta charset="UTF-8">
   <meta http-equiv="X-UA-Compatible" content="IE=edge">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>Orders</title>

   <!-- font awesome cdn link  -->
   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

   <!-- custom css file link  -->
   <link rel="stylesheet" href="css/style.css">
   <style>
      /* Style for the delete and complain buttons */
      .delete-btn, .complain-btn {
         background-color: #e62222;
         color: white;
         border: none;
         padding: 10px 20px;
         border-radius: 5px;
         cursor: pointer;
         transition: background-color 0.3s;
         margin-left: 10px;
      }

      .delete-btn:hover, .complain-btn:hover {
         background-color: #ff3636;
      }

      /* Modal styles */
      .modal {
         display: none;
         position: fixed;
         z-index: 1;
         left: 0;
         top: 0;
         width: 100%;
         height: 100%;
         overflow: auto;
         background-color: rgba(0, 0, 0, 0.6);
         padding-top: 60px;
      }

      .modal-content {
         background-color: #fff;
         margin: 5% auto;
         padding: 20px;
         border-radius: 10px;
         box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
         width: 80%;
         max-width: 500px;
         font-family: Arial, sans-serif;
         transition: all 0.3s ease;
      }

      .close {
         color: #333;
         float: right;
         font-size: 28px;
         font-weight: bold;
      }

      .close:hover,
      .close:focus {
         color: #ff6666;
         cursor: pointer;
      }

      /* Form styles */
      .modal h2 {
         font-size: 24px;
         margin-bottom: 20px;
         color: #333;
         text-align: center;
      }

      .modal label {
         font-size: 16px;
         color: #333;
         display: block;
         margin-bottom: 8px;
      }

      .modal input,
      .modal textarea {
         width: 100%;
         padding: 12px;
         margin-bottom: 20px;
         border: 1px solid #ddd;
         border-radius: 5px;
         font-size: 16px;
         transition: border-color 0.3s ease;
      }

      .modal input:focus,
      .modal textarea:focus {
         border-color: #4CAF50;
         outline: none;
      }

      /* Make textarea bigger */
      .modal textarea {
         height: 120px;
         resize: none;
      }

      /* Submit button */
      .modal button {
         background-color: #4CAF50;
         color: white;
         padding: 12px 20px;
         border: none;
         border-radius: 5px;
         cursor: pointer;
         font-size: 16px;
         width: 100%;
         transition: background-color 0.3s ease;
      }

      .modal button:hover {
         background-color: #45a049;
      }

      /* Make the form inputs and labels look aligned */
      .modal .form-group {
         margin-bottom: 20px;
      }

      .modal .form-group label {
         font-weight: bold;
      }

      .modal .form-group input,
      .modal .form-group textarea {
         font-size: 16px;
      }
   </style>
</head>

<body>

   <?php include 'header.php'; ?>

   <div class="heading">
      <h3>Your Orders</h3>
      <p><a href="index.php">Home</a> / Orders</p>
   </div>

   <section class="placed-orders">
      <h1 class="title">Placed Orders</h1>

      <div class="box-container">
         <?php
         // Check if $order_query is a valid result object before using mysqli_num_rows
         if ($order_query && mysqli_num_rows($order_query) > 0) {
            while ($fetch_orders = mysqli_fetch_assoc($order_query)) {
         ?>
               <div class="box">
                  <p>Placed on: <span><?php echo $fetch_orders['placed_on']; ?></span></p>
                  <p>Name: <span><?php echo $fetch_orders['name']; ?></span></p>
                  <p>Number: <span><?php echo $fetch_orders['number']; ?></span></p>
                  <p>Email: <span><?php echo $fetch_orders['email']; ?></span></p>
                  <p>Address: <span><?php echo $fetch_orders['address']; ?></span></p>
                  <p>Payment method: <span><?php echo $fetch_orders['method']; ?></span></p>
                  <p>Your orders: <span><?php echo $fetch_orders['total_products']; ?></span></p>
                  <p>Total price: <span>Rs.<?php echo $fetch_orders['total_price']; ?>/-</span></p>
                  <p>Payment status: <span style="color:<?php echo ($fetch_orders['payment_status'] == 'Pending') ? 'red' : 'green'; ?>;"><?php echo $fetch_orders['payment_status']; ?></span></p>

                  <!-- Delete and Complain buttons -->
                  <div style="margin-top: 2rem; text-align:center;">
                     <form action="orders.php" method="get" onsubmit="return confirm('Delete this order?');" style="display:inline-block;">
                        <input type="hidden" name="delete" value="<?php echo $fetch_orders['id']; ?>">
                        <button type="submit" class="delete-btn">Delete Order</button>
                     </form>

                     <button class="delete-btn" onclick="openModal('<?php echo $fetch_orders['name']; ?>', '<?php echo $fetch_orders['email']; ?>', '<?php echo $fetch_orders['number']; ?>')">Complain</button>
                  </div>
               </div>
               <?php
            }
         } else {
            echo '<p class="empty">No orders placed yet!</p>';
         }
         ?>
      </div>
      <form method="post" action="" onsubmit="return confirm('Are you sure you want to clear your entire orders?');">
         <button type="submit" name="delete_all" style="margin-left:45%;" class="delete-btn">Delete All Orders</button>
      </form>
   </section>

   <!-- Modal HTML -->
   <div id="complainModal" class="modal">
      <div class="modal-content">
         <span class="close" onclick="closeModal()">&times;</span>
         <h2>Submit Your Complaint</h2>
         <form id="complainForm" method="post">
            <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">
            <label for="name">Name</label>
            <input type="text" id="modalName" name="name" required>

            <label for="email">Email</label>
            <input type="email" id="modalEmail" name="email" required>

            <label for="number">Number</label>
            <input type="text" id="modalNumber" name="number" required>

            <label for="message">Message</label>
            <textarea name="message" id="modalMessage" rows="4" required></textarea>

            <button type="submit" name="submit_complaint">Submit</button>
         </form>
      </div>
   </div>

   <script>
      // Function to open modal and pre-fill the fields
      function openModal(name, email, number) {
         document.getElementById('modalName').value = name;
         document.getElementById('modalEmail').value = email;
         document.getElementById('modalNumber').value = number;
         document.getElementById('complainModal').style.display = 'block';
      }

      // Function to close the modal
      function closeModal() {
         document.getElementById('complainModal').style.display = 'none';
      }

      // Close modal if the user clicks outside of the modal
      window.onclick = function (event) {
         if (event.target == document.getElementById('complainModal')) {
            closeModal();
         }
      }
   </script>

   <?php include 'footer.php'; ?>
   <script src="js/script.js"></script>
</body>
</html>