<?php
require_once 'config.php';

class UserRegistration {
    private $conn;
    private $errors = [];
    
    public function __construct($db_connection) {
        $this->conn = $db_connection;
    }
    
    private function custom_hash($password) {
        $salt = 'abc123!@#'; 
        $hashed = '';
        for ($i = 0; $i < strlen($password); $i++) {
            $hashed .= dechex(ord($password[$i]) + ord($salt[$i % strlen($salt)]));
        }
        return $hashed;
    }
    
    private function validateInput($data) {
        if (empty($data['username']) || empty($data['email']) || empty($data['number']) || 
            empty($data['password']) || empty($data['confirm_password'])) {
            $this->errors[] = "Please fill all the details.";
            return false;
        }
        
        if ($data['password'] !== $data['confirm_password']) {
            $this->errors[] = "Passwords do not match.";
            return false;
        }
        
        return true;
    }
    
    private function checkExistingUser($username, $email) {
        $check_sql = "SELECT * FROM user_details WHERE username = ? OR email = ?";
        $check_stmt = $this->conn->prepare($check_sql);
        $check_stmt->bind_param("ss", $username, $email);
        $check_stmt->execute();
        $result = $check_stmt->get_result();
        
        if ($result->num_rows > 0) {
            $this->errors[] = "User already registered with this email or username. Choose a different one.";
            $check_stmt->close();
            return false;
        }
        
        $check_stmt->close();
        return true;
    }
    
    public function processRegistration($post_data) {
        if (!$this->validateInput($post_data)) {
            return false;
        }
        
        $username = $post_data['username'];
        $email = $post_data['email'];
        $number = $post_data['number'];
        $password = $post_data['password'];
        $status = "Active";
        
        if (!$this->checkExistingUser($username, $email)) {
            return false;
        }
        
        $hashed_password = $this->custom_hash($password);
        
        $sql = "INSERT INTO user_details (username, email, contact_number, password, status) VALUES (?, ?, ?, ?, ?)";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("sssss", $username, $email, $number, $hashed_password, $status);
        
        if ($stmt->execute()) {
            $stmt->close();
            return true;
        } else {
            $this->errors[] = "Database error: " . $stmt->error;
            $stmt->close();
            return false;
        }
    }
    
    public function getErrors() {
        return $this->errors;
    }
    
    public function displayErrors() {
        if (!empty($this->errors)) {
            foreach ($this->errors as $error) {
                echo "<script>alert('$error');</script>";
            }
        }
    }
}

// Process the form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $registration = new UserRegistration($conn);
    
    if ($registration->processRegistration($_POST)) {
        header("location: login.php");
        exit();
    } else {
        $registration->displayErrors();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.3.0/css/all.min.css">
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
    <title>BloomNest - Register</title>
    <style>
        :root {
            --primary: #4e54c8;
            --primary-light: #8f94fb;
            --accent: #ff6b6b;
            --dark: #2c2c54;
            --light: #f7f7f7;
            --success: #38c172;
            --error: #e74c3c;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }
        
        body {
            min-height: 100vh;
            display: flex;
            background-color: var(--light);
        }
        
        .container {
            display: flex;
            width: 100%;
            min-height: 100vh;
        }
        
        .illustration-section {
            flex: 1;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 2rem;
            color: white;
            position: relative;
            overflow: hidden;
        }
        
        .illustration-section::before {
            content: '';
            position: absolute;
            width: 100%;
            height: 100%;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 1440 320'%3E%3Cpath fill='%23ffffff' fill-opacity='0.1' d='M0,128L48,117.3C96,107,192,85,288,112C384,139,480,213,576,218.7C672,224,768,160,864,138.7C960,117,1056,139,1152,149.3C1248,160,1344,160,1392,160L1440,160L1440,320L1392,320C1344,320,1248,320,1152,320C1056,320,960,320,864,320C768,320,672,320,576,320C480,320,384,320,288,320C192,320,96,320,48,320L0,320Z'%3E%3C/path%%3E%3C/svg%3E");
            background-size: cover;
            background-position: bottom;
            opacity: 0.2;
        }
        
        .illustration-content {
            max-width: 500px;
            z-index: 1;
            text-align: center;
        }
        
        .illustration-content h1 {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            font-weight: 700;
        }
        
        .illustration-content p {
            font-size: 1.1rem;
            margin-bottom: 2rem;
            opacity: 0.9;
        }
        
        .graphic {
            width: 100%;
            max-width: 400px;
            margin: 2rem 0;
        }
        
        .form-section {
            flex: 1;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 2rem;
            background-color: white;
        }
        
        .form-container {
            width: 100%;
            max-width: 450px;
            padding: 2.5rem;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
            background-color: white;
        }
        
        .logo {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .logo h2 {
            color: var(--primary);
            font-size: 1.8rem;
            font-weight: 700;
        }
        
        .logo p {
            color: var(--dark);
            opacity: 0.7;
            margin-top: 0.5rem;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
            position: relative;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--dark);
        }
        
        .input-with-icon {
            position: relative;
        }
        
        .input-with-icon input {
            width: 100%;
            padding: 0.875rem 1rem 0.875rem 3rem;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s ease;
        }
        
        .input-with-icon input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(78, 84, 200, 0.1);
            outline: none;
        }
        
        .input-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #aaa;
        }
        
        .error {
            color: var(--error);
            font-size: 0.875rem;
            margin-top: 0.5rem;
            display: block;
        }
        
        .btn-register {
            width: 100%;
            padding: 1rem;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 1rem;
        }
        
        .btn-register:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(78, 84, 200, 0.3);
        }
        
        .btn-register:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }
        
        .login-redirect {
            text-align: center;
            margin-top: 2rem;
            color: var(--dark);
        }
        
        .login-redirect a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
        }
        
        .login-redirect a:hover {
            text-decoration: underline;
        }
        
        @media (max-width: 900px) {
            .container {
                flex-direction: column;
            }
            
            .illustration-section {
                padding: 2rem 1rem;
                min-height: 300px;
            }
            
            .illustration-content {
                max-width: 100%;
            }
            
            .graphic {
                max-width: 250px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="illustration-section">
            <div class="illustration-content">
                <h1>Join BloomNest Today</h1>
                <p>Create an account to explore our beautiful collection of flowers and plants</p>
                <svg class="graphic" viewBox="0 0 500 300" xmlns="http://www.w3.org/2000/svg">
                    <path d="M250,50 C300,30 350,70 350,120 C350,170 300,200 250,180 C200,200 150,170 150,120 C150,70 200,30 250,50 Z" fill="#ffffff" opacity="0.8"/>
                    <path d="M250,180 L250,280" stroke="#ffffff" stroke-width="8"/>
                    <circle cx="250" cy="50" r="15" fill="#ffffff"/>
                    <circle cx="300" cy="80" r="12" fill="#ffffff"/>
                    <circle cx="200" cy="80" r="12" fill="#ffffff"/>
                    <circle cx="280" cy="130" r="10" fill="#ffffff"/>
                    <circle cx="220" cy="130" r="10" fill="#ffffff"/>
                </svg>
                <p>Already have an account? <a href="login.php" style="color: white; text-decoration: underline; font-weight: 600;">Sign In</a></p>
            </div>
        </div>
        
        <div class="form-section">
            <div class="form-container">
                <div class="logo">
                    <h2>BloomNest</h2>
                    <p>Create your account</p>
                </div>
                
                <form action="" method="post" id="registrationForm">
                    <div class="form-group">
                        <label for="username">Username</label>
                        <div class="input-with-icon">
                            <div class="input-icon">
                                <i class="fas fa-user"></i>
                            </div>
                            <input type="text" name="username" id="username" placeholder="Enter your username">
                        </div>
                        <span id="username_err" class="error"></span>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <div class="input-with-icon">
                            <div class="input-icon">
                                <i class="fas fa-envelope"></i>
                            </div>
                            <input type="email" name="email" id="email" placeholder="Enter your email">
                        </div>
                        <span id="email_err" class="error"></span>
                    </div>
                    
                    <div class="form-group">
                        <label for="number">Phone Number</label>
                        <div class="input-with-icon">
                            <div class="input-icon">
                                <i class="fas fa-phone"></i>
                            </div>
                            <input type="text" name="number" id="number" placeholder="Enter your phone number">
                        </div>
                        <span id="number_err" class="error"></span>
                    </div>
                    
                    <div class="form-group">
                        <label for="password">Password</label>
                        <div class="input-with-icon">
                            <div class="input-icon">
                                <i class="fas fa-lock"></i>
                            </div>
                            <input type="password" name="password" id="password" placeholder="Create a password">
                        </div>
                        <span id="password_err" class="error"></span>
                    </div>
                    
                    <div class="form-group">
                        <label for="cpassword">Confirm Password</label>
                        <div class="input-with-icon">
                            <div class="input-icon">
                                <i class="fas fa-lock"></i>
                            </div>
                            <input type="password" name="confirm_password" id="cpassword" placeholder="Confirm your password">
                        </div>
                        <span id="cpassword_err" class="error"></span>
                    </div>
                    
                    <button type="submit" class="btn-register" id="registerButton" disabled>Register Now</button>
                    
                    <div class="login-redirect">
                        Already have an account? <a href="login.php">Sign In</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script type="module" src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.js"></script>
    <script>
        // Function to validate the Username field
        function validateUsername(username) {
            var usernameRegex = /^[a-zA-Z][a-zA-Z0-9]*$/;
            return usernameRegex.test(username);
        }

        // Function to validate the Email field
        function validateEmail(email) {
            var emailRegex = /^[a-zA-Z0-9._%+-]+@[a-zA-Z]+\.[a-zA-Z]{2,}$/;
            return emailRegex.test(email);
        }

        // Function to validate the Phone Number field
        function validatePhoneNumber(number) {
            var numberRegex = /^(98|97)\d{8}$/;
            return numberRegex.test(number);
        }

        // Function to validate the Password field
        function validatePassword(password) {
            var passwordRegex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)[a-zA-Z\d]{8,}$/;
            return passwordRegex.test(password);
        }

        // Real-time field validation
        $(document).ready(function() {
            $("#username").on("keyup", function() {
                var username = $(this).val();
                var errorSpan = $("#username_err");
                if (validateUsername(username)) {
                    errorSpan.text("");
                    $(this).css("border-color", "#ddd");
                } else {
                    errorSpan.text("Invalid Username. Only alphabets and numbers are allowed, and must start with a letter.");
                    $(this).css("border-color", "#e74c3c");
                }
                checkFormValidity();
            });

            $("#email").on("keyup", function() {
                var email = $(this).val();
                var errorSpan = $("#email_err");
                if (validateEmail(email)) {
                    errorSpan.text("");
                    $(this).css("border-color", "#ddd");
                } else {
                    errorSpan.text("Invalid Email format.");
                    $(this).css("border-color", "#e74c3c");
                }
                checkFormValidity();
            });

            $("#number").on("keyup", function() {
                var number = $(this).val();
                var errorSpan = $("#number_err");
                if (validatePhoneNumber(number)) {
                    errorSpan.text("");
                    $(this).css("border-color", "#ddd");
                } else {
                    errorSpan.text("Invalid Phone Number. Please enter 10 digits starting with 97 or 98.");
                    $(this).css("border-color", "#e74c3c");
                }
                checkFormValidity();
            });

            $("#password, #cpassword").on("keyup", function() {
                var password = $("#password").val();
                var cpassword = $("#cpassword").val();
                var passwordErrorSpan = $("#password_err");
                var cpasswordErrorSpan = $("#cpassword_err");

                if (validatePassword(password)) {
                    passwordErrorSpan.text("");
                    $("#password").css("border-color", "#ddd");
                } else {
                    passwordErrorSpan.text("Password must be at least 8 characters and contain at least one lowercase letter, one uppercase letter, and one number.");
                    $("#password").css("border-color", "#e74c3c");
                }

                if (password === cpassword) {
                    cpasswordErrorSpan.text("");
                    $("#cpassword").css("border-color", "#ddd");
                } else {
                    cpasswordErrorSpan.text("Passwords do not match.");
                    $("#cpassword").css("border-color", "#e74c3c");
                }
                checkFormValidity();
            });

            // Enable the "Register Now" button only when all fields are valid
            function checkFormValidity() {
                var username = $("#username").val();
                var email = $("#email").val();
                var number = $("#number").val();
                var password = $("#password").val();
                var cpassword = $("#cpassword").val();
                
                var usernameValid = validateUsername(username);
                var emailValid = validateEmail(email);
                var numberValid = validatePhoneNumber(number);
                var passwordValid = validatePassword(password);
                var passwordsMatch = (password === cpassword);

                if (usernameValid && emailValid && numberValid && passwordValid && passwordsMatch) {
                    $("#registerButton").prop("disabled", false);
                } else {
                    $("#registerButton").prop("disabled", true);
                }
            }
        });
    </script>
</body>
</html>