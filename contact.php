<?php
session_start();
include 'config.php';

class ContactManager {
    private $conn;
    private $user_id;
    private $user_name;
    private $user_email;
    private $is_logged_in;
    private $successMessage = '';
    private $errorMessage = '';
    
    public function __construct($connection) {
        $this->conn = $connection;
        $this->is_logged_in = isset($_SESSION['id']);
        
        if ($this->is_logged_in) {
            $this->user_id = $_SESSION['id'];
            $this->loadUserData();
        } else {
            $this->user_id = null;
            $this->user_name = 'Guest';
            $this->user_email = '';
        }
    }
    
    private function loadUserData() {
        $query = "SELECT username, email FROM user_details WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $this->user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($user_data = $result->fetch_assoc()) {
            $this->user_name = $user_data['username'] ?? 'Guest';
            $this->user_email = $user_data['email'] ?? '';
        }
    }
    
    public function processContactForm($postData) {
        if (!$this->is_logged_in) {
            $this->errorMessage = "You must be logged in to send a message.";
            return false;
        }
        
        $name = trim($postData['name']);
        $email = trim($postData['email']);
        $number = trim($postData['number']);
        $message = trim($postData['message']);
        
        // Server-side validation
        if (!$this->validateName($name)) {
            $this->errorMessage = "Invalid name format.";
            return false;
        }
        
        if (!$this->validateEmail($email)) {
            $this->errorMessage = "Invalid email format. Please use a Gmail address.";
            return false;
        }
        
        if (!$this->validatePhoneNumber($number)) {
            $this->errorMessage = "Invalid phone number. Must start with 97 or 98 and be 10 digits total.";
            return false;
        }
        
        if ($email !== $this->user_email) {
            $this->errorMessage = "Email must match your registered account email.";
            return false;
        }
        
        // Insert into messages table
        return $this->saveMessage($name, $email, $number, $message);
    }
    
    private function validateName($name) {
        return preg_match("/^[^\d][a-zA-Z\s]*$/", $name);
    }
    
    private function validateEmail($email) {
        return preg_match("/^[^\d][a-zA-Z0-9._%+-]+@gmail\.com$/", $email);
    }
    
    private function validatePhoneNumber($number) {
        return preg_match("/^(97|98)[0-9]{8}$/", $number);
    }
    
    private function saveMessage($name, $email, $number, $message) {
        $query = "INSERT INTO message (user_id, name, email, number, message) VALUES (?, ?, ?, ?, ?)";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("issss", $this->user_id, $name, $email, $number, $message);
        
        if ($stmt->execute()) {
            $this->successMessage = "Message sent successfully!";
            return true;
        } else {
            $this->errorMessage = "There was an error saving your message.";
            return false;
        }
    }
    
    public function isLoggedIn() {
        return $this->is_logged_in;
    }
    
    public function getUserName() {
        return $this->user_name;
    }
    
    public function getUserEmail() {
        return $this->user_email;
    }
    
    public function getSuccessMessage() {
        return $this->successMessage;
    }
    
    public function getErrorMessage() {
        return $this->errorMessage;
    }
    
    public function getPostValue($field) {
        return isset($_POST[$field]) ? htmlspecialchars($_POST[$field]) : '';
    }
}

// Initialize ContactManager
$contactManager = new ContactManager($conn);

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['send'])) {
    $contactManager->processContactForm($_POST);
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Us - BloomNest</title>

    <!-- Font Awesome CDN link -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <!-- Custom CSS file link -->
    <link rel="stylesheet" href="css/style.css">

    <style>
        :root {
            --primary: #8e44ad;
            --primary-dark: #6c3483;
            --primary-light: #e8d6f3;
            --accent: #ff6b8b;
            --accent-light: #ffd6de;
            --text: #333;
            --text-light: #777;
            --white: #ffffff;
            --light-bg: #f9f3f3;
        }

        body {
            background: linear-gradient(135deg, var(--light-bg) 0%, #f2e6ff 100%);
            min-height: 100vh;
            font-family: 'Poppins', sans-serif;
        }

        .contact-section {
            padding: 5rem 0;
            display: flex;
            align-items: center;
            position: relative;
            overflow: hidden;
        }

        .contact-section::before {
            content: '';
            position: absolute;
            top: -50px;
            right: -50px;
            width: 200px;
            height: 200px;
            background: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="%238e44ad22"><path d="M12,2C6.5,2,2,6.5,2,12s4.5,10,10,10s10-4.5,10-10S17.5,2,12,2z M12,20c-4.4,0-8-3.6-8-8s3.6-8,8-8s8,3.6,8-8S16.4,20,12,20z"/></svg>');
            background-repeat: no-repeat;
            opacity: 0.3;
        }

        .contact-section::after {
            content: '';
            position: absolute;
            bottom: -50px;
            left: -50px;
            width: 300px;
            height: 300px;
            background: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="%23ff6b8b22"><path d="M12,21.35L10.55,20.03C5.4,15.36 2,12.28 2,8.5C2,5.42 4.42,3 7.5,3C9.24,3 10.91,3.81 12,5.09C13.09,3.81 14.76,3 16.5,3C19.58,3 22,5.42 22,8.5C22,12.28 18.6,15.36 13.45,20.03L12,21.35Z"/></svg>');
            background-repeat: no-repeat;
            opacity: 0.2;
        }

        .contact-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem;
            position: relative;
            z-index: 1;
            width: 100%;
        }

        .contact-heading {
            text-align: center;
            margin-bottom: 3rem;
            position: relative;
        }

        .contact-heading h1 {
            font-size: 3.1rem;
            color: var(--primary-dark);
            margin-bottom: 1rem;
            font-weight: 800;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.1);
            position: relative;
            display: inline-block;
        }

        .contact-heading h1::after {
            content: '';
            position: absolute;
            bottom: -10px;
            left: 50%;
            transform: translateX(-50%);
            width: 80px;
            height: 4px;
            background: linear-gradient(to right, var(--primary), var(--accent));
            border-radius: 2px;
        }

        .contact-heading p {
            color: var(--text-light);
            font-size: 2.1rem;
            margin-top: 1.5rem;
        }

        .contact-heading p a {
            color: var(--primary);
            text-decoration: none;
            transition: all 0.3s;
            font-weight: 600;
            position: relative;
        }

        .contact-heading p a::before {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 0;
            height: 2px;
            background: var(--accent);
            transition: width 0.3s;
        }

        .contact-heading p a:hover {
            color: var(--accent);
        }

        .contact-heading p a:hover::before {
            width: 100%;
        }

        .contact-form-container {
            background: var(--white);
            border-radius: 20px;
            box-shadow: 0 15px 50px rgba(0, 0, 0, 0.1);
            padding: 3rem;
            width: 90%;
            max-width: 900px;
            margin: 0 auto;
            position: relative;
            overflow: hidden;
            transition: transform 0.3s, box-shadow 0.3s;
        }

        .contact-form-container:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
        }

        .contact-form-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 5px;
            background: linear-gradient(to right, var(--primary), var(--accent));
        }

        .contact-form {
            width: 100%;
        }

        .contact-form h2 {
            text-align: center;
            color: var(--primary-dark);
            margin-bottom: 2.5rem;
            font-size: 2.5rem;
            font-weight: 700;
            position: relative;
        }

        .contact-form h2::after {
            content: '';
            position: absolute;
            bottom: -10px;
            left: 50%;
            transform: translateX(-50%);
            width: 50px;
            height: 3px;
            background: var(--accent);
            border-radius: 2px;
        }

        .form-group {
            margin-bottom: 2rem;
            position: relative;
        }

        .form-input {
            width: 100%;
            padding: 18px 25px;
            border: 2px solid #e6e6e6;
            border-radius: 12px;
            font-size: 2.1rem;
            transition: all 0.3s;
            background: #fafafa;
            color: var(--text);
            font-weight: 500;
        }

        .form-input:focus {
            outline: none;
            border-color: var(--primary);
            background: var(--white);
            box-shadow: 0 0 0 4px rgba(142, 68, 173, 0.1);
        }

        .form-input.error {
            border-color: var(--accent);
            animation: shake 0.5s;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }

        textarea.form-input {
            min-height: 180px;
            resize: vertical;
        }

        .form-group i {
            position: absolute;
            right: 20px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--primary);
            font-size: 1.5rem;
            opacity: 0.7;
        }

        textarea + i {
            top: 25px;
            transform: none;
        }

        .error-message {
            color: var(--accent);
            font-size: 1.5rem;
            margin-top: 0.5rem;
            display: block;
            font-weight: 500;
            padding: 10px;
            background-color: #ffebee;
            border-radius: 8px;
            text-align: center;
        }

        .success-message {
            background: linear-gradient(135deg, #2ecc71, #27ae60);
            color: var(--white);
            padding: 15px 20px;
            border-radius: 12px;
            margin: 1.5rem 0;
            text-align: center;
            font-weight: 600;
            box-shadow: 0 5px 15px rgba(46, 204, 113, 0.3);
            animation: fadeIn 0.5s;
        }

        .login-prompt {
            text-align: center;
            padding: 30px;
            background-color: #f9f9f9;
            border-radius: 12px;
            margin: 20px 0;
        }

        .login-prompt p {
            font-size: 2.0rem;
            margin-bottom: 20px;
            color: var(--text);
        }

        .login-prompt .btn {
            margin: 0 10px;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .btn-submit {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: var(--white);
            border: none;
            padding: 18px 30px;
            border-radius: 12px;
            font-size: 2.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            width: 100%;
            text-transform: uppercase;
            letter-spacing: 1px;
            position: relative;
            overflow: hidden;
        }

        .btn-submit::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: 0.5s;
        }

        .btn-submit:hover {
            background: linear-gradient(135deg, var(--primary-dark) 0%, #5a287d 100%);
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(142, 68, 173, 0.4);
        }

        .btn-submit:hover::before {
            left: 100%;
        }

        .btn-submit:disabled {
            background: #cccccc;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .btn-submit:disabled:hover::before {
            left: -100%;
        }

        .decoration {
            position: absolute;
            z-index: 0;
        }

        .decoration-1 {
            top: 10%;
            right: 5%;
            font-size: 5rem;
            color: var(--primary-light);
            opacity: 0.5;
        }

        .decoration-2 {
            bottom: 15%;
            left: 5%;
            font-size: 4rem;
            color: var(--accent-light);
            opacity: 0.5;
        }

        @media (max-width: 992px) {
            .contact-form-container {
                width: 85%;
                max-width: 800px;
            }
        }

        @media (max-width: 768px) {
            .contact-form-container {
                width: 90%;
                padding: 2rem;
            }
            
            .contact-heading h1 {
                font-size: 2.2rem;
            }
            
            .contact-form h2 {
                font-size: 1.6rem;
            }
            
            .form-input {
                padding: 15px 20px;
                font-size: 1rem;
            }
            
            .btn-submit {
                padding: 15px 25px;
                font-size: 1rem;
            }
            
            .decoration-1, .decoration-2 {
                display: none;
            }
        }

        @media (max-width: 576px) {
            .contact-form-container {
                width: 95%;
                padding: 1.5rem;
            }
            
            .contact-heading h1 {
                font-size: 2rem;
            }
            
            .contact-form h2 {
                font-size: 1.4rem;
            }
            
            .form-input {
                padding: 12px 15px;
            }
            
            .login-prompt {
                padding: 20px;
            }
            
            .login-prompt p {
                font-size: 1rem;
            }
        }
    </style>
</head>

<body>
<?php include 'header.php'; ?>

    <section class="contact-section">
        <div class="decoration decoration-1">
            <i class="fas fa-leaf"></i>
        </div>
        <div class="decoration decoration-2">
            <i class="fas fa-heart"></i>
        </div>
        
        <div class="contact-container">
            <div class="contact-heading">
                <h1>Contact Us</h1>
                <p><a href="index.php">Home</a> / Contact</p>
            </div>
            
            <div class="contact-form-container">
                <form id="contactForm" action="" method="post" class="contact-form">
                    <h2>Say Something!</h2>
                    
                    <?php if (!$contactManager->isLoggedIn()): ?>
                        <div class="login-prompt">
                            <p>You need to be logged in to send us a message.</p>
                            <a href="login.php" class="btn">Login Now</a>
                            <a href="register.php" class="btn">Register</a>
                        </div>
                    <?php endif; ?>
                    
                    <div class="form-group">
                        <input type="text" name="name" id="name" required placeholder="Enter your name" 
                               class="form-input <?php if($contactManager->getErrorMessage() && strpos($contactManager->getErrorMessage(),'name')!==false) echo 'error'; ?>" 
                               value="<?php echo $contactManager->isLoggedIn() ? htmlspecialchars($contactManager->getUserName()) : $contactManager->getPostValue('name'); ?>"
                               <?php if (!$contactManager->isLoggedIn()) echo 'disabled'; ?>>
                        <i class="fas fa-user"></i>
                    </div>
                    
                    <div class="form-group">
                        <input type="email" name="email" id="email" required placeholder="Enter your email" 
                               class="form-input <?php if($contactManager->getErrorMessage() && (strpos($contactManager->getErrorMessage(),'email')!==false || strpos($contactManager->getErrorMessage(),'Email')!==false)) echo 'error'; ?>" 
                               value="<?php echo $contactManager->isLoggedIn() ? htmlspecialchars($contactManager->getUserEmail()) : $contactManager->getPostValue('email'); ?>"
                               <?php if (!$contactManager->isLoggedIn()) echo 'disabled'; ?>>
                        <i class="fas fa-envelope"></i>
                    </div>
                    
                    <div class="form-group">
                        <input type="text" name="number" id="number" required placeholder="Enter your number" 
                               class="form-input <?php if($contactManager->getErrorMessage() && strpos($contactManager->getErrorMessage(),'phone')!==false) echo 'error'; ?>" 
                               value="<?php echo $contactManager->getPostValue('number'); ?>"
                               <?php if (!$contactManager->isLoggedIn()) echo 'disabled'; ?>>
                        <i class="fas fa-phone"></i>
                    </div>
                    
                    <div class="form-group">
                        <textarea name="message" class="form-input" placeholder="Enter your message" id="message" cols="30" rows="10"
                                  <?php if (!$contactManager->isLoggedIn()) echo 'disabled'; ?>><?php echo $contactManager->getPostValue('message'); ?></textarea>
                        <i class="fas fa-comment"></i>
                    </div>
                    
                    <button type="submit" name="send" class="btn-submit" <?php if (!$contactManager->isLoggedIn()) echo 'disabled'; ?>>
                        <i class="fas fa-paper-plane"></i> Send Message
                    </button>
                    
                    <?php if ($contactManager->getSuccessMessage()): ?>
                        <div class="success-message"><?php echo $contactManager->getSuccessMessage(); ?></div>
                    <?php endif; ?>
                    
                    <?php if ($contactManager->getErrorMessage()): ?>
                        <div class="error-message"><?php echo $contactManager->getErrorMessage(); ?></div>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </section>

<?php include 'footer.php'; ?>
<script src="js/script.js"></script>
</body>
</html>