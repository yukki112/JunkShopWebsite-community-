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
$user_query = "SELECT first_name, last_name, profile_image FROM users WHERE id = ?";
$user_stmt = mysqli_prepare($conn, $user_query);
mysqli_stmt_bind_param($user_stmt, "i", $user_id);
mysqli_stmt_execute($user_stmt);
$user_result = mysqli_stmt_get_result($user_stmt);
$user = mysqli_fetch_assoc($user_result);
$user_name = $user['first_name'] . ' ' . $user['last_name'];
$user_initials = strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1));

// Get 3 most recent transactions
$transaction_query = "SELECT * FROM transactions 
                     WHERE user_id = ? 
                     ORDER BY transaction_date DESC, transaction_time DESC 
                     LIMIT 3";
$transaction_stmt = mysqli_prepare($conn, $transaction_query);
mysqli_stmt_bind_param($transaction_stmt, "i", $user_id);
mysqli_stmt_execute($transaction_stmt);
$transaction_result = mysqli_stmt_get_result($transaction_stmt);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JunkValue - Customer Portal</title>
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

        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .action-btn {
            background-color: white;
            border-radius: 10px;
            padding: 20px 15px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            box-shadow: 0 3px 10px rgba(0,0,0,0.08);
            border: 1px solid rgba(0,0,0,0.05);
            text-decoration: none;
            color: var(--text-dark);
        }

        .action-btn:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.12);
            border-color: var(--icon-green);
        }

        .action-btn i {
            font-size: 28px;
            color: var(--icon-green);
            margin-bottom: 12px;
            background-color: rgba(106, 127, 70, 0.1);
            width: 50px;
            height: 50px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
        }

        .action-btn span {
            display: block;
            font-weight: 500;
            font-size: 15px;
        }

        .price-table {
            width: 100%;
            border-collapse: collapse;
        }

        .price-table th, .price-table td {
            padding: 14px 15px;
            text-align: left;
            border-bottom: 1px solid rgba(0,0,0,0.05);
        }

        .price-table th {
            background-color: rgba(106, 127, 70, 0.05);
            font-weight: 600;
            color: var(--icon-green);
        }

        .price-table tr:last-child td {
            border-bottom: none;
        }

        .price-table tr:hover td {
            background-color: rgba(106, 127, 70, 0.03);
        }

        .loyalty-status {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 25px;
            background-color: rgba(106, 127, 70, 0.05);
            padding: 20px;
            border-radius: 10px;
        }

        .loyalty-badge {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, #e9ecef 0%, #d1d7dc 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            color: var(--sales-orange);
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
        }

        .progress-bar {
            height: 10px;
            background-color: #e9ecef;
            border-radius: 5px;
            margin: 12px 0;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            width: 65%;
            background: linear-gradient(90deg, var(--icon-green) 0%, var(--stock-green) 100%);
            border-radius: 5px;
            position: relative;
            animation: progressAnimation 1.5s ease-in-out;
        }

        @keyframes progressAnimation {
            from { width: 0; }
            to { width: 65%; }
        }

        .transaction {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 18px 0;
            border-bottom: 1px solid rgba(0,0,0,0.05);
            transition: all 0.3s;
        }

        .transaction:hover {
            background-color: rgba(106, 127, 70, 0.03);
            border-radius: 8px;
            padding: 18px 15px;
        }

        .transaction:last-child {
            border-bottom: none;
        }

        .transaction-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .transaction-icon {
            width: 45px;
            height: 45px;
            background-color: rgba(106, 127, 70, 0.1);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--icon-green);
            font-size: 18px;
        }

        .transaction-details h4 {
            margin-bottom: 5px;
            font-weight: 500;
        }

        .transaction-details p {
            font-size: 14px;
            color: var(--text-light);
        }

        .transaction-amount {
            font-weight: 600;
            color: var(--icon-green);
            font-size: 16px;
        }

        .grid-2-col {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .benefits-list {
            list-style: none;
        }

        .benefits-list li {
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .benefits-list i {
            color: var(--icon-green);
            font-size: 18px;
        }

        .reward-card {
            background-color: rgba(106, 127, 70, 0.05);
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
        }

        .reward-card p {
            font-size: 14px;
            margin-bottom: 10px;
        }

        .progress-mini {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .progress-mini-bar {
            flex-grow: 1;
            height: 6px;
            background-color: #e9ecef;
            border-radius: 3px;
            overflow: hidden;
        }

        .progress-mini-fill {
            height: 100%;
            width: 30%;
            background: linear-gradient(90deg, var(--icon-green) 0%, var(--stock-green) 100%);
            border-radius: 3px;
        }

        .btn-primary {
            background: linear-gradient(90deg, var(--icon-green) 0%, var(--stock-green) 100%);
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 8px;
            cursor: pointer;
            width: 100%;
            font-weight: 500;
            font-size: 15px;
            transition: all 0.3s;
            box-shadow: 0 3px 10px rgba(106, 127, 70, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(106, 127, 70, 0.4);
        }

        .trend-up {
            color: var(--stock-green);
        }

        .trend-down {
            color: var(--sales-orange);
        }

        .trend-neutral {
            color: var(--text-light);
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
            animation: fadeIn 0.3s;
        }
        
        .modal-content {
            background-color: white;
            margin: 10% auto;
            padding: 25px;
            border-radius: 12px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 5px 30px rgba(0,0,0,0.3);
            position: relative;
            animation: slideDown 0.3s;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        @keyframes slideDown {
            from { transform: translateY(-50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        
        .close-modal {
            position: absolute;
            top: 15px;
            right: 20px;
            font-size: 24px;
            font-weight: bold;
            color: var(--text-light);
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .close-modal:hover {
            color: var(--icon-green);
        }
        
        .modal h3 {
            color: var(--icon-green);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        /* Calculator Styles */
        .calculator-form {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        .form-group label {
            font-weight: 500;
            color: var(--text-dark);
        }
        
        .form-group select, .form-group input {
            padding: 12px 15px;
            border: 1px solid rgba(0,0,0,0.1);
            border-radius: 8px;
            font-size: 15px;
        }
        
        .form-group select:focus, .form-group input:focus {
            outline: none;
            border-color: var(--icon-green);
        }
        
        .result-container {
            margin-top: 20px;
            text-align: center;
            padding: 20px;
            background-color: rgba(106, 127, 70, 0.05);
            border-radius: 8px;
        }
        
        #calculated-result {
            font-size: 28px;
            font-weight: 600;
            color: var(--icon-green);
            margin-top: 10px;
        }
        
        /* QR Code Styles */
        .qr-code-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 15px;
            padding: 20px 0;
        }
        
        .qr-code-image {
            width: 200px;
            height: 200px;
            border: 1px solid rgba(0,0,0,0.1);
            padding: 10px;
            background: white;
        }
        
        /* Referral Styles */
        .referral-link-container {
            display: flex;
            margin: 15px 0;
        }
        
        #referral-link {
            flex-grow: 1;
            padding: 12px 15px;
            border: 1px solid rgba(0,0,0,0.1);
            border-radius: 8px 0 0 8px;
            font-size: 14px;
        }
        
        .btn-copy {
            background-color: var(--icon-green);
            color: white;
            border: none;
            padding: 0 15px;
            border-radius: 0 8px 8px 0;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn-copy:hover {
            background-color: var(--stock-green);
        }
        
        .social-share {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
        
        .social-btn {
            flex: 1;
            padding: 10px;
            border: none;
            border-radius: 6px;
            color: white;
            font-weight: 500;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.3s;
        }
        
        .social-btn.facebook {
            background-color: #3b5998;
        }
        
        .social-btn.whatsapp {
            background-color: #25D366;
        }
        
        .social-btn.email {
            background-color: var(--sales-orange);
        }
        
        .social-btn:hover {
            transform: translateY(-2px);
        }

        /* Responsive styles */
        @media (max-width: 1024px) {
            .dashboard {
                grid-template-columns: 1fr;
            }
            
            .sidebar {
                position: static;
            }
            
            .grid-2-col {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .quick-actions {
                grid-template-columns: 1fr 1fr;
            }
            
            .loyalty-status {
                flex-direction: column;
                text-align: center;
            }
            
            .header-content {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
        }

        @media (max-width: 480px) {
            .quick-actions {
                grid-template-columns: 1fr;
            }
            
            .card {
                padding: 20px 15px;
            }

            .social-share {
                flex-direction: column;
            }
        }

        /* Animation for cards */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .card {
            animation: fadeIn 0.5s ease-out forwards;
        }

        .card:nth-child(1) { animation-delay: 0.1s; }
        .card:nth-child(2) { animation-delay: 0.2s; }
        .card:nth-child(3) { animation-delay: 0.3s; }
        .card:nth-child(4) { animation-delay: 0.4s; }

        /* Hover effects for loyalty badge */
        .loyalty-badge {
            transition: all 0.3s;
        }

        .loyalty-badge:hover {
            transform: scale(1.1);
            box-shadow: 0 5px 15px rgba(217, 122, 65, 0.3);
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
            <div class="sidebar">
                <ul class="nav-menu">
                    <li><a href="#" class="active"><i class="fas fa-home"></i> Dashboard</a></li>
                    <li><a href="Transaction.php"><i class="fas fa-history"></i> Transaction History</a></li>
                    <li><a href="Schedule.php"><i class="fas fa-calendar-alt"></i> Schedule Pickup</a></li>
                    <li><a href="prices.php"><i class="fas fa-coins"></i> Current Prices</a></li>
                    <li><a href="rewards.php"><i class="fas fa-award"></i> Loyalty Rewards</a></li>
                    <li><a href="settings.php"><i class="fas fa-user-cog"></i> Account Settings</a></li>
                    <li><a href="Login/Login.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                </ul>
            </div>
            
            <!-- Main Content -->
            <div class="main-content">
                <!-- Welcome Banner -->
                <div class="card" style="background: linear-gradient(135deg, var(--panel-cream) 0%, var(--bg-beige) 100%); border: 1px solid rgba(217, 122, 65, 0.2);">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <h2 style="color: var(--topbar-brown); margin-bottom: 10px;">Welcome back, <?php echo htmlspecialchars($user['first_name']); ?>!</h2>
                            <p style="color: var(--text-dark); max-width: 600px;">Ready to turn your scrap into cash? Check today's prices, schedule a pickup, or track your rewards below.</p>
                        </div>
                        <div style="font-size: 60px; color: rgba(217, 122, 65, 0.2);">
                            <i class="fas fa-recycle"></i>
                        </div>
                    </div>
                </div>
                
                <!-- Quick Actions -->
                <div class="quick-actions">
                    <a href="Schedule.php" class="action-btn">
                        <i class="fas fa-truck"></i>
                        <span>Request Pickup</span>
                    </a>
                    <div class="action-btn" onclick="document.getElementById('priceCalculatorModal').style.display='block'">
                        <i class="fas fa-calculator"></i>
                        <span>Price Calculator</span>
                    </div>
                    <div class="action-btn" onclick="document.getElementById('qrCodeModal').style.display='block'">
                        <i class="fas fa-qrcode"></i>
                        <span>Scan QR Code</span>
                    </div>
                    <div class="action-btn" onclick="document.getElementById('referFriendModal').style.display='block'">
                        <i class="fas fa-share-alt"></i>
                        <span>Refer a Friend</span>
                    </div>
                </div>
                
              <!-- Current Prices -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-coins"></i> Today's Scrap Prices</h3>
        <span style="color: var(--text-light); font-size: 14px;"><i class="fas fa-sync-alt"></i> Updated: <?php echo date('g:i A'); ?></span>
    </div>
    <table class="price-table">
        <thead>
            <tr>
                <th>Material</th>
                <th>Price (per kg)</th>
                <th>Trend</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Copper Wire</td>
                <td>₱285.70</td>
                <td><i class="fas fa-arrow-up trend-up"></i> +₱5.00</td>
            </tr>
            <tr>
                <td>PET Bottles</td>
                <td>₱28.57</td>
                <td><i class="fas fa-arrow-up trend-up"></i> +₱1.00</td>
            </tr>
            <tr>
                <td>Aluminum Cans</td>
                <td>₱68.57</td>
                <td><i class="fas fa-arrow-down trend-down"></i> -₱2.00</td>
            </tr>
            <tr>
                <td>Cardboard</td>
                <td>₱17.14</td>
                <td><i class="fas fa-equals trend-neutral"></i></td>
            </tr>
            <tr>
                <td>Steel</td>
                <td>₱45.71</td>
                <td><i class="fas fa-arrow-up trend-up"></i> +₱3.00</td>
            </tr>
            <tr>
                <td>Glass Bottles</td>
                <td>₱14.29</td>
                <td><i class="fas fa-equals trend-neutral"></i></td>
            </tr>
            <tr>
                <td>Computer Parts</td>
                <td>₱85.71</td>
                <td><i class="fas fa-arrow-up trend-up"></i> +₱8.00</td>
            </tr>
            <tr>
                <td>Iron Scrap</td>
                <td>₱18.00</td>
                <td><i class="fas fa-equals trend-neutral"></i></td>
            </tr>
            <tr>
                <td>Stainless Steel</td>
                <td>₱65.00</td>
                <td><i class="fas fa-arrow-up trend-up"></i> +₱3.00</td>
            </tr>
            <tr>
                <td>E-Waste</td>
                <td>₱120.00</td>
                <td><i class="fas fa-arrow-up trend-up"></i> +₱8.00</td>
            </tr>
        </tbody>
    </table>
</div>
                
                <!-- Loyalty Program -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-award"></i> Your Loyalty Rewards</h3>
                    </div>
                    <div class="loyalty-status">
                        <div class="loyalty-badge">
                            <i class="fas fa-crown"></i>
                        </div>
                        <div class="loyalty-progress">
                            <div class="progress-bar">
                                <div class="progress-fill"></div>
                            </div>
                            <p style="color: var(--text-dark);">Silver Member (650/1000 points to Gold)</p>
                        </div>
                    </div>
                    <div class="grid-2-col">
                        <div>
                            <h4 style="margin-bottom: 15px; color: var(--text-dark);">Your Benefits</h4>
                            <ul class="benefits-list">
                                <li><i class="fas fa-check-circle"></i> 5% bonus on all sales</li>
                                <li><i class="fas fa-check-circle"></i> 2 free pickups/month</li>
                                <li><i class="fas fa-check-circle"></i> Priority service</li>
                            </ul>
                        </div>
                        <div>
                            <h4 style="margin-bottom: 15px; color: var(--text-dark);">Quick Rewards</h4>
                            <div class="reward-card">
                                <p>Recycle 50kg this week to earn +100 points</p>
                                <div class="progress-mini">
                                    <div class="progress-mini-bar">
                                        <div class="progress-mini-fill"></div>
                                    </div>
                                    <span style="font-size: 12px; color: var(--text-light);">15/50kg</span>
                                </div>
                            </div>
                            <button class="btn-primary">
                                Redeem Points (650 available)
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Transactions -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-history"></i> Recent Transactions</h3>
                        <a href="Transaction.php" style="color: var(--icon-green); text-decoration: none; font-weight: 500;">View All <i class="fas fa-arrow-right"></i></a>
                    </div>
                    <div>
                        <?php if (mysqli_num_rows($transaction_result) > 0): ?>
                            <?php while ($transaction = mysqli_fetch_assoc($transaction_result)): ?>
                                <?php
                                // Determine icon and color based on transaction type
                                $icon = '';
                                $color = 'var(--icon-green)';
                                switch($transaction['transaction_type']) {
                                    case 'Pickup':
                                        $icon = 'fa-truck-loading';
                                        break;
                                    case 'Walk-in':
                                        $icon = 'fa-coins';
                                        break;
                                    case 'Loyalty':
                                        $icon = 'fa-award';
                                        $color = 'var(--sales-orange)';
                                        break;
                                    default:
                                        $icon = 'fa-exchange-alt';
                                }
                                ?>
                                <div class="transaction">
                                    <div class="transaction-info">
                                        <div class="transaction-icon" style="color: <?php echo $color; ?>">
                                            <i class="fas <?php echo $icon; ?>"></i>
                                        </div>
                                        <div class="transaction-details">
                                            <h4><?php echo htmlspecialchars($transaction['name']); ?></h4>
                                            <p>
                                                <?php echo date('M j, Y', strtotime($transaction['transaction_date'])); ?> • 
                                                <?php echo htmlspecialchars($transaction['item_details']); ?>
                                            </p>
                                        </div>
                                    </div>
                                    <div class="transaction-amount" style="color: <?php echo $color; ?>">
                                        +₱<?php echo number_format($transaction['amount'], 2); ?>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div style="text-align: center; padding: 30px; color: var(--text-light);">
                                <i class="fas fa-info-circle" style="font-size: 30px; margin-bottom: 15px; color: var(--icon-green);"></i>
                                <p>No recent transactions found</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Price Calculator Modal -->
<div id="priceCalculatorModal" class="modal">
    <div class="modal-content">
        <span class="close-modal">&times;</span>
        <h3><i class="fas fa-calculator"></i> Price Calculator</h3>
        <div class="calculator-form">
            <div class="form-group">
                <label for="material-type">Material Type</label>
                <select id="material-type">
                    <option value="copper">Copper Wire</option>
                    <option value="pet">PET Bottles</option>
                    <option value="aluminum">Aluminum Cans</option>
                    <option value="cardboard">Cardboard</option>
                    <option value="steel">Steel</option>
                    <option value="glass">Glass Bottles</option>
                    <option value="computer">Computer Parts</option>
                    <option value="iron">Iron Scrap</option>
                    <option value="stainless">Stainless Steel</option>
                    <option value="ewaste">E-Waste</option>
                </select>
            </div>
            <div class="form-group">
                <label for="weight">Weight (kg)</label>
                <input type="number" id="weight" placeholder="Enter weight in kilograms" step="0.01">
            </div>
            <button id="calculate-btn" class="btn-primary">Calculate Value</button>
            <div class="result-container">
                <h4>Estimated Value:</h4>
                <div id="calculated-result">₱0.00</div>
            </div>
        </div>
    </div>
</div>

    <!-- QR Code Modal -->
    <div id="qrCodeModal" class="modal">
        <div class="modal-content">
            <span class="close-modal">&times;</span>
            <h3><i class="fas fa-qrcode"></i> Your QR Code</h3>
            <div class="qr-code-container">
                <?php 
                // Generate a random string for the QR code (in a real app, this would be a user-specific code)
                $qr_code_data = 'JUNKPRO-' . $user_id . '-' . bin2hex(random_bytes(3));
                ?>
                <img src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=<?php echo urlencode($qr_code_data); ?>" 
                     alt="QR Code for <?php echo htmlspecialchars($user_name); ?>" class="qr-code-image">
                <p>Scan this code at our facility for quick check-in</p>
                <button class="btn-primary" onclick="downloadQRCode()">
                    <i class="fas fa-download"></i> Download QR Code
                </button>
            </div>
        </div>
    </div>

    <!-- Refer a Friend Modal -->
    <div id="referFriendModal" class="modal">
        <div class="modal-content">
            <span class="close-modal">&times;</span>
            <h3><i class="fas fa-share-alt"></i> Refer a Friend</h3>
            <div class="referral-content">
                <p>Share your referral link and earn 100 loyalty points for each friend who signs up and completes their first transaction!</p>
                <div class="referral-link-container">
                    <input type="text" id="referral-link" value="https://JunkValue.com/signup?ref=<?php echo $user_id; ?>" readonly>
                    <button class="btn-copy" onclick="copyReferralLink()">
                        <i class="fas fa-copy"></i> Copy
                    </button>
                </div>
                <p>Or share directly:</p>
                <div class="social-share">
                    <button class="social-btn facebook" onclick="shareOnFacebook()">
                        <i class="fab fa-facebook-f"></i> Facebook
                    </button>
                    <button class="social-btn whatsapp" onclick="shareOnWhatsApp()">
                        <i class="fab fa-whatsapp"></i> WhatsApp
                    </button>
                    <button class="social-btn email" onclick="shareViaEmail()">
                        <i class="fas fa-envelope"></i> Email
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Modal functionality
        document.addEventListener('DOMContentLoaded', function() {
            // Get modal elements
            const priceCalcModal = document.getElementById('priceCalculatorModal');
            const qrCodeModal = document.getElementById('qrCodeModal');
            const referModal = document.getElementById('referFriendModal');
            
            // Get close buttons
            const closeButtons = document.querySelectorAll('.close-modal');
            
            // Close modals when clicking X
            closeButtons.forEach(function(btn) {
                btn.onclick = function() {
                    const modal = this.closest('.modal');
                    modal.style.display = 'none';
                }
            });
            
            // Close modals when clicking outside
            window.onclick = function(event) {
                if (event.target.classList.contains('modal')) {
                    event.target.style.display = 'none';
                }
            }
            
                // Price calculator logic
    const calculateBtn = document.getElementById('calculate-btn');
    calculateBtn.addEventListener('click', calculatePrice);
    
    function calculatePrice() {
        const material = document.getElementById('material-type').value;
        const weight = parseFloat(document.getElementById('weight').value) || 0;
        
        // Prices per kg - updated with the new values
        const prices = {
            'copper': 285.70,
            'pet': 28.57,
            'aluminum': 68.57,
            'cardboard': 17.14,
            'steel': 45.71,
            'glass': 14.29,
            'computer': 85.71,
            'iron': 18.00,
            'stainless': 65.00,
            'ewaste': 120.00
        };
        
        const pricePerKg = prices[material];
        const total = (pricePerKg * weight).toFixed(2);
        
        document.getElementById('calculated-result').textContent = `₱${total}`;
    }

        });
        
        // Download QR Code
        function downloadQRCode() {
            const qrCodeImage = document.querySelector('.qr-code-image');
            const link = document.createElement('a');
            link.href = qrCodeImage.src;
            link.download = 'JunkValue-qrcode.png';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
        
        // Copy referral link
        function copyReferralLink() {
            const referralLink = document.getElementById('referral-link');
            referralLink.select();
            document.execCommand('copy');
            
            // Show copied message
            const copyBtn = document.querySelector('.btn-copy');
            const originalText = copyBtn.innerHTML;
            copyBtn.innerHTML = '<i class="fas fa-check"></i> Copied!';
            
            setTimeout(function() {
                copyBtn.innerHTML = originalText;
            }, 2000);
        }

        // Social sharing functions
        function shareOnFacebook() {
            const url = encodeURIComponent(document.getElementById('referral-link').value);
            window.open(`https://www.facebook.com/sharer/sharer.php?u=${url}`, '_blank');
        }

        function shareOnWhatsApp() {
            const text = encodeURIComponent("Join me on JunkValue and get bonus points on your first transaction! ");
            const url = encodeURIComponent(document.getElementById('referral-link').value);
            window.open(`https://wa.me/?text=${text}${url}`, '_blank');
        }

        function shareViaEmail() {
            const subject = encodeURIComponent("Join me on JunkValue!");
            const body = encodeURIComponent(`Hi there,\n\nI thought you might be interested in JunkValue. Use my referral link to sign up and get bonus points on your first transaction!\n\n${document.getElementById('referral-link').value}\n\nBest regards,\n${'<?php echo htmlspecialchars($user_name); ?>'}`);
            window.open(`mailto:?subject=${subject}&body=${body}`);
        }
    </script>
</body>
</html>
<?php
// Close prepared statements
mysqli_stmt_close($user_stmt);
mysqli_stmt_close($transaction_stmt);
// Close connection
mysqli_close($conn);
?>