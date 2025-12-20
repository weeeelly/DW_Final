<?php
// Initialize session and database connection
require_once __DIR__ . '/../config.php';

// Check if user is logged in
requireLogin();

// Get database connection
$conn = getDBConnection();

// Get current user information
$userId = getCurrentUserId();
$username = getCurrentUsername();
?>