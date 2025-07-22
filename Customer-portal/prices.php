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
$user_query = "SELECT first_name, last_name, profile_image FROM users WHERE id = ?";
$user_stmt = mysqli_prepare($conn, $user_query);
mysqli_stmt_bind_param($user_stmt, "i", $user_id);
mysqli_stmt_execute($user_stmt);
$user_result = mysqli_stmt_get_result($user_stmt);
$user = mysqli_fetch_assoc($user_result);
$user_name = $user['first_name'] . ' ' . $user['last_name'];
$user_initials = strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1));

// Get current prices from database
$prices = [];
$price_query = "SELECT * FROM materials ORDER BY material_option";
$price_result = mysqli_query($conn, $price_query);
if ($price_result) {
    while ($row = mysqli_fetch_assoc($price_result)) {
        $prices[] = $row;
    }
}

// Generate sample price history data
$price_history = [];
$material_colors = [
    'Copper Wire' => '#D97A41', // Using your sales-orange for copper
    'Aluminum Cans' => '#708B4C', // Using your stock-green for aluminum
    'Iron Scrap' => '#3C342C', // Using your topbar-brown for iron
    'E-Waste' => '#6A7F46', // Using your icon-green for e-waste
    'Stainless Steel' => '#2E2B29' // Using your text-dark for stainless
];

// Generate 30 days of historical data for each material
foreach ($prices as $material) {
    $history_data = [];
    $current_price = $material['unit_price'];
    
    // Generate random but somewhat realistic price fluctuations
    for ($i = 30; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $fluctuation = rand(-5, 5) / 10; // Random small fluctuation
        $history_data[$date] = max(1, $current_price * (1 + $fluctuation));
    }
    
    $price_history[$material['id']] = [
        'name' => $material['material_option'],
        'color' => $material_colors[$material['material_option']] ?? '#6A7F46',
        'data' => $history_data,
        'current' => $current_price,
        'weekly_high' => max($history_data),
        'monthly_avg' => array_sum($history_data) / count($history_data),
        'trend' => end($history_data) > $history_data[array_keys($history_data)[count($history_data)-2]] ? 'up' : 'down',
        'change_amount' => abs(end($history_data) - $history_data[array_keys($history_data)[count($history_data)-2]]),
        'change_percent' => (abs(end($history_data) - $history_data[array_keys($history_data)[count($history_data)-2]]) / $history_data[array_keys($history_data)[count($history_data)-2]]) * 100
    ];
}

// Prepare chart data
$chart_labels = array_keys($price_history[$prices[0]['id']]['data']);
$chart_datasets = [];

foreach ($prices as $material) {
    if (isset($price_history[$material['id']])) {
        $chart_datasets[] = [
            'label' => $material['material_option'],
            'data' => array_values($price_history[$material['id']]['data']),
            'borderColor' => $price_history[$material['id']]['color'],
            'backgroundColor' => str_replace(')', ', 0.1)', $price_history[$material['id']]['color']),
            'borderWidth' => 3,
            'tension' => 0.3,
            'fill' => true,
            'pointBackgroundColor' => $price_history[$material['id']]['color'],
            'pointBorderColor' => '#fff',
            'pointBorderWidth' => 2
        ];
    }
}

$chart_data_json = json_encode([
    'labels' => $chart_labels,
    'datasets' => $chart_datasets
]);

// Close database connection
mysqli_close($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JunkValue - Current Prices</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <!-- Add Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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

        /* Prices Page Specific Styles */
        .price-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .price-tabs {
            display: flex;
            border-bottom: 1px solid rgba(0,0,0,0.1);
            margin-bottom: 25px;
            gap: 5px;
        }
        
        .price-tab {
            padding: 12px 25px;
            cursor: pointer;
            border-bottom: 3px solid transparent;
            font-weight: 500;
            color: var(--text-light);
            transition: all 0.3s;
            border-radius: 5px 5px 0 0;
        }
        
        .price-tab:hover {
            background-color: rgba(106, 127, 70, 0.05);
            color: var(--text-dark);
        }
        
        .price-tab.active {
            border-bottom-color: var(--icon-green);
            color: var(--icon-green);
            background-color: rgba(106, 127, 70, 0.05);
        }
        
        .price-cards {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .price-card {
            background-color: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.05);
            border-top: 4px solid var(--icon-green);
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
        }
        
        .price-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.1);
        }
        
        .price-card h3 {
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
            color: var(--text-dark);
            font-size: 18px;
        }
        
        .price-card i {
            font-size: 22px;
        }
        
        .price-value {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 5px;
            color: var(--text-dark);
        }
        
        .price-change {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 15px;
            margin-bottom: 15px;
        }
        
        .price-change.up {
            color: var(--stock-green);
        }
        
        .price-change.down {
            color: var(--sales-orange);
        }
        
        .price-change.neutral {
            color: var(--text-light);
        }
        
        .price-meta {
            font-size: 14px;
            color: var(--text-light);
            margin-top: 15px;
            border-top: 1px solid rgba(0,0,0,0.05);
            padding-top: 15px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }
        
        .price-meta-item {
            display: flex;
            flex-direction: column;
        }
        
        .price-meta-label {
            font-size: 13px;
            margin-bottom: 3px;
        }
        
        .price-meta-value {
            font-weight: 500;
            color: var(--text-dark);
        }
        
        .price-history-chart {
            height: 400px;
            background-color: white;
            border-radius: 12px;
            margin-top: 20px;
            padding: 25px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.05);
            border: 1px solid rgba(0,0,0,0.05);
        }
        
        .chart-container {
            position: relative;
            height: 100%;
            width: 100%;
        }
        
        .chart-legend {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 20px;
            justify-content: center;
        }
        
        .chart-legend-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            cursor: pointer;
            padding: 6px 12px;
            border-radius: 20px;
            background-color: rgba(0,0,0,0.03);
            transition: all 0.2s;
            border: 1px solid rgba(0,0,0,0.05);
        }
        
        .chart-legend-item:hover {
            background-color: rgba(0,0,0,0.05);
        }
        
        .chart-legend-item.hidden {
            opacity: 0.5;
            text-decoration: line-through;
        }
        
        .chart-legend-color {
            width: 14px;
            height: 14px;
            border-radius: 3px;
        }
        
        .price-alert {
            display: flex;
            align-items: center;
            gap: 15px;
            background-color: rgba(217, 122, 65, 0.1);
            padding: 15px 20px;
            border-radius: 8px;
            margin-top: 25px;
            border-left: 4px solid var(--sales-orange);
        }
        
        .price-alert i {
            color: var(--sales-orange);
            font-size: 20px;
        }
        
        .price-alert-content {
            flex: 1;
        }
        
        .price-alert-title {
            font-weight: 600;
            margin-bottom: 5px;
            color: var(--text-dark);
        }
        
        .price-alert-text {
            font-size: 14px;
            color: var(--text-dark);
        }
        
        .price-table-container {
            overflow-x: auto;
            margin-top: 25px;
            border-radius: 8px;
            border: 1px solid rgba(0,0,0,0.05);
            box-shadow: 0 2px 5px rgba(0,0,0,0.03);
        }
        
        .price-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 800px;
        }
        
        .price-table th {
            background-color: rgba(106, 127, 70, 0.05);
            padding: 14px 16px;
            text-align: left;
            font-weight: 600;
            color: var(--icon-green);
            font-size: 14px;
            border-bottom: 2px solid rgba(0,0,0,0.05);
        }
        
        .price-table td {
            padding: 14px 16px;
            border-bottom: 1px solid rgba(0,0,0,0.05);
            font-size: 14px;
        }
        
        .price-table tr:last-child td {
            border-bottom: none;
        }
        
        .price-table tr:hover {
            background-color: rgba(106, 127, 70, 0.03);
        }
        
        .trend-cell {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .subscribe-form {
            display: flex;
            gap: 10px;
            margin-top: 30px;
            flex-wrap: wrap;
            align-items: center;
        }
        
        .subscribe-form select {
            padding: 12px 15px;
            border-radius: 8px;
            border: 1px solid rgba(0,0,0,0.1);
            min-width: 250px;
            font-size: 14px;
            background-color: white;
        }
        
        .subscribe-form button {
            background: linear-gradient(90deg, var(--icon-green) 0%, var(--stock-green) 100%);
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s;
            box-shadow: 0 3px 10px rgba(106, 127, 70, 0.3);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .subscribe-form button:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(106, 127, 70, 0.4);
        }
        
        .material-icon {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
        }
        
        /* Responsive styles */
        @media (max-width: 1024px) {
            .dashboard {
                grid-template-columns: 1fr;
            }
            
            .sidebar {
                position: static;
            }
            
            .price-history-chart {
                height: 350px;
                padding: 20px;
            }
        }
        
        @media (max-width: 768px) {
            .price-cards {
                grid-template-columns: 1fr 1fr;
            }
            
            .price-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .price-history-chart {
                height: 300px;
                padding: 15px;
            }
            
            .price-card h3 {
                font-size: 16px;
            }
            
            .price-value {
                font-size: 24px;
            }
        }
        
        @media (max-width: 480px) {
            .price-cards {
                grid-template-columns: 1fr;
            }
            
            .price-tabs {
                overflow-x: auto;
                padding-bottom: 5px;
            }
            
            .price-tab {
                padding: 10px 15px;
                white-space: nowrap;
            }
            
            .price-history-chart {
                height: 250px;
                padding: 10px;
            }
            
            .price-alert {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            .subscribe-form {
                flex-direction: column;
                align-items: stretch;
            }
            
            .subscribe-form select {
                min-width: 100%;
            }
            
            .subscribe-form button {
                width: 100%;
                justify-content: center;
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
                    <li><a href="schedule.php"><i class="fas fa-calendar-alt"></i> Schedule Pickup</a></li>
                    <li><a href="#" class="active"><i class="fas fa-coins"></i> Current Prices</a></li>
                    <li><a href="rewards.php"><i class="fas fa-award"></i> Loyalty Rewards</a></li>
                    <li><a href="settings.php"><i class="fas fa-user-cog"></i> Account Settings</a></li>
                    <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                </ul>
            </div>
            
            <!-- Main Content -->
            <div class="main-content">
                <div class="card">
                    <div class="price-header">
                        <h2 style="color: var(--text-dark); display: flex; align-items: center; gap: 10px;">
                            <i class="fas fa-coins" style="color: var(--icon-green);"></i> Current Scrap Prices
                        </h2>
                        <div style="display: flex; align-items: center; gap: 15px;">
                            <span style="color: var(--text-light); font-size: 14px;">
                                <i class="fas fa-sync-alt"></i> Updated: <?php echo date('F j, Y g:i A'); ?>
                            </span>
                            <button class="btn" style="background: rgba(106, 127, 70, 0.1); color: var(--icon-green); padding: 8px 15px; border-radius: 8px; border: none; display: flex; align-items: center; gap: 8px;">
                                <i class="fas fa-bell"></i> Price Alerts
                            </button>
                        </div>
                    </div>
                    
                    <div class="price-tabs">
                        <div class="price-tab active">All Materials</div>
                        <div class="price-tab">Metals</div>
                        <div class="price-tab">E-Waste</div>
                        <div class="price-tab">Plastics</div>
                        <div class="price-tab">Paper</div>
                    </div>
                    
                    <!-- Price Cards -->
                    <div class="price-cards">
                        <?php foreach ($prices as $material): 
                            $history = $price_history[$material['id']];
                            $trend_class = $history['trend'] === 'up' ? 'up' : ($history['trend'] === 'down' ? 'down' : 'neutral');
                            $trend_icon = $history['trend'] === 'up' ? 'fa-arrow-up' : ($history['trend'] === 'down' ? 'fa-arrow-down' : 'fa-equals');
                            $icon_color = $history['color'];
                            
                            // Set icons based on material type
                            $material_icon = '';
                            switch(true) {
                                case strpos($material['material_option'], 'Copper') !== false:
                                    $material_icon = 'fa-bolt';
                                    break;
                                case strpos($material['material_option'], 'Aluminum') !== false:
                                    $material_icon = 'fa-cubes';
                                    break;
                                case strpos($material['material_option'], 'Iron') !== false:
                                    $material_icon = 'fa-weight-hanging';
                                    break;
                                case strpos($material['material_option'], 'E-Waste') !== false:
                                    $material_icon = 'fa-microchip';
                                    break;
                                case strpos($material['material_option'], 'Steel') !== false:
                                    $material_icon = 'fa-industry';
                                    break;
                                default:
                                    $material_icon = 'fa-box';
                            }
                        ?>
                        <div class="price-card" style="border-top-color: <?php echo $icon_color; ?>">
                            <div class="material-icon" style="background-color: <?php echo $icon_color; ?>">
                                <i class="fas <?php echo $material_icon; ?>"></i>
                            </div>
                            <h3><?php echo htmlspecialchars($material['material_option']); ?></h3>
                            <div class="price-value">₱<?php echo number_format($material['unit_price'], 2); ?>/kg</div>
                            <div class="price-change <?php echo $trend_class; ?>">
                                <i class="fas <?php echo $trend_icon; ?>"></i>
                                <span>
                                    <?php if ($history['trend'] !== 'equal'): ?>
                                        <?php echo $history['trend'] === 'up' ? '+' : '-'; ?>
                                    <?php endif; ?>
                                    ₱<?php echo number_format($history['change_amount'], 2); ?> 
                                    (<?php echo number_format($history['change_percent'], 1); ?>%)
                                </span>
                            </div>
                            <div class="price-meta">
                                <div class="price-meta-item">
                                    <span class="price-meta-label">Weekly High</span>
                                    <span class="price-meta-value">₱<?php echo number_format($history['weekly_high'], 2); ?></span>
                                </div>
                                <div class="price-meta-item">
                                    <span class="price-meta-label">Monthly Avg</span>
                                    <span class="price-meta-value">₱<?php echo number_format($history['monthly_avg'], 2); ?></span>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Price History Chart -->
                    <h3 style="color: var(--text-dark); margin-bottom: 15px; display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-chart-line" style="color: var(--icon-green);"></i> Price Trends (Last 30 Days)
                    </h3>
                    <div class="price-history-chart">
                        <div class="chart-container">
                            <canvas id="priceHistoryChart"></canvas>
                        </div>
                        <div class="chart-legend" id="chartLegend"></div>
                    </div>
                    
                    <!-- Price Alert -->
                    <div class="price-alert">
                        <i class="fas fa-exclamation-circle"></i>
                        <div class="price-alert-content">
                            <div class="price-alert-title">Market Alert</div>
                            <div class="price-alert-text">
                                Copper prices have increased 12% this month due to high demand. Consider selling your copper scrap now to maximize your earnings.
                            </div>
                        </div>
                    </div>
                    
                    <!-- Detailed Price Table -->
                    <h3 style="margin-top: 30px; color: var(--text-dark); display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-list" style="color: var(--icon-green);"></i> Detailed Price List
                    </h3>
                    <div class="price-table-container">
                        <table class="price-table">
                            <thead>
                                <tr>
                                    <th>Material</th>
                                    <th>Grade</th>
                                    <th>Price (per kg)</th>
                                    <th>Trend (7d)</th>
                                    <th>Min. Weight</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($prices as $material): 
                                    $history = $price_history[$material['id']];
                                    $trend_icon = $history['trend'] === 'up' ? 'fa-arrow-up' : ($history['trend'] === 'down' ? 'fa-arrow-down' : 'fa-equals');
                                    $trend_color = $history['trend'] === 'up' ? 'var(--stock-green)' : ($history['trend'] === 'down' ? 'var(--sales-orange)' : 'var(--text-light)');
                                    
                                    // Determine grade based on material (simplified for demo)
                                    $grade = '';
                                    if (strpos($material['material_option'], 'Copper') !== false) {
                                        $grade = strpos($material['material_option'], 'Wire') !== false ? '#1 Bright' : '#2 Mixed';
                                    } elseif (strpos($material['material_option'], 'Aluminum') !== false) {
                                        $grade = strpos($material['material_option'], 'Cans') !== false ? 'Clean' : 'Extrusion';
                                    } elseif (strpos($material['material_option'], 'Iron') !== false) {
                                        $grade = 'Heavy Melt';
                                    } elseif (strpos($material['material_option'], 'E-Waste') !== false) {
                                        $grade = 'Mixed';
                                    } elseif (strpos($material['material_option'], 'Plastic') !== false) {
                                        $grade = 'PET Clear';
                                    } elseif (strpos($material['material_option'], 'Stainless Steel') !== false) {
                                        $grade = '304 Grade';
                                    }
                                    
                                    // Determine min weight (simplified for demo)
                                    $min_weight = '5kg';
                                    if (strpos($material['material_option'], 'Wire') !== false || strpos($material['material_option'], 'E-Waste') !== false) {
                                        $min_weight = '1kg';
                                    } elseif (strpos($material['material_option'], 'Cans') !== false || strpos($material['material_option'], 'Plastic') !== false) {
                                        $min_weight = '10kg';
                                    } elseif (strpos($material['material_option'], 'Iron') !== false) {
                                        $min_weight = '20kg';
                                    }
                                ?>
                                <tr>
                                    <td style="font-weight: 500;"><?php echo htmlspecialchars($material['material_option']); ?></td>
                                    <td><?php echo htmlspecialchars($grade); ?></td>
                                    <td style="font-weight: 600;">₱<?php echo number_format($material['unit_price'], 2); ?></td>
                                    <td class="trend-cell">
                                        <i class="fas <?php echo $trend_icon; ?>" style="color: <?php echo $trend_color; ?>"></i>
                                        <span style="color: <?php echo $trend_color; ?>"><?php 
                                            echo $history['trend'] === 'up' ? '+' : ($history['trend'] === 'down' ? '-' : '');
                                            echo number_format($history['change_percent'], 1); ?>%
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($min_weight); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Price Alert Subscription -->
                    <h3 style="margin-top: 40px; color: var(--text-dark); display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-bell" style="color: var(--sales-orange);"></i> Get Price Alerts
                    </h3>
                    <p style="color: var(--text-light); margin-bottom: 15px;">
                        Receive notifications when prices for your selected materials change significantly.
                    </p>
                    <form class="subscribe-form">
                        <select>
                            <option value="">Select material to monitor</option>
                            <?php foreach ($prices as $material): ?>
                                <option value="<?php echo $material['id']; ?>"><?php echo htmlspecialchars($material['material_option']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="button">
                            <i class="fas fa-bell"></i> Set Alert
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Initialize the price history chart
        const chartData = <?php echo $chart_data_json; ?>;
        
        // Format dates to be more readable (show only day/month)
        const formattedLabels = chartData.labels.map(label => {
            const date = new Date(label);
            return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
        });
        
        const ctx = document.getElementById('priceHistoryChart').getContext('2d');
        const priceHistoryChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: formattedLabels,
                datasets: chartData.datasets.map(dataset => ({
                    ...dataset,
                    pointRadius: 3,
                    pointHoverRadius: 6,
                    pointHitRadius: 10,
                    pointHoverBackgroundColor: dataset.borderColor,
                    pointHoverBorderColor: '#fff',
                    pointHoverBorderWidth: 2
                }))
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                        backgroundColor: 'rgba(46, 43, 41, 0.95)',
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        borderColor: 'rgba(255,255,255,0.1)',
                        borderWidth: 1,
                        padding: 12,
                        callbacks: {
                            label: function(context) {
                                let label = context.dataset.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                if (context.parsed.y !== null) {
                                    label += '₱' + context.parsed.y.toFixed(2) + '/kg';
                                }
                                return label;
                            },
                            title: function(context) {
                                const date = new Date(chartData.labels[context[0].dataIndex]);
                                return date.toLocaleDateString('en-US', { 
                                    weekday: 'short', 
                                    year: 'numeric', 
                                    month: 'short', 
                                    day: 'numeric' 
                                });
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: false,
                        ticks: {
                            callback: function(value) {
                                return '₱' + value.toFixed(2);
                            },
                            color: 'var(--text-light)'
                        },
                        grid: {
                            color: 'rgba(0,0,0,0.05)',
                            drawBorder: false
                        }
                    },
                    x: {
                        grid: {
                            display: false,
                            drawBorder: false
                        },
                        ticks: {
                            maxRotation: 45,
                            minRotation: 45,
                            color: 'var(--text-light)',
                            callback: function(value, index, values) {
                                // Show only every 5th day to avoid clutter
                                if (index % 5 === 0 || index === values.length - 1) {
                                    return value;
                                }
                                return '';
                            }
                        }
                    }
                },
                interaction: {
                    mode: 'nearest',
                    axis: 'x',
                    intersect: false
                },
                elements: {
                    line: {
                        cubicInterpolationMode: 'monotone'
                    }
                }
            }
        });
        
        // Create custom legend
        const legendContainer = document.getElementById('chartLegend');
        chartData.datasets.forEach(dataset => {
            const legendItem = document.createElement('div');
            legendItem.className = 'chart-legend-item';
            legendItem.innerHTML = `
                <div class="chart-legend-color" style="background-color: ${dataset.borderColor}"></div>
                <span>${dataset.label}</span>
            `;
            
            // Add click event to toggle dataset visibility
            legendItem.addEventListener('click', function() {
                const meta = priceHistoryChart.getDatasetMeta(
                    chartData.datasets.findIndex(ds => ds.label === dataset.label)
                );
                meta.hidden = meta.hidden === null ? true : !meta.hidden;
                priceHistoryChart.update();
                
                // Toggle class to show disabled state
                this.classList.toggle('hidden', meta.hidden);
            });
            
            legendContainer.appendChild(legendItem);
        });
        
        // Tab switching functionality
        const tabs = document.querySelectorAll('.price-tab');
        tabs.forEach(tab => {
            tab.addEventListener('click', function() {
                document.querySelector('.price-tab.active')?.classList.remove('active');
                this.classList.add('active');
                
                // In a real app, this would filter the price cards/table
                // For this demo, we're just showing the tab change
            });
        });
    </script>
</body>
</html>