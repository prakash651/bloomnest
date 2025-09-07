<?php
session_start();

// AdminAuth class to handle authentication operations
class AdminAuth {
    private $conn;
    
    public function __construct($connection) {
        $this->conn = $connection;
    }
    
    // Check if admin is already logged in
    public function isLoggedIn() {
        return isset($_SESSION["adminname"]);
    }
    
    // Redirect if already logged in
    public function redirectIfLoggedIn($url = "dashboard.php") {
        if ($this->isLoggedIn()) {
            header("location: " . $url);
            exit;
        }
    }
    
    // Validate admin credentials
    public function validateCredentials($adminname, $password) {
        // Input validation
        if (empty($adminname) || empty($password)) {
            return "Please enter Admin Name and Password";
        }
        
        if (!$this->validateAdminName($adminname)) {
            return "Admin name must not start with a number.";
        }
        
        // Database validation
        $sql = "SELECT id, admin_name, admin_password FROM admin_detail WHERE admin_name = ?";
        $stmt = mysqli_prepare($this->conn, $sql);
        
        if (!$stmt) {
            return "Database error: " . mysqli_error($this->conn);
        }
        
        mysqli_stmt_bind_param($stmt, "s", $adminname);
        
        if (!mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            return "Database error: " . mysqli_error($this->conn);
        }
        
        mysqli_stmt_store_result($stmt);
        
        if (mysqli_stmt_num_rows($stmt) != 1) {
            mysqli_stmt_close($stmt);
            return "This admin is not registered.";
        }
        
        mysqli_stmt_bind_result($stmt, $id, $db_adminname, $hashed_password);
        mysqli_stmt_fetch($stmt);
        mysqli_stmt_close($stmt);
        
        // Verify password
        if (!password_verify($password, $hashed_password)) {
            return "Admin Name and Password did not match.";
        }
        
        // Set session variables
        $_SESSION["adminname"] = $db_adminname;
        $_SESSION["id"] = $id;
        $_SESSION["logged_in"] = true;
        
        return true; // Success
    }
    
    // Validate admin name format
    private function validateAdminName($adminname) {
        $adminname = trim($adminname);
        $nameRegex = '/^[^\d][\w\s]*$/'; // Ensures the name doesn't start with a number
        return preg_match($nameRegex, $adminname);
    }
    
    // Validate password format (commented out but available if needed)
    private function validatePassword($password) {
        $password = trim($password);
        $passwordRegex = '/^(?=.*[A-Z])(?=.*[!@#$%^&*])[A-Za-z\d!@#$%^&*]{8,}$/';
        return preg_match($passwordRegex, $password);
    }
}

// Database connection
require_once "config/dbconnect.php";

// Initialize AdminAuth
$adminAuth = new AdminAuth($conn);

// Redirect if already logged in
$adminAuth->redirectIfLoggedIn();

// Process login form
$error = "";
if ($_SERVER['REQUEST_METHOD'] == "POST") {
    $adminname = trim($_POST['admin_name'] ?? '');
    $password = trim($_POST['password'] ?? '');
    
    $result = $adminAuth->validateCredentials($adminname, $password);
    
    if ($result === true) {
        header("location: dashboard.php");
        exit;
    } else {
        $error = $result;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="assets/css/lstyle.css">
    <link href="https://fonts.googleapis.com/css2?family=Noto+Serif&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.3.0/css/all.min.css">
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/1.8.3/jquery.min.js"></script>
    <title>Flower Shop - Admin Login</title>
    
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
            text-align: center;
        }
        
        .title {
            margin-bottom: 30px;
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
        
        .error-message {
            color: #ff6666;
            margin-bottom: 20px;
            padding: 10px;
            background: #fff0f0;
            border-radius: 5px;
            border-left: 4px solid #ff6666;
        }
        
        @media (max-width: 480px) {
            .wrapper-right {
                padding: 30px 20px;
            }
            
            .title h1 {
                font-size: 24px;
            }
        }
    </style>
</head>
<body>
<div class="wrapper-right">
    <div class="title">
        <h1>Welcome Back,</h1>
        <p>Sign In to your admin account</p>
    </div>
    
    <?php if (!empty($error)): ?>
        <div class="error-message">
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>
    
    <form action="" method="post" onsubmit="return validateLoginForm()">
        <div class="form-card">
            <span class="label">Admin Name</span>
            <div class="input-box">
                <input type="text" id="adminname" name="admin_name" placeholder="Enter Admin Name" required
                       value="<?php echo htmlspecialchars($_POST['admin_name'] ?? ''); ?>">
                <ion-icon name="person-outline"></ion-icon>
            </div>
        </div>
        <div class="form-card">
            <span class="label">Password</span>
            <div class="input-box">
                <input type="password" name="password" id="password" placeholder="Enter Admin Password" required>
                <ion-icon name="lock-closed-outline"></ion-icon>
            </div>
        </div>
        <input type="submit" value="Login" class="login-btn">
    </form>
</div>

<script>
    function validateLoginForm() {
        // Admin name validation: must not start with a number
        const adminName = document.getElementById('adminname').value.trim();
        const nameRegex = /^[^\d][\w\s]*$/; // Ensures the name doesn't start with a number
        if (!nameRegex.test(adminName)) {
            alert("Admin name must not start with a number.");
            return false;
        }

        // Password validation (commented out but available if needed)
        // const password = document.getElementById('password').value.trim();
        // const passwordRegex = /^(?=.*[A-Z])(?=.*[!@#$%^&*])[A-Za-z\d!@#$%^&*]{8,}$/;
        // if (!passwordRegex.test(password)) {
        //     alert("Password must be at least 8 characters long, contain at least one capital letter, and one special character.");
        //     return false;
        // }

        return true; // If both validations pass
    }
</script>

</body>
<script type="module" src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.esm.js"></script>
<script nomodule src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.js"></script>
</html>