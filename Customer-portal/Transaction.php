<?php
// Start session and include database connection
session_start();
require_once 'db_connection.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Get current user ID
$user_id = $_SESSION['user_id'];

// Check if clear filters was clicked
if (isset($_GET['clear_filters'])) {
    header("Location: transactions.php");
    exit();
}

// Initialize filter variables with proper sanitization
$type_filter = isset($_GET['type']) ? mysqli_real_escape_string($conn, $_GET['type']) : '';
$status_filter = isset($_GET['status']) ? mysqli_real_escape_string($conn, $_GET['status']) : '';
$date_from = isset($_GET['date_from']) ? mysqli_real_escape_string($conn, $_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? mysqli_real_escape_string($conn, $_GET['date_to']) : '';
$search_query = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';

// Pagination variables
$transactions_per_page = 5; // Changed from 10 to 5
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($current_page < 1) $current_page = 1;
$offset = ($current_page - 1) * $transactions_per_page;

// Build base SQL query with prepared statement approach
$sql = "SELECT * FROM transactions WHERE user_id = ?";

// Initialize parameters array
$params = array($user_id);
$types = "i"; // i for integer

// Add filters to query
if (!empty($type_filter)) {
    $sql .= " AND transaction_type = ?";
    $params[] = $type_filter;
    $types .= "s";
}

if (!empty($status_filter)) {
    $sql .= " AND status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

if (!empty($date_from) && !empty($date_to)) {
    $sql .= " AND transaction_date BETWEEN ? AND ?";
    $params[] = $date_from;
    $params[] = $date_to;
    $types .= "ss";
}

if (!empty($search_query)) {
    $sql .= " AND (transaction_id LIKE CONCAT('%', ?, '%') 
              OR item_details LIKE CONCAT('%', ?, '%'))";
    $params[] = $search_query;
    $params[] = $search_query;
    $types .= "ss";
}

// Order by most recent first
$sql .= " ORDER BY transaction_date DESC, transaction_time DESC";

// Get total count for pagination
$count_sql = $sql;
$count_stmt = mysqli_prepare($conn, $count_sql);
if ($count_stmt === false) {
    die("Error preparing count statement: " . mysqli_error($conn));
}

// Bind parameters for count query
mysqli_stmt_bind_param($count_stmt, $types, ...$params);

// Execute count query
if (!mysqli_stmt_execute($count_stmt)) {
    die("Error executing count statement: " . mysqli_stmt_error($count_stmt));
}

$count_result = mysqli_stmt_get_result($count_stmt);
$total_transactions = mysqli_num_rows($count_result);
$total_pages = ceil($total_transactions / $transactions_per_page);

// Add LIMIT to main query for pagination
$sql .= " LIMIT ? OFFSET ?";
$params[] = $transactions_per_page;
$params[] = $offset;
$types .= "ii";

// Prepare and execute main query with proper parameter binding
$stmt = mysqli_prepare($conn, $sql);
if ($stmt === false) {
    die("Error preparing statement: " . mysqli_error($conn));
}

// Bind parameters for main query
mysqli_stmt_bind_param($stmt, $types, ...$params);

// Execute query
if (!mysqli_stmt_execute($stmt)) {
    die("Error executing statement: " . mysqli_stmt_error($stmt));
}

// Get result
$result = mysqli_stmt_get_result($stmt);

// Get user info for header
$user_query = "SELECT first_name, last_name, profile_image FROM users WHERE id = ?";
$user_stmt = mysqli_prepare($conn, $user_query);
mysqli_stmt_bind_param($user_stmt, "i", $user_id);
mysqli_stmt_execute($user_stmt);
$user_result = mysqli_stmt_get_result($user_stmt);
$user = mysqli_fetch_assoc($user_result);
$user_name = $user['last_name'] . ' ' . $user['first_name'];
$user_initials = strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JunkValue - Transaction History</title>
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
            --accent-blue: #4A89DC;
            --sidebar-width: 280px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', -apple-system, BlinkMacSystemFont, sans-serif;
        }

        body {
            background-color: var(--bg-beige);
            color: var(--text-dark);
            line-height: 1.6;
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar - New Vibrant Design */
        .sidebar {
            width: var(--sidebar-width);
            background: linear-gradient(180deg, var(--topbar-brown) 0%, #2A2520 100%);
            color: white;
            padding: 30px 0;
            position: sticky;
            top: 0;
            height: 100vh;
            box-shadow: 5px 0 15px rgba(0,0,0,0.1);
            z-index: 10;
            overflow-y: auto;
        }

        .sidebar-header {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 0 20px 30px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            margin-bottom: 30px;
        }

        .user-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background-color: var(--panel-cream);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: var(--topbar-brown);
            font-size: 24px;
            margin-bottom: 15px;
            border: 3px solid var(--sales-orange);
            box-shadow: 0 4px 10px rgba(0,0,0,0.2);
            transition: all 0.3s ease;
        }

        .user-avatar:hover {
            transform: scale(1.05);
            box-shadow: 0 6px 15px rgba(0,0,0,0.3);
        }

        .user-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 50%;
        }

        .user-name {
            font-size: 26px;
            font-weight: 600;
            margin-bottom: 5px;
            text-align: center;
        }

        .user-status {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 12px;
            color: var(--panel-cream);
            opacity: 0.8;
        }

        .status-indicator {
            width: 8px;
            height: 8px;
            background-color: #2ECC71;
            border-radius: 50%;
        }

        .nav-menu {
            list-style: none;
            padding: 0 15px;
        }

        .nav-menu li {
            margin-bottom: 5px;
        }

        .nav-menu a {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 12px 20px;
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
            border-radius: 8px;
            position: relative;
            overflow: hidden;
        }

        .nav-menu a::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            width: 3px;
            height: 100%;
            background-color: var(--sales-orange);
            transform: translateX(-10px);
            transition: all 0.3s ease;
            opacity: 0;
        }

        .nav-menu a:hover {
            background-color: rgba(255,255,255,0.1);
            color: white;
            transform: translateX(5px);
        }

        .nav-menu a:hover::before {
            transform: translateX(0);
            opacity: 1;
        }

        .nav-menu a.active {
            background-color: rgba(255,255,255,0.15);
            color: white;
            font-weight: 600;
        }

        .nav-menu a.active::before {
            transform: translateX(0);
            opacity: 1;
        }

        .nav-menu i {
            width: 20px;
            text-align: center;
            font-size: 18px;
            color: var(--panel-cream);
        }

        .nav-menu a.active i {
            color: var(--sales-orange);
        }

        .sidebar-footer {
            padding: 20px;
            margin-top: 30px;
            border-top: 1px solid rgba(255,255,255,0.1);
            text-align: center;
        }

        .logout-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            width: 100%;
            padding: 12px;
            background-color: rgba(255,255,255,0.1);
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .logout-btn:hover {
            background-color: rgba(255,255,255,0.2);
            transform: translateY(-2px);
        }

        /* Main Content Area */
        .main-content {
            flex: 1;
            padding: 30px;
            overflow-y: auto;
        }

        /* Header */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .page-title {
            font-size: 28px;
            font-weight: 700;
            color: var(--topbar-brown);
            position: relative;
            display: inline-block;
        }

        .page-title::after {
            content: '';
            position: absolute;
            bottom: -5px;
            left: 0;
            width: 50px;
            height: 3px;
            background-color: var(--sales-orange);
            border-radius: 3px;
        }

        .notification-bell {
            position: relative;
            width: 40px;
            height: 40px;
            background-color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .notification-bell:hover {
            transform: scale(1.1) rotate(15deg);
        }

        .notification-badge {
            position: absolute;
            top: -3px;
            right: -3px;
            width: 18px;
            height: 18px;
            background-color: var(--sales-orange);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            font-weight: bold;
        }

        /* Dashboard Cards */
        .dashboard-card {
            background-color: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            margin-bottom: 30px;
            border: 1px solid rgba(0,0,0,0.05);
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

        .view-all {
            color: var(--icon-green);
            text-decoration: none;
            font-weight: 500;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 5px;
            transition: all 0.3s ease;
        }

        .view-all:hover {
            color: var(--sales-orange);
            transform: translateX(3px);
        }

        /* Transaction Filters */
        .transaction-filters {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        .filter-group label {
            font-size: 14px;
            font-weight: 500;
            color: var(--text-dark);
        }
        
        select, .date-input, .search-bar input {
            padding: 10px 15px;
            border: 1px solid rgba(0,0,0,0.1);
            border-radius: 8px;
            background-color: white;
            font-size: 14px;
            transition: all 0.3s;
            width: 100%;
        }
        
        select:focus, .date-input:focus, .search-bar input:focus {
            outline: none;
            border-color: var(--icon-green);
            box-shadow: 0 0 0 3px rgba(106, 127, 70, 0.1);
        }
        
        .search-bar {
            position: relative;
            grid-column: 1 / -1;
        }
        
        .search-bar input {
            padding-left: 40px;
        }
        
        .search-bar i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-dark);
            opacity: 0.7;
        }
        
        .filter-buttons {
            display: flex;
            gap: 10px;
            grid-column: 1 / -1;
        }
        
        .btn {
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            font-size: 14px;
            transition: all 0.3s;
            border: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
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
        
        .btn-secondary {
            background-color: white;
            color: var(--text-dark);
            border: 1px solid rgba(0,0,0,0.1);
        }
        
        .btn-secondary:hover {
            background-color: #f8f9fa;
            border-color: rgba(0,0,0,0.2);
        }

        /* Transaction Table */
        .transaction-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }

        .transaction-table thead {
            position: sticky;
            top: 0;
        }

        .transaction-table th {
            background-color: rgba(106, 127, 70, 0.08);
            font-weight: 600;
            color: var(--icon-green);
            padding: 12px 15px;
            text-align: left;
            border-bottom: 2px solid rgba(106, 127, 70, 0.2);
        }

        .transaction-table td {
            padding: 14px 15px;
            text-align: left;
            border-bottom: 1px solid rgba(0,0,0,0.05);
        }

        .transaction-table tr:last-child td {
            border-bottom: none;
        }

        .transaction-table tr:hover td {
            background-color: rgba(106, 127, 70, 0.03);
        }

        .transaction-id {
            color: var(--icon-green);
            font-weight: 500;
            font-family: 'Courier New', monospace;
        }
        
        .transaction-items {
            font-size: 13px;
            color: var(--text-dark);
            opacity: 0.7;
            margin-top: 5px;
        }
        
        .transaction-status {
            display: inline-flex;
            align-items: center;
            padding: 5px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
            gap: 5px;
        }
        
        .transaction-status i {
            font-size: 10px;
        }
        
        .status-completed {
            background-color: rgba(40, 167, 69, 0.1);
            color: #28a745;
        }
        
        .status-pending {
            background-color: rgba(255, 193, 7, 0.1);
            color: #d39e00;
        }
        
        .status-cancelled {
            background-color: rgba(220, 53, 69, 0.1);
            color: #dc3545;
        }
        
        .transaction-amount {
            font-weight: 600;
            white-space: nowrap;
        }

        /* Buttons */
        .btn {
            display: inline-block;
            padding: 12px 25px;
            border-radius: 8px;
            font-weight: 500;
            font-size: 15px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: center;
            text-decoration: none;
            border: none;
            position: relative;
            overflow: hidden;
        }

        .btn::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, rgba(255,255,255,0.2) 0%, rgba(255,255,255,0) 100%);
            z-index: 1;
        }

        .btn-primary {
            background: linear-gradient(90deg, var(--icon-green) 0%, var(--stock-green) 100%);
            color: white;
            box-shadow: 0 5px 15px rgba(106, 127, 70, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(106, 127, 70, 0.4);
        }

        .export-btn {
            background: linear-gradient(90deg, var(--sales-orange) 0%, #e67a41 100%);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
            box-shadow: 0 3px 10px rgba(217, 122, 65, 0.3);
        }
        
        .export-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(217, 122, 65, 0.4);
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 50px 20px;
            color: var(--text-dark);
            opacity: 0.7;
        }

        .empty-state i {
            font-size: 50px;
            color: var(--icon-green);
            margin-bottom: 20px;
            opacity: 0.5;
        }

        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            margin-top: 30px;
            gap: 8px;
            align-items: center;
        }
        
        .page-btn {
            min-width: 40px;
            height: 40px;
            border: 1px solid rgba(0,0,0,0.1);
            background-color: white;
            border-radius: 8px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 500;
            color: var(--text-dark);
            transition: all 0.2s ease;
            text-decoration: none;
            padding: 0 12px;
        }
        
        .page-btn:hover:not(.active, .disabled) {
            background-color: rgba(106, 127, 70, 0.1);
            border-color: var(--icon-green);
            color: var(--icon-green);
        }
        
        .page-btn.active {
            background-color: var(--icon-green);
            color: white;
            border-color: var(--icon-green);
            font-weight: 600;
            box-shadow: 0 2px 5px rgba(106, 127, 70, 0.2);
        }
        
        .page-btn.disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .page-btn i {
            font-size: 14px;
        }
        
        .page-dots {
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-dark);
            opacity: 0.7;
        }

        /* Mobile Menu Toggle */
        .mobile-menu-toggle {
            display: none;
            width: 40px;
            height: 40px;
            background-color: var(--sales-orange);
            color: white;
            border-radius: 8px;
            position: fixed;
            top: 20px;
            left: 20px;
            z-index: 100;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            cursor: pointer;
            box-shadow: 0 3px 10px rgba(0,0,0,0.2);
        }

        /* Responsive styles */
        @media (max-width: 1200px) {
            .sidebar {
                width: 240px;
            }
        }

        @media (max-width: 992px) {
            .sidebar {
                position: fixed;
                left: -100%;
                transition: all 0.3s ease;
            }
            
            .sidebar.active {
                left: 0;
            }
            
            .main-content {
                margin-left: 0;
                padding: 20px;
            }
            
            .mobile-menu-toggle {
                display: block;
            }
        }

        @media (max-width: 768px) {
            .transaction-filters {
                grid-template-columns: 1fr 1fr;
            }
            
            .transaction-table {
                display: block;
                overflow-x: auto;
                white-space: nowrap;
            }
        }
        
        @media (max-width: 576px) {
            .transaction-filters {
                grid-template-columns: 1fr;
            }
            
            .filter-buttons {
                flex-direction: column;
            }
            
            .page-btn {
                min-width: 36px;
                height: 36px;
                font-size: 14px;
            }
            
            .page-dots {
                width: 36px;
                height: 36px;
            }
            
            .dashboard-card {
                padding: 20px 15px;
            }
            
            .transaction-table td, 
            .transaction-table th {
                padding: 12px 10px;
            }
        }
    </style>
</head>
<body>
    <!-- Mobile Menu Toggle -->
    <div class="mobile-menu-toggle" id="mobileMenuToggle">
        <i class="fas fa-bars"></i>
    </div>

    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="user-avatar">
                <?php if (!empty($user['profile_image']) && file_exists($user['profile_image'])): ?>
                    <img src="<?php echo htmlspecialchars($user['profile_image']); ?>" alt="Profile">
                <?php else: ?>
                    <?php echo $user_initials; ?>
                <?php endif; ?>
            </div>
            <div class="user-name"><?php echo htmlspecialchars($user_name); ?></div>
            <div class="user-status">
                <span class="status-indicator"></span>
                <span>Active</span>
            </div>
        </div>
        
        <ul class="nav-menu">
            <li><a href="index.php"><i class="fas fa-home"></i> Dashboard</a></li>
            <li><a href="#" class="active"><i class="fas fa-history"></i> Transaction History</a></li>
            <li><a href="schedule.php"><i class="fas fa-calendar-alt"></i> Schedule Pickup</a></li>
            <li><a href="prices.php"><i class="fas fa-coins"></i> Current Prices</a></li>
            <li><a href="rewards.php"><i class="fas fa-award"></i> Loyalty Rewards</a></li>
            <li><a href="settings.php"><i class="fas fa-user-cog"></i> Account Settings</a></li>
        </ul>
        
        <div class="sidebar-footer">
            <a href="login/login.php" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="main-content">
        <div class="header">
            <h1 class="page-title">Transaction History</h1>
            <div class="notification-bell">
                <i class="fas fa-bell"></i>
                <span class="notification-badge">3</span>
            </div>
        </div>
        
        <div class="dashboard-card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-history"></i> Your Transactions</h3>
                <button class="export-btn">
                    <i class="fas fa-download"></i> Export
                </button>
            </div>
            
            <!-- Filters -->
            <form method="GET" action="">
                <input type="hidden" name="page" value="1"> <!-- Reset to page 1 when filters change -->
                <div class="transaction-filters">
                    <div class="filter-group">
                        <label>Transaction Type</label>
                        <select name="type">
                            <option value="">All Types</option>
                            <option value="Pickup" <?php echo ($type_filter == 'Pickup') ? 'selected' : ''; ?>>Pickups</option>
                            <option value="Walk-in" <?php echo ($type_filter == 'Walk-in') ? 'selected' : ''; ?>>Walk-in Sales</option>
                            <option value="Loyalty" <?php echo ($type_filter == 'Loyalty') ? 'selected' : ''; ?>>Loyalty Rewards</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label>Status</label>
                        <select name="status">
                            <option value="">All Statuses</option>
                            <option value="Completed" <?php echo ($status_filter == 'Completed') ? 'selected' : ''; ?>>Completed</option>
                            <option value="Pending" <?php echo ($status_filter == 'Pending') ? 'selected' : ''; ?>>Pending</option>
                            <option value="Cancelled" <?php echo ($status_filter == 'Cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label>Date From</label>
                        <input type="date" class="date-input" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label>Date To</label>
                        <input type="date" class="date-input" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>">
                    </div>
                    
                    <div class="search-bar">
                        <i class="fas fa-search"></i>
                        <input type="text" name="search" placeholder="Search transactions..." value="<?php echo htmlspecialchars($search_query); ?>">
                    </div>
                    
                    <div class="filter-buttons">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-filter"></i> Apply Filters
                        </button>
                        
                        <a href="transaction.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Clear Filters
                        </a>
                    </div>
                </div>
            </form>
            
            <!-- Transaction Table -->
            <div style="overflow-x: auto;">
                <table class="transaction-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Date of Transacation</th>
                            <th>Type</th>
                            <th>Details</th>
                            <th>Status</th>
                            <th>Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (mysqli_num_rows($result) > 0): ?>
                            <?php while ($transaction = mysqli_fetch_assoc($result)): ?>
                                <tr>
                                    <td class="transaction-id">#<?php echo htmlspecialchars($transaction['transaction_id']); ?></td>
                                    <td>
                                        <?php echo date('M j, Y', strtotime($transaction['transaction_date'])); ?>
                                    
                                    </td>
                                    <td>
                                        <?php 
                                        $type_icon = '';
                                        switch($transaction['transaction_type']) {
                                            case 'Pickup': $type_icon = 'fa-truck'; break;
                                            case 'Walk-in': $type_icon = 'fa-walking'; break;
                                            case 'Loyalty': $type_icon = 'fa-award'; break;
                                            default: $type_icon = 'fa-exchange-alt';
                                        }
                                        ?>
                                        <i class="fas <?php echo $type_icon; ?>" style="margin-right: 8px; color: var(--icon-green);"></i>
                                        <?php echo htmlspecialchars($transaction['transaction_type']); ?>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($transaction['item_details']); ?>
                                        <?php if (!empty($transaction['additional_info'])): ?>
                                            <div class="transaction-items">
                                                <?php echo htmlspecialchars($transaction['additional_info']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php 
                                        $status_class = 'status-' . strtolower($transaction['status']);
                                        $status_icon = '';
                                        switch($transaction['status']) {
                                            case 'Completed': $status_icon = 'fa-check-circle'; break;
                                            case 'Pending': $status_icon = 'fa-clock'; break;
                                            case 'Cancelled': $status_icon = 'fa-times-circle'; break;
                                        }
                                        echo '<span class="transaction-status ' . $status_class . '">' . 
                                             '<i class="fas ' . $status_icon . '"></i>' . 
                                             htmlspecialchars($transaction['status']) . '</span>';
                                        ?>
                                    </td>
                                    <td class="transaction-amount" style="color: <?php 
                                        echo ($transaction['transaction_type'] == 'Loyalty') ? 'var(--sales-orange)' : 'var(--icon-green)';
                                    ?>;">
                                        +₱<?php echo number_format($transaction['amount'], 2); ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="empty-state">
                                    <i class="fas fa-info-circle"></i>
                                    <p>No transactions found</p>
                                    <?php if (!empty($type_filter) || !empty($status_filter) || !empty($date_from) || !empty($search_query)): ?>
                                        <p>Try adjusting your filters or <a href="transaction.php" style="color: var(--icon-green);">clear all filters</a></p>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($current_page > 1): ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => 1])); ?>" class="page-btn" title="First Page">
                            <i class="fas fa-angle-double-left"></i>
                        </a>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $current_page - 1])); ?>" class="page-btn" title="Previous Page">
                            <i class="fas fa-angle-left"></i>
                        </a>
                    <?php endif; ?>
                    
                    <?php 
                    // Show page numbers
                    $start_page = max(1, $current_page - 2);
                    $end_page = min($total_pages, $current_page + 2);
                    
                    if ($start_page > 1) {
                        echo '<a href="?' . http_build_query(array_merge($_GET, ['page' => 1])) . '" class="page-btn">1</a>';
                        if ($start_page > 2) {
                            echo '<span class="page-dots">...</span>';
                        }
                    }
                    
                    for ($i = $start_page; $i <= $end_page; $i++) {
                        $active = ($i == $current_page) ? 'active' : '';
                        echo '<a href="?' . http_build_query(array_merge($_GET, ['page' => $i])) . '" class="page-btn ' . $active . '">' . $i . '</a>';
                    }
                    
                    if ($end_page < $total_pages) {
                        if ($end_page < $total_pages - 1) {
                            echo '<span class="page-dots">...</span>';
                        }
                        echo '<a href="?' . http_build_query(array_merge($_GET, ['page' => $total_pages])) . '" class="page-btn">' . $total_pages . '</a>';
                    }
                    ?>
                    
                    <?php if ($current_page < $total_pages): ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $current_page + 1])); ?>" class="page-btn" title="Next Page">
                            <i class="fas fa-angle-right"></i>
                        </a>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $total_pages])); ?>" class="page-btn" title="Last Page">
                            <i class="fas fa-angle-double-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Mobile menu toggle
        document.getElementById('mobileMenuToggle').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('active');
        });
    </script>
</body>
</html>
<?php
// Close prepared statements
if (isset($stmt)) {
    mysqli_stmt_close($stmt);
}
if (isset($count_stmt)) {
    mysqli_stmt_close($count_stmt);
}
if (isset($user_stmt)) {
    mysqli_stmt_close($user_stmt);
}
// Close connection
mysqli_close($conn);
?>