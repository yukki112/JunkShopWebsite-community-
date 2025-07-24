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
$user_name = $user['last_name'] . ' ' . $user['first_name'];
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

        /* Welcome Banner */
        .welcome-banner {
            background: linear-gradient(135deg, var(--panel-cream) 0%, #E8DFC8 100%);
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
            position: relative;
            overflow: hidden;
            border: 1px solid rgba(217, 122, 65, 0.3);
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }

        .welcome-content h2 {
            font-size: 24px;
            font-weight: 700;
            color: var(--topbar-brown);
            margin-bottom: 10px;
        }

        .welcome-content p {
            color: var(--text-dark);
            max-width: 600px;
            margin-bottom: 15px;
        }

        .welcome-icon {
            position: absolute;
            right: 20px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 100px;
            color: rgba(217, 122, 65, 0.1);
            z-index: 1;
        }

        /* Quick Actions */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .action-card {
            background-color: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
            cursor: pointer;
            text-decoration: none;
            color: var(--text-dark);
            border: 1px solid rgba(0,0,0,0.05);
            position: relative;
            overflow: hidden;
        }

        .action-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 5px;
            background: linear-gradient(90deg, var(--sales-orange), var(--icon-green));
        }

        .action-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }

        .action-icon {
            font-size: 30px;
            color: var(--icon-green);
            margin-bottom: 20px;
            width: 60px;
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
            background-color: rgba(106, 127, 70, 0.1);
            transition: all 0.3s ease;
        }

        .action-card:hover .action-icon {
            transform: rotate(10deg) scale(1.1);
            background-color: rgba(106, 127, 70, 0.2);
        }

        .action-title {
            font-weight: 600;
            font-size: 18px;
            margin-bottom: 10px;
        }

        .action-desc {
            font-size: 14px;
            color: var(--text-dark);
            opacity: 0.8;
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

        /* Price Table */
        .price-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }

        .price-table thead {
            position: sticky;
            top: 0;
        }

        .price-table th {
            background-color: rgba(106, 127, 70, 0.08);
            font-weight: 600;
            color: var(--icon-green);
            padding: 12px 15px;
            text-align: left;
            border-bottom: 2px solid rgba(106, 127, 70, 0.2);
        }

        .price-table td {
            padding: 14px 15px;
            text-align: left;
            border-bottom: 1px solid rgba(0,0,0,0.05);
        }

        .price-table tr:last-child td {
            border-bottom: none;
        }

        .price-table tr:hover td {
            background-color: rgba(106, 127, 70, 0.03);
        }

        /* Loyalty Program */
        .loyalty-card {
            display: flex;
            align-items: center;
            gap: 25px;
            background: linear-gradient(135deg, rgba(106, 127, 70, 0.05) 0%, rgba(242, 234, 211, 0.5) 100%);
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 25px;
            border: 1px solid rgba(106, 127, 70, 0.1);
            position: relative;
            overflow: hidden;
        }

        .loyalty-card::after {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 100%;
            height: 200%;
            background: radial-gradient(circle, rgba(106,127,70,0.05) 0%, rgba(106,127,70,0) 70%);
            z-index: 1;
        }

        .loyalty-badge {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--panel-cream) 0%, #d1d7dc 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            color: var(--sales-orange);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            flex-shrink: 0;
            transition: all 0.3s ease;
            z-index: 2;
        }

        .loyalty-badge:hover {
            transform: rotate(15deg) scale(1.1);
            box-shadow: 0 8px 20px rgba(0,0,0,0.2);
        }

        .progress-container {
            flex-grow: 1;
            z-index: 2;
        }

        .progress-label {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            font-size: 14px;
        }

        .progress-bar {
            height: 10px;
            background-color: #e9ecef;
            border-radius: 5px;
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

        /* Benefits Grid */
        .benefits-grid {
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
            gap: 15px;
            padding: 10px 0;
            border-bottom: 1px dashed rgba(0,0,0,0.1);
        }

        .benefits-list i {
            color: var(--icon-green);
            font-size: 20px;
            flex-shrink: 0;
            width: 30px;
            height: 30px;
            background-color: rgba(106, 127, 70, 0.1);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* Reward Card */
        .reward-card {
            background-color: rgba(106, 127, 70, 0.05);
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            border: 1px dashed rgba(106, 127, 70, 0.3);
            position: relative;
            overflow: hidden;
        }

        .reward-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, rgba(255,255,255,0.1) 0%, rgba(255,255,255,0) 100%);
            z-index: 1;
        }

        .reward-card p {
            font-size: 15px;
            margin-bottom: 15px;
            position: relative;
            z-index: 2;
        }

        .progress-mini {
            display: flex;
            align-items: center;
            gap: 10px;
            position: relative;
            z-index: 2;
        }

        .progress-mini-bar {
            flex-grow: 1;
            height: 8px;
            background-color: #e9ecef;
            border-radius: 4px;
            overflow: hidden;
        }

        .progress-mini-fill {
            height: 100%;
            width: 30%;
            background: linear-gradient(90deg, var(--icon-green) 0%, var(--stock-green) 100%);
            border-radius: 4px;
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

        /* Transactions */
        .transaction-list {
            display: grid;
            gap: 15px;
        }

        .transaction-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 18px 20px;
            border-radius: 12px;
            transition: all 0.3s ease;
            background-color: white;
            box-shadow: 0 3px 10px rgba(0,0,0,0.03);
            border: 1px solid rgba(0,0,0,0.05);
        }

        .transaction-item:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.08);
            border-color: rgba(106, 127, 70, 0.3);
        }

        .transaction-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .transaction-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            background-color: rgba(106, 127, 70, 0.1);
            color: var(--icon-green);
            flex-shrink: 0;
        }

        .transaction-details h4 {
            margin-bottom: 5px;
            font-weight: 500;
        }

        .transaction-details p {
            font-size: 14px;
            color: var(--text-dark);
            opacity: 0.7;
        }

        .transaction-amount {
            font-weight: 600;
            font-size: 18px;
            color: var(--icon-green);
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
            padding: 30px;
            border-radius: 15px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
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
            top: 20px;
            right: 20px;
            font-size: 24px;
            font-weight: bold;
            color: var(--text-dark);
            opacity: 0.5;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .close-modal:hover {
            opacity: 1;
            color: var(--icon-green);
            transform: rotate(90deg);
        }
        
        .modal h3 {
            color: var(--icon-green);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 22px;
        }
        
        /* Calculator Styles */
        .calculator-form {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        .form-group label {
            font-weight: 500;
            color: var(--text-dark);
            font-size: 14px;
        }
        
        .form-group select, .form-group input {
            padding: 14px 15px;
            border: 1px solid rgba(0,0,0,0.1);
            border-radius: 8px;
            font-size: 15px;
            background-color: white;
            transition: all 0.3s ease;
        }
        
        .form-group select:focus, .form-group input:focus {
            outline: none;
            border-color: var(--icon-green);
            box-shadow: 0 0 0 3px rgba(106, 127, 70, 0.1);
        }
        
        .result-container {
            margin-top: 20px;
            text-align: center;
            padding: 25px;
            background-color: rgba(106, 127, 70, 0.05);
            border-radius: 10px;
            border: 1px dashed rgba(106, 127, 70, 0.3);
        }
        
        #calculated-result {
            font-size: 32px;
            font-weight: 700;
            color: var(--icon-green);
            margin-top: 10px;
        }
        
        /* QR Code Styles */
        .qr-code-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 20px;
            padding: 20px 0;
        }
        
        .qr-code-image {
            width: 220px;
            height: 220px;
            border: 1px solid rgba(0,0,0,0.1);
            padding: 15px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.05);
        }
        
        /* Referral Styles */
        .referral-link-container {
            display: flex;
            margin: 20px 0;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
        }
        
        #referral-link {
            flex-grow: 1;
            padding: 14px 15px;
            border: none;
            font-size: 14px;
            background-color: white;
        }
        
        .btn-copy {
            background-color: var(--icon-green);
            color: white;
            border: none;
            padding: 0 20px;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .btn-copy:hover {
            background-color: var(--stock-green);
        }
        
        .social-share {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        
        .social-btn {
            flex: 1;
            padding: 12px;
            border: none;
            border-radius: 8px;
            color: white;
            font-weight: 500;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.3s;
            font-size: 14px;
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
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
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
            .quick-actions {
                grid-template-columns: 1fr 1fr;
            }
            
            .benefits-grid {
                grid-template-columns: 1fr;
            }
            
            .loyalty-card {
                flex-direction: column;
                text-align: center;
            }
        }

        @media (max-width: 576px) {
            .quick-actions {
                grid-template-columns: 1fr;
            }
            
            .dashboard-card {
                padding: 20px;
            }

            .social-share {
                flex-direction: column;
            }
            
            .modal-content {
                padding: 20px;
            }
        }

        /* Animations */
        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }

        .action-card:hover .action-icon {
            animation: float 1.5s ease-in-out infinite;
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

        @media (max-width: 992px) {
            .mobile-menu-toggle {
                display: flex;
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
            <li><a href="#" class="active"><i class="fas fa-home"></i> Dashboard</a></li>
            <li><a href="Transaction.php"><i class="fas fa-history"></i> Transaction History</a></li>
            <li><a href="Schedule.php"><i class="fas fa-calendar-alt"></i> Schedule Pickup</a></li>
            <li><a href="prices.php"><i class="fas fa-coins"></i> Current Prices</a></li>
            <li><a href="rewards.php"><i class="fas fa-award"></i> Loyalty Rewards</a></li>
            <li><a href="settings.php"><i class="fas fa-user-cog"></i> Account Settings</a></li>
        </ul>
        
        <div class="sidebar-footer">
            <a href="Login/Login.php" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="main-content">
        <div class="header">
            <h1 class="page-title">Dashboard</h1>
            <div class="notification-bell">
                <i class="fas fa-bell"></i>
                <span class="notification-badge">3</span>
            </div>
        </div>
        
        <!-- Welcome Banner -->
        <div class="welcome-banner">
            <div class="welcome-content">
                <h2>Welcome back, <?php echo htmlspecialchars($user['first_name']); ?>!</h2>
                <p>Ready to turn your scrap into cash? Check today's prices, schedule a pickup, or track your rewards below.</p>
                <button class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 10px;">
                    <i class="fas fa-plus"></i> New Transaction
                </button>
            </div>
            <div class="welcome-icon">
                <i class="fas fa-recycle"></i>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="quick-actions">
            <a href="Schedule.php" class="action-card">
                <div class="action-icon">
                    <i class="fas fa-truck"></i>
                </div>
                <h3 class="action-title">Request Pickup</h3>
                <p class="action-desc">Schedule a collection for your recyclables</p>
            </a>
            
            <div class="action-card" onclick="document.getElementById('priceCalculatorModal').style.display='block'">
                <div class="action-icon">
                    <i class="fas fa-calculator"></i>
                </div>
                <h3 class="action-title">Price Calculator</h3>
                <p class="action-desc">Estimate your earnings</p>
            </div>
            
            <div class="action-card" onclick="document.getElementById('qrCodeModal').style.display='block'">
                <div class="action-icon">
                    <i class="fas fa-qrcode"></i>
                </div>
                <h3 class="action-title">Scan QR Code</h3>
                <p class="action-desc">Quick check-in at our facility</p>
            </div>
            
            <div class="action-card" onclick="document.getElementById('referFriendModal').style.display='block'">
                <div class="action-icon">
                    <i class="fas fa-share-alt"></i>
                </div>
                <h3 class="action-title">Refer a Friend</h3>
                <p class="action-desc">Earn bonus points for referrals</p>
            </div>
        </div>
        
        <!-- Current Prices -->
        <div class="dashboard-card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-coins"></i> Today's Scrap Prices</h3>
                <span style="color: var(--text-dark); opacity: 0.7; font-size: 14px;">
                    <i class="fas fa-sync-alt"></i> Updated: <?php echo date('g:i A'); ?>
                </span>
            </div>
            <div style="overflow-x: auto;">
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
        </div>
        
        <!-- Two Column Layout -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px;">
            <!-- Loyalty Program -->
            <div class="dashboard-card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-award"></i> Your Loyalty Rewards</h3>
                </div>
                
                <div class="loyalty-card">
                    <div class="loyalty-badge">
                        <i class="fas fa-crown"></i>
                    </div>
                    <div class="progress-container">
                        <div class="progress-label">
                            <span>Silver Member</span>
                            <span>650/1000 points</span>
                        </div>
                        <div class="progress-bar">
                            <div class="progress-fill"></div>
                        </div>
                    </div>
                </div>
                
                <h4 style="margin-bottom: 15px; color: var(--text-dark);">Your Benefits</h4>
                <ul class="benefits-list">
                    <li><i class="fas fa-check-circle"></i> 5% bonus on all sales</li>
                    <li><i class="fas fa-check-circle"></i> 2 free pickups/month</li>
                    <li><i class="fas fa-check-circle"></i> Priority service</li>
                    <li><i class="fas fa-check-circle"></i> Exclusive offers</li>
                </ul>
                
                <div class="reward-card">
                    <p>Recycle 50kg this week to earn +100 points</p>
                    <div class="progress-mini">
                        <div class="progress-mini-bar">
                            <div class="progress-mini-fill"></div>
                        </div>
                        <span style="font-size: 12px; color: var(--text-dark); opacity: 0.7;">15/50kg</span>
                    </div>
                </div>
                
                <button class="btn btn-primary">
                    Redeem Points (650 available)
                </button>
            </div>
            
            <!-- Recent Transactions -->
            <div class="dashboard-card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-history"></i> Recent Transactions</h3>
                    <a href="Transaction.php" class="view-all">
                        View All <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
                
                <div class="transaction-list">
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
                            <div class="transaction-item">
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
                        <div class="empty-state">
                            <i class="fas fa-info-circle"></i>
                            <p>No recent transactions found</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Price Calculator Modal -->
    <div id="priceCalculatorModal" class="modal">
        <div class="modal-content">
            <span class="close-modal" onclick="document.getElementById('priceCalculatorModal').style.display='none'">&times;</span>
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
                <button id="calculate-btn" class="btn btn-primary">Calculate Value</button>
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
            <span class="close-modal" onclick="document.getElementById('qrCodeModal').style.display='none'">&times;</span>
            <h3><i class="fas fa-qrcode"></i> Your QR Code</h3>
            <div class="qr-code-container">
                <?php 
                // Generate a random string for the QR code (in a real app, this would be a user-specific code)
                $qr_code_data = 'JUNKPRO-' . $user_id . '-' . bin2hex(random_bytes(3));
                ?>
                <img src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=<?php echo urlencode($qr_code_data); ?>" 
                     alt="QR Code for <?php echo htmlspecialchars($user_name); ?>" class="qr-code-image">
                <p>Scan this code at our facility for quick check-in</p>
                <button class="btn btn-primary" onclick="downloadQRCode()">
                    <i class="fas fa-download"></i> Download QR Code
                </button>
            </div>
        </div>
    </div>

    <!-- Refer a Friend Modal -->
    <div id="referFriendModal" class="modal">
        <div class="modal-content">
            <span class="close-modal" onclick="document.getElementById('referFriendModal').style.display='none'">&times;</span>
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
        // Mobile menu toggle
        document.getElementById('mobileMenuToggle').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('active');
        });

        // Modal functionality
        document.addEventListener('DOMContentLoaded', function() {
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

            // Close modals when clicking outside
            window.onclick = function(event) {
                if (event.target.classList.contains('modal')) {
                    event.target.style.display = 'none';
                }
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