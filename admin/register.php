<?php
require_once "config/dbconnect.php";
session_start();

// AdminRegistration class to handle registration operations
class AdminRegistration {
    private $conn;
    private $adminname;
    private $password;
    private $confirm_password;
    private $adminname_err;
    private $password_err;
    private $confirm_password_err;
    
    public function __construct($connection) {
        $this->conn = $connection;
        $this->adminname = "";
        $this->password = "";
        $this->confirm_password = "";
        $this->adminname_err = "";
        $this->password_err = "";
        $this->confirm_password_err = "";
    }
    
    public function processRegistration() {
        if ($_SERVER['REQUEST_METHOD'] == "POST") {
            $this->validateAdminName();
            $this->validatePassword();
            $this->validateConfirmPassword();
            
            if (empty($this->adminname_err) && empty($this->password_err) && empty($this->confirm_password_err)) {
                return $this->registerAdmin();
            }
        }
        return false;
    }
    
    private function validateAdminName() {
        if (empty(trim($_POST["adminname"]))) {
            $this->adminname_err = "Admin name cannot be blank";
            $this->showAlert($this->adminname_err);
            return;
        }
        
        $adminname = trim($_POST['adminname']);
        
        // Check if adminname already exists
        $sql = "SELECT id FROM admin_detail WHERE adminname = ?";
        $stmt = mysqli_prepare($this->conn, $sql);
        
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "s", $adminname);
            
            if (mysqli_stmt_execute($stmt)) {
                mysqli_stmt_store_result($stmt);
                if (mysqli_stmt_num_rows($stmt) == 1) {
                    $this->adminname_err = "Admin name is already taken. Please choose a different name.";
                    $this->showAlert($this->adminname_err);
                } else {
                    $this->adminname = $adminname;
                }
            } else {
                $this->showAlert("Something went wrong while checking admin name availability.");
            }
            mysqli_stmt_close($stmt);
        }
    }
    
    private function validatePassword() {
        if (empty(trim($_POST['password']))) {
            $this->password_err = "Password cannot be blank";
            $this->showAlert($this->password_err);
            return;
        }
        
        $password = trim($_POST['password']);
        if (strlen($password) < 5) {
            $this->password_err = "Password cannot be less than 5 characters";
            $this->showAlert($this->password_err);
            return;
        }
        
        // Additional password strength validation
        if (!$this->isPasswordStrong($password)) {
            $this->password_err = "Password must contain at least one uppercase letter, one lowercase letter, one number, and one special character";
            $this->showAlert($this->password_err);
            return;
        }
        
        $this->password = $password;
    }
    
    private function validateConfirmPassword() {
        if (empty(trim($_POST['confirm_password']))) {
            $this->confirm_password_err = "Please confirm your password";
            $this->showAlert($this->confirm_password_err);
            return;
        }
        
        $confirm_password = trim($_POST['confirm_password']);
        if ($this->password !== $confirm_password) {
            $this->confirm_password_err = "Passwords do not match";
            $this->showAlert($this->confirm_password_err);
            return;
        }
        
        $this->confirm_password = $confirm_password;
    }
    
    private function isPasswordStrong($password) {
        // Password must contain at least one uppercase letter, one lowercase letter, one number, and one special character
        $pattern = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{5,}$/';
        return preg_match($pattern, $password);
    }
    
    private function registerAdmin() {
        $sql = "INSERT INTO admin_detail (adminname, password) VALUES (?, ?)";
        $stmt = mysqli_prepare($this->conn, $sql);
        
        if ($stmt) {
            $param_adminname = $this->adminname;
            $param_password = password_hash($this->password, PASSWORD_DEFAULT);
            
            mysqli_stmt_bind_param($stmt, "ss", $param_adminname, $param_password);
            
            if (mysqli_stmt_execute($stmt)) {
                mysqli_stmt_close($stmt);
                return true;
            } else {
                $this->showAlert("Something went wrong during registration. Please try again.");
            }
            mysqli_stmt_close($stmt);
        }
        return false;
    }
    
    private function showAlert($message) {
        echo "<script>alert('" . addslashes($message) . "');</script>";
    }
    
    public function getAdminName() {
        return htmlspecialchars($this->adminname);
    }
    
    public function redirectIfLoggedIn($url = "index.php") {
        if (isset($_SESSION['adminname'])) {
            header("location: " . $url);
            exit;
        }
    }
}

// Initialize AdminRegistration
$adminRegistration = new AdminRegistration($conn);

// Redirect if already logged in
$adminRegistration->redirectIfLoggedIn();

// Process registration
if ($adminRegistration->processRegistration()) {
    header("location: login.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BloomNest - Admin Registration</title>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Serif&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.3.0/css/all.min.css">
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/1.8.3/jquery.min.js"></script>
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Noto Serif', serif;
        }
        
        body {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background: linear-gradient(135deg, #ff7eb9 0%, #7fc96b 100%);
            padding: 20px;
        }
        
        .wrapper-right {
            background: white;
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 450px;
            position: relative;
        }
        
        .signup {
            position: absolute;
            top: 20px;
            right: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .signup p {
            color: #6c757d;
            margin: 0;
        }
        
        .signup-btn {
            padding: 8px 15px;
            border: 2px solid #ff7eb9;
            border-radius: 20px;
            background: white;
            color: #ff7eb9;
            cursor: pointer;
            transition: all 0.3s;
            font-weight: 500;
        }
        
        .signup-btn:hover {
            background: #ff7eb9;
            color: white;
        }
        
        .back a {
            color: #6c757d;
            font-size: 20px;
            text-decoration: none;
            transition: color 0.3s;
        }
        
        .back a:hover {
            color: #ff7eb9;
        }
        
        .title {
            margin-bottom: 30px;
            text-align: center;
        }
        
        .title h1 {
            color: #2d3e40;
            font-size: 28px;
            margin-bottom: 10px;
        }
        
        .title p {
            color: #7a7a7a;
            font-size: 16px;
        }
        
        .form-card {
            margin-bottom: 25px;
            text-align: left;
        }
        
        .label {
            display: block;
            margin-bottom: 8px;
            color: #2d3e40;
            font-weight: 500;
        }
        
        .input-box {
            position: relative;
        }
        
        .input-box input {
            width: 100%;
            padding: 15px 15px 15px 45px;
            border: 2px solid #e6e6e6;
            border-radius: 10px;
            font-size: 16px;
            transition: all 0.3s;
            outline: none;
        }
        
        .input-box input:focus {
            border-color: #ff7eb9;
            box-shadow: 0 0 0 2px rgba(255, 126, 185, 0.2);
        }
        
        .input-box ion-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 20px;
            color: #7a7a7a;
        }
        
        .login-btn {
            width: 100%;
            padding: 15px;
            border: none;
            border-radius: 10px;
            background: linear-gradient(135deg, #ff7eb9 0%, #7fc96b 100%);
            color: white;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .login-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }
        
        .password-strength {
            margin-top: 5px;
            font-size: 12px;
            color: #6c757d;
        }
        
        @media (max-width: 480px) {
            .wrapper-right {
                padding: 30px 20px;
            }
            
            .title h1 {
                font-size: 24px;
            }
            
            .signup {
                position: static;
                justify-content: center;
                margin-bottom: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="wrapper-right">
        <div class="signup">
            <p>Already have an account?</p>
            <button class="signup-btn" onclick="location.href='login.php'">Login</button>
            <div class="back">
                <a href="index.php"><i class="fas fa-times"></i></a>
            </div>
        </div>

        <div class="title">
            <h1>Welcome,</h1>
            <p>Register as an admin for BloomNest</p>
        </div>
        
        <form action="" method="post" onsubmit="return validateRegistrationForm()">
            <div class="form-card">
                <span class="label">Admin Name</span>
                <div class="input-box">
                    <input type="text" name="adminname" id="adminname" placeholder="Admin Name" 
                           value="<?php echo $adminRegistration->getAdminName(); ?>" required>
                    <ion-icon name="person-outline"></ion-icon>
                </div>
            </div>

            <div class="form-card">
                <span class="label">Password</span>
                <div class="input-box">
                    <input type="password" name="password" id="password" placeholder="Password" required>
                    <ion-icon name="lock-closed-outline"></ion-icon>
                </div>
                <div class="password-strength">
                    Must contain uppercase, lowercase, number, and special character
                </div>
            </div>
            
            <div class="form-card">
                <span class="label">Confirm Password</span>
                <div class="input-box">
                    <input type="password" name="confirm_password" id="cpassword" placeholder="Re-Type your Password" required>
                    <ion-icon name="lock-closed-outline"></ion-icon>
                </div>
            </div>
            
            <input type="submit" class="login-btn" value="Register Now">
        </form>
    </div>

    <script>
        function validateRegistrationForm() {
            // Admin name validation: must not start with a number
            const adminName = document.getElementById('adminname').value.trim();
            const nameRegex = /^[^\d][\w\s]*$/; // Ensures the name doesn't start with a number
            if (!nameRegex.test(adminName)) {
                alert("Admin name must not start with a number.");
                return false;
            }

            // Password validation: must be strong
            const password = document.getElementById('password').value;
            const passwordRegex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{5,}$/;
            if (!passwordRegex.test(password)) {
                alert("Password must contain at least one uppercase letter, one lowercase letter, one number, and one special character.");
                return false;
            }

            // Confirm password validation
            const confirmPassword = document.getElementById('cpassword').value;
            if (password !== confirmPassword) {
                alert("Passwords do not match.");
                return false;
            }

            return true; // If all validations pass
        }
    </script>

    <script type="module" src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.js"></script>
</body>
</html>