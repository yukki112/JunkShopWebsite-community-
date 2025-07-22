<?php
// Database configuration
define('DB_SERVER', 'localhost:3307');
define('DB_USERNAME', 'root'); // Default XAMPP username
define('DB_PASSWORD', ''); // Default XAMPP has no password
define('DB_NAME', 'JunkShop');

// Attempt to connect to MySQL database
$conn = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Check connection
if($conn === false){
    die("ERROR: Could not connect. " . mysqli_connect_error());
}
?>