<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

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

// Get user info for header (updated to include profile_image)
$user_query = "SELECT first_name, last_name, profile_image FROM users WHERE id = ?";
$user_stmt = mysqli_prepare($conn, $user_query);
mysqli_stmt_bind_param($user_stmt, "i", $user_id);
mysqli_stmt_execute($user_stmt);
$user_result = mysqli_stmt_get_result($user_stmt);
$user = mysqli_fetch_assoc($user_result);
$user_name = $user['first_name'] . ' ' . $user['last_name'];
$user_initials = strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1));

// Get user loyalty data with tier information
$loyalty_query = "SELECT ul.current_points, ul.lifetime_points, 
                  lt.tier_name AS current_tier, lt.min_points AS current_min_points,
                  lt.bonus_percentage, lt.free_pickups,
                  lt2.tier_name AS next_tier, lt2.min_points AS next_min_points
                  FROM user_loyalty ul
                  LEFT JOIN loyalty_tiers lt ON ul.current_tier_id = lt.id
                  LEFT JOIN loyalty_tiers lt2 ON ul.next_tier_id = lt2.id
                  WHERE ul.user_id = ?";
$loyalty_stmt = mysqli_prepare($conn, $loyalty_query);
mysqli_stmt_bind_param($loyalty_stmt, "i", $user_id);
mysqli_stmt_execute($loyalty_stmt);
$loyalty_result = mysqli_stmt_get_result($loyalty_stmt);
$loyalty_data = mysqli_fetch_assoc($loyalty_result);

// Calculate tier progress
if ($loyalty_data) {
    $current_points = $loyalty_data['current_points'];
    $current_tier = strtolower($loyalty_data['current_tier']);
    $current_tier_name = ucfirst($loyalty_data['current_tier']);
    $next_tier_name = $loyalty_data['next_tier'] ?? 'Gold';
    $next_min_points = $loyalty_data['next_min_points'] ?? 3000;
    $current_min_points = $loyalty_data['current_min_points'] ?? 0;
    
    $progress_percentage = ($current_points - $current_min_points) / 
                         ($next_min_points - $current_min_points) * 100;
    $progress_percentage = min(max($progress_percentage, 0), 100); // Clamp between 0-100
} else {
    // Default values if no loyalty data exists
    $current_points = 0;
    $current_tier = 'bronze';
    $current_tier_name = 'Bronze';
    $next_tier_name = 'Silver';
    $next_min_points = 1000;
    $progress_percentage = 0;
    $loyalty_data = [
        'bonus_percentage' => 0,
        'free_pickups' => 0
    ];
}

// Get active challenges
$challenges_query = "SELECT c.id, c.challenge_name, c.description, c.target_value, 
                    c.target_metric, c.points_reward, c.start_date, c.end_date,
                    ucp.current_value, ucp.is_completed
                    FROM loyalty_challenges c
                    LEFT JOIN user_challenge_progress ucp ON c.id = ucp.challenge_id AND ucp.user_id = ?
                    WHERE c.is_active = 1 AND c.end_date >= CURDATE()";
$challenges_stmt = mysqli_prepare($conn, $challenges_query);
mysqli_stmt_bind_param($challenges_stmt, "i", $user_id);
mysqli_stmt_execute($challenges_stmt);
$challenges_result = mysqli_stmt_get_result($challenges_stmt);
$active_challenges = mysqli_fetch_all($challenges_result, MYSQLI_ASSOC);

// Get available rewards
$rewards_query = "SELECT * FROM rewards WHERE is_active = 1";
$rewards_result = mysqli_query($conn, $rewards_query);
$available_rewards = mysqli_fetch_all($rewards_result, MYSQLI_ASSOC);

// Get points history
$points_query = "SELECT * FROM points_history 
                WHERE user_id = ? 
                ORDER BY created_at DESC 
                LIMIT 5";
$points_stmt = mysqli_prepare($conn, $points_query);
mysqli_stmt_bind_param($points_stmt, "i", $user_id);
mysqli_stmt_execute($points_stmt);
$points_result = mysqli_stmt_get_result($points_stmt);
$points_history = mysqli_fetch_all($points_result, MYSQLI_ASSOC);

// Handle reward redemption
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['redeem_reward'])) {
    $reward_id = intval($_POST['reward_id']);
    
    // Verify user has enough points
    if ($current_points >= $reward['points_cost']) {
        // Process redemption (implementation depends on your business logic)
        $success_message = "Reward redeemed successfully!";
    } else {
        $error_message = "You don't have enough points for this reward";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JunkValue - Loyalty Rewards</title>
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

        .card:nth-child(1) { animation-delay: 0.1s; }
        .card:nth-child(2) { animation-delay: 0.2s; }
        .card:nth-child(3) { animation-delay: 0.3s; }
        .card:nth-child(4) { animation-delay: 0.4s; }

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
            transition: all 0.3s;
        }

        .loyalty-badge:hover {
            transform: scale(1.1);
            box-shadow: 0 5px 15px rgba(217, 122, 65, 0.3);
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

        .grid-2-col {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        /* Loyalty Rewards Specific Styles */
        .loyalty-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .tier-card {
            background: linear-gradient(135deg, #f5f7fa 0%, #e4e8eb 100%);
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 30px;
            position: relative;
            overflow: hidden;
            border: 1px solid rgba(0,0,0,0.05);
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }
        
        .tier-card.gold {
            background: linear-gradient(135deg, #f9d423 0%, #e4a012 100%);
            border: 1px solid rgba(217, 122, 65, 0.2);
        }
        
        .tier-card.silver {
            background: linear-gradient(135deg, #e0e0e0 0%, #b8b8b8 100%);
            border: 1px solid rgba(0,0,0,0.1);
        }
        
        .tier-card.bronze {
            background: linear-gradient(135deg, #cd7f32 0%, #a66928 100%);
            border: 1px solid rgba(112, 139, 76, 0.2);
        }
        
        .tier-badge {
            position: absolute;
            top: 20px;
            right: 20px;
            background-color: rgba(0,0,0,0.1);
            padding: 5px 15px;
            border-radius: 20px;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 14px;
            color: white;
        }
        
        .tier-progress {
            margin: 20px 0;
        }
        
        .progress-text {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
            font-size: 14px;
            color: white;
        }
        
        .tier-benefits {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }
        
        .benefit-item {
            background-color: rgba(255,255,255,0.2);
            padding: 15px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .benefit-icon {
            width: 40px;
            height: 40px;
            background-color: rgba(255,255,255,0.3);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        
        .rewards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
            margin-top: 30px;
        }
        
        .reward-card {
            background-color: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 3px 15px rgba(0,0,0,0.05);
            border: 1px solid rgba(0,0,0,0.05);
            transition: all 0.3s;
        }
        
        .reward-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        
        .reward-card h3 {
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .reward-card i {
            color: var(--icon-green);
            font-size: 20px;
        }
        
        .reward-points {
            display: inline-block;
            background-color: rgba(106, 127, 70, 0.1);
            color: var(--icon-green);
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: bold;
            margin-top: 5px;
        }
        
        .reward-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid rgba(0,0,0,0.05);
        }
        
        .challenge-card {
            background-color: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 3px 15px rgba(0,0,0,0.05);
            border-left: 4px solid var(--icon-green);
        }
        
        .challenge-progress {
            margin-top: 15px;
        }
        
        .progress-details {
            display: flex;
            justify-content: space-between;
            font-size: 14px;
            margin-top: 5px;
        }
        
        .points-history {
            margin-top: 40px;
        }
        
        /* Success/Error Messages */
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        
        .alert-success {
            background-color: #dff0d8;
            color: #3c763d;
            border: 1px solid #d6e9c6;
        }
        
        .alert-error {
            background-color: #f2dede;
            color: #a94442;
            border: 1px solid #ebccd1;
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
            .tier-benefits, .rewards-grid {
                grid-template-columns: 1fr 1fr;
            }
            
            .header-content {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
        }

        @media (max-width: 480px) {
            .tier-benefits, .rewards-grid {
                grid-template-columns: 1fr;
            }
            
            .card {
                padding: 20px 15px;
            }
        }

        /* Animation for cards */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
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
                    <li><a href="transaction.php"><i class="fas fa-history"></i> Transaction History</a></li>
                    <li><a href="schedule.php"><i class="fas fa-calendar-alt"></i> Schedule Pickup</a></li>
                    <li><a href="prices.php"><i class="fas fa-coins"></i> Current Prices</a></li>
                    <li><a href="#" class="active"><i class="fas fa-award"></i> Loyalty Rewards</a></li>
                    <li><a href="settings.php"><i class="fas fa-user-cog"></i> Account Settings</a></li>
                    <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                </ul>
            </div>
            
            <!-- Main Content -->
            <div class="main-content">
                <!-- Welcome Banner -->
                <div class="card" style="background: linear-gradient(135deg, var(--panel-cream) 0%, var(--bg-beige) 100%); border: 1px solid rgba(217, 122, 65, 0.2);">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <h2 style="color: var(--topbar-brown); margin-bottom: 10px;">Loyalty Rewards</h2>
                            <p style="color: var(--text-dark); max-width: 600px;">Earn points with every transaction and unlock exclusive benefits as you climb through our loyalty tiers.</p>
                        </div>
                        <div style="font-size: 60px; color: rgba(217, 122, 65, 0.2);">
                            <i class="fas fa-medal"></i>
                        </div>
                    </div>
                </div>
                
                <?php if (isset($success_message)): ?>
                    <div class="alert alert-success">
                        <?php echo htmlspecialchars($success_message); ?>
                    </div>
                <?php elseif (isset($error_message)): ?>
                    <div class="alert alert-error">
                        <?php echo htmlspecialchars($error_message); ?>
                    </div>
                <?php endif; ?>
                
                <!-- Points Summary -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-coins"></i> Your Points Summary</h3>
                        <div style="display: flex; align-items: center; gap: 15px;">
                            <div style="background-color: rgba(106, 127, 70, 0.1); padding: 8px 15px; border-radius: 20px; font-weight: bold; color: var(--icon-green);">
                                <i class="fas fa-coins"></i> <?php echo number_format($current_points); ?> Points
                            </div>
                            <button class="btn-primary" style="width: auto; padding: 8px 15px;">
                                <i class="fas fa-gift"></i> How It Works
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Tier Status -->
                <div class="card tier-card <?php echo $current_tier; ?>">
                    <div class="tier-badge"><?php echo $current_tier_name; ?> Tier</div>
                    <h3 style="color: white;">Your Current Status</h3>
                    <p style="color: white; opacity: 0.9;">You're <?php echo round($progress_percentage); ?>% toward <?php echo $next_tier_name; ?> Tier</p>
                    
                    <div class="tier-progress">
                        <div class="progress-text">
                            <span><?php echo number_format($current_points); ?> points</span>
                            <span><?php echo number_format($current_points); ?>/<?php echo number_format($next_min_points); ?> points</span>
                        </div>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: <?php echo $progress_percentage; ?>%"></div>
                        </div>
                    </div>
                    
                    <div class="tier-benefits">
                        <div class="benefit-item">
                            <div class="benefit-icon">
                                <i class="fas fa-percentage" style="color: white;"></i>
                            </div>
                            <div>
                                <h4 style="margin: 0; color: white;"><?php echo $loyalty_data['bonus_percentage']; ?>% Bonus</h4>
                                <p style="margin: 0; color: white; opacity: 0.8; font-size: 14px;">On all scrap sales</p>
                            </div>
                        </div>
                        <div class="benefit-item">
                            <div class="benefit-icon">
                                <i class="fas fa-truck" style="color: white;"></i>
                            </div>
                            <div>
                                <h4 style="margin: 0; color: white;"><?php echo $loyalty_data['free_pickups']; ?> Free Pickups</h4>
                                <p style="margin: 0; color: white; opacity: 0.8; font-size: 14px;">Per month</p>
                            </div>
                        </div>
                        <div class="benefit-item">
                            <div class="benefit-icon">
                                <i class="fas fa-clock" style="color: white;"></i>
                            </div>
                            <div>
                                <h4 style="margin: 0; color: white;">Priority Service</h4>
                                <p style="margin: 0; color: white; opacity: 0.8; font-size: 14px;">Faster processing</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Next Tier Preview -->
                <div class="card tier-card <?php echo strtolower($next_tier_name); ?>" style="opacity: 0.8;">
                    <div class="tier-badge"><?php echo $next_tier_name; ?> Tier</div>
                    <h3 style="color: white;">Next Tier Preview</h3>
                    <p style="color: white; opacity: 0.9;">Reach <?php echo number_format($next_min_points); ?> points to unlock</p>
                    
                    <div class="tier-progress">
                        <div class="progress-text">
                            <span><?php echo number_format($next_min_points - $current_points); ?> points needed</span>
                            <span><?php echo number_format($next_min_points); ?> points</span>
                        </div>
                        <div class="progress-bar">
                            <div class="progress-fill"></div>
                        </div>
                    </div>
                    
                    <div class="tier-benefits">
                        <div class="benefit-item">
                            <div class="benefit-icon">
                                <i class="fas fa-percentage" style="color: white;"></i>
                            </div>
                            <div>
                                <h4 style="margin: 0; color: white;">
                                    <?php echo ($next_tier_name == 'Gold') ? '7%' : '5%'; ?> Bonus
                                </h4>
                                <p style="margin: 0; color: white; opacity: 0.8; font-size: 14px;">On all scrap sales</p>
                            </div>
                        </div>
                        <div class="benefit-item">
                            <div class="benefit-icon">
                                <i class="fas fa-truck" style="color: white;"></i>
                            </div>
                            <div>
                                <h4 style="margin: 0; color: white;">
                                    <?php echo ($next_tier_name == 'Gold') ? '4' : '2'; ?> Free Pickups
                                </h4>
                                <p style="margin: 0; color: white; opacity: 0.8; font-size: 14px;">Per month</p>
                            </div>
                        </div>
                        <div class="benefit-item">
                            <div class="benefit-icon">
                                <i class="fas fa-star" style="color: white;"></i>
                            </div>
                            <div>
                                <h4 style="margin: 0; color: white;">VIP Treatment</h4>
                                <p style="margin: 0; color: white; opacity: 0.8; font-size: 14px;">Exclusive offers</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Active Challenges -->
                <?php if (!empty($active_challenges)): ?>
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-trophy"></i> Earn Bonus Points</h3>
                        </div>
                        <?php foreach ($active_challenges as $challenge): ?>
                            <div class="challenge-card">
                                <h4><?php echo htmlspecialchars($challenge['challenge_name']); ?></h4>
                                <p><?php echo htmlspecialchars($challenge['description']); ?></p>
                                
                                <div class="challenge-progress">
                                    <div class="progress-bar">
                                        <?php
                                        $progress = ($challenge['current_value'] / $challenge['target_value']) * 100;
                                        $progress = min(max($progress, 0), 100);
                                        ?>
                                        <div class="progress-fill" style="width: <?php echo $progress; ?>%"></div>
                                    </div>
                                    <div class="progress-details">
                                        <span><?php echo $challenge['current_value']; ?>/<?php echo $challenge['target_value']; ?> <?php echo $challenge['target_metric']; ?></span>
                                        <span>+<?php echo $challenge['points_reward']; ?> points</span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                
                <!-- Redeemable Rewards -->
                <?php if (!empty($available_rewards)): ?>
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-gift"></i> Redeem Your Points</h3>
                        </div>
                        <div class="rewards-grid">
                            <?php foreach ($available_rewards as $reward): ?>
                                <div class="reward-card">
                                    <h3>
                                        <i class="fas <?php 
                                            switch($reward['reward_type']) {
                                                case 'cash': echo 'fa-money-bill-wave'; break;
                                                case 'service': echo 'fa-truck'; break;
                                                case 'discount': echo 'fa-store'; break;
                                                default: echo 'fa-gift';
                                            }
                                        ?>"></i> 
                                        <?php echo htmlspecialchars($reward['reward_name']); ?>
                                    </h3>
                                    <p><?php echo htmlspecialchars($reward['description']); ?></p>
                                    <div class="reward-points"><?php echo number_format($reward['points_cost']); ?> points</div>
                                    <div class="reward-actions">
                                        <span style="color: var(--text-light); font-size: 14px;">
                                            <?php echo ($current_points >= $reward['points_cost']) ? 'Available' : 'Not enough points'; ?>
                                        </span>
                                        <form method="POST">
                                            <input type="hidden" name="reward_id" value="<?php echo $reward['id']; ?>">
                                            <button type="submit" name="redeem_reward" class="btn-primary" style="width: auto; padding: 5px 15px;" 
                                                <?php echo ($current_points < $reward['points_cost']) ? 'disabled' : ''; ?>>
                                                Redeem
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Points History -->
                <div class="card points-history">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-history"></i> Points History</h3>
                        <a href="#" style="color: var(--icon-green); text-decoration: none; font-weight: 500;">View All <i class="fas fa-arrow-right"></i></a>
                    </div>
                    <table class="price-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Activity</th>
                                <th>Points</th>
                                <th>Balance</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($points_history)): ?>
                                <?php foreach ($points_history as $history): ?>
                                    <tr>
                                        <td><?php echo date('M j, Y', strtotime($history['created_at'])); ?></td>
                                        <td><?php echo htmlspecialchars($history['description']); ?></td>
                                        <td style="color: <?php echo ($history['points_change'] > 0) ? 'var(--icon-green)' : 'var(--sales-orange)'; ?>;">
                                            <?php echo ($history['points_change'] > 0 ? '+' : '') . $history['points_change']; ?>
                                        </td>
                                        <td><?php echo $history['balance_after']; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" style="text-align: center; color: var(--text-light);">No points history yet</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Simple animation for progress bars
        document.addEventListener('DOMContentLoaded', function() {
            const progressBars = document.querySelectorAll('.progress-fill');
            progressBars.forEach(bar => {
                const width = bar.style.width;
                bar.style.width = '0';
                setTimeout(() => {
                    bar.style.width = width;
                }, 100);
            });
        });
    </script>
</body>
</html>
<?php
// Close database connection
mysqli_close($conn);
?>