<?php
session_start();
include "adminHeader.php";
include "sidebar.php";
include_once "config/dbconnect.php";

// Import PHPMailer classes
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../vendor/autoload.php'; // Adjust path as needed

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if the user is already logged in
if (!isset($_SESSION['adminname'])) {
    header("location: index.php");
    exit;
}

// MessageManager class to handle message operations
class MessageManager {
    private $conn;
    private $mailer;
    
    public function __construct($connection) {
        $this->conn = $connection;
        $this->initializeMailer();
    }
    
    private function initializeMailer() {
        // SMTP configuration
        $smtp_host = 'smtp.gmail.com';
        $smtp_username = 'nestbloom9@gmail.com';
        $smtp_password = 'koxh dsxs jtwt ugad';
        $smtp_port = 587;
        $smtp_encryption = 'tls';
        
        // Create a new PHPMailer instance
        $this->mailer = new PHPMailer(true);
        
        try {
            // Server settings
            $this->mailer->isSMTP();
            $this->mailer->Host = $smtp_host;
            $this->mailer->SMTPAuth = true;
            $this->mailer->Username = $smtp_username;
            $this->mailer->Password = $smtp_password;
            $this->mailer->SMTPSecure = $smtp_encryption;
            $this->mailer->Port = $smtp_port;
        } catch (Exception $e) {
            error_log("Mailer initialization failed: " . $e->getMessage());
        }
    }
    
    public function sendReply($message_id, $email, $name, $reply_message) {
        try {
            // Recipients
            $this->mailer->setFrom('noreply@BloomNest.com', 'BloomNest Admin');
            $this->mailer->addAddress($email, $name);
            
            // Content
            $this->mailer->isHTML(true);
            $this->mailer->Subject = 'Re: Your message to BloomNest';
            $this->mailer->Body = '
                <!DOCTYPE html>
                <html>
                <head>
                    <title>Response from BloomNest</title>
                </head>
                <body>
                    <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
                        <h2 style="color: #4a5568;">Response from BloomNest</h2>
                        <p>Dear ' . htmlspecialchars($name) . ',</p>
                        <p>' . nl2br(htmlspecialchars($reply_message)) . '</p>
                        <br>
                        <p>Best regards,<br>BloomNest Team</p>
                    </div>
                </body>
                </html>
            ';
            
            $this->mailer->AltBody = "Dear $name,\n\n$reply_message\n\nBest regards,\nBloomNest Team";
            
            // Send email
            if ($this->mailer->send()) {
                // Update database to mark as replied
                $this->markAsReplied($message_id);
                return "Reply sent successfully to $name";
            } else {
                return "Failed to send reply. Please try again.";
            }
        } catch (Exception $e) {
            return "Message could not be sent. Mailer Error: {$this->mailer->ErrorInfo}";
        }
    }
    
    private function markAsReplied($message_id) {
        $message_id = mysqli_real_escape_string($this->conn, $message_id);
        $query = "UPDATE `message` SET replied = 1 WHERE id = '$message_id'";
        return mysqli_query($this->conn, $query);
    }
    
    public function deleteMessage($message_id) {
        $message_id = mysqli_real_escape_string($this->conn, $message_id);
        $query = "DELETE FROM `message` WHERE id = '$message_id'";
        return mysqli_query($this->conn, $query);
    }
    
    public function getAllMessages() {
        $query = "SELECT * FROM `message` ORDER BY id DESC";
        $result = mysqli_query($this->conn, $query);
        return $result;
    }
    
    public function getMessageById($message_id) {
        $message_id = mysqli_real_escape_string($this->conn, $message_id);
        $query = "SELECT * FROM `message` WHERE id = '$message_id'";
        $result = mysqli_query($this->conn, $query);
        return $result;
    }
}

// Initialize MessageManager
$messageManager = new MessageManager($conn);
$success_msg = "";
$error_msg = "";

// Handle reply form submission
if (isset($_POST['reply_submit'])) {
    $result = $messageManager->sendReply(
        $_POST['message_id'],
        $_POST['email'],
        $_POST['name'],
        $_POST['reply_message']
    );
    
    if (strpos($result, 'successfully') !== false) {
        $success_msg = $result;
    } else {
        $error_msg = $result;
    }
}

// Handle message deletion
if (isset($_GET['delete'])) {
    if ($messageManager->deleteMessage($_GET['delete'])) {
        header('location:messages.php');
        exit;
    } else {
        $error_msg = "Failed to delete message.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Messages Management</title>
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
            padding: 20px;
            text-align: center;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            transition: transform 0.3s;
        }
        .box-users:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 12px rgba(0,0,0,0.15);
        }
        .name {
            color: #2d3e40;
            margin-bottom: 10px;
            font-weight: 500;
        }
        .option-btn, .delete-btn {
            display: inline-block;
            padding: 8px 15px;
            margin: 5px;
            border-radius: 5px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s;
            cursor: pointer;
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
        .badge-success {
            display: inline-block;
            padding: 5px 10px;
            background-color: #28a745;
            color: white;
            border-radius: 4px;
            margin-bottom: 10px;
        }
        .reply-form {
            margin-top: 20px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 10px;
            border: 1px solid #e9ecef;
        }
        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid #ced4da;
            border-radius: 5px;
            margin-bottom: 10px;
        }
        .alert {
            max-width: 600px;
            margin: 20px auto;
            border-radius: 10px;
        }
        .alert-success {
            color: #155724;
            background-color: #d4edda;
            border-color: #c3e6cb;
        }
        .alert-error {
            color: #721c24;
            background-color: #f8d7da;
            border-color: #f5c6cb;
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
            <i class="fa regular fa-envelope" style="font-size:30px; color:white;"></i>
        </button>
    </div>

    <div class="content">
        <section class="add-products">
            <h1 class="title">Messages from Users</h1>
        </section>

        <!-- Display success/error messages -->
        <?php if (!empty($success_msg)): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success_msg); ?></div>
        <?php endif; ?>
        
        <?php if (!empty($error_msg)): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error_msg); ?></div>
        <?php endif; ?>

        <section class="show-products">
            <div class="box-container">
                <?php
                $messages = $messageManager->getAllMessages();
                if (mysqli_num_rows($messages) > 0) {
                    while ($message = mysqli_fetch_assoc($messages)) {
                ?>
                <div class="box-users">
                    <div class="name"><?php echo "Name: ", htmlspecialchars($message['name']); ?></div>
                    <div class="name"><?php echo "Email: ", htmlspecialchars($message['email']); ?></div>
                    <div class="name"><?php echo "Contact: ", htmlspecialchars($message['number']); ?></div>
                    <div class="name"><?php echo "Message: ", htmlspecialchars($message['message']); ?></div>
                    
                    <?php if (isset($message['replied']) && $message['replied'] == 1): ?>
                        <div class="badge-success">Replied</div>
                    <?php endif; ?>
                    
                    <!-- Reply Button -->
                    <button class="option-btn" onclick="showReplyForm(<?php echo $message['id']; ?>)">Reply</button>
                    <a href="messages.php?delete=<?php echo $message['id']; ?>" class="delete-btn" onclick="return confirm('Are you sure you want to delete this message?');">Delete</a>

                    <!-- Hidden Reply Form -->
                    <div id="reply-form-<?php echo $message['id']; ?>" class="reply-form" style="display:none;">
                        <h4>Reply to <?php echo htmlspecialchars($message['name']); ?></h4>
                        <form method="POST" action="">
                            <input type="hidden" name="message_id" value="<?php echo $message['id']; ?>">
                            <input type="hidden" name="email" value="<?php echo htmlspecialchars($message['email']); ?>">
                            <input type="hidden" name="name" value="<?php echo htmlspecialchars($message['name']); ?>">
                            
                            <textarea name="reply_message" class="form-control" rows="4" placeholder="Enter your reply" required></textarea>
                            <br>
                            <button type="submit" name="reply_submit" class="option-btn">Send Reply</button>
                        </form>
                    </div>
                </div>
                <?php
                    }
                } else {
                    echo '<p class="empty">No messages yet!</p>';
                }
                ?>
            </div>
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
        
        // Function to show the reply form
        function showReplyForm(id) {
            var form = document.getElementById('reply-form-' + id);
            if (form.style.display === 'none') {
                form.style.display = 'block';
            } else {
                form.style.display = 'none';
            }
        }
    </script>

    <script src="https://code.jquery.com/jquery-3.1.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.12.9/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.0.0/dist/js/bootstrap.min.js"></script>
</body>
</html>