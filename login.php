<?php
session_start();

// check if the user is already logged in
if (isset($_SESSION['username'])) {
    header("location: index.php");
    exit;
}

require_once "config.php";

function custom_hash($password) {
    $salt = 'abc123!@#';
    $hashed = '';
    for ($i = 0; $i < strlen($password); $i++) {
        $hashed .= dechex(ord($password[$i]) + ord($salt[$i % strlen($salt)]));
    }
    return $hashed;
}

$username = $password = "";
$err = "";

// if request method is post
if ($_SERVER['REQUEST_METHOD'] == "POST") {
    if (empty(trim($_POST['username'])) || empty(trim($_POST['password']))) {
        $err = "Please enter username and password";
        echo "<script>alert('$err');</script>";
    } else {
        $username = trim($_POST['username']);
        $password = trim($_POST['password']);
    }

    if (empty($err)) {
        $sql = "SELECT id, username, password, status FROM user_details WHERE username = ?";
        $stmt = mysqli_prepare($conn, $sql);
        $param_username = $username;
        mysqli_stmt_bind_param($stmt, "s", $param_username);

        // Try to execute this statement
        if (mysqli_stmt_execute($stmt)) {
            mysqli_stmt_store_result($stmt);
            if (mysqli_stmt_num_rows($stmt) == 1) {
                mysqli_stmt_bind_result($stmt, $id, $username, $stored_hashed_password, $status);
                if (mysqli_stmt_fetch($stmt)) {
                    if ($status === 'Active') {
                        // Compare the custom hashed password
                        if (custom_hash($password) === $stored_hashed_password) {
                            // Password is correct, allow login
                            session_start();
                            $_SESSION["username"] = $username;
                            $_SESSION["id"] = $id;
                            $_SESSION["loggedin"] = true;

                            // Redirect user to welcome page
                            header("location: index.php");
                        } else {
                            $err = "Username and password do not match.";
                        }
                    } else {
                        $err = "Your account is blocked due to some activity.";
                    }
                }
            } else {
                $err = "This user is not registered.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.3.0/css/all.min.css">
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
    <title>BloomNest - Login</title>
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
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 1440 320'%3E%3Cpath fill='%23ffffff' fill-opacity='0.1' d='M0,128L48,117.3C96,107,192,85,288,112C384,139,480,213,576,218.7C672,224,768,160,864,138.7C960,117,1056,139,1152,149.3C1248,160,1344,160,1392,160L1440,160L1440,320L1392,320C1344,320,1248,320,1152,320C1056,320,960,320,864,320C768,320,672,320,576,320C480,320,384,320,288,320C192,320,96,320,48,320L0,320Z'%3E%3C/path%3E%3C/svg%3E");
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
        
        .error-message {
            color: var(--error);
            font-size: 0.875rem;
            margin-top: 0.5rem;
            display: <?php echo !empty($err) ? 'block' : 'none'; ?>;
            padding: 0.5rem;
            background-color: rgba(231, 76, 60, 0.1);
            border-radius: 4px;
            border-left: 3px solid var(--error);
        }
        
        .btn-login {
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
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(78, 84, 200, 0.3);
        }
        
        .form-links {
            display: flex;
            justify-content: space-between;
            margin-top: 1rem;
        }
        
        .form-links a {
            color: var(--primary);
            text-decoration: none;
            font-size: 0.9rem;
            transition: color 0.3s ease;
        }
        
        .form-links a:hover {
            text-decoration: underline;
            color: var(--primary-light);
        }
        
        .register-redirect {
            text-align: center;
            margin-top: 2rem;
            color: var(--dark);
        }
        
        .register-redirect a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
        }
        
        .register-redirect a:hover {
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
            
            .form-links {
                flex-direction: column;
                gap: 0.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="illustration-section">
            <div class="illustration-content">
                <h1>Welcome Back to BloomNest</h1>
                <p>Sign in to continue your journey with our beautiful collection</p>
                <svg class="graphic" viewBox="0 0 500 300" xmlns="http://www.w3.org/2000/svg">
                    <path d="M250,50 C300,30 350,70 350,120 C350,170 300,200 250,180 C200,200 150,170 150,120 C150,70 200,30 250,50 Z" fill="#ffffff" opacity="0.8"/>
                    <path d="M250,180 L250,280" stroke="#ffffff" stroke-width="8"/>
                    <circle cx="250" cy="50" r="15" fill="#ffffff"/>
                    <circle cx="300" cy="80" r="12" fill="#ffffff"/>
                    <circle cx="200" cy="80" r="12" fill="#ffffff"/>
                    <circle cx="280" cy="130" r="10" fill="#ffffff"/>
                    <circle cx="220" cy="130" r="10" fill="#ffffff"/>
                </svg>
                <p>Don't have an account? <a href="register.php" style="color: white; text-decoration: underline; font-weight: 600;">Sign Up</a></p>
            </div>
        </div>
        
        <div class="form-section">
            <div class="form-container">
                <div class="logo">
                    <h2>BloomNest</h2>
                    <p>Sign in to your account</p>
                </div>
                
                <?php if (!empty($err)): ?>
                    <div class="error-message">
                        <?php echo $err; ?>
                    </div>
                <?php endif; ?>
                
                <form action="login.php" method="POST">
                    <div class="form-group">
                        <label for="username">Username</label>
                        <div class="input-with-icon">
                            <div class="input-icon">
                                <i class="fas fa-user"></i>
                            </div>
                            <input type="text" id="username" name="username" placeholder="Enter your username" required value="<?php echo htmlspecialchars($username); ?>">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="password">Password</label>
                        <div class="input-with-icon">
                            <div class="input-icon">
                                <i class="fas fa-lock"></i>
                            </div>
                            <input type="password" id="password" name="password" placeholder="Enter your password" required>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn-login">Login Now</button>
                    
                    <div class="form-links">
                        <a href="forgot-password.php">Forgot Password?</a>
                        <a href="register.php">Create Account</a>
                    </div>
                </form>
                
                <div class="register-redirect">
                    Don't have an account? <a href="register.php">Sign Up</a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>