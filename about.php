<?php
include 'config.php';
session_start();
error_reporting(0);

// User class to handle user-related operations
class User {
    private $conn;
    
    public function __construct($connection) {
        $this->conn = $connection;
    }
    
    public function getUserData($user_id) {
        $query = "SELECT username FROM user_details WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }
    
    public function isLoggedIn() {
        return isset($_SESSION['id']);
    }
}

// Review class to handle review-related operations
class Review {
    private $conn;
    
    public function __construct($connection) {
        $this->conn = $connection;
    }
    
    public function getAllReviews($limit = 6) {
        $query = "SELECT * FROM reviews LIMIT ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $reviews = [];
        while ($row = $result->fetch_assoc()) {
            $reviews[] = $row;
        }
        
        return $reviews;
    }
    
    public function addReview($user_id, $user_name, $review_text, $rating, $image = '') {
        $query = "INSERT INTO reviews (user_id, user_name, review_text, rating, review_image) VALUES (?, ?, ?, ?, ?)";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("issis", $user_id, $user_name, $review_text, $rating, $image);
        return $stmt->execute();
    }
    
    public function handleImageUpload($file) {
        if (!empty($file['name'])) {
            $image_name = $file['name'];
            $image_temp = $file['tmp_name'];
            $image_folder = 'uploaded_img/' . $image_name;
            
            if (move_uploaded_file($image_temp, $image_folder)) {
                return $image_name;
            }
        }
        
        return '';
    }
}

// Author class to handle author-related operations
class Author {
    private $conn;
    
    public function __construct($connection) {
        $this->conn = $connection;
    }
    
    public function getAllAuthors($limit = 6) {
        $query = "SELECT * FROM authors LIMIT ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $authors = [];
        while ($row = $result->fetch_assoc()) {
            $authors[] = $row;
        }
        
        return $authors;
    }
    
    public function getAuthorBooks($author_id) {
        $query = "SELECT * FROM books WHERE author_id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $author_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $books = [];
        while ($row = $result->fetch_assoc()) {
            $books[] = $row;
        }
        
        return $books;
    }
}

// Initialize classes with existing database connection
$user = new User($conn);
$review = new Review($conn);
$author = new Author($conn);

// Check if user is logged in
$is_logged_in = $user->isLoggedIn();
$user_id = null;
$user_name = 'Guest';

if ($is_logged_in) {
    $user_id = $_SESSION['id'];
    $user_data = $user->getUserData($user_id);
    $user_name = $user_data['username'] ?? 'Guest';
}

// Handle review submission
if (isset($_POST['submit_review'])) {
    if (!$is_logged_in) {
        header("Location: login.php");
        exit();
    }
    
    $review_text = $_POST['review_text'];
    $ratings = $_POST['rating'] ?? [];
    $rating = count($ratings);
    
    $image_name = $review->handleImageUpload($_FILES['review_image']);
    
    if ($review->addReview($user_id, $user_name, $review_text, $rating, $image_name)) {
        echo "<script>alert('Review submitted successfully!');</script>";
    } else {
        echo "<script>alert('Review submission failed!');</script>";
    }
}

// Fetch data
$reviews = $review->getAllReviews();
$authors = $author->getAllAuthors();

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About Us</title>

    <!-- Font Awesome CDN link -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <!-- Custom CSS file link -->
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/css/bootstrap.min.css">

    <style>
        .review-form {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-top: 20px;
        }

        .review-form input,
        .review-form textarea {
            padding: 10px;
            border-radius: 5px;
            border: 1px solid #ccc;
        }

        .rating {
            display: flex;
            gap: 5px;
            justify-content: flex-start;
        }

        .rating input {
            display: none;
        }

        .rating label {
            cursor: pointer;
            font-size: 20px;
            color: #ccc;
        }

        .rating input:checked+label {
            color: #f39c12;
        }

        .review-container {
            min-height: 10vh;
            background-color: var(--light-bg);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }

        .review-container form {
            padding: 2rem;
            width: 50rem;
            border-radius: .5rem;
            box-shadow: var(--box-shadow);
            border: var(--border);
            background-color: var(--white);
            text-align: center;
        }

        .review-container form .box {
            width: 100%;
            border-radius: .5rem;
            background-color: var(--light-bg);
            padding: 1.2rem 1.4rem;
            font-size: 1.8rem;
            color: var(--black);
            border: var(--border);
            margin: 1rem 0;
        }

        .rating-title {
            text-align: center;
            margin: 2rem;
            text-transform: uppercase;
            color: var(--black);
            font-size: 4rem;
        }

        .rating {
            display: flex;
            gap: 5px;
            justify-content: flex-start;
        }

        .rating input[type="checkbox"] {
            display: none;
        }

        .rating label {
            cursor: pointer;
            font-size: 30px;
            color: #ccc;
            transition: color 0.3s;
        }

        .rating input[type="checkbox"]:checked+label {
            color: #f39c12;
        }

        label {
            font-size: 20px;
        }

        .modal-body ul {
            font-size: 20px;
        }
        
        .login-prompt {
            text-align: center;
            padding: 20px;
            background-color: var(--white);
            border-radius: .5rem;
            box-shadow: var(--box-shadow);
        }
        
        .login-prompt p {
            font-size: 18px;
            margin-bottom: 20px;
            color: var(--black);
        }
        
        .about {
            padding: 2rem 0;
        }
        
        .about .flex {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 2rem;
        }
        
        .about .image {
            flex: 1 1 40rem;
        }
        
        .about .image img {
            width: 100%;
            border-radius: .5rem;
        }
        
        .about .content {
            flex: 1 1 40rem;
        }
        
        .about .content h3 {
            font-size: 3rem;
            color: var(--black);
            margin-bottom: 1rem;
        }
        
        .about .content p {
            font-size: 1.6rem;
            color: var(--light-color);
            line-height: 1.8;
            padding: 1rem 0;
        }
        
        .reviews .box-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(30rem, 1fr));
            gap: 1.5rem;
        }
        
        .reviews .box {
            padding: 2rem;
            border: var(--border);
            box-shadow: var(--box-shadow);
            border-radius: .5rem;
            background-color: var(--white);
            text-align: center;
        }
        
        .reviews .box img {
            height: 10rem;
            width: 10rem;
            border-radius: 50%;
            margin-bottom: 1rem;
            object-fit: cover;
        }
        
        .reviews .box p {
            font-size: 1.6rem;
            color: var(--light-color);
            line-height: 1.8;
            padding: 1rem 0;
        }
        
        .reviews .box .stars {
            margin: 1rem 0;
        }
        
        .reviews .box .stars i {
            font-size: 1.8rem;
            color: #f39c12;
        }
        
        .reviews .box h3 {
            font-size: 2rem;
            color: var(--black);
        }
        
        .empty {
            text-align: center;
            font-size: 2rem;
            color: var(--light-color);
            grid-column: 1 / -1;
        }
    </style>
</head>

<body>

    <?php include 'header.php'; ?>

    <div class="heading">
        <h3>About Us</h3>
        <p><a href="index.php">home</a> / About</p>
    </div>

    <section class="about">
        <div class="flex">
            <div class="image">
                <img src="images/about-img.jpg" alt="">
            </div>
            <div class="content">
                <h3>Why Choose Us?</h3>
                <p>At BloomNest, we believe in the power of flowers to brighten lives and create unforgettable moments.
                    Our carefully curated arrangements are crafted with love and attention to detail, ensuring you
                    always
                    receive the freshest and most beautiful blooms.</p>
                <p>What sets us apart is our commitment to quality and customer happiness. We offer personalized floral
                    recommendations, fast and reliable delivery, and exceptional customer service to make your gifting
                    experience smooth and delightful.</p>
                <a href="contact.php" class="btn">Contact Us</a>
            </div>
        </div>
    </section>

    <section class="reviews">
        <h1 class="rating-title">Client's Reviews</h1>

        <div class="box-container">
            <!-- Display existing reviews -->
            <?php if (!empty($reviews)): ?>
                <?php foreach ($reviews as $review_item): ?>
                    <div class="box">
                        <img src="<?php echo !empty($review_item['review_image']) ? 'uploaded_img/' . $review_item['review_image'] : 'images/default-user.png'; ?>" alt="<?php echo $review_item['user_name']; ?>">
                        <p><?php echo $review_item['review_text']; ?></p>
                        <div class="stars">
                            <?php for ($i = 0; $i < $review_item['rating']; $i++): ?>
                                <i class="fas fa-star"></i>
                            <?php endfor; ?>
                            <?php for ($i = $review_item['rating']; $i < 5; $i++): ?>
                                <i class="far fa-star"></i>
                            <?php endfor; ?>
                        </div>
                        <h3><?php echo $review_item['user_name']; ?></h3>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="empty">No reviews found!</p>
            <?php endif; ?>
        </div>

        <h1 class="rating-title">Submit Your Review</h1>
        <div class="review-container">
            <?php if ($is_logged_in): ?>
                <form class="review-form" method="POST" enctype="multipart/form-data">
                    <label for="review">Explain your Experience</label>
                    <input type="text" class="box" name="review_text" placeholder="Write your review..." required>
                    <label for="image">Upload Your Image</label>
                    <input type="file" name="review_image" accept="image/*">
                    <div class="rating">
                        <input type="checkbox" name="rating[]" id="star1" value="1">
                        <label for="star1">&#9733;</label>
                        <input type="checkbox" name="rating[]" id="star2" value="2">
                        <label for="star2">&#9733;</label>
                        <input type="checkbox" name="rating[]" id="star3" value="3">
                        <label for="star3">&#9733;</label>
                        <input type="checkbox" name="rating[]" id="star4" value="4">
                        <label for="star4">&#9733;</label>
                        <input type="checkbox" name="rating[]" id="star5" value="5">
                        <label for="star5">&#9733;</label>
                    </div>
                    <button type="submit" name="submit_review" class="btn">Submit Review</button>
                </form>
            <?php else: ?>
                <div class="login-prompt">
                    <p>You need to be logged in to submit a review.</p>
                    <a href="login.php" class="btn">Login Now</a>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Modal Structure -->
    <div class="modal fade" id="authorModal" tabindex="-1" aria-labelledby="authorModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="authorModalLabel"></h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <img id="authorModalImage" src="" class="img-fluid mb-3" alt="" style="max-height: 170px;">
                    <h2>Books by <span id="authorModalName"></span>:</h2>
                    <ul id="booksList" class="list-group">
                        <!-- Books will be inserted here via JavaScript -->
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <?php include 'footer.php'; ?>

    <!-- Custom JS file link -->
    <script src="js/script.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        $(document).ready(function () {
            // When the author's name is clicked
            $('.author-name').on('click', function (event) {
                event.preventDefault();
                
                var authorId = $(this).data('id');
                var authorName = $(this).data('name');
                var authorImage = $(this).data('image');

                $('#authorModalLabel').text(authorName);
                $('#authorModalName').text(authorName);
                $('#authorModalImage').attr('src', authorImage);
                $('#booksList').empty();

                // Make AJAX request to fetch books for the selected author
                $.ajax({
                    url: 'fetch_books.php',
                    method: 'POST',
                    data: { author_id: authorId },
                    dataType: 'json',
                    success: function (response) {
                        if (response.length > 0) {
                            response.forEach(function (book) {
                                $('#booksList').append('<li class="list-group-item">' + book.name + ' - Rs.' + book.price + '</li>');
                            });
                        } else {
                            $('#booksList').append('<li class="list-group-item">No books found for this author.</li>');
                        }
                    },
                    error: function () {
                        $('#booksList').append('<li class="list-group-item">Error fetching books.</li>');
                    }
                });

                $('#authorModal').modal('show');
            });

            // Star rating functionality
            const stars = document.querySelectorAll('.rating input[type="checkbox"]');
            stars.forEach((star, index) => {
                star.addEventListener('change', () => {
                    for (let i = 0; i <= index; i++) {
                        stars[i].checked = true;
                    }
                    for (let i = index + 1; i < stars.length; i++) {
                        stars[i].checked = false;
                    }
                });
            });
        });
    </script>

</body>

</html>