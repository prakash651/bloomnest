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

// UserManager class to handle user operations
class UserManager {
    private $conn;
    
    public function __construct($connection) {
        $this->conn = $connection;
    }
    
    public function updateUserStatus($user_id, $status) {
        $user_id = mysqli_real_escape_string($this->conn, $user_id);
        $status = mysqli_real_escape_string($this->conn, $status);
        
        $query = "UPDATE `user_details` SET Status = '$status' WHERE id = '$user_id'";
        $result = mysqli_query($this->conn, $query);
        
        if (!$result) {
            return "Failed to update user status: " . mysqli_error($this->conn);
        }
        
        return true; // Success
    }
    
    public function deleteUser($user_id) {
        $user_id = mysqli_real_escape_string($this->conn, $user_id);
        
        $query = "DELETE FROM `user_details` WHERE id = '$user_id'";
        $result = mysqli_query($this->conn, $query);
        
        if (!$result) {
            return "Failed to delete user: " . mysqli_error($this->conn);
        }
        
        return true; // Success
    }
    
    public function getUsersByStatus($status = "") {
        $query = "SELECT * FROM `user_details`";
        
        if (!empty($status)) {
            $status = mysqli_real_escape_string($this->conn, $status);
            $query .= " WHERE Status = '$status'";
        }
        
        $query .= " ORDER BY username ASC";
        $result = mysqli_query($this->conn, $query);
        
        return $result;
    }
    
    public function getUserById($user_id) {
        $user_id = mysqli_real_escape_string($this->conn, $user_id);
        
        $query = "SELECT * FROM `user_details` WHERE id = '$user_id'";
        $result = mysqli_query($this->conn, $query);
        
        return $result;
    }
    
    public function isValidStatus($status) {
        $validStatuses = ['Active', 'Passive', ''];
        return in_array($status, $validStatuses);
    }
}

// Initialize UserManager
$userManager = new UserManager($conn);
$error_msg = "";

// Process form submissions
if (isset($_POST['update_genre'])) {
    $result = $userManager->updateUserStatus(
        $_POST['update_g_id'],
        $_POST['update_status']
    );
    
    if ($result === true) {
        header('location:users.php');
        exit;
    } else {
        $error_msg = $result;
    }
}

if (isset($_GET['delete'])) {
    $result = $userManager->deleteUser($_GET['delete']);
    
    if ($result === true) {
        header('location:users.php');
        exit;
    } else {
        $error_msg = $result;
    }
}

// Get status filter
$statusFilter = "";
if (isset($_GET['status']) && $userManager->isValidStatus($_GET['status'])) {
    $statusFilter = $_GET['status'];
}

// Get users based on filter
$users = $userManager->getUsersByStatus($statusFilter);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Flower Shop - Users Management</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.0.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="admin_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&family=Montserrat:wght@300;400;500&display=swap" rel="stylesheet">

    <style>
        body {
            background: #f8f9fa;
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
        .action-buttons {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-bottom: 30px;
            flex-wrap: wrap;
        }
        .btn-filter {
            background: linear-gradient(135deg, #ff7eb9 0%, #7fc96b 100%);
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: 25px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .btn-filter:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
            color: white;
            text-decoration: none;
        }
        .btn-filter.active {
            background: linear-gradient(135deg, #ff5fa3 0%, #6cb256 100%);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }
        .box-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            padding: 20px;
            max-width: 1200px;
            margin: 0 auto;
        }
        .box-users {
            background: linear-gradient(135deg, #ff7eb9 0%, #7fc96b 100%);
            border-radius: 15px;
            padding: 20px;
            color: white;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            transition: transform 0.3s;
        }
        .box-users:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 12px rgba(0,0,0,0.15);
        }
        .box-users .name {
            margin-bottom: 10px;
            font-size: 1.1rem;
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
        .edit-product-form {
            max-width: 500px;
            margin: 30px auto;
            padding: 20px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .edit-product-form .box {
            width: 100%;
            padding: 12px;
            margin: 10px 0;
            border: 1px solid #ddd;
            border-radius: 5px;
            background: #f9f9f9;
        }
        .edit-product-form select.box {
            background: white;
        }
        .btn-primary {
            background: linear-gradient(135deg, #ff7eb9 0%, #7fc96b 100%);
            border: none;
            padding: 12px 25px;
            border-radius: 25px;
            color: white;
            font-weight: 500;
            transition: all 0.3s;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }
        .empty {
            text-align: center;
            color: #2d3e40;
            font-size: 1.2rem;
            margin: 40px 0;
            grid-column: 1 / -1;
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
        .status-badge {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            font-weight: 600;
            margin-bottom: 10px;
        }
        .status-active {
            background-color: rgba(255, 255, 255, 0.2);
            color: #fff;
            border: 2px solid #fff;
        }
        .status-passive {
            background-color: rgba(255, 255, 255, 0.1);
            color: #fff;
            border: 2px solid #fff;
        }
        @media (max-width: 768px) {
            .content {
                margin-left: 0;
            }
            .box-container {
                grid-template-columns: 1fr;
                padding: 10px;
            }
            .action-buttons {
                flex-direction: column;
                align-items: center;
            }
            .btn-filter {
                width: 80%;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <?php include "./adminHeader.php"; ?>
    <?php include "./sidebar.php"; ?>
    
    <div id="main">
        <button class="openbtn" onclick="toggleNav()" style="width:90px; border-radius:10px; background: linear-gradient(135deg, #ff7eb9 0%, #7fc96b 100%);">
            <i class="fa fa-users" style="font-size:30px; color:white;"></i>
        </button>
    </div>

    <div class="content">
        <section class="add-products">
            <h1 class="title">Our Valued Customers</h1>

            <!-- Display error messages -->
            <?php if (!empty($error_msg)): ?>
                <div class="alert-error"><?php echo htmlspecialchars($error_msg); ?></div>
            <?php endif; ?>

            <!-- Status Filter Buttons -->
            <div class="action-buttons">
                <a href="users.php?status=Active" class="btn-filter <?php echo $statusFilter === 'Active' ? 'active' : ''; ?>">
                    Active Customers
                </a>
                <a href="users.php?status=Passive" class="btn-filter <?php echo $statusFilter === 'Passive' ? 'active' : ''; ?>">
                    Passive Customers
                </a>
                <a href="users.php" class="btn-filter <?php echo empty($statusFilter) ? 'active' : ''; ?>">
                    All Customers
                </a>
            </div>
        </section>

        <section class="show-products">
            <div class="box-container">
                <?php
                if (mysqli_num_rows($users) > 0) {
                    while ($user = mysqli_fetch_assoc($users)) {
                        $statusClass = strtolower($user['Status']);
                ?>
                <div class="box-users">
                    <div class="status-badge status-<?php echo $statusClass; ?>">
                        <?php echo htmlspecialchars($user['Status']); ?>
                    </div>
                    <div class="name"><strong>Name:</strong> <?php echo htmlspecialchars($user['username']); ?></div>
                    <div class="name"><strong>Email:</strong> <?php echo htmlspecialchars($user['email']); ?></div>
                    <div class="name"><strong>Contact:</strong> <?php echo htmlspecialchars($user['contact_number']); ?></div>
                    
                    <div style="margin-top: 15px;">
                        <a href="users.php?update=<?php echo $user['id']; ?>" class="option-btn">Update</a>
                        <a href="users.php?delete=<?php echo $user['id']; ?>" class="delete-btn"
                            onclick="return confirm('Are you sure you want to delete this customer?');">Delete</a>
                    </div>
                </div>
                <?php
                    }
                } else {
                    $message = empty($statusFilter) ? 'No customers found!' : "No customers found with status: " . htmlspecialchars($statusFilter);
                    echo '<p class="empty">' . $message . '</p>';
                }
                ?>
            </div>
        </section>

        <section class="edit-product-form">
            <?php
            if (isset($_GET['update'])) {
                $user_id = $_GET['update'];
                $user_result = $userManager->getUserById($user_id);
                
                if (mysqli_num_rows($user_result) > 0) {
                    $user_data = mysqli_fetch_assoc($user_result);
            ?>
            <form action="" method="post" enctype="multipart/form-data">
                <h3 style="text-align: center; color: #2d3e40; margin-bottom: 20px;">Update Customer Status</h3>
                
                <div class="name box"><strong>Name:</strong> <?php echo htmlspecialchars($user_data['username']); ?></div>
                <div class="name box"><strong>Email:</strong> <?php echo htmlspecialchars($user_data['email']); ?></div>
                <div class="name box"><strong>Contact:</strong> <?php echo htmlspecialchars($user_data['contact_number']); ?></div>
                
                <input type="hidden" name="update_g_id" value="<?php echo $user_data['id']; ?>">
                
                <select name="update_status" class="box" required>
                    <option value="" disabled selected>Select Status</option>
                    <option value="Active" <?php echo ($user_data['Status'] == 'Active') ? 'selected' : ''; ?>>Active</option>
                    <option value="Passive" <?php echo ($user_data['Status'] == 'Passive') ? 'selected' : ''; ?>>Passive</option>
                </select>
                
                <div style="text-align: center; margin-top: 20px;">
                    <input type="submit" value="Update Status" name="update_genre" class="btn-primary">
                    <input type="reset" value="Cancel" id="close-update" class="option-btn" onclick="location.href = 'users.php'">
                </div>
            </form>
            <?php
                } else {
                    echo '<p class="empty">User not found!</p>';
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
                location.href = 'users.php';
            }
        }
    </script>

    <script src="https://code.jquery.com/jquery-3.1.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.12.9/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.0.0/dist/js/bootstrap.min.js"></script>
</body>
</html>