<?php
include 'config.php'; 
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require 'vendor/autoload.php';
session_start();

class PasswordResetService {
    private $conn;
    private $mailer;
    
    public function __construct($connection) {
        $this->conn = $connection;
        $this->initializeMailer();
    }
    
    private function initializeMailer() {
        $this->mailer = new PHPMailer(true);
        $this->mailer->isSMTP();
        $this->mailer->Host = 'smtp.gmail.com';
        $this->mailer->SMTPAuth = true;
        $this->mailer->Username = 'nestbloom9@gmail.com';
        $this->mailer->Password = 'koxh dsxs jtwt ugad';
        $this->mailer->SMTPSecure = 'tls';
        $this->mailer->Port = 587;
        $this->mailer->setFrom('noreply@BloomNest.com', 'BloomNest');
    }
    
    public function generateOtp() {
        $otp = '';
        $seed = time() * 98765;
        for ($i = 0; $i < 6; $i++) {
            $otp .= ($seed * ($i + 1)) % 10; 
            $seed += 345; 
        }
        return $otp;
    }
    
    public function customHash($password) {
        $salt = 'abc123!@#'; 
        $hashed = '';
        for ($i = 0; $i < strlen($password); $i++) {
            $hashed .= dechex(ord($password[$i]) + ord($salt[$i % strlen($salt)]));
        }
        return $hashed;
    }
    
    public function handleEmailSubmission($email) {
        $email = $this->sanitizeInput($email);
        
        if (!$this->isEmailRegistered($email)) {
            return "No account found with that email address.";
        }
        
        $otp = $this->generateOtp();
        $_SESSION['otp'] = $otp;
        $_SESSION['email'] = $email;
        
        if ($this->sendOtpEmail($email, $otp)) {
            $this->redirectWithAlert('A 6-digit OTP has been sent to your email.', 'forgot-password.php?step=verify');
        } else {
            return "Failed to send OTP email. Please try again.";
        }
    }
    
    public function handleOtpVerification($otpInput) {
        $otpInput = $this->sanitizeInput($otpInput);
        
        if (!isset($_SESSION['otp']) || $_SESSION['otp'] != $otpInput) {
            return "Invalid OTP. Please try again.";
        }
        
        $this->redirectWithAlert('OTP verified successfully.', 'forgot-password.php?step=reset');
    }
    
    public function handlePasswordReset($newPassword) {
        $newPassword = $this->sanitizeInput($newPassword);
        
        if (!$this->validatePassword($newPassword)) {
            return "Password must start with a letter, be at least 8 characters long, and contain at least one special character.";
        }
        
        if (!isset($_SESSION['email'])) {
            return "Session expired. Please start over.";
        }
        
        $email = $_SESSION['email'];
        $hashedPassword = $this->customHash($newPassword);
        
        if ($this->updatePassword($email, $hashedPassword)) {
            unset($_SESSION['otp']);
            unset($_SESSION['email']);
            $this->redirectWithAlert('Password has been reset successfully.', 'login.php');
        } else {
            return "Failed to reset password. Please try again.";
        }
    }
    
    private function isEmailRegistered($email) {
        $stmt = $this->conn->prepare("SELECT id FROM user_details WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();
        
        return $result->num_rows > 0;
    }
    
    private function sendOtpEmail($email, $otp) {
        try {
            $this->mailer->clearAddresses();
            $this->mailer->addAddress($email);
            $this->mailer->isHTML(true);
            $this->mailer->Subject = 'Password Reset OTP';
            $this->mailer->Body = "Your OTP for password reset is: <strong>$otp</strong>";
            
            return $this->mailer->send();
        } catch (Exception $e) {
            error_log("Mail Error: " . $this->mailer->ErrorInfo);
            return false;
        }
    }
    
    private function updatePassword($email, $hashedPassword) {
        $stmt = $this->conn->prepare("UPDATE user_details SET password = ? WHERE email = ?");
        $stmt->bind_param("ss", $hashedPassword, $email);
        $success = $stmt->execute();
        $stmt->close();
        
        return $success;
    }
    
    private function validatePassword($password) {
        $passwordRegex = '^[a-zA-Z][\w!@#$%^&*]{7,}$';
        return preg_match($passwordRegex, $password);
    }
    
    private function sanitizeInput($input) {
        return htmlspecialchars(strip_tags(trim($input)));
    }
    
    private function redirectWithAlert($message, $url) {
        echo "<script>
            alert('" . addslashes($message) . "');
            window.location.href = '$url';
        </script>";
        exit();
    }
    
    public function getCurrentStep() {
        return $_GET['step'] ?? '';
    }
    
    public function renderForm() {
        $step = $this->getCurrentStep();
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Forgot Password</title>
            <style>
                .form {
                    display: flex;
                    flex-direction: column;
                    gap: 10px;
                    max-width: 350px;
                    padding: 20px;
                    border-radius: 20px;
                    background-color: #1a1a1a;
                    color: #fff;
                    border: 1px solid #333;
                    margin-top: 30vh;
                    margin-left: auto;
                    margin-right: auto;
                }
                .title {
                    font-size: 28px;
                    font-weight: 600;
                    text-align: center;
                    color: #00bfff;
                }
                .message {
                    text-align: center;
                    color: rgba(255, 255, 255, 0.7);
                }
                .input {
                    background-color: #333;
                    color: #fff;
                    width: 85%;
                    padding: 10px;
                    outline: 0;
                    border: 1px solid rgba(105, 105, 105, 0.397);
                    border-radius: 10px;
                }
                .submit {
                    align-self:center;
                    width: 50%;
                    border: none;
                    padding: 10px;
                    border-radius: 10px;
                    color: #fff;
                    background-color: #00bfff;
                    cursor: pointer;
                }
                .submit:hover {
                    background-color: #00bfff96;
                }
                .error {
                    color: #ff6b6b;
                    text-align: center;
                    margin-bottom: 10px;
                }
            </style>
        </head>
        <body>
            <center>
            <form class="form" method="POST" onsubmit="return validateForm()">
            <?php
            switch ($step) {
                case 'verify':
                    $this->renderOtpForm();
                    break;
                case 'reset':
                    $this->renderPasswordResetForm();
                    break;
                default:
                    $this->renderEmailForm();
                    break;
            }
            ?>
            </form>

            <script>
            function validateForm() {
                <?php if ($step == 'reset') { ?>
                const password = document.getElementById('new_password').value;
                const passwordRegex = /^[a-zA-Z][\w!@#$%^&*]{7,}$/;

                if (!passwordRegex.test(password)) {
                    alert("Password must start with a letter, be at least 8 characters long, and contain at least one special character.");
                    return false;
                }
                <?php } ?>
                return true;
            }
            </script>
            </center>
        </body>
        </html>
        <?php
    }
    
    private function renderEmailForm() {
        ?>
        <p class="title">Forgot Password?</p>
        <p class="message">Enter your registered Email</p>
        <label>
            <input class="input" type="email" name="email" id="email" placeholder="Enter Email" required>
        </label> 
        <button class="submit" type="submit">Submit</button>
        <?php
    }
    
    private function renderOtpForm() {
        ?>
        <p class="title">Verify OTP</p>
        <p class="message">Enter the 6-digit OTP sent to your email</p>
        <label>
            <input class="input" type="text" name="otp" placeholder="Enter OTP" required maxlength="6">
        </label> 
        <button class="submit" name="otp_verify" type="submit">Verify OTP</button>
        <?php
    }
    
    private function renderPasswordResetForm() {
        ?>
        <p class="title">Reset Password</p>
        <p class="message">Enter your new password</p>
        <label>
            <input class="input" type="password" name="new_password" id="new_password" placeholder="New Password" required>
        </label> 
        <button class="submit" name="reset_password" type="submit">Reset Password</button>
        <?php
    }
}

// Usage
try {
    $passwordService = new PasswordResetService($conn);
    $error = '';
    
    // Handle form submissions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['email'])) {
            $error = $passwordService->handleEmailSubmission($_POST['email']);
        } elseif (isset($_POST['otp_verify'])) {
            $error = $passwordService->handleOtpVerification($_POST['otp']);
        } elseif (isset($_POST['reset_password'])) {
            $error = $passwordService->handlePasswordReset($_POST['new_password']);
        }
    }
    
    // Display form with any errors
    $passwordService->renderForm();
    if ($error) {
        echo '<div class="error">' . htmlspecialchars($error) . '</div>';
    }
    
} catch (Exception $e) {
    error_log("Password Reset Error: " . $e->getMessage());
    echo '<div class="error">An unexpected error occurred. Please try again later.</div>';
}
?>