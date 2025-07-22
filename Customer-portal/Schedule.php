<?php
// Start session and include database connection
session_start();
require_once 'db_connection.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Get current user ID
$user_id = $_SESSION['user_id'];

// Get user info for header
$user_query = "SELECT first_name, last_name, profile_image, address FROM users WHERE id = ?";
$user_stmt = mysqli_prepare($conn, $user_query);
mysqli_stmt_bind_param($user_stmt, "i", $user_id);
mysqli_stmt_execute($user_stmt);
$user_result = mysqli_stmt_get_result($user_stmt);
$user = mysqli_fetch_assoc($user_result);
$user_name = $user['first_name'] . ' ' . $user['last_name'];
$user_initials = strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1));
$user_address = $user['address'] ?? ''; // Get the user's address

// Ensure all required materials exist in database
$required_materials = [
    ['Copper Wire', 285.70],
    ['PET Bottles', 28.57],
    ['Aluminum Cans', 68.57],
    ['Cardboard', 17.14],
    ['Steel', 45.71],
    ['Glass Bottles', 14.29],
    ['Computer Parts', 85.71],
    ['Iron Scrap', 18.00],
    ['Stainless Steel', 65.00],
    ['E-Waste', 120.00]
];

foreach ($required_materials as $material) {
    $material_name = $material[0];
    $unit_price = $material[1];
    
    $check_query = "SELECT id FROM materials WHERE material_option = ?";
    $stmt = mysqli_prepare($conn, $check_query);
    mysqli_stmt_bind_param($stmt, "s", $material_name);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($result) == 0) {
        $insert_query = "INSERT INTO materials (material_option, unit_price) VALUES (?, ?)";
        $stmt = mysqli_prepare($conn, $insert_query);
        mysqli_stmt_bind_param($stmt, "sd", $material_name, $unit_price);
        mysqli_stmt_execute($stmt);
    }
}

// Get materials from database
$materials = [];
$material_query = "SELECT * FROM materials ORDER BY material_option";
$material_result = mysqli_query($conn, $material_query);
if ($material_result) {
    while ($row = mysqli_fetch_assoc($material_result)) {
        $materials[] = $row;
    }
}

// Process form submission
$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_pickup'])) {
    // Validate and sanitize inputs
    $pickup_date = mysqli_real_escape_string($conn, $_POST['pickup_date']);
    $time_slot = mysqli_real_escape_string($conn, $_POST['time_slot']);
    $address = mysqli_real_escape_string($conn, $_POST['address']);
    $special_instructions = mysqli_real_escape_string($conn, $_POST['special_instructions']);
    $estimated_value = 0;
    
    // Validate materials
    if (!isset($_POST['materials']) || !is_array($_POST['materials']) || count($_POST['materials']) === 0) {
        $errors[] = "Please add at least one material";
    }
    
    if (empty($errors)) {
        // Calculate estimated value from materials
        foreach ($_POST['materials'] as $material) {
            if (isset($material['id'], $material['quantity']) && is_numeric($material['quantity'])) {
                $material_id = intval($material['id']);
                $quantity = floatval($material['quantity']);
                
                // Get unit price for this material
                $price_query = "SELECT unit_price FROM materials WHERE id = ?";
                $stmt = mysqli_prepare($conn, $price_query);
                mysqli_stmt_bind_param($stmt, "i", $material_id);
                mysqli_stmt_execute($stmt);
                $price_result = mysqli_stmt_get_result($stmt);
                if ($price_result && $price_row = mysqli_fetch_assoc($price_result)) {
                    $estimated_value += $quantity * $price_row['unit_price'];
                }
            }
        }
        
        // Insert pickup record
        $pickup_query = "INSERT INTO pickups (user_id, pickup_date, time_slot, address, special_instructions, estimated_value, status) 
                         VALUES (?, ?, ?, ?, ?, ?, 'Scheduled')";
        $stmt = mysqli_prepare($conn, $pickup_query);
        mysqli_stmt_bind_param($stmt, "issssd", $user_id, $pickup_date, $time_slot, $address, $special_instructions, $estimated_value);
        
        if (mysqli_stmt_execute($stmt)) {
            $pickup_id = mysqli_insert_id($conn);
            
            // Generate transaction ID
            $transaction_id = 'TXN-' . date('Ymd') . '-' . str_pad($pickup_id, 5, '0', STR_PAD_LEFT);
            
            // Build materials list for transaction details
            $materials_list = [];
            foreach ($_POST['materials'] as $material) {
                if (isset($material['id'], $material['quantity']) && is_numeric($material['quantity'])) {
                    $material_id = intval($material['id']);
                    $quantity = floatval($material['quantity']);
                    
                    // Get material name
                    $material_name_query = "SELECT material_option FROM materials WHERE id = ?";
                    $material_name_stmt = mysqli_prepare($conn, $material_name_query);
                    mysqli_stmt_bind_param($material_name_stmt, "i", $material_id);
                    mysqli_stmt_execute($material_name_stmt);
                    $material_name_result = mysqli_stmt_get_result($material_name_stmt);
                    $material_name_row = mysqli_fetch_assoc($material_name_result);
                    
                    if ($material_name_row) {
                        $materials_list[] = $material_name_row['material_option'] . " (" . $quantity . "kg)";
                    }
                }
            }
            
            $item_details = "Scheduled Pickup: " . implode(", ", $materials_list);
            $additional_info = "Address: " . $address;
            if (!empty($special_instructions)) {
                $additional_info .= "\nSpecial Instructions: " . $special_instructions;
            }
            
            // Insert into transactions table
            $transaction_query = "INSERT INTO transactions 
                                 (transaction_id, user_id, transaction_type, transaction_date, transaction_time, 
                                 item_details, additional_info, status, amount, created_at) 
                                 VALUES (?, ?, 'Pickup', ?, ?, ?, ?, 'Pending', ?, NOW())";
            $transaction_stmt = mysqli_prepare($conn, $transaction_query);
            
            $current_date = date('Y-m-d');
            $current_time = date('H:i:s');
            
            mysqli_stmt_bind_param($transaction_stmt, "sissssd", 
                $transaction_id,
                $user_id,
                $current_date,
                $current_time,
                $item_details,
                $additional_info,
                $estimated_value
            );
            
            if (!mysqli_stmt_execute($transaction_stmt)) {
                error_log("Failed to create transaction record: " . mysqli_error($conn));
            }
            
            // Insert pickup materials
            foreach ($_POST['materials'] as $material) {
                if (isset($material['id'], $material['quantity']) && is_numeric($material['quantity'])) {
                    $material_id = intval($material['id']);
                    $quantity = floatval($material['quantity']);
                    
                    // Get unit price for this material
                    $price_query = "SELECT unit_price FROM materials WHERE id = ?";
                    $stmt = mysqli_prepare($conn, $price_query);
                    mysqli_stmt_bind_param($stmt, "i", $material_id);
                    mysqli_stmt_execute($stmt);
                    $price_result = mysqli_stmt_get_result($stmt);
                    if ($price_result && $price_row = mysqli_fetch_assoc($price_result)) {
                        $estimated_price = $quantity * $price_row['unit_price'];
                        
                        $material_query = "INSERT INTO pickup_materials (pickup_id, material_id, quantity_kg, estimated_price) 
                                         VALUES (?, ?, ?, ?)";
                        $material_stmt = mysqli_prepare($conn, $material_query);
                        mysqli_stmt_bind_param($material_stmt, "iidd", $pickup_id, $material_id, $quantity, $estimated_price);
                        mysqli_stmt_execute($material_stmt);
                        mysqli_stmt_close($material_stmt);
                    }
                }
            }
            
            $success = true;
        } else {
            $errors[] = "Error scheduling pickup: " . mysqli_error($conn);
        }
        
        mysqli_stmt_close($stmt);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JunkValue - Schedule Pickup</title>
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

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
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

        /* Pickup Steps */
        .pickup-steps {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
            position: relative;
        }
        
        .step-progress-bar {
            position: absolute;
            height: 3px;
            background-color: #e9ecef;
            top: 15px;
            left: 0;
            right: 0;
            z-index: 1;
        }
        
        .step-progress-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--icon-green) 0%, var(--stock-green) 100%);
            width: 33%;
            transition: all 0.3s;
        }
        
        .step {
            display: flex;
            flex-direction: column;
            align-items: center;
            z-index: 2;
        }
        
        .step-number {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background-color: #e9ecef;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-bottom: 5px;
            transition: all 0.3s;
        }
        
        .step.active .step-number {
            background: linear-gradient(135deg, var(--icon-green) 0%, var(--stock-green) 100%);
            color: white;
            box-shadow: 0 3px 10px rgba(106, 127, 70, 0.3);
        }
        
        .step.completed .step-number {
            background: linear-gradient(135deg, var(--icon-green) 0%, var(--stock-green) 100%);
            color: white;
        }
        
        .step.completed .step-number::after {
            content: "\f00c";
            font-family: "Font Awesome 6 Free";
            font-weight: 900;
        }
        
        .step-label {
            font-size: 13px;
            font-weight: 500;
            color: var(--text-dark);
        }
        
        /* Form Sections */
        .pickup-form-section {
            display: none;
        }
        
        .pickup-form-section.active {
            display: block;
            animation: fadeIn 0.3s ease;
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
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--icon-green);
            box-shadow: 0 0 0 3px rgba(106, 127, 70, 0.1);
        }
        
        .form-group textarea {
            min-height: 100px;
            resize: vertical;
        }
        
        /* Material Rows */
        .material-row {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
            gap: 15px;
        }
        
        .material-select {
            flex: 2;
            padding: 12px 15px;
            border: 1px solid rgba(0,0,0,0.1);
            border-radius: 8px;
            font-size: 15px;
            background-color: white;
        }
        
        .material-quantity {
            flex: 1;
            padding: 12px 15px;
            border: 1px solid rgba(0,0,0,0.1);
            border-radius: 8px;
            max-width: 120px;
            font-size: 15px;
        }
        
        .material-value {
            flex: 1;
            font-weight: 600;
            color: var(--icon-green);
            min-width: 100px;
            font-size: 15px;
        }
        
        .remove-material {
            color: #dc3545;
            cursor: pointer;
            padding: 10px;
            font-size: 16px;
            transition: all 0.2s;
        }
        
        .remove-material:hover {
            transform: scale(1.1);
        }
        
        .add-material {
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--icon-green);
            cursor: pointer;
            margin: 20px 0;
            font-weight: 500;
            padding: 10px 0;
            transition: all 0.2s;
        }
        
        .add-material:hover {
            color: var(--stock-green);
        }
        
        .add-material i {
            font-size: 18px;
        }
        
        /* Summary */
        .pickup-summary {
            background-color: rgba(106, 127, 70, 0.05);
            border-radius: 12px;
            padding: 25px;
            margin-top: 25px;
            border: 1px solid rgba(106, 127, 70, 0.1);
        }
        
        .summary-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            font-size: 15px;
        }
        
        .summary-total {
            font-weight: 600;
            border-top: 1px solid rgba(0,0,0,0.1);
            padding-top: 15px;
            margin-top: 15px;
            font-size: 16px;
        }
        
        /* Buttons */
        .btn {
            padding: 12px 25px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            border: none;
            font-size: 15px;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-outline {
            background-color: white;
            border: 1px solid rgba(0,0,0,0.1);
            color: var(--text-dark);
        }
        
        .btn-outline:hover {
            background-color: #f8f9fa;
            border-color: rgba(0,0,0,0.2);
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
        
        /* Time Slots */
        .time-slots {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
            gap: 12px;
            margin-top: 15px;
        }
        
        .time-slot {
            border: 1px solid rgba(0,0,0,0.1);
            border-radius: 8px;
            padding: 15px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 14px;
            background-color: white;
        }
        
        .time-slot:hover {
            border-color: var(--icon-green);
            box-shadow: 0 3px 10px rgba(0,0,0,0.05);
        }
        
        .time-slot.selected {
            background: linear-gradient(135deg, var(--icon-green) 0%, var(--stock-green) 100%);
            color: white;
            border-color: var(--icon-green);
            box-shadow: 0 3px 10px rgba(106, 127, 70, 0.3);
        }
        
        .time-slot.disabled {
            opacity: 0.5;
            cursor: not-allowed;
            background-color: #f8f9fa;
        }
        
        /* Messages */
        .error-message {
            color: #dc3545;
            margin-bottom: 20px;
            padding: 15px;
            background-color: #f8d7da;
            border-radius: 8px;
            border: 1px solid #f5c6cb;
        }
        
        .success-message {
            color: #28a745;
            margin-bottom: 30px;
            padding: 30px;
            background-color: #d4edda;
            border-radius: 12px;
            border: 1px solid #c3e6cb;
            text-align: center;
        }

        .success-message h3 {
            margin-top: 0;
            color: #28a745;
            font-size: 24px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .success-message p {
            margin-bottom: 15px;
            font-size: 16px;
            line-height: 1.6;
        }

        .success-message .btn {
            margin-top: 15px;
            padding: 12px 30px;
            font-size: 16px;
        }
        
        /* Form Actions */
        .form-actions {
            display: flex;
            justify-content: space-between;
            margin-top: 30px;
        }
        
        /* Terms Checkbox */
        .terms-checkbox {
            display: flex;
            align-items: center;
            margin: 30px 0;
            padding: 20px;
            background-color: rgba(106, 127, 70, 0.05);
            border-radius: 12px;
            border: 1px dashed rgba(106, 127, 70, 0.3);
        }

        .terms-checkbox input[type="checkbox"] {
            width: 20px;
            height: 20px;
            margin-right: 15px;
            accent-color: var(--icon-green);
        }

        .terms-checkbox label {
            font-size: 15px;
            cursor: pointer;
            color: var(--text-dark);
        }

        .terms-checkbox a {
            color: var(--icon-green);
            text-decoration: none;
            font-weight: 500;
        }

        .terms-checkbox a:hover {
            text-decoration: underline;
        }
        
        /* Error states */
        .invalid {
            border-color: #dc3545 !important;
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
            .material-row {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            .material-select,
            .material-quantity {
                width: 100%;
                max-width: none;
            }
            
            .time-slots {
                grid-template-columns: 1fr 1fr;
            }
            
            .form-actions {
                flex-direction: column;
                gap: 10px;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
        }
        
        @media (max-width: 480px) {
            .pickup-steps {
                margin-bottom: 20px;
            }
            
            .step-label {
                font-size: 12px;
            }
            
            .time-slots {
                grid-template-columns: 1fr;
            }
            
            .card {
                padding: 20px;
            }
            
            .pickup-summary {
                padding: 20px;
            }
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
                    <li><a href="transaction.php"><i class="fas fa-history"></i> Transaction History</a></li>
                    <li><a href="#" class="active"><i class="fas fa-calendar-alt"></i> Schedule Pickup</a></li>
                    <li><a href="prices.php"><i class="fas fa-coins"></i> Current Prices</a></li>
                    <li><a href="rewards.php"><i class="fas fa-award"></i> Loyalty Rewards</a></li>
                    <li><a href="settings.php"><i class="fas fa-user-cog"></i> Account Settings</a></li>
                    <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                </ul>
            </div>
            
            <!-- Main Content -->
            <div class="main-content">
                <div class="card">
                    <?php if ($success): ?>
                        <div class="success-message">
                            <h3><i class="fas fa-check-circle"></i> Pickup Scheduled Successfully!</h3>
                            <p>Your junk pickup has been scheduled for <strong><?php echo htmlspecialchars($pickup_date); ?></strong> during <strong><?php echo htmlspecialchars($time_slot); ?></strong>.</p>
                            <p>Estimated Value: <strong>₱<?php echo number_format($estimated_value, 2); ?></strong></p>
                            <p>We'll send you a confirmation email with all the details and notify you when our collector is on the way.</p>
                            <div style="display: flex; gap: 15px; justify-content: center; margin-top: 20px;">
                                <a href="transaction.php" class="btn btn-primary">
                                    <i class="fas fa-history"></i> View My Pickups
                                </a>
                                <a href="index.php" class="btn btn-outline">
                                    <i class="fas fa-home"></i> Return to Dashboard
                                </a>
                            </div>
                        </div>
                    <?php else: ?>
                        <?php if (!empty($errors)): ?>
                            <div class="error-message">
                                <?php foreach ($errors as $error): ?>
                                    <p><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></p>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        
                        <h2 style="margin-bottom: 20px; color: var(--text-dark); display: flex; align-items: center; gap: 10px;">
                            <i class="fas fa-calendar-alt" style="color: var(--icon-green);"></i> Schedule a Junk Pickup
                        </h2>
                        
                        <!-- Step Progress -->
                        <div class="pickup-steps">
                            <div class="step-progress-bar">
                                <div class="step-progress-fill" id="stepProgress"></div>
                            </div>
                            <div class="step active" id="step1">
                                <div class="step-number">1</div>
                                <div class="step-label">Materials</div>
                            </div>
                            <div class="step" id="step2">
                                <div class="step-number">2</div>
                                <div class="step-label">Time & Address</div>
                            </div>
                            <div class="step" id="step3">
                                <div class="step-number">3</div>
                                <div class="step-label">Confirmation</div>
                            </div>
                        </div>
                        
                        <form method="POST" id="pickupForm">
                            <!-- Step 1: Materials -->
                            <div class="pickup-form-section active" id="section1">
                                <h3 style="margin-bottom: 20px; color: var(--text-dark);">What are you recycling?</h3>
                                
                                <div id="materialList">
                                    <div class="material-row" data-index="0">
                                        <select class="material-select" name="materials[0][id]" required>
                                            <option value="">Select material</option>
                                            <?php foreach ($materials as $material): ?>
                                                <option value="<?php echo $material['id']; ?>" 
                                                        data-price="<?php echo $material['unit_price']; ?>">
                                                    <?php echo htmlspecialchars($material['material_option']); ?> (₱<?php echo number_format($material['unit_price'], 2); ?>/kg)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <input type="number" class="material-quantity" name="materials[0][quantity]" 
                                               placeholder="Weight (kg)" min="0.1" step="0.1" required>
                                        <span class="material-value">₱0.00</span>
                                        <span class="remove-material" style="visibility: hidden;">
                                            <i class="fas fa-times"></i>
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="add-material" id="addMaterial">
                                    <i class="fas fa-plus-circle"></i>
                                    <span>Add another material</span>
                                </div>
                                
                                <div class="pickup-summary" id="materialSummary">
                                    <h4 style="margin-bottom: 15px; color: var(--text-dark);">Estimated Value</h4>
                                    <div id="summaryItems">
                                        <!-- Items will be added here by JavaScript -->
                                    </div>
                                    <div class="summary-total">
                                        <span>Estimated Total</span>
                                        <span id="estimatedTotal">₱0.00</span>
                                    </div>
                                    <p style="font-size: 14px; color: var(--text-light); margin-top: 15px;">
                                        <i class="fas fa-info-circle"></i> Final amount may vary based on actual weight and quality at time of pickup.
                                    </p>
                                </div>
                                
                                <div class="form-actions">
                                    <button class="btn btn-outline" disabled>Back</button>
                                    <button class="btn btn-primary" type="button" id="continueButton" onclick="validateMaterials()">
                                        <i class="fas fa-arrow-right"></i> Continue
                                    </button>
                                </div>
                            </div>
                            
                            <!-- Step 2: Time & Address -->
                            <div class="pickup-form-section" id="section2">
                                <h3 style="margin-bottom: 20px; color: var(--text-dark);">When & Where?</h3>
                                
                                <div class="form-group">
                                    <label>Pickup Date</label>
                                    <input type="date" id="pickupDate" name="pickup_date" min="<?php echo date('Y-m-d'); ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label>Time Slot</label>
                                    <div class="time-slots">
                                        <div class="time-slot" data-value="8:00 - 10:00 AM">8:00 - 10:00 AM</div>
                                        <div class="time-slot selected" data-value="10:00 - 12:00 PM">10:00 - 12:00 PM</div>
                                        <div class="time-slot" data-value="1:00 - 3:00 PM">1:00 - 3:00 PM</div>
                                        <div class="time-slot" data-value="3:00 - 5:00 PM">3:00 - 5:00 PM</div>
                                        <div class="time-slot disabled" data-value="5:00 - 7:00 PM">5:00 - 7:00 PM</div>
                                    </div>
                                    <input type="hidden" id="timeSlotInput" name="time_slot" value="10:00 - 12:00 PM" required>
                                </div>
                                
                                <div class="form-group">
                                    <label>Pickup Address</label>
                                    <textarea id="pickupAddress" name="address" required><?php echo htmlspecialchars($user_address); ?></textarea>
                                    <button type="button" id="useSavedAddress" class="btn btn-outline" style="margin-top: 10px; padding: 8px 15px; font-size: 13px;">
                                        <i class="fas fa-undo"></i> Use my saved address
                                    </button>
                                </div>
                                
                                <div class="form-group">
                                    <label>Special Instructions (Optional)</label>
                                    <textarea id="specialInstructions" name="special_instructions" placeholder="E.g. Gate code, landmarks, specific location on property, etc."></textarea>
                                </div>
                                
                                <div class="form-actions">
                                    <button class="btn btn-outline" type="button" onclick="prevStep(2)">
                                        <i class="fas fa-arrow-left"></i> Back
                                    </button>
                                    <button class="btn btn-primary" type="button" onclick="validateTimeAndAddress()">
                                        <i class="fas fa-arrow-right"></i> Continue
                                    </button>
                                </div>
                            </div>
                            
                            <!-- Step 3: Confirmation -->
                            <div class="pickup-form-section" id="section3">
                                <h3 style="margin-bottom: 20px; color: var(--text-dark);">Confirm Your Pickup</h3>
                                
                                <div class="pickup-summary">
                                    <h4 style="margin-bottom: 20px; color: var(--text-dark);">Pickup Details</h4>
                                    
                                    <div class="summary-item">
                                        <span><strong>Materials:</strong></span>
                                        <span></span>
                                    </div>
                                    <div style="margin-left: 15px; margin-bottom: 15px;" id="confirmationMaterials">
                                        <!-- Will be filled by JavaScript -->
                                    </div>
                                    
                                    <div class="summary-item">
                                        <span>Estimated Value:</span>
                                        <span id="confirmationValue">₱0.00</span>
                                    </div>
                                    
                                    <div class="summary-item">
                                        <span>Pickup Date:</span>
                                        <span id="confirmationDate"></span>
                                    </div>
                                    
                                    <div class="summary-item">
                                        <span>Time Slot:</span>
                                        <span id="confirmationTime"></span>
                                    </div>
                                    
                                    <div class="summary-item">
                                        <span>Address:</span>
                                        <span id="confirmationAddress" style="max-width: 300px; display: inline-block; text-align: right;"></span>
                                    </div>
                                    
                                    <div class="summary-item">
                                        <span>Special Instructions:</span>
                                        <span id="confirmationInstructions" style="font-style: italic;"></span>
                                    </div>
                                </div>
                                
                                <div class="terms-checkbox">
                                    <input type="checkbox" id="confirmTerms" name="confirm_terms" required>
                                    <label for="confirmTerms">I agree to the <a href="terms.php">Terms of Service</a> and confirm that all materials listed are acceptable for recycling per our guidelines.</label>
                                </div>
                                
                                <div class="form-actions">
                                    <button class="btn btn-outline" type="button" onclick="prevStep(3)">
                                        <i class="fas fa-arrow-left"></i> Back
                                    </button>
                                    <button class="btn btn-primary" id="confirmBtn" type="submit" name="confirm_pickup" disabled>
                                        <i class="fas fa-calendar-check"></i> Confirm & Schedule Pickup
                                    </button>
                                </div>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Set minimum date to today
        document.getElementById('pickupDate').min = new Date().toISOString().split('T')[0];
        
        // Time slot selection
        const timeSlots = document.querySelectorAll('.time-slot:not(.disabled)');
        timeSlots.forEach(slot => {
            slot.addEventListener('click', function() {
                document.querySelector('.time-slot.selected')?.classList.remove('selected');
                this.classList.add('selected');
                document.getElementById('timeSlotInput').value = this.dataset.value;
            });
        });
        
        // Terms checkbox
        document.getElementById('confirmTerms').addEventListener('change', function() {
            document.getElementById('confirmBtn').disabled = !this.checked;
        });
        
        // Use saved address button
        document.getElementById('useSavedAddress').addEventListener('click', function() {
            document.getElementById('pickupAddress').value = `<?php echo addslashes($user_address); ?>`;
        });
        
        function nextStep(currentStep) {
            // Hide current section and show next
            document.getElementById(`section${currentStep}`).classList.remove('active');
            document.getElementById(`section${currentStep + 1}`).classList.add('active');
            
            // Update step indicators
            document.getElementById(`step${currentStep}`).classList.remove('active');
            document.getElementById(`step${currentStep}`).classList.add('completed');
            document.getElementById(`step${currentStep + 1}`).classList.add('active');
            
            // Update progress bar
            document.getElementById('stepProgress').style.width = `${(currentStep / 3) * 100}%`;
            
            // Scroll to top of form
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
        
        function prevStep(currentStep) {
            document.getElementById(`section${currentStep}`).classList.remove('active');
            document.getElementById(`section${currentStep - 1}`).classList.add('active');
            
            document.getElementById(`step${currentStep}`).classList.remove('active');
            document.getElementById(`step${currentStep - 1}`).classList.add('active');
            document.getElementById(`step${currentStep - 1}`).classList.remove('completed');
            
            // Update progress bar
            document.getElementById('stepProgress').style.width = `${((currentStep - 2) / 3) * 100}%`;
            
            // Scroll to top of form
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
        
        // Add material row
        let materialCounter = 1;
        document.getElementById('addMaterial').addEventListener('click', function() {
            const newRow = document.createElement('div');
            newRow.className = 'material-row';
            newRow.dataset.index = materialCounter;
            newRow.innerHTML = `
                <select class="material-select" name="materials[${materialCounter}][id]" required>
                    <option value="">Select material</option>
                    <?php foreach ($materials as $material): ?>
                        <option value="<?php echo $material['id']; ?>" 
                                data-price="<?php echo $material['unit_price']; ?>">
                            <?php echo htmlspecialchars($material['material_option']); ?> (₱<?php echo number_format($material['unit_price'], 2); ?>/kg)
                        </option>
                    <?php endforeach; ?>
                </select>
                <input type="number" class="material-quantity" name="materials[${materialCounter}][quantity]" 
                       placeholder="Weight (kg)" min="0.1" step="0.1" required>
                <span class="material-value">₱0.00</span>
                <span class="remove-material">
                    <i class="fas fa-times"></i>
                </span>
            `;
            document.getElementById('materialList').appendChild(newRow);
            materialCounter++;
            
            // Add event listeners to the new row
            addMaterialEventListeners(newRow);
        });
        
        // Function to add event listeners to a material row
        function addMaterialEventListeners(row) {
            const select = row.querySelector('.material-select');
            const input = row.querySelector('.material-quantity');
            const valueSpan = row.querySelector('.material-value');
            const removeBtn = row.querySelector('.remove-material');
            
            // Calculate value when material or quantity changes
            select.addEventListener('change', function() {
                calculateRowValue(row);
            });
            
            input.addEventListener('input', function() {
                calculateRowValue(row);
            });
            
            // Remove row when X is clicked
            removeBtn.addEventListener('click', function() {
                row.remove();
                calculateTotalValue();
            });
            
            function calculateRowValue() {
                const price = parseFloat(select.options[select.selectedIndex]?.dataset.price) || 0;
                const quantity = parseFloat(input.value) || 0;
                const total = price * quantity;
                valueSpan.textContent = `₱${total.toFixed(2)}`;
                calculateTotalValue();
            }
        }
        
        // Function to calculate total value of all materials
        function calculateTotalValue() {
            const rows = document.querySelectorAll('.material-row');
            let items = [];
            
            rows.forEach(row => {
                const select = row.querySelector('.material-select');
                const input = row.querySelector('.material-quantity');
                const materialId = select.value;
                const quantity = parseFloat(input.value) || 0;
                
                if (materialId && quantity > 0) {
                    const materialName = select.options[select.selectedIndex].text.split(' (')[0];
                    const price = parseFloat(select.options[select.selectedIndex].dataset.price) || 0;
                    const itemTotal = quantity * price;
                    
                    items.push({
                        name: materialName,
                        quantity: quantity,
                        price: price,
                        total: itemTotal
                    });
                }
            });
            
            // Update summary items
            const summaryItems = document.getElementById('summaryItems');
            summaryItems.innerHTML = '';
            
            let grandTotal = 0;
            items.forEach(item => {
                grandTotal += item.total;
                const itemElement = document.createElement('div');
                itemElement.className = 'summary-item';
                itemElement.innerHTML = `
                    <span>${item.name} (${item.quantity}kg)</span>
                    <span>₱${item.total.toFixed(2)}</span>
                `;
                summaryItems.appendChild(itemElement);
            });
            
            // Update grand total
            document.getElementById('estimatedTotal').textContent = `₱${grandTotal.toFixed(2)}`;
        }
        
        function validateMaterials() {
            let valid = true;
            const rows = document.querySelectorAll('.material-row');
            
            // Reset error states
            document.querySelectorAll('.material-select, .material-quantity').forEach(el => {
                el.classList.remove('invalid');
            });
            
            // Remove any existing error messages
            const existingError = document.querySelector('#section1 .error-message');
            if (existingError) existingError.remove();
            
            // Check if at least one material is selected with quantity
            let hasValidMaterial = false;
            rows.forEach(row => {
                const select = row.querySelector('.material-select');
                const input = row.querySelector('.material-quantity');
                
                if (!select.value || !input.value || parseFloat(input.value) <= 0) {
                    valid = false;
                    select.classList.add('invalid');
                    input.classList.add('invalid');
                } else {
                    hasValidMaterial = true;
                }
            });
            
            if (!valid || !hasValidMaterial) {
                // Create and show error message
                const errorDiv = document.createElement('div');
                errorDiv.className = 'error-message';
                errorDiv.innerHTML = '<p><i class="fas fa-exclamation-circle"></i> Please select at least one material and enter a valid quantity (greater than 0)</p>';
                document.querySelector('#section1 h3').insertAdjacentElement('afterend', errorDiv);
                
                // Scroll to error message
                errorDiv.scrollIntoView({ behavior: 'smooth', block: 'center' });
                return false;
            }
            
            // Proceed to next step if valid
            nextStep(1);
            return true;
        }
        
        function validateTimeAndAddress() {
            // Validate time and address
            const date = document.getElementById('pickupDate');
            const address = document.getElementById('pickupAddress');
            
            // Reset error states
            date.classList.remove('invalid');
            address.classList.remove('invalid');
            
            // Remove any existing error messages
            const existingError = document.querySelector('#section2 .error-message');
            if (existingError) existingError.remove();
            
            let isValid = true;
            let errorMessage = '';
            
            if (!date.value) {
                date.classList.add('invalid');
                errorMessage += '<p><i class="fas fa-exclamation-circle"></i> Please select a pickup date</p>';
                isValid = false;
            }
            
            if (!document.getElementById('timeSlotInput').value) {
                errorMessage += '<p><i class="fas fa-exclamation-circle"></i> Please select a time slot</p>';
                isValid = false;
            }
            
            if (!address.value) {
                address.classList.add('invalid');
                errorMessage += '<p><i class="fas fa-exclamation-circle"></i> Please enter a pickup address</p>';
                isValid = false;
            }
            
            if (!isValid) {
                // Create and show error message
                const errorDiv = document.createElement('div');
                errorDiv.className = 'error-message';
                errorDiv.innerHTML = errorMessage;
                document.querySelector('#section2 h3').insertAdjacentElement('afterend', errorDiv);
                
                // Scroll to error message
                errorDiv.scrollIntoView({ behavior: 'smooth', block: 'center' });
                return;
            }
            
            updateConfirmationDetails();
            nextStep(2);
        }
        
        // Update confirmation details
        function updateConfirmationDetails() {
            // Materials
            const materialsHtml = [];
            const materialItems = document.querySelectorAll('.material-row');
            materialItems.forEach(item => {
                const select = item.querySelector('.material-select');
                const input = item.querySelector('.material-quantity');
                if (select.value && input.value) {
                    const materialName = select.options[select.selectedIndex].text.split(' (')[0];
                    materialsHtml.push(`• ${input.value}kg ${materialName}`);
                }
            });
            document.getElementById('confirmationMaterials').innerHTML = materialsHtml.join('<br>');
            
            // Estimated value
            document.getElementById('confirmationValue').textContent = document.getElementById('estimatedTotal').textContent;
            
            // Date
            const date = new Date(document.getElementById('pickupDate').value);
            document.getElementById('confirmationDate').textContent = date.toLocaleDateString('en-US', { 
                year: 'numeric', month: 'long', day: 'numeric' 
            });
            
            // Time
            document.getElementById('confirmationTime').textContent = document.getElementById('timeSlotInput').value;
            
            // Address
            document.getElementById('confirmationAddress').textContent = document.getElementById('pickupAddress').value;
            
            // Instructions
            const instructions = document.getElementById('specialInstructions').value || 'None provided';
            document.getElementById('confirmationInstructions').textContent = instructions;
        }
        
        // Add event listeners to existing material selects/inputs
        document.querySelectorAll('#materialList .material-select, #materialList .material-quantity').forEach(el => {
            if (el.classList.contains('material-select') || el.classList.contains('material-quantity')) {
                el.addEventListener('change', function() {
                    const row = this.closest('.material-row');
                    calculateRowValue(row);
                });
                el.addEventListener('input', function() {
                    const row = this.closest('.material-row');
                    calculateRowValue(row);
                });
            }
        });
        
        // Initialize calculation
        calculateTotalValue();
        
        // Helper function to calculate value for a single row
        function calculateRowValue(row) {
            const select = row.querySelector('.material-select');
            const input = row.querySelector('.material-quantity');
            const valueSpan = row.querySelector('.material-value');
            
            const price = parseFloat(select.options[select.selectedIndex]?.dataset.price) || 0;
            const quantity = parseFloat(input.value) || 0;
            const total = price * quantity;
            valueSpan.textContent = `₱${total.toFixed(2)}`;
            calculateTotalValue();
        }
    </script>
</body>
</html>
<?php
// Close database connection
mysqli_close($conn);
?>