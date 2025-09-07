<?php
session_start();
include "./adminHeader.php";
include "./sidebar.php";
include_once "config/dbconnect.php";

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if the user is already logged in
if (!isset($_SESSION['adminname'])) {
    header("location: index.php");
    exit;
}

// Category class to handle category operations
class CategoryManager {
    private $conn;
    
    public function __construct($connection) {
        $this->conn = $connection;
    }
    
    public function addCategory($name) {
        $name = mysqli_real_escape_string($this->conn, $name);
        $select_category_name = mysqli_query($this->conn, "SELECT name FROM `categories` WHERE name = '$name'");
        
        if (!$select_category_name) {
            return 'Query failed: ' . mysqli_error($this->conn);
        }
        
        if (mysqli_num_rows($select_category_name) > 0) {
            return 'Category name already added';
        } else {
            $add_category_query = mysqli_query($this->conn, "INSERT INTO `categories`(name) VALUES('$name')");
            
            if ($add_category_query) {
                return 'Category added successfully!';
            } else {
                return 'Category could not be added: ' . mysqli_error($this->conn);
            }
        }
    }
    
    public function updateCategory($id, $name) {
        $update_c_id = mysqli_real_escape_string($this->conn, $id);
        $update_name = mysqli_real_escape_string($this->conn, $name);
        
        $result = mysqli_query($this->conn, "UPDATE `categories` SET name = '$update_name' WHERE id = '$update_c_id'");
        return $result;
    }
    
    public function deleteCategory($id) {
        $delete_id = mysqli_real_escape_string($this->conn, $id);
        $result = mysqli_query($this->conn, "DELETE FROM `categories` WHERE id = '$delete_id'");
        return $result;
    }
    
    public function getAllCategories() {
        $result = mysqli_query($this->conn, "SELECT * FROM `categories`");
        return $result;
    }
    
    public function getCategoryById($id) {
        $category_id = mysqli_real_escape_string($this->conn, $id);
        $result = mysqli_query($this->conn, "SELECT * FROM `categories` WHERE id = '$category_id'");
        return $result;
    }
}

// Initialize CategoryManager
$categoryManager = new CategoryManager($conn);

// Process form submissions
$message = [];
if (isset($_POST['add_category'])) {
    $name = $_POST['name'];
    $result = $categoryManager->addCategory($name);
    $message[] = $result;
}

if (isset($_POST['update_category'])) {
    $update_c_id = $_POST['update_c_id'];
    $update_name = $_POST['update_name'];
    
    if ($categoryManager->updateCategory($update_c_id, $update_name)) {
        header('location:categories.php');
        exit;
    } else {
        $message[] = 'Update failed: ' . mysqli_error($conn);
    }
}

if (isset($_GET['delete'])) {
    $delete_id = $_GET['delete'];
    if ($categoryManager->deleteCategory($delete_id)) {
        header('location:categories.php');
        exit;
    } else {
        $message[] = 'Deletion failed: ' . mysqli_error($conn);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Categories Management</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.0.0/dist/css/bootstrap.min.css" integrity="sha384-Gn5384xqQ1aoWXA+058RXPxPg6fy4IWvTNh0E263XmFcJlSAwiGgFAW/dAiS6JXm" crossorigin="anonymous">
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
            height: auto;
            background: url("assets/images/floral-bg.png") no-repeat center;
            background-size: contain;
            padding: 20px;
            margin-left: 250px;
            transition: margin-left 0.3s;
        }
        .container.allContent-section {
            padding: 20px;
        }
        .box-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        .box-users {
            text-align: center;
            color: white;
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            background: linear-gradient(135deg, #ff7eb9 0%, #7fc96b 100%);
            transition: transform 0.3s;
            height: 200px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }
        .box-users:hover {
            transform: translateY(-5px);
        }
        .title {
            color: #2d3e40;
            margin: 20px 0;
            font-family: 'Playfair Display', serif;
            text-align: center;
        }
        .add-products {
            background: white;
            padding: 20px;
            border-radius: 15px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            margin-bottom: 30px;
            max-width: 800px;
            margin-left: auto;
            margin-right: auto;
        }
        .add-products h3 {
            color: #7fc96b;
            margin-bottom: 15px;
        }
        .box {
            width: 100%;
            padding: 12px;
            margin-bottom: 15px;
            border: 1px solid #ced4da;
            border-radius: 5px;
            font-size: 16px;
        }
        .box:focus {
            border-color: #ff7eb9;
            box-shadow: 0 0 0 0.2rem rgba(255, 126, 185, 0.25);
        }
        .option-btn {
            background: linear-gradient(135deg, #ff7eb9 0%, #7fc96b 100%);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s;
            margin-right: 10px;
        }
        .option-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }
        .delete-btn {
            background: #dc3545;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s;
        }
        .delete-btn:hover {
            background: #c82333;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }
        .empty {
            text-align: center;
            color: #6c757d;
            font-size: 18px;
            padding: 20px;
        }
        .edit-product-form {
            background: white;
            padding: 20px;
            border-radius: 15px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            margin: 30px auto;
            max-width: 600px;
        }
        .btn-primary {
            background: linear-gradient(135deg, #ff7eb9 0%, #7fc96b 100%);
            border: none;
        }
        .btn-primary:hover {
            background: linear-gradient(135deg, #ff5fa3 0%, #6cb256 100%);
            transform: translateY(-2px);
        }
        .alert {
            max-width: 800px;
            margin: 20px auto;
        }
        @media (max-width: 768px) {
            .content {
                margin-left: 0;
            }
            .box-container {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
  <div id="main">
    <button class="openbtn" onclick="toggleNav()" style="width:85px; border-radius:10px; background: linear-gradient(135deg, #ff7eb9 0%, #7fc96b 100%);">
        <i class="fa fa-bars" style="font-size:30px; color:white;"></i>
    </button>
  </div>

  <div class="content">
    <div class="container allContent-section py-4">
        <!-- Display messages -->
        <?php if (!empty($message)): ?>
            <?php foreach ($message as $msg): ?>
                <div class="alert alert-info"><?php echo $msg; ?></div>
            <?php endforeach; ?>
        <?php endif; ?>
        
        <section class="add-products">
            <h1 class="title">Our Categories</h1>

            <form action="" method="post" enctype="multipart/form-data" onsubmit="return validateCategory()">
                <h3>Add Category</h3>
                <input type="text" name="name" id="category-name" class="box" placeholder="Enter Category" required>
                <input type="submit" value="Add Category" name="add_category" class="option-btn">
            </form>

            <script>
            function validateCategory() {
                const categoryName = document.getElementById('category-name').value.trim();
                const categoryRegex = /^[a-zA-Z\s]+$/; // Only allows letters and spaces
                if (!categoryRegex.test(categoryName)) {
                    alert("Category must not contain numbers or special characters.");
                    return false;
                }
                return true;
            }
            </script>
        </section>

        <section class="show-products">
            <div class="box-container">
                <?php
                $select_categories = $categoryManager->getAllCategories();
                if ($select_categories && mysqli_num_rows($select_categories) > 0) {
                    while ($fetch_categories = mysqli_fetch_assoc($select_categories)) {
                ?>
                <div class="box-users">
                    <div class="name"><?php echo htmlspecialchars($fetch_categories['name']); ?></div>
                    <div class="action-buttons mt-3">
                        <a href="categories.php?update=<?php echo $fetch_categories['id']; ?>" class="option-btn">Update</a>
                        <a href="categories.php?delete=<?php echo $fetch_categories['id']; ?>" class="delete-btn" onclick="return confirm('Delete this category?');">Delete</a>
                    </div>
                </div>
                <?php
                    }
                } else {
                    echo '<p class="empty">No categories added yet!</p>';
                }
                ?>
            </div>
        </section>

        <section class="edit-product-form">
            <?php
            if (isset($_GET['update'])) {
                $update_id = $_GET['update'];
                $update_query = $categoryManager->getCategoryById($update_id);
                if ($update_query && mysqli_num_rows($update_query) > 0) {
                    $fetch_update = mysqli_fetch_assoc($update_query);
            ?>
            <form action="" method="post" enctype="multipart/form-data">
                <input type="hidden" name="update_c_id" value="<?php echo $fetch_update['id']; ?>">
                <input type="text" name="update_name" value="<?php echo htmlspecialchars($fetch_update['name']); ?>" class="box" required placeholder="Enter category">
                <input type="submit" value="Update" name="update_category" class="option-btn">
                <input type="reset" value="Cancel" id="close-update" class="option-btn" onclick="location.href = 'categories.php'" style="background: #6c757d;">
            </form>
            <?php
                } else {
                    echo '<p class="empty">Category not found!</p>';
                }
            } else {
                echo '<script>document.querySelector(".edit-product-form").style.display = "none";</script>';
            }
            ?>
        </section>
    </div>
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
    
    // Close the form when clicking outside of it
    window.onclick = function(event) {
        const modal = document.querySelector('.edit-product-form');
        if (event.target === modal) {
            modal.style.display = "none";
            location.href = 'categories.php'; 
        }
    }
  </script>
  
    <script src="https://code.jquery.com/jquery-3.1.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.12.9/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.0.0/dist/js/bootstrap.min.js"></script>
</body>
</html>