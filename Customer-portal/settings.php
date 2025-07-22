<?php
// Start session and include database connection
session_start();
require_once 'db_connection.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: Login/Login.php");
    exit();
}

// Get current user ID and info
$user_id = $_SESSION['user_id'];
$user_query = "SELECT id, first_name, last_name, phone, address, profile_image, user_type, created_at FROM users WHERE id = ?";
$user_stmt = mysqli_prepare($conn, $user_query);
mysqli_stmt_bind_param($user_stmt, "i", $user_id);
mysqli_stmt_execute($user_stmt);
$user_result = mysqli_stmt_get_result($user_stmt);
$user = mysqli_fetch_assoc($user_result);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        // Update profile information
        $address = mysqli_real_escape_string($conn, $_POST['address']);
        
        $update_query = "UPDATE users SET address = ? WHERE id = ?";
        $update_stmt = mysqli_prepare($conn, $update_query);
        mysqli_stmt_bind_param($update_stmt, "si", $address, $user_id);
        mysqli_stmt_execute($update_stmt);
        
        // Refresh user data
        mysqli_stmt_execute($user_stmt);
        $user_result = mysqli_stmt_get_result($user_stmt);
        $user = mysqli_fetch_assoc($user_result);
        
        $success_message = "Profile updated successfully!";
    }
    elseif (isset($_POST['upload_avatar'])) {
        if (isset($_POST['avatar_data']) && !empty($_POST['avatar_data'])) {
            $avatar_data = $_POST['avatar_data'];
            $image_parts = explode(";base64,", $avatar_data);
            $image_type_aux = explode("image/", $image_parts[0]);
            $image_type = $image_type_aux[1];
            $image_base64 = base64_decode($image_parts[1]);
            
            // Generate unique filename
            $filename = 'avatar_' . $user_id . '_' . time() . '.png';
            $filepath = 'uploads/' . $filename;
            
            // Create uploads directory if it doesn't exist
            if (!is_dir('uploads')) {
                mkdir('uploads', 0755, true);
            }
            
            // Save the file
            if (file_put_contents($filepath, $image_base64)) {
                // Delete old image if exists
                if (!empty($user['profile_image']) && file_exists($user['profile_image'])) {
                    @unlink($user['profile_image']);
                }
                
                // Update database
                $update_img_query = "UPDATE users SET profile_image = ? WHERE id = ?";
                $update_img_stmt = mysqli_prepare($conn, $update_img_query);
                mysqli_stmt_bind_param($update_img_stmt, "si", $filepath, $user_id);
                mysqli_stmt_execute($update_img_stmt);
                
                // Refresh user data
                $user['profile_image'] = $filepath;
                $success_message = "Profile picture updated successfully!";
            } else {
                $error_message = "Failed to save profile picture";
            }
        }
    }
    elseif (isset($_POST['delete_account'])) {
        if ($_POST['confirmation'] === "DELETE") {
            // Delete user account
            $delete_query = "DELETE FROM users WHERE id = ?";
            $delete_stmt = mysqli_prepare($conn, $delete_query);
            mysqli_stmt_bind_param($delete_stmt, "i", $user_id);
            mysqli_stmt_execute($delete_stmt);
            
            // Delete profile image if exists
            if (!empty($user['profile_image']) && file_exists($user['profile_image'])) {
                @unlink($user['profile_image']);
            }
            
            // Logout and redirect
            session_destroy();
            header("Location: Login/Login.php");
            exit();
        } else {
            $error_message = "Please type DELETE to confirm account deletion";
        }
    }
    elseif (isset($_POST['change_password'])) {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        // Verify current password
        $check_query = "SELECT password_hash FROM users WHERE id = ?";
        $check_stmt = mysqli_prepare($conn, $check_query);
        mysqli_stmt_bind_param($check_stmt, "i", $user_id);
        mysqli_stmt_execute($check_stmt);
        $check_result = mysqli_stmt_get_result($check_stmt);
        $check_data = mysqli_fetch_assoc($check_result);
        
        if (password_verify($current_password, $check_data['password_hash'])) {
            if ($new_password === $confirm_password) {
                // Update password
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $update_pw_query = "UPDATE users SET password_hash = ? WHERE id = ?";
                $update_pw_stmt = mysqli_prepare($conn, $update_pw_query);
                mysqli_stmt_bind_param($update_pw_stmt, "si", $hashed_password, $user_id);
                mysqli_stmt_execute($update_pw_stmt);
                
                $success_message = "Password changed successfully!";
            } else {
                $error_message = "New passwords don't match";
            }
        } else {
            $error_message = "Current password is incorrect";
        }
    }
    elseif (isset($_POST['change_phone'])) {
        $new_phone = mysqli_real_escape_string($conn, $_POST['new_phone']);
        
        $update_phone_query = "UPDATE users SET phone = ? WHERE id = ?";
        $update_phone_stmt = mysqli_prepare($conn, $update_phone_query);
        mysqli_stmt_bind_param($update_phone_stmt, "si", $new_phone, $user_id);
        mysqli_stmt_execute($update_phone_stmt);
        
        // Refresh user data
        mysqli_stmt_execute($user_stmt);
        $user_result = mysqli_stmt_get_result($user_stmt);
        $user = mysqli_fetch_assoc($user_result);
        
        $success_message = "Phone number updated successfully!";
    }
}

// Format user data for display
$user_name = $user['first_name'] . ' ' . $user['last_name'];
$user_initials = strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1));
$member_since = date('F Y', strtotime($user['created_at']));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JunkValue - Account Settings</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.12/cropper.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root {
            --bg-beige: #E6D8C3;
            --sales-orange: #D97A41;
            --stock-green: #708B4C;
            --panel-cream: #F2EAD3;
            --topbar-brown: #3C342C;
            --text-dark: #2E2B29;
            --icon-green: #6A7F46;
            --icon-orange: #D97A41;
            
            /* Mapped to existing variables */
            --primary-color: var(--stock-green);
            --secondary-color: var(--sales-orange);
            --accent-color: var(--icon-green);
            --dark-color: var(--topbar-brown);
            --light-color: var(--panel-cream);
            --text-color: var(--text-dark);
            --text-light: #777;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background-color: var(--bg-beige);
            color: var(--text-dark);
            line-height: 1.6;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 20px;
        }

        header {
            background-color: var(--topbar-brown);
            color: white;
            padding: 15px 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            font-size: 24px;
            font-weight: 700;
            color: var(--panel-cream);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .logo::before {
            content: "♻";
            font-size: 28px;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .user-info span {
            font-weight: 500;
            color: var(--panel-cream);
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: var(--panel-cream);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: var(--topbar-brown);
            overflow: hidden;
        }

        .user-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .dashboard {
            display: grid;
            grid-template-columns: 250px 1fr;
            gap: 30px;
            padding: 30px 0;
        }

        .sidebar {
            background-color: var(--panel-cream);
            border-radius: 15px;
            padding: 25px 0;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            height: fit-content;
            position: sticky;
            top: 80px;
        }

        .nav-menu {
            list-style: none;
        }

        .nav-menu li {
            margin-bottom: 5px;
        }

        .nav-menu a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 25px;
            color: var(--text-dark);
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s;
            border-left: 3px solid transparent;
        }

        .nav-menu a:hover {
            background-color: rgba(106, 127, 70, 0.1);
            border-left-color: var(--icon-green);
        }

        .nav-menu a.active {
            background-color: rgba(106, 127, 70, 0.15);
            border-left-color: var(--icon-green);
            color: var(--icon-green);
        }

        .nav-menu i {
            width: 20px;
            text-align: center;
            font-size: 18px;
        }

        .main-content {
            display: grid;
            grid-template-columns: 1fr;
            gap: 25px;
        }

        .card {
            background-color: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            animation: fadeIn 0.5s ease-out forwards;
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid rgba(0,0,0,0.05);
        }

        .card-title {
            font-size: 20px;
            font-weight: 600;
            color: var(--text-dark);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .card-title i {
            color: var(--icon-green);
        }

        .btn {
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s;
            border: none;
            font-size: 15px;
        }
        
        .btn-primary {
            background: linear-gradient(90deg, var(--icon-green) 0%, var(--stock-green) 100%);
            color: white;
            box-shadow: 0 3px 10px rgba(106, 127, 70, 0.3);
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(106, 127, 70, 0.4);
        }
        
        .btn-outline {
            background-color: transparent;
            border: 1px solid rgba(0,0,0,0.1);
            color: var(--text-dark);
        }
        
        .btn-outline:hover {
            background-color: rgba(0,0,0,0.03);
        }
        
        .btn-danger {
            background-color: #d32f2f;
            color: white;
        }
        
        .btn-danger:hover {
            background-color: #c62828;
        }

        /* Account Settings Specific Styles */
        .settings-tabs {
            display: flex;
            border-bottom: 1px solid rgba(0,0,0,0.05);
            margin-bottom: 25px;
        }
        
        .settings-tab {
            padding: 12px 20px;
            cursor: pointer;
            border-bottom: 3px solid transparent;
            font-weight: 500;
            color: var(--text-light);
        }
        
        .settings-tab.active {
            border-bottom-color: var(--icon-green);
            color: var(--icon-green);
        }
        
        .settings-section {
            display: none;
        }
        
        .settings-section.active {
            display: block;
            animation: fadeIn 0.3s ease;
        }
        
        .profile-header {
            display: flex;
            align-items: center;
            gap: 30px;
            margin-bottom: 30px;
        }
        
        .profile-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background-color: rgba(106, 127, 70, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 36px;
            font-weight: bold;
            color: var(--icon-green);
            position: relative;
            overflow: hidden;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .profile-avatar:hover {
            transform: scale(1.05);
        }
        
        .profile-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .avatar-upload {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background-color: rgba(0,0,0,0.5);
            color: white;
            text-align: center;
            padding: 8px;
            font-size: 14px;
            opacity: 0;
            transition: opacity 0.3s;
        }
        
        .profile-avatar:hover .avatar-upload {
            opacity: 1;
        }
        
        #avatarInput {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            opacity: 0;
            cursor: pointer;
        }
        
        .profile-info h3 {
            margin-bottom: 8px;
            color: var(--text-dark);
        }
        
        .profile-info p {
            color: var(--text-light);
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
            color: var(--text-dark);
        }
        
        .form-group input, 
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid rgba(0,0,0,0.1);
            border-radius: 8px;
            font-size: 15px;
            transition: all 0.3s;
        }
        
        .form-group textarea {
            min-height: 100px;
            resize: vertical;
        }
        
        .form-group input:focus, 
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--icon-green);
            box-shadow: 0 0 0 3px rgba(106, 127, 70, 0.1);
        }
        
        input[readonly], input[disabled] {
            background-color: rgba(0,0,0,0.03);
            cursor: not-allowed;
        }
        
        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 15px;
            margin-top: 30px;
            border-top: 1px solid rgba(0,0,0,0.05);
            padding-top: 20px;
        }
        
        .security-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px;
            border: 1px solid rgba(0,0,0,0.05);
            border-radius: 10px;
            margin-bottom: 15px;
            transition: all 0.3s;
        }
        
        .security-item:hover {
            background-color: rgba(106, 127, 70, 0.03);
        }
        
        .security-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .security-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background-color: rgba(106, 127, 70, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--icon-green);
            font-size: 20px;
        }
        
        .verification-badge {
            display: inline-block;
            padding: 3px 10px;
            background-color: var(--icon-green);
            color: white;
            border-radius: 20px;
            font-size: 12px;
            margin-left: 10px;
        }
        
        .delete-account {
            background-color: rgba(255, 0, 0, 0.05);
            border: 1px solid rgba(255, 0, 0, 0.1);
            padding: 25px;
            border-radius: 10px;
            margin-top: 40px;
        }
        
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 8px;
        }
        
        .alert-success {
            background-color: rgba(40, 167, 69, 0.1);
            color: #28a745;
            border: 1px solid rgba(40, 167, 69, 0.2);
        }
        
        .alert-danger {
            background-color: rgba(220, 53, 69, 0.1);
            color: #dc3545;
            border: 1px solid rgba(220, 53, 69, 0.2);
        }
        
        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        
        .modal-content {
            background-color: white;
            margin: 10% auto;
            padding: 25px;
            border-radius: 12px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 5px 30px rgba(0,0,0,0.2);
            animation: modalFadeIn 0.3s ease;
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .modal-header h3 {
            color: var(--text-dark);
        }
        
        .close {
            font-size: 24px;
            cursor: pointer;
            color: var(--text-light);
            transition: color 0.3s;
        }
        
        .close:hover {
            color: var(--text-dark);
        }
        
        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid rgba(0,0,0,0.05);
        }
        
        /* Cropper Modal Styles */
        #avatarModal {
            display: none;
            position: fixed;
            z-index: 1050;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.8);
        }
        
        #avatarModal .modal-content {
            background-color: white;
            margin: 2% auto;
            padding: 20px;
            border-radius: 12px;
            width: 90%;
            max-width: 800px;
        }
        
        #avatarModal .modal-body {
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        
        #imagePreview {
            max-width: 100%;
            max-height: 60vh;
        }
        
        .cropper-container {
            margin-bottom: 20px;
        }
        
        .cropper-buttons {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }

        /* Responsive styles */
        @media (max-width: 1024px) {
            .dashboard {
                grid-template-columns: 1fr;
            }
            
            .sidebar {
                position: static;
            }
        }

        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .profile-header {
                flex-direction: column;
                text-align: center;
            }
            
            .security-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
        }

        @media (max-width: 480px) {
            .card {
                padding: 20px 15px;
            }
            
            .form-actions, .modal-footer {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
            }
        }

        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @keyframes modalFadeIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>
    <header>
        <div class="container header-content">
            <div class="logo">JunkValue</div>
            <div class="user-info">
                <span>Hello, <?php echo htmlspecialchars($user_name); ?>!</span>
                <div class="user-avatar">
                    <?php if (!empty($user['profile_image']) && file_exists($user['profile_image'])): ?>
                        <img src="<?php echo htmlspecialchars($user['profile_image']); ?>" alt="Profile">
                    <?php else: ?>
                        <?php echo $user_initials; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </header>
    
    <div class="container">
        <div class="dashboard">
            <!-- Sidebar -->
            <div class="sidebar">
                <ul class="nav-menu">
                    <li><a href="index.php"><i class="fas fa-home"></i> Dashboard</a></li>
                    <li><a href="Transaction.php"><i class="fas fa-history"></i> Transaction History</a></li>
                    <li><a href="Schedule.php"><i class="fas fa-calendar-alt"></i> Schedule Pickup</a></li>
                    <li><a href="prices.php"><i class="fas fa-coins"></i> Current Prices</a></li>
                    <li><a href="rewards.php"><i class="fas fa-award"></i> Loyalty Rewards</a></li>
                    <li><a href="#" class="active"><i class="fas fa-user-cog"></i> Account Settings</a></li>
                    <li><a href="Login/Login.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                </ul>
            </div>
            
            <!-- Main Content -->
            <div class="main-content">
                <div class="card">
                    <h2 style="margin-bottom: 20px; color: var(--text-dark);">Account Settings</h2>
                    
                    <?php if (isset($success_message)): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (isset($error_message)): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Settings Tabs -->
                    <div class="settings-tabs">
                        <div class="settings-tab active" onclick="showSection('profile')">Profile</div>
                        <div class="settings-tab" onclick="showSection('security')">Security</div>
                    </div>
                    
                    <!-- Profile Section -->
                    <div class="settings-section active" id="profileSection">
                        <div class="profile-header">
                            <div class="profile-avatar" id="avatarContainer">
                                <?php if (!empty($user['profile_image'])): ?>
                                    <img src="<?php echo htmlspecialchars($user['profile_image']); ?>" alt="Profile">
                                <?php else: ?>
                                    <span><?php echo $user_initials; ?></span>
                                <?php endif; ?>
                                <div class="avatar-upload">
                                    <i class="fas fa-camera"></i> Change
                                </div>
                                <input type="file" id="avatarInput" accept="image/*">
                            </div>
                            <div class="profile-info">
                                <h3><?php echo htmlspecialchars($user_name); ?></h3>
                                <p>Member since <?php echo $member_since; ?></p>
                            </div>
                        </div>
                        
                        <form method="POST" action="">
                            <div class="form-grid">
                                <div class="form-group">
                                    <label>First Name</label>
                                    <input type="text" value="<?php echo htmlspecialchars($user['first_name']); ?>" readonly>
                                </div>
                                <div class="form-group">
                                    <label>Last Name</label>
                                    <input type="text" value="<?php echo htmlspecialchars($user['last_name']); ?>" readonly>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label>Account Type</label>
                                <input type="text" value="<?php echo htmlspecialchars($user['user_type']); ?>" readonly>
                            </div>
                            
                            <div class="form-group">
                                <label>Phone Number</label>
                                <input type="tel" value="<?php echo htmlspecialchars($user['phone']); ?>" readonly>
                            </div>
                            
                            <div class="form-group">
                                <label>Default Pickup Address</label>
                                <textarea name="address" style="min-height: 100px;"><?php echo htmlspecialchars($user['address']); ?></textarea>
                            </div>
                            
                            <div class="form-actions">
                                <button type="button" class="btn btn-outline">Cancel</button>
                                <button type="submit" name="update_profile" class="btn btn-primary">Save Changes</button>
                            </div>
                        </form>
                    </div>
                    
                    <!-- Security Section -->
                    <div class="settings-section" id="securitySection">
                        <h3 style="margin-bottom: 20px; color: var(--text-dark);">Security Settings</h3>
                        
                        <div class="security-item">
                            <div class="security-info">
                                <div class="security-icon">
                                    <i class="fas fa-lock"></i>
                                </div>
                                <div>
                                    <h4 style="margin: 0; color: var(--text-dark);">Password</h4>
                                    <p style="margin: 0; color: var(--text-light);">Last changed 3 months ago</p>
                                </div>
                            </div>
                            <button class="btn btn-outline" style="padding: 8px 15px;" onclick="openModal('passwordModal')">Change</button>
                        </div>
                        
                        <div class="security-item">
                            <div class="security-info">
                                <div class="security-icon">
                                    <i class="fas fa-mobile-alt"></i>
                                </div>
                                <div>
                                    <h4 style="margin: 0; color: var(--text-dark);">Phone Number</h4>
                                    <p style="margin: 0; color: var(--text-light);"><?php echo htmlspecialchars($user['phone']); ?></p>
                                </div>
                            </div>
                            <button class="btn btn-outline" style="padding: 8px 15px;" onclick="openModal('phoneModal')">Change</button>
                        </div>
                        
                        <div class="delete-account">
                            <h4 style="margin-top: 0; color: #dc3545;"><i class="fas fa-exclamation-triangle"></i> Danger Zone</h4>
                            <p style="color: var(--text-dark);">Once you delete your account, there is no going back. Please be certain.</p>
                            <button type="button" class="btn btn-outline" style="border-color: #dc3545; color: #dc3545; margin-top: 10px;" onclick="openModal('deleteModal')">
                                Delete Account
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Change Password Modal -->
    <div id="passwordModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Change Password</h3>
                <span class="close" onclick="closeModal('passwordModal')">&times;</span>
            </div>
            <form method="POST" action="">
                <div class="form-group">
                    <label>Current Password</label>
                    <input type="password" name="current_password" required>
                </div>
                <div class="form-group">
                    <label>New Password</label>
                    <input type="password" name="new_password" required>
                </div>
                <div class="form-group">
                    <label>Confirm New Password</label>
                    <input type="password" name="confirm_password" required>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="closeModal('passwordModal')">Cancel</button>
                    <button type="submit" name="change_password" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Change Phone Modal -->
    <div id="phoneModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Change Phone Number</h3>
                <span class="close" onclick="closeModal('phoneModal')">&times;</span>
            </div>
            <form method="POST" action="">
                <div class="form-group">
                    <label>New Phone Number</label>
                    <input type="tel" name="new_phone" value="<?php echo htmlspecialchars($user['phone']); ?>" required>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="closeModal('phoneModal')">Cancel</button>
                    <button type="submit" name="change_phone" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Account Modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 style="color: #dc3545;"><i class="fas fa-exclamation-triangle"></i> Delete Account</h3>
                <span class="close" onclick="closeModal('deleteModal')">&times;</span>
            </div>
            <p style="color: var(--text-dark); margin-bottom: 15px;">This action cannot be undone. This will permanently delete your account and all associated data.</p>
            <p style="color: var(--text-dark); margin-bottom: 15px;">To confirm, please type <strong>DELETE</strong> in the box below:</p>
            <form method="POST" action="">
                <div class="form-group">
                    <input type="text" name="confirmation" required>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="closeModal('deleteModal')">Cancel</button>
                    <button type="submit" name="delete_account" class="btn btn-danger">Delete Account</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Avatar Cropping Modal -->
    <div id="avatarModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Crop Profile Picture</h3>
                <span class="close" onclick="closeModal('avatarModal')">&times;</span>
            </div>
            <div class="modal-body">
                <div id="imagePreview"></div>
                <div class="cropper-buttons">
                    <button type="button" class="btn btn-outline" onclick="closeModal('avatarModal')">Cancel</button>
                    <button type="button" class="btn btn-primary" id="cropButton">Crop & Save</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Include CropperJS library -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.12/cropper.min.js"></script>
    
    <script>
        // Tab switching functionality
        function showSection(section) {
            document.querySelectorAll('.settings-section').forEach(sec => {
                sec.classList.remove('active');
            });
            
            document.querySelectorAll('.settings-tab').forEach(tab => {
                tab.classList.remove('active');
            });
            
            document.getElementById(section + 'Section').classList.add('active');
            event.currentTarget.classList.add('active');
        }
        
        // Modal functionality
        function openModal(modalId) {
            document.getElementById(modalId).style.display = 'block';
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target.className === 'modal') {
                event.target.style.display = 'none';
            }
        }
        
        // Avatar upload and cropping functionality
        const avatarInput = document.getElementById('avatarInput');
        const avatarModal = document.getElementById('avatarModal');
        const imagePreview = document.getElementById('imagePreview');
        let cropper;

        avatarInput.addEventListener('change', function(e) {
            if (this.files && this.files[0]) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    // Destroy previous cropper instance if exists
                    if (cropper) {
                        cropper.destroy();
                    }
                    
                    // Create new image element
                    imagePreview.innerHTML = '';
                    const img = document.createElement('img');
                    img.id = 'imageToCrop';
                    img.src = e.target.result;
                    imagePreview.appendChild(img);
                    
                    // Initialize cropper
                    cropper = new Cropper(img, {
                        aspectRatio: 1,
                        viewMode: 1,
                        autoCropArea: 0.8,
                        responsive: true,
                        guides: false
                    });
                    
                    // Show modal
                    openModal('avatarModal');
                }
                
                reader.readAsDataURL(this.files[0]);
            }
        });

        // Handle crop button click
        document.getElementById('cropButton').addEventListener('click', function() {
            if (cropper) {
                // Get cropped canvas
                const canvas = cropper.getCroppedCanvas({
                    width: 300,
                    height: 300,
                    minWidth: 256,
                    minHeight: 256,
                    maxWidth: 1024,
                    maxHeight: 1024,
                    fillColor: '#fff',
                    imageSmoothingEnabled: true,
                    imageSmoothingQuality: 'high',
                });
                
                if (canvas) {
                    // Convert canvas to data URL
                    const croppedImageData = canvas.toDataURL('image/png');
                    
                    // Create hidden form and submit
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.style.display = 'none';
                    
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'avatar_data';
                    input.value = croppedImageData;
                    form.appendChild(input);
                    
                    const input2 = document.createElement('input');
                    input2.type = 'hidden';
                    input2.name = 'upload_avatar';
                    input2.value = '1';
                    form.appendChild(input2);
                    
                    document.body.appendChild(form);
                    form.submit();
                }
            }
        });

        // Make sure the avatar container is clickable
        document.getElementById('avatarContainer').addEventListener('click', function(e) {
            // Only trigger if clicking on the avatar container itself, not its children
            if (e.target === this) {
                document.getElementById('avatarInput').click();
            }
        });

        // Make sure the "Change" text is clickable
        document.querySelector('.avatar-upload').addEventListener('click', function(e) {
            e.stopPropagation();
            document.getElementById('avatarInput').click();
        });
    </script>
</body>
</html>
<?php
// Close prepared statements
if (isset($user_stmt)) mysqli_stmt_close($user_stmt);
if (isset($update_stmt)) mysqli_stmt_close($update_stmt);
if (isset($delete_stmt)) mysqli_stmt_close($delete_stmt);
if (isset($update_pw_stmt)) mysqli_stmt_close($update_pw_stmt);
if (isset($update_phone_stmt)) mysqli_stmt_close($update_phone_stmt);
if (isset($update_img_stmt)) mysqli_stmt_close($update_img_stmt);
// Close connection
mysqli_close($conn);
?>