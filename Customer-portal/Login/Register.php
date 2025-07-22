<?php
// Start session and include database connection
session_start();
require_once 'db_connection.php';

// Initialize variables
$errors = [];
$success = false;
$firstName = $lastName = $email = $phone = $address = $userType = '';

// Process form when submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize and validate inputs
    $firstName = mysqli_real_escape_string($conn, trim($_POST['firstName']));
    $lastName = mysqli_real_escape_string($conn, trim($_POST['lastName']));
    $email = mysqli_real_escape_string($conn, trim($_POST['email']));
    $phone = mysqli_real_escape_string($conn, trim($_POST['phone']));
    $address = mysqli_real_escape_string($conn, trim($_POST['address']));
    $password = $_POST['password'];
    $confirmPassword = $_POST['confirmPassword'];
    $userType = mysqli_real_escape_string($conn, $_POST['userType']);
    $agreedTerms = isset($_POST['agreeTerms']) ? 1 : 0;
    
    // Validate inputs
    if (empty($firstName)) $errors[] = "First name is required";
    if (empty($lastName)) $errors[] = "Last name is required";
    if (empty($email)) $errors[] = "Email is required";
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Invalid email format";
    if (empty($phone)) $errors[] = "Phone number is required";
    if (empty($address)) $errors[] = "Address is required";
    if (empty($password)) $errors[] = "Password is required";
    if (strlen($password) < 8) $errors[] = "Password must be at least 8 characters";
    if ($password !== $confirmPassword) $errors[] = "Passwords do not match";
    if (empty($userType)) $errors[] = "User type is required";
    if (!$agreedTerms) $errors[] = "You must agree to the terms and conditions";
    
    // Check if email already exists
    $emailCheck = mysqli_query($conn, "SELECT email FROM users WHERE email = '$email'");
    if (mysqli_num_rows($emailCheck) > 0) {
        $errors[] = "Email already registered";
    }
    
    // If no errors, proceed with registration
    if (empty($errors)) {
        // Hash password
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        
        // Insert into database
        $sql = "INSERT INTO users (
            first_name, 
            last_name, 
            email, 
            phone, 
            address, 
            password_hash, 
            user_type,
            agreed_terms
        ) VALUES (
            '$firstName',
            '$lastName',
            '$email',
            '$phone',
            '$address',
            '$passwordHash',
            '$userType',
            $agreedTerms
        )";
        
        if (mysqli_query($conn, $sql)) {
            $success = true;
            
            // Clear form fields
            $firstName = $lastName = $email = $phone = $address = $userType = '';
            
            // You might want to send a verification email here
        } else {
            $errors[] = "Registration failed: " . mysqli_error($conn);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JunkValue - Register</title>
    <style>
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
        }
        
        .register-container {
            max-width: 1200px;
            width: 100%;
        }
        
        .register-card {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            overflow: hidden;
            display: flex;
        }
        
        .register-form {
            flex: 1;
            padding: 40px;
        }
        
        .register-promo {
            flex: 1;
            background: linear-gradient(135deg, var(--primary) 0%, #3C342C 100%);
            color: white;
            padding: 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        
        .logo {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 24px;
            font-weight: bold;
            color: var(--primary);
            margin-bottom: 30px;
        }
        
        .logo i {
            font-size: 28px;
        }
        
        h2 {
            font-size: 24px;
            margin-bottom: 10px;
        }
        
        .subtitle {
            color: #6c757d;
            margin-bottom: 30px;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
        }
        
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
        }
        
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--primary);
        }
        
        .password-strength {
            height: 4px;
            background-color: #eee;
            border-radius: 2px;
            margin-top: 8px;
            overflow: hidden;
        }
        
        .strength-bar {
            height: 100%;
            width: 0%;
            background-color: #dc3545;
            transition: all 0.3s;
        }
        
        .terms {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            margin: 25px 0;
        }
        
        .terms input {
            margin-top: 3px;
        }
        
        .terms label {
            font-size: 14px;
        }
        
        .terms a {
            color: var(--primary);
            text-decoration: none;
        }
        
        .terms a:hover {
            text-decoration: underline;
        }
        
        .btn {
            padding: 12px 25px;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 500;
            font-size: 16px;
            transition: all 0.3s;
            width: 100%;
            border: none;
        }
        
        .btn-primary {
            background-color: var(--primary);
            color: white;
        }
        
        .btn-primary:hover {
            background-color: #218838;
        }
        
        .login-link {
            text-align: center;
            margin-top: 20px;
            color: #6c757d;
        }
        
        .login-link a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
        }
        
        .login-link a:hover {
            text-decoration: underline;
        }
        
        .promo-content {
            max-width: 350px;
            margin: 0 auto;
            text-align: center;
        }
        
        .promo-content h2 {
            font-size: 28px;
            margin-bottom: 20px;
        }
        
        .feature {
            display: flex;
            align-items: flex-start;
            gap: 15px;
            margin-bottom: 25px;
            text-align: left;
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
        
        .feature-text h4 {
            margin-bottom: 5px;
        }
        
        .feature-text p {
            opacity: 0.9;
            font-size: 14px;
        }
        
        .error-message {
            color: #dc3545;
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            padding: 10px 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            display: none;
        }
        
        .success-message {
            color: #28a745;
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            padding: 10px 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            display: none;
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
        @media (max-width: 768px) {
            .register-card {
                flex-direction: column;
            }
            
            .register-promo {
                display: none;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
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
    <div class="register-container">
        <div class="register-card">
            <div class="register-form">
                <div class="logo">
                    <i class="fas fa-recycle"></i>
                    <span>JunkValue</span>
                </div>
                
                <h2>Create Your Account</h2>
                <p class="subtitle">Start turning your scrap into cash today</p>
                
                <?php if (!empty($errors)): ?>
                    <div class="error-message" style="display: block;">
                        <?php foreach ($errors as $error): ?>
                            <p><?php echo htmlspecialchars($error); ?></p>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="success-message" style="display: block;">
                        <p>Account created successfully! You can now <a href="login.php">login</a>.</p>
                    </div>
                <?php endif; ?>
                
                <form id="registrationForm" method="POST" action="">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="firstName">First Name</label>
                            <input type="text" id="firstName" name="firstName" placeholder="Juan" value="<?php echo htmlspecialchars($firstName); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="lastName">Last Name</label>
                            <input type="text" id="lastName" name="lastName" placeholder="Dela Cruz" value="<?php echo htmlspecialchars($lastName); ?>" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input type="email" id="email" name="email" placeholder="juan.delacruz@example.com" value="<?php echo htmlspecialchars($email); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="phone">Phone Number</label>
                        <input type="tel" id="phone" name="phone" placeholder="0912 345 6789" value="<?php echo htmlspecialchars($phone); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="address">Pickup Address</label>
                        <input type="text" id="address" name="address" placeholder="123 Main St, Barangay San Jose" value="<?php echo htmlspecialchars($address); ?>" required>
                    </div>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="password">Password</label>
                            <input type="password" id="password" name="password" placeholder="Create a password" required>
                            <div class="password-strength">
                                <div class="strength-bar" id="strengthBar"></div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="confirmPassword">Confirm Password</label>
                            <input type="password" id="confirmPassword" name="confirmPassword" placeholder="Confirm your password" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="userType">I am a:</label>
                        <select id="userType" name="userType" required>
                            <option value="">Select account type</option>
                            <option value="individual" <?php echo ($userType === 'individual') ? 'selected' : ''; ?>>Individual Recycler</option>
                            <option value="business" <?php echo ($userType === 'business') ? 'selected' : ''; ?>>Business/Junk Shop</option>
                            <option value="collector" <?php echo ($userType === 'collector') ? 'selected' : ''; ?>>Scrap Collector</option>
                        </select>
                    </div>
                    
                    <div class="terms">
                        <input type="checkbox" id="agreeTerms" name="agreeTerms" required <?php echo isset($_POST['agreeTerms']) ? 'checked' : ''; ?>>
                        <label for="agreeTerms">
                            I agree to the <a href="../../terms-of-service.html">Terms of Service</a> and <a href="../../privacy-policy.html">Privacy Policy</a>.
                            I consent to receive communications about my account.
                        </label>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Create Account</button>
                </form>
                
                <div class="login-link">
                    Already have an account? <a href="login.php">Sign in</a>
                </div>
            </div>
            
            <div class="register-promo">
                <div class="promo-content">
                    <h2>Join Our Recycling Community</h2>
                    <p style="margin-bottom: 30px; opacity: 0.9;">
                        Get the best prices for your scrap materials and help make the environment cleaner.
                    </p>
                    
                    <div class="feature">
                        <div class="feature-icon">
                            <i class="fas fa-coins"></i>
                        </div>
                        <div class="feature-text">
                            <h4>Competitive Prices</h4>
                            <p>Real-time pricing based on current market rates</p>
                        </div>
                    </div>
                    
                    <div class="feature">
                        <div class="feature-icon">
                            <i class="fas fa-truck"></i>
                        </div>
                        <div class="feature-text">
                            <h4>Free Pickup Service</h4>
                            <p>We'll collect your scrap at your convenience</p>
                        </div>
                    </div>
                    
                    <div class="feature">
                        <div class="feature-icon">
                            <i class="fas fa-award"></i>
                        </div>
                        <div class="feature-text">
                            <h4>Loyalty Rewards</h4>
                            <p>Earn points for every transaction and redeem rewards</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Password strength indicator
        const passwordInput = document.getElementById('password');
        const strengthBar = document.getElementById('strengthBar');
        
        passwordInput.addEventListener('input', function() {
            const password = this.value;
            let strength = 0;
            
            // Length check
            if (password.length >= 8) strength += 1;
            if (password.length >= 12) strength += 1;
            
            // Complexity checks
            if (/[A-Z]/.test(password)) strength += 1;
            if (/[0-9]/.test(password)) strength += 1;
            if (/[^A-Za-z0-9]/.test(password)) strength += 1;
            
            // Update strength bar
            let width = 0;
            let color = '#dc3545'; // Red
            
            if (strength > 3) {
                width = 100;
                color = '#28a745'; // Green
            } else if (strength > 1) {
                width = 66;
                color = '#fd7e14'; // Orange
            } else if (password.length > 0) {
                width = 33;
            }
            
            strengthBar.style.width = width + '%';
            strengthBar.style.backgroundColor = color;
        });
        
        // Phone number formatting
        document.getElementById('phone').addEventListener('input', function(e) {
            let value = this.value.replace(/\D/g, '');
            
            if (value.length > 0) {
                value = value.match(/.{1,4}/g).join(' ');
            }
            
            this.value = value;
        });
    </script>
</body>
</html>