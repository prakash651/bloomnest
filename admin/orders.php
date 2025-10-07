<?php
session_start();
// include "./adminHeader.php";
include "./sidebar.php";
include_once "config/dbconnect.php";

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if the user is already logged in
if (!isset($_SESSION['adminname'])) {
    header("location: index.php");
    exit;
}

// OrderManager class to handle order operations
class OrderManager {
    private $conn;
    
    public function __construct($connection) {
        $this->conn = $connection;
    }
    
    public function updateOrderStatus($order_id, $status) {
        $order_id = mysqli_real_escape_string($this->conn, $order_id);
        $status = mysqli_real_escape_string($this->conn, $status);
        
        $query = "UPDATE orders SET payment_status = '$status' WHERE id = '$order_id'";
        $result = mysqli_query($this->conn, $query);
        
        if (!$result) {
            return "Failed to update order status: " . mysqli_error($this->conn);
        }
        
        return true; // Success
    }
    
    public function deleteOrder($order_id) {
        $order_id = mysqli_real_escape_string($this->conn, $order_id);
        
        $query = "DELETE FROM orders WHERE id = '$order_id'";
        $result = mysqli_query($this->conn, $query);
        
        if (!$result) {
            return "Failed to delete order: " . mysqli_error($this->conn);
        }
        
        return true; // Success
    }
    
    public function getOrdersByStatus($status) {
        $status = mysqli_real_escape_string($this->conn, $status);
        
        $query = "SELECT * FROM orders WHERE payment_status = '$status' ORDER BY id DESC";
        $result = mysqli_query($this->conn, $query);
        
        return $result;
    }
    
    public function getOrderById($order_id) {
        $order_id = mysqli_real_escape_string($this->conn, $order_id);
        
        $query = "SELECT * FROM orders WHERE id = '$order_id'";
        $result = mysqli_query($this->conn, $query);
        
        return $result;
    }
    
    public function isValidStatus($status) {
        $validStatuses = ['Pending', 'Completed'];
        return in_array($status, $validStatuses);
    }
}

// Initialize OrderManager
$orderManager = new OrderManager($conn);
$error_msg = "";

// Process form submissions
if (isset($_POST['update_genre'])) {
    $result = $orderManager->updateOrderStatus(
        $_POST['update_g_id'],
        $_POST['update_status']
    );
    
    if ($result === true) {
        header('location:orders.php');
        exit;
    } else {
        $error_msg = $result;
    }
}

if (isset($_GET['delete'])) {
    $result = $orderManager->deleteOrder($_GET['delete']);
    
    if ($result === true) {
        header('location:orders.php');
        exit;
    } else {
        $error_msg = $result;
    }
}

// Get status filter (default to Pending)
$status = isset($_GET['status']) && $orderManager->isValidStatus($_GET['status']) 
    ? $_GET['status'] 
    : 'Pending';

// Get orders for the selected status
$orders = $orderManager->getOrdersByStatus($status);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Orders Management</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.0.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="admin_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&family=Montserrat:wght@300;400;500&display=swap" rel="stylesheet">

    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Montserrat', sans-serif;
        }
        .content {
            margin-left: 250px;
            padding: 20px;
            transition: margin-left 0.3s;
        }
        .title {
            text-align: center;
            color: #2d3e40;
            font-family: 'Playfair Display', serif;
            margin: 20px 0;
            font-size: 2.5rem;
        }
        .filter-buttons {
            display: flex;
            justify-content: center;
            margin: 20px 0;
            gap: 15px;
        }
        .filter-btn {
            padding: 12px 25px;
            border: none;
            border-radius: 25px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 500;
            transition: all 0.3s;
            text-decoration: none;
        }
        .filter-btn-pending {
            background: linear-gradient(135deg, #ff7eb9 0%, #ff9ec5 100%);
            color: white;
        }
        .filter-btn-completed {
            background: linear-gradient(135deg, #7fc96b 0%, #9dd684 100%);
            color: white;
        }
        .filter-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }
        .box-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
            padding: 20px;
            max-width: 1200px;
            margin: 0 auto;
        }
        .box {
            background: white;
            border-radius: 15px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            transition: transform 0.3s;
        }
        .box:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 12px rgba(0,0,0,0.15);
        }
        .name {
            color: #2d3e40;
            margin-bottom: 10px;
            font-weight: 500;
        }
        .status-badge {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            font-weight: 600;
            margin-bottom: 15px;
        }
        .status-pending {
            background-color: #fff0f0;
            color: #dc3545;
        }
        .status-completed {
            background-color: #f0fff0;
            color: #28a745;
        }
        .option-btn, .delete-btn {
            display: inline-block;
            padding: 8px 15px;
            margin: 5px;
            border-radius: 5px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s;
        }
        .option-btn {
            background: white;
            color: #ff7eb9;
            border: 2px solid #ff7eb9;
        }
        .option-btn:hover {
            background: #ff7eb9;
            color: white;
            text-decoration: none;
        }
        .delete-btn {
            background: white;
            color: #ff6666;
            border: 2px solid #ff6666;
        }
        .delete-btn:hover {
            background: #ff6666;
            color: white;
            text-decoration: none;
        }
        .empty {
            text-align: center;
            color: #2d3e40;
            font-size: 1.2rem;
            margin: 40px 0;
            grid-column: 1 / -1;
        }
        .edit-product-form {
            max-width: 500px;
            margin: 30px auto;
            padding: 20px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid #ced4da;
            border-radius: 5px;
            margin-bottom: 15px;
        }
        .btn-primary {
            background: linear-gradient(135deg, #ff7eb9 0%, #7fc96b 100%);
            border: none;
            padding: 12px 25px;
            border-radius: 25px;
            color: white;
            font-weight: 500;
            transition: all 0.3s;
            margin-right: 10px;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }
        .alert-error {
            color: #721c24;
            background-color: #f8d7da;
            border-color: #f5c6cb;
            padding: 15px;
            margin: 20px auto;
            border-radius: 10px;
            max-width: 600px;
            text-align: center;
        }
        @media (max-width: 768px) {
            .content {
                margin-left: 0;
            }
            .box-container {
                grid-template-columns: 1fr;
                padding: 10px;
            }
            .filter-buttons {
                flex-direction: column;
                align-items: center;
            }
        }
    </style>
</head>
<body>
    <?php include "./adminHeader.php"; ?>
    <?php include "./sidebar.php"; ?>
    
    <div id="main">
        <button class="openbtn" onclick="toggleNav()" style="width:90px; border-radius:10px; background: linear-gradient(135deg, #ff7eb9 0%, #7fc96b 100%);">
            <i class="fa fa-list" style="font-size:30px; color:white;"></i>
        </button>
    </div>

    <div class="content">
        <section class="add-products">
            <h1 class="title">Order Management</h1>
        </section>

        <!-- Display error messages -->
        <?php if (!empty($error_msg)): ?>
            <div class="alert-error"><?php echo htmlspecialchars($error_msg); ?></div>
        <?php endif; ?>

        <section class="filter-buttons">
            <a href="orders.php?status=Pending" class="filter-btn filter-btn-pending <?php echo $status === 'Pending' ? 'active' : ''; ?>">
                Pending Orders
            </a>
            <a href="orders.php?status=Completed" class="filter-btn filter-btn-completed <?php echo $status === 'Completed' ? 'active' : ''; ?>">
                Completed Orders
            </a>
        </section>

        <section class="show-products">
            <div class="box-container">
                <?php
                if (mysqli_num_rows($orders) > 0) {
                    while ($order = mysqli_fetch_assoc($orders)) {
                        $statusClass = strtolower($order['payment_status']);
                ?>
                <div class="box">
                    <div class="status-badge status-<?php echo $statusClass; ?>">
                        <?php echo htmlspecialchars($order['payment_status']); ?>
                    </div>
                    <div class="name"><?php echo "Name: ", htmlspecialchars($order['name']); ?></div>
                    <div class="name"><?php echo "Address: ", htmlspecialchars($order['address']); ?></div>
                    <div class="name"><?php echo "Contact: ", htmlspecialchars($order['number']); ?></div>
                    <div class="name"><?php echo "Method: ", htmlspecialchars($order['method']); ?></div>
                    <div class="name"><?php echo "Products: ", htmlspecialchars($order['total_products']); ?></div>
                    <div class="name"><?php echo "Price: Rs.", htmlspecialchars($order['total_price']); ?></div>
                    <div class="name"><?php echo "Placed On: ", htmlspecialchars($order['placed_on']); ?></div>
                    
                    <div class="action-buttons">
                        <a href="orders.php?update=<?php echo $order['id']; ?>" class="option-btn">Update</a>
                        <a href="orders.php?delete=<?php echo $order['id']; ?>" class="delete-btn" onclick="return confirm('Are you sure you want to delete this order?');">Delete</a>
                    </div>
                </div>
                <?php
                    }
                } else {
                    echo '<p class="empty">No Orders Found for ' . htmlspecialchars($status) . ' Status!</p>';
                }
                ?>
            </div>
        </section>

        <section class="edit-product-form">
            <?php
            if (isset($_GET['update'])) {
                $order_id = $_GET['update'];
                $order_result = $orderManager->getOrderById($order_id);
                
                if (mysqli_num_rows($order_result) > 0) {
                    $order_data = mysqli_fetch_assoc($order_result);
            ?>
            <form action="" method="post" enctype="multipart/form-data">
                <h3 style="text-align: center; color: #2d3e40; margin-bottom: 20px;">Update Order Status</h3>
                
                <input type="hidden" name="update_g_id" value="<?php echo $order_data['id']; ?>">
                
                <select name="update_status" class="form-control" required>
                    <option value="" disabled selected>Select Status</option>
                    <option value="Pending" <?php echo $order_data['payment_status'] === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="Completed" <?php echo $order_data['payment_status'] === 'Completed' ? 'selected' : ''; ?>>Completed</option>
                </select>

                <div style="text-align: center; margin-top: 20px;">
                    <input type="submit" value="Update Status" name="update_genre" class="btn-primary">
                    <input type="reset" value="Cancel" id="close-update" class="option-btn" onclick="location.href = 'orders.php'">
                </div>
            </form>
            <?php
                } else {
                    echo '<p class="empty">Order not found!</p>';
                }
            } else {
                echo '<script>document.querySelector(".edit-product-form").style.display = "none";</script>';
            }
            ?>
        </section>
    </div>
    
    <div class="footer">
        <?php include 'adminfooter.php' ?>
    </div>

    <script>
        function toggleNav() {
            const sidebar = document.querySelector('.sidebar');
            const content = document.querySelector('.content');
            if (sidebar.style.width === '250px') {
                sidebar.style.width = '0';
                content.style.marginLeft = '0';
            } else {
                sidebar.style.width = '250px';
                content.style.marginLeft = '250px';
            }
        }
        
        // Close the modal when clicking outside of it
        window.onclick = function(event) {
            const modal = document.querySelector('.edit-product-form');
            if (event.target === modal) {
                modal.style.display = "none";
                location.href = 'orders.php';
            }
        }
    </script>

    <script src="https://code.jquery.com/jquery-3.1.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.12.9/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.0.0/dist/js/bootstrap.min.js"></script>
</body>
</html>