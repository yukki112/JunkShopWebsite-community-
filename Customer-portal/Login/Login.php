<?php
// Start session and include database connection
session_start();
require_once 'db_connection.php';

// Initialize variables
$errors = [];
$email = '';

// Process login form when submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    // Sanitize and validate inputs
    $email = mysqli_real_escape_string($conn, trim($_POST['email']));
    $password = $_POST['password'];
    
    // Validate inputs
    if (empty($email)) {
        $errors[] = "Email is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    }
    
    if (empty($password)) {
        $errors[] = "Password is required";
    }
    
    // If no errors, proceed with login
    if (empty($errors)) {
        // Check if user exists
        $sql = "SELECT id, first_name, last_name, email, password_hash, is_admin FROM users WHERE email = '$email'";
        $result = mysqli_query($conn, $sql);
        
        if (mysqli_num_rows($result) === 1) {
            $user = mysqli_fetch_assoc($result);
            
            // Verify password
            if (password_verify($password, $user['password_hash'])) {
                // Clear any existing session data
                session_unset();
                session_regenerate_id(true);
                
                // Set common session variables
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
                
                // Check if user is admin
                if ($user['is_admin']) {
                    $_SESSION['admin_logged_in'] = true;
                    $_SESSION['user_type'] = 'admin';
                    // Redirect to admin dashboard
                    header("Location: ../../admin/index.php");
                    exit();
                } else {
                    $_SESSION['community_logged_in'] = true;
                    $_SESSION['user_type'] = 'community';
                    // Redirect to regular user page
                    header("Location: ../index.php");
                    exit();
                }
            } else {
                $errors[] = "Invalid email or password";
            }
        } else {
            $errors[] = "Invalid email or password";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JunkValue - Login</title>
    <style>
        /* Global Styles */
        :root {
            --primary: #3C342C;
            --secondary: #ffc107;
            --dark: #343a40;
            --light: #f8f9fa;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background: linear-gradient(rgba(0,0,0,0.7), rgba(0,0,0,0.7)), url('../img/Background.jpg');
            background-size: cover;
            background-position: center;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            position: relative;
        }
        
        .container {
            max-width: 1200px;
            width: 100%;
            margin: 0 auto;
            display: flex;
            justify-content: center;
            align-items: center;
            flex-direction: column;
            min-height: 100vh;
        }

        /* Back Button Styles */
        .back-to-main {
            position: fixed;
            top: 30px;
            left: 30px;
            z-index: 1000;
        }

        .back-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background-color: var(--primary);
            color: white;
            text-decoration: none;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .back-btn:hover {
            width: 220px;
            border-radius: 25px;
        }

        .back-btn i {
            font-size: 20px;
            position: absolute;
            left: 15px;
            transition: all 0.3s ease;
        }

        .back-btn:hover i {
            left: 15px;
        }

        .back-btn::after {
            content: 'Back to Main Website';
            position: absolute;
            white-space: nowrap;
            left: 50px;
            opacity: 0;
            transition: all 0.3s ease;
            font-weight: 500;
        }

        .back-btn:hover::after {
            opacity: 1;
            left: 50px;
        }

        /* Auth Card Styles */
        .auth-card {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            overflow: hidden;
            display: flex;
            max-width: 900px;
            width: 100%;
            position: relative;
        }
        
        .auth-left {
            flex: 1;
            padding: 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        
        .auth-right {
            flex: 1;
            background: linear-gradient(135deg, var(--primary) 0%, #3C342C 100%);
            color: white;
            padding: 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
        }
        
        .auth-logo {
            font-size: 28px;
            font-weight: bold;
            margin-bottom: 30px;
            color: var(--primary);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .auth-logo i {
            font-size: 32px;
        }
        
        .auth-title {
            font-size: 24px;
            margin-bottom: 10px;
        }
        
        .auth-subtitle {
            color: #6c757d;
            margin-bottom: 30px;
        }
        
        .auth-form .form-group {
            margin-bottom: 20px;
        }
        
        .auth-form label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
        }
        
        .auth-form input {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
        }
        
        .auth-form input:focus {
            outline: none;
            border-color: var(--primary);
        }
        
        .auth-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 25px;
        }
        
        .btn {
            padding: 12px 25px;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 500;
            font-size: 16px;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background-color: var(--primary);
            color: white;
            border: none;
        }
        
        .btn-primary:hover {
            background-color: #218838;
        }
        
        .btn-outline {
            background-color: white;
            border: 1px solid #ddd;
            color: var(--dark);
        }
        
        .btn-outline:hover {
            border-color: var(--primary);
            color: var(--primary);
        }
        
        .auth-link {
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
        }
        
        .auth-link:hover {
            text-decoration: underline;
        }
        
        .auth-divider {
            display: flex;
            align-items: center;
            margin: 20px 0;
            color: #6c757d;
        }
        
        .auth-divider::before,
        .auth-divider::after {
            content: "";
            flex: 1;
            border-bottom: 1px solid #ddd;
        }
        
        .auth-divider::before {
            margin-right: 15px;
        }
        
        .auth-divider::after {
            margin-left: 15px;
        }
        
        .social-login {
            display: flex;
            gap: 15px;
            margin-bottom: 25px;
        }
        
        .social-btn {
            flex: 1;
            padding: 10px;
            border-radius: 5px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            font-weight: 500;
            cursor: pointer;
            border: 1px solid #ddd;
            background-color: white;
        }
        
        .social-btn.google {
            color: #db4437;
        }
        
        .social-btn.facebook {
            color: #4267B2;
        }
        
        .auth-switch {
            text-align: center;
            margin-top: 30px;
            color: #6c757d;
        }
        
        .auth-features {
            margin-top: 40px;
        }
        
        .feature-item {
            display: flex;
            align-items: flex-start;
            gap: 15px;
            margin-bottom: 25px;
        }
        
        .feature-icon {
            width: 40px;
            height: 40px;
            background-color: rgba(255,255,255,0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        
        .error-message {
            color: #dc3545;
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            padding: 10px 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .auth-card {
                flex-direction: column;
            }
            
            .auth-right {
                display: none;
            }
            
            .social-login {
                flex-direction: column;
            }
            
            .back-to-main {
                top: 15px;
                left: 15px;
            }
            
            .back-btn {
                width: 40px;
                height: 40px;
            }
            
            .back-btn:hover {
                width: 120px;
            }
        }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <!-- Back Button -->
    <div class="back-to-main">
        <a href="../../index.html" class="back-btn" title="Back to Main Website">
            <i class="fas fa-arrow-left"></i>
        </a>
    </div>

    <div class="container">
        <div class="auth-card">
            <!-- Left Side - Form -->
            <div class="auth-left">
                <div class="auth-logo">
                    <i class="fas fa-recycle"></i>
                    <span>JunkValue</span>
                </div>
                
                <h2 class="auth-title">Welcome Back!</h2>
                <p class="auth-subtitle">Sign in to your account to continue</p>
                
                <?php if (!empty($errors)): ?>
                    <div class="error-message">
                        <?php foreach ($errors as $error): ?>
                            <p><?php echo htmlspecialchars($error); ?></p>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                
                <div class="social-login">
                    <button class="social-btn google">
                        <i class="fab fa-google"></i>
                        Google
                    </button>
                    <button class="social-btn facebook">
                        <i class="fab fa-facebook-f"></i>
                        Facebook
                    </button>
                </div>
                
                <div class="auth-divider">OR</div>
                
                <form class="auth-form" method="POST" action="">
                    <div class="form-group">
                        <label for="loginEmail">Email Address</label>
                        <input type="email" id="loginEmail" name="email" placeholder="Enter your email" 
                               value="<?php echo htmlspecialchars($email); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="loginPassword">Password</label>
                        <input type="password" id="loginPassword" name="password" placeholder="Enter your password" required>
                    </div>
                    
                    <div class="auth-actions">
                        <div>
                            <input type="checkbox" id="rememberMe" name="remember">
                            <label for="rememberMe">Remember me</label>
                        </div>
                        <a href="forgot_password.php" class="auth-link">Forgot Password?</a>
                    </div>
                    
                    <button type="submit" name="login" class="btn btn-primary" style="width: 100%; margin-top: 10px;">
                        Sign In
                    </button>
                </form>
                
                <div class="auth-switch">
                    Don't have an account? <a href="register.php" class="auth-link">Sign up</a>
                </div>
            </div>
            
            <!-- Right Side - Promo Content -->
            <div class="auth-right">
                <h2 style="font-size: 28px; margin-bottom: 20px;">Join Our Recycling Community</h2>
                <p style="margin-bottom: 30px; opacity: 0.9; max-width: 350px;">
                    Turn your scrap into cash while helping the environment. Get the best prices and exclusive rewards.
                </p>
                
                <div class="auth-features">
                    <div class="feature-item">
                        <div class="feature-icon">
                            <i class="fas fa-coins"></i>
                        </div>
                        <div>
                            <h4 style="margin-bottom: 5px;">Best Prices Guaranteed</h4>
                            <p style="opacity: 0.8;">Get real-time market rates for your scrap materials</p>
                        </div>
                    </div>
                    
                    <div class="feature-item">
                        <div class="feature-icon">
                            <i class="fas fa-truck"></i>
                        </div>
                        <div>
                            <h4 style="margin-bottom: 5px;">Easy Pickup Scheduling</h4>
                            <p style="opacity: 0.8;">We come to you at your convenience</p>
                        </div>
                    </div>
                    
                    <div class="feature-item">
                        <div class="feature-icon">
                            <i class="fas fa-award"></i>
                        </div>
                        <div>
                            <h4 style="margin-bottom: 5px;">Loyalty Rewards</h4>
                            <p style="opacity: 0.8;">Earn points and bonuses for every transaction</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Simple client-side validation
        document.querySelector('.auth-form').addEventListener('submit', function(e) {
            const email = document.getElementById('loginEmail').value;
            const password = document.getElementById('loginPassword').value;
            
            if (!email || !password) {
                e.preventDefault();
                alert('Please fill in all fields');
                return false;
            }
            
            return true;
        });
    </script>
</body>
</html>