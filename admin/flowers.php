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

// FlowerManager class to handle product operations
class FlowerManager {
    private $conn;
    private $uploadDir = '../uploaded_img/';
    private $maxFileSize = 2000000; // 2MB
    private $allowedExtensions = ['jpg', 'jpeg', 'png'];
    
    public function __construct($connection) {
        $this->conn = $connection;
    }
    
    public function addProduct($name, $price, $stock, $image, $category) {
        // Validate inputs
        if (!$this->validateName($name)) {
            return 'Flower arrangement name must not start with a number and must end with a letter.';
        }
        
        if (!$this->validateImage($image)) {
            return 'Invalid image file. Please upload a JPG, JPEG, or PNG file under 2MB.';
        }
        
        // Check if product already exists
        $name = mysqli_real_escape_string($this->conn, $name);
        $select_product_name = mysqli_query($this->conn, "SELECT name FROM `products` WHERE name = '$name'");
        
        if (!$select_product_name) {
            return 'Database query failed: ' . mysqli_error($this->conn);
        }
        
        if (mysqli_num_rows($select_product_name) > 0) {
            return 'Flower arrangement already exists!';
        }
        
        // Process image upload
        $image_name = $image['name'];
        $image_tmp_name = $image['tmp_name'];
        $image_size = $image['size'];
        $image_folder = $this->uploadDir . $image_name;
        
        // Insert product into database
        $price = mysqli_real_escape_string($this->conn, $price);
        $stock = mysqli_real_escape_string($this->conn, $stock);
        $category = mysqli_real_escape_string($this->conn, $category);
        
        $add_product_query = mysqli_query($this->conn, 
            "INSERT INTO `products`(name, category, price, image, stocks) 
             VALUES('$name','$category', '$price', '$image_name', '$stock')");
        
        if (!$add_product_query) {
            return 'Failed to add flower arrangement: ' . mysqli_error($this->conn);
        }
        
        // Move uploaded file
        if (!move_uploaded_file($image_tmp_name, $image_folder)) {
            return 'Failed to upload image.';
        }
        
        return 'Flower arrangement added successfully!';
    }
    
    public function updateProduct($id, $name, $price, $stock, $category, $newImage = null, $oldImage = null) {
        // Validate inputs
        if (!$this->validateName($name)) {
            return 'Flower arrangement name must not start with a number and must end with a letter.';
        }
        
        $id = mysqli_real_escape_string($this->conn, $id);
        $name = mysqli_real_escape_string($this->conn, $name);
        $price = mysqli_real_escape_string($this->conn, $price);
        $stock = mysqli_real_escape_string($this->conn, $stock);
        $category = mysqli_real_escape_string($this->conn, $category);
        
        // Update product details
        $update_query = mysqli_query($this->conn, 
            "UPDATE `products` SET name = '$name', price = '$price', 
             category = '$category', stocks = '$stock' WHERE id = '$id'");
        
        if (!$update_query) {
            return 'Failed to update product: ' . mysqli_error($this->conn);
        }
        
        // Handle image update if provided
        if ($newImage && $oldImage) {
            if (!$this->validateImage($newImage)) {
                return 'Invalid image file. Please upload a JPG, JPEG, or PNG file under 2MB.';
            }
            
            $new_image_name = $newImage['name'];
            $new_image_tmp_name = $newImage['tmp_name'];
            $new_image_folder = $this->uploadDir . $new_image_name;
            
            // Update image in database
            $update_image_query = mysqli_query($this->conn, 
                "UPDATE `products` SET image = '$new_image_name' WHERE id = '$id'");
            
            if (!$update_image_query) {
                return 'Failed to update image: ' . mysqli_error($this->conn);
            }
            
            // Move new image and delete old one
            if (!move_uploaded_file($new_image_tmp_name, $new_image_folder)) {
                return 'Failed to upload new image.';
            }
            
            if (file_exists($this->uploadDir . $oldImage)) {
                unlink($this->uploadDir . $oldImage);
            }
        }
        
        return true; // Success
    }
    
    public function deleteProduct($id) {
        $id = mysqli_real_escape_string($this->conn, $id);
        
        // Get image filename
        $image_query = mysqli_query($this->conn, "SELECT image FROM `products` WHERE id = '$id'");
        
        if (!$image_query) {
            return 'Failed to fetch product image: ' . mysqli_error($this->conn);
        }
        
        $image_data = mysqli_fetch_assoc($image_query);
        $image_filename = $image_data['image'];
        
        // Delete product from database
        $delete_query = mysqli_query($this->conn, "DELETE FROM `products` WHERE id = '$id'");
        
        if (!$delete_query) {
            return 'Failed to delete product: ' . mysqli_error($this->conn);
        }
        
        // Delete image file
        if ($image_filename && file_exists($this->uploadDir . $image_filename)) {
            unlink($this->uploadDir . $image_filename);
        }
        
        return true; // Success
    }
    
    public function getAllProducts() {
        $query = "
            SELECT p.*, c.name AS category_name 
            FROM products p 
            JOIN categories c ON p.category = c.id
            ORDER BY p.name
        ";
        $result = mysqli_query($this->conn, $query);
        return $result;
    }
    
    public function getProductById($id) {
        $id = mysqli_real_escape_string($this->conn, $id);
        $query = "SELECT * FROM `products` WHERE id = '$id'";
        $result = mysqli_query($this->conn, $query);
        return $result;
    }
    
    public function getAllCategories() {
        $query = "SELECT * FROM `categories` ORDER BY name";
        $result = mysqli_query($this->conn, $query);
        return $result;
    }
    
    private function validateName($name) {
        $name = trim($name);
        $nameRegex = '/^[^\d].*[a-zA-Z]$/';
        return preg_match($nameRegex, $name);
    }
    
    private function validateImage($image) {
        if (!$image || !isset($image['name']) || $image['error'] !== UPLOAD_ERR_OK) {
            return false;
        }
        
        // Check file size
        if ($image['size'] > $this->maxFileSize) {
            return false;
        }
        
        // Check file extension
        $extension = strtolower(pathinfo($image['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, $this->allowedExtensions)) {
            return false;
        }
        
        return true;
    }
}

// Initialize FlowerManager
$flowerManager = new FlowerManager($conn);
$message = [];

// Process form submissions
if (isset($_POST['add_product'])) {
    $result = $flowerManager->addProduct(
        $_POST['name'],
        $_POST['price'],
        $_POST['stock'],
        $_FILES['image'],
        $_POST['category']
    );
    
    if ($result === true) {
        $message[] = 'Flower arrangement added successfully!';
    } else {
        $message[] = $result;
    }
}

if (isset($_POST['update_product'])) {
    $result = $flowerManager->updateProduct(
        $_POST['update_p_id'],
        $_POST['update_name'],
        $_POST['update_price'],
        $_POST['update_stock'],
        $_POST['update_category'],
        $_FILES['update_image'],
        $_POST['update_old_image']
    );
    
    if ($result === true) {
        header('location:flowers.php');
        exit;
    } else {
        $message[] = $result;
    }
}

if (isset($_GET['delete'])) {
    $result = $flowerManager->deleteProduct($_GET['delete']);
    
    if ($result === true) {
        header('location:flowers.php');
        exit;
    } else {
        $message[] = $result;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Flower Shop - Products Management</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.0.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <link rel="stylesheet" href="admin_style.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&family=Montserrat:wght@300;400;500&display=swap" rel="stylesheet">

    <style>
        body {
            background: #f8f9fa;
            font-family: 'Montserrat', sans-serif;
        }

        #main {
            padding: 20px;
        }

        .openbtn {
            background: linear-gradient(135deg, #ff7eb9 0%, #7fc96b 100%);
            border: none;
            padding: 10px 15px;
            border-radius: 10px;
            color: white;
            cursor: pointer;
            transition: all 0.3s;
        }

        .openbtn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        .title {
            text-align: center;
            color: #2d3e40;
            font-family: 'Playfair Display', serif;
            margin: 20px 0;
            font-size: 2.5rem;
        }

        .add-products {
            max-width: 600px;
            margin: 0 auto 40px;
            padding: 20px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .add-products h3 {
            text-align: center;
            color: #2d3e40;
            margin-bottom: 20px;
            font-family: 'Playfair Display', serif;
        }

        .add-products .box {
            width: 100%;
            padding: 12px;
            margin: 10px 0;
            border: 1px solid #ddd;
            border-radius: 5px;
            background: #f9f9f9;
            transition: border-color 0.3s;
        }

        .add-products .box:focus {
            border-color: #ff7eb9;
            outline: none;
        }

        .add-products select.box {
            background: white;
        }

        .add-products .btn {
            background: linear-gradient(135deg, #ff7eb9 0%, #7fc96b 100%);
            border: none;
            padding: 12px 25px;
            border-radius: 25px;
            color: white;
            font-weight: 500;
            transition: all 0.3s;
            width: 100%;
            margin-top: 10px;
        }

        .add-products .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }

        .box-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            padding: 20px;
            max-width: 1200px;
            margin: 0 auto;
        }

        .show-products .box {
            background: white;
            border-radius: 15px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            transition: transform 0.3s;
        }

        .show-products .box:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 12px rgba(0,0,0,0.15);
        }

        .show-products .box img {
            width: 100%;
            height: 200px;
            object-fit: cover;
            border-radius: 10px;
            margin-bottom: 15px;
        }

        .show-products .box .name {
            color: #2d3e40;
            font-weight: 600;
            font-size: 1.2rem;
            margin-bottom: 8px;
        }

        .show-products .box .price {
            color: #ff7eb9;
            font-weight: 600;
            font-size: 1.1rem;
            margin-bottom: 8px;
        }

        .show-products .box .category {
            color: #7fc96b;
            margin-bottom: 15px;
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

        .edit-product-form img {
            width: 100%;
            height: 200px;
            object-fit: cover;
            border-radius: 10px;
            margin-bottom: 15px;
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
            margin-right: 10px;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }

        .alert {
            max-width: 600px;
            margin: 20px auto;
            border-radius: 10px;
        }

        @media (max-width: 768px) {
            .box-container {
                grid-template-columns: 1fr;
                padding: 10px;
            }
            
            .add-products {
                margin: 0 10px 40px;
                padding: 15px;
            }
        }
    </style>
</head>
<body>
    <div id="main">
        <button class="openbtn" onclick="toggleNav()">
            <i class="fa-solid fa-fan" style="font-size:24px;"></i> Manage Flowers
        </button>
    </div>

    <!-- Display messages -->
    <?php if (!empty($message)): ?>
        <?php foreach ($message as $msg): ?>
            <div class="alert alert-info"><?php echo htmlspecialchars($msg); ?></div>
        <?php endforeach; ?>
    <?php endif; ?>

    <section class="add-products">
        <h1 class="title">Flower Arrangements</h1>
        <form action="" method="post" enctype="multipart/form-data" onsubmit="return validateFlowerForm()">
            <h3>Add New Arrangement</h3>
            <input type="text" name="name" id="flower-name" class="box" placeholder="Enter Flower Arrangement Name" required>
            <input type="number" min="150" name="price" class="box" placeholder="Enter Price (Rs)" required>

            <select name="category" class="box" required>
                <option value="" disabled selected>Select Category</option>
                <?php
                $categories = $flowerManager->getAllCategories();
                if (mysqli_num_rows($categories) > 0) {
                    while ($category = mysqli_fetch_assoc($categories)) {
                        echo '<option value="'.$category['id'].'">'.$category['name'].'</option>';
                    }
                } else {
                    echo '<option value="" disabled>No categories available</option>';
                }
                ?>
            </select>

            <input type="file" name="image" id="flower-image" accept="image/jpg, image/jpeg, image/png" class="box" required>
            <input type="number" name="stock" class="box" placeholder="Enter Stock Quantity" min="0" required>
            <input type="submit" value="Add Flower Arrangement" name="add_product" class="btn">
        </form>
    </section>

    <section class="show-products">
        <div class="box-container">
            <?php
            $products = $flowerManager->getAllProducts();
            if (mysqli_num_rows($products) > 0) {
                while ($product = mysqli_fetch_assoc($products)) {
                    ?>
                    <div class="box">
                        <img src="../uploaded_img/<?php echo htmlspecialchars($product['image']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>">
                        <div class="name"><?php echo htmlspecialchars($product['name']); ?></div>
                        <div class="price">Rs. <?php echo htmlspecialchars($product['price']); ?>/-</div>
                        <div class="category">Category: <?php echo htmlspecialchars($product['category_name']); ?></div>
                        <div>
                            <a href="flowers.php?update=<?php echo $product['id']; ?>" class="option-btn">Update</a>
                            <a href="flowers.php?delete=<?php echo $product['id']; ?>" class="delete-btn" onclick="return confirm('Delete this flower arrangement?');">Delete</a>
                        </div>
                    </div>
                    <?php
                }
            } else {
                echo '<p class="empty">No flower arrangements added yet!</p>';
            }
            ?>
        </div>
    </section>

    <section class="edit-product-form">
        <?php
        if (isset($_GET['update'])) {
            $product_id = $_GET['update'];
            $product = $flowerManager->getProductById($product_id);
            
            if (mysqli_num_rows($product) > 0) {
                $product_data = mysqli_fetch_assoc($product);
                $categories = $flowerManager->getAllCategories();
                ?>
                <form action="" method="post" enctype="multipart/form-data">
                    <h3 style="text-align: center; color: #2d3e40; margin-bottom: 20px;">Update Flower Arrangement</h3>
                    
                    <img src="../uploaded_img/<?php echo htmlspecialchars($product_data['image']); ?>" alt="Current Image">
                    
                    <input type="hidden" name="update_p_id" value="<?php echo $product_data['id']; ?>">
                    <input type="hidden" name="update_old_image" value="<?php echo htmlspecialchars($product_data['image']); ?>">
                    
                    <input type="text" name="update_name" value="<?php echo htmlspecialchars($product_data['name']); ?>" class="box" required placeholder="Flower arrangement name">
                    <input type="number" name="update_price" value="<?php echo htmlspecialchars($product_data['price']); ?>" min="0" class="box" required placeholder="Price">
                    
                    <select name="update_category" class="box" required>
                        <?php
                        if (mysqli_num_rows($categories) > 0) {
                            while ($category = mysqli_fetch_assoc($categories)) {
                                $selected = ($category['id'] == $product_data['category']) ? 'selected' : '';
                                echo '<option value="'.$category['id'].'" '.$selected.'>'.$category['name'].'</option>';
                            }
                        } else {
                            echo '<option value="" disabled>No categories available</option>';
                        }
                        ?>
                    </select>
                    
                    <input type="file" class="box" name="update_image" accept="image/jpg, image/jpeg, image/png">
                    <input type="number" name="update_stock" value="<?php echo htmlspecialchars($product_data['stocks']); ?>" class="box" required placeholder="Stock quantity">

                    <div style="text-align: center; margin-top: 20px;">
                        <input type="submit" value="Update" name="update_product" class="btn-primary">
                        <input type="reset" value="Cancel" id="close-update" class="option-btn" onclick="location.href = 'flowers.php'">
                    </div>
                </form>
                <?php
            } else {
                echo '<p class="empty">Product not found!</p>';
            }
        } else {
            echo '<script>document.querySelector(".edit-product-form").style.display = "none";</script>';
        }
        ?>
    </section>

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
        
        function validateFlowerForm() {
            // Validate the flower name: must not start with a number and must end with a letter
            const flowerName = document.getElementById('flower-name').value.trim();
            const nameRegex = /^[^\d].*[a-zA-Z]$/; // Ensures the name doesn't start with a number and ends with a letter
            if (!nameRegex.test(flowerName)) {
                alert("Flower arrangement name must not start with a number and must end with a letter.");
                return false;
            }

            // Validate the image file (only JPEG, JPG, or PNG files)
            const imageInput = document.getElementById('flower-image');
            const imagePath = imageInput.value;
            const allowedExtensions = /(\.jpg|\.jpeg|\.png)$/i; // Accepts .jpg, .jpeg, .png
            
            if (!allowedExtensions.exec(imagePath)) {
                alert("Please upload a file with .png, .jpg, or .jpeg extension.");
                imageInput.value = ''; // Clear the input field
                return false;
            }

            return true; // If all validations pass
        }
        
        // Close the modal when clicking outside of it
        window.onclick = function(event) {
            const modal = document.querySelector('.edit-product-form');
            if (event.target === modal) {
                modal.style.display = "none";
                location.href = 'flowers.php'; // Redirect to avoid keeping modal open on page refresh
            }
        }
    </script>
    <script src="https://code.jquery.com/jquery-3.1.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.12.9/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.0.0/dist/js/bootstrap.min.js"></script>
</body>
</html>