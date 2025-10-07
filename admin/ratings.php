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

// RatingsManager class to handle review operations
class RatingsManager {
    private $conn;
    
    public function __construct($connection) {
        $this->conn = $connection;
    }
    
    public function deleteReview($review_id) {
        $review_id = mysqli_real_escape_string($this->conn, $review_id);
        
        $query = "DELETE FROM `reviews` WHERE id = '$review_id'";
        $result = mysqli_query($this->conn, $query);
        
        if (!$result) {
            return "Failed to delete review: " . mysqli_error($this->conn);
        }
        
        return true; // Success
    }
    
    public function getAllReviews() {
        $query = "SELECT * FROM `reviews` ORDER BY id DESC";
        $result = mysqli_query($this->conn, $query);
        return $result;
    }
    
    public function getReviewById($review_id) {
        $review_id = mysqli_real_escape_string($this->conn, $review_id);
        $query = "SELECT * FROM `reviews` WHERE id = '$review_id'";
        $result = mysqli_query($this->conn, $query);
        return $result;
    }
    
    public function generateStarRating($rating) {
        $stars = '';
        $fullStars = floor($rating);
        $halfStar = ($rating - $fullStars) >= 0.5;
        $emptyStars = 5 - $fullStars - ($halfStar ? 1 : 0);
        
        // Full stars
        for ($i = 0; $i < $fullStars; $i++) {
            $stars .= '<i class="fas fa-star" style="color: #ffc107;"></i>';
        }
        
        // Half star
        if ($halfStar) {
            $stars .= '<i class="fas fa-star-half-alt" style="color: #ffc107;"></i>';
        }
        
        // Empty stars
        for ($i = 0; $i < $emptyStars; $i++) {
            $stars .= '<i class="far fa-star" style="color: #ffc107;"></i>';
        }
        
        return $stars;
    }
}

// Initialize RatingsManager
$ratingsManager = new RatingsManager($conn);
$error_msg = "";

// Handle review deletion
if (isset($_GET['delete'])) {
    $result = $ratingsManager->deleteReview($_GET['delete']);
    
    if ($result === true) {
        header('location:ratings.php');
        exit;
    } else {
        $error_msg = $result;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ratings Management</title>
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
        .box-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
            padding: 20px;
            max-width: 1200px;
            margin: 0 auto;
        }
        .box-users {
            background: white;
            border-radius: 15px;
            padding: 25px;
            text-align: center;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            transition: transform 0.3s;
            position: relative;
        }
        .box-users:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 12px rgba(0,0,0,0.15);
        }
        .name {
            color: #2d3e40;
            margin-bottom: 12px;
            font-weight: 500;
            font-size: 1.1rem;
        }
        .message {
            color: #6c757d;
            margin-bottom: 15px;
            font-style: italic;
            line-height: 1.5;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        .rating-stars {
            margin-bottom: 20px;
            font-size: 1.5rem;
        }
        .delete-btn {
            display: inline-block;
            padding: 10px 20px;
            border: 2px solid #ff6666;
            border-radius: 25px;
            color: #ff6666;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s;
            background: white;
        }
        .delete-btn:hover {
            background: #ff6666;
            color: white;
            text-decoration: none;
            transform: translateY(-2px);
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
        .review-date {
            color: #6c757d;
            font-size: 0.9rem;
            margin-top: 10px;
            font-style: italic;
        }
        @media (max-width: 768px) {
            .content {
                margin-left: 0;
            }
            .box-container {
                grid-template-columns: 1fr;
                padding: 10px;
            }
        }
    </style>
</head>
<body>
    <?php include "./adminHeader.php"; ?>
    <?php include "./sidebar.php"; ?>
    
    <div id="main">
        <button class="openbtn" onclick="toggleNav()" style="width:90px; border-radius:10px; background: linear-gradient(135deg, #ff7eb9 0%, #7fc96b 100%);">
            <i class="fa fa-star" style="font-size:30px; color:white;"></i>
        </button>
    </div>

    <div class="content">
        <section class="add-products">
            <h1 class="title">User Ratings & Reviews</h1>
        </section>

        <!-- Display error messages -->
        <?php if (!empty($error_msg)): ?>
            <div class="alert-error"><?php echo htmlspecialchars($error_msg); ?></div>
        <?php endif; ?>

        <section class="show-products">
            <div class="box-container">
                <?php
                $reviews = $ratingsManager->getAllReviews();
                if (mysqli_num_rows($reviews) > 0) {
                    while ($review = mysqli_fetch_assoc($reviews)) {
                ?>
                <div class="box-users">
                    <div class="name"><?php echo "Name: ", htmlspecialchars($review['user_name']); ?></div>
                    
                    <div class="message">
                        <?php echo htmlspecialchars($review['review_text']); ?>
                    </div>
                    
                    <div class="rating-stars">
                        <?php echo $ratingsManager->generateStarRating($review['rating']); ?>
                        <span style="color: #6c757d; font-size: 1rem; margin-left: 10px;">
                            (<?php echo number_format($review['rating'], 1); ?>/5)
                        </span>
                    </div>
                    
                    <?php if (isset($review['created_at']) && !empty($review['created_at'])): ?>
                        <div class="review-date">
                            Posted on: <?php echo htmlspecialchars($review['created_at']); ?>
                        </div>
                    <?php endif; ?>
                    
                    <a href="ratings.php?delete=<?php echo $review['id']; ?>" class="delete-btn" onclick="return confirm('Are you sure you want to delete this review?');">
                        Delete Review
                    </a>
                </div>
                <?php
                    }
                } else {
                    echo '<p class="empty">No reviews added yet!</p>';
                }
                ?>
            </div>
        </section>

        <section class="edit-product-form">
            <?php
            if (isset($_GET['update'])) {
                $update_id = $_GET['update'];
                $update_query = mysqli_query($conn, "SELECT * FROM `user_details` WHERE id = '$update_id'") or die('query failed');
                if (mysqli_num_rows($update_query) > 0) {
                    while ($fetch_update = mysqli_fetch_assoc($update_query)) {
            ?>
            <form action="" method="post" enctype="multipart/form-data">
                <input type="hidden" name="update_g_id" value="<?php echo $fetch_update['id']; ?>">
                <div class="name box"><?php echo htmlspecialchars($fetch_update['username']); ?></div>
                <div class="name box"><?php echo htmlspecialchars($fetch_update['email']); ?></div>
                <div class="name box"><?php echo htmlspecialchars($fetch_update['contact_number']); ?></div>
                
                <select name="update_status" class="box" required>
                    <option value="" disabled selected>Select Status</option>
                    <option value="Active">Active</option>
                    <option value="Passive">Passive</option>
                </select>

                <input type="submit" value="update" name="update_genre" class="option-btn btn-primary">
                <input type="reset" value="cancel" id="close-update" class="option-btn" onclick="location.href = 'users.php'">
            </form>
            <?php
                    }
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
    </script>

    <script src="https://code.jquery.com/jquery-3.1.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.12.9/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.0.0/dist/js/bootstrap.min.js"></script>
</body>
</html>