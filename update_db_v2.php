<?php
require_once 'config.php';

$conn = getDBConnection();

// Add ai_estimated_age column
try {
    $conn->query("ALTER TABLE users ADD COLUMN ai_estimated_age VARCHAR(50) DEFAULT NULL");
    echo "Added ai_estimated_age column.<br>";
} catch (Exception $e) {
    echo "ai_estimated_age column might already exist.<br>";
}

// Add ai_tags column
try {
    $conn->query("ALTER TABLE users ADD COLUMN ai_tags TEXT DEFAULT NULL");
    echo "Added ai_tags column.<br>";
} catch (Exception $e) {
    echo "ai_tags column might already exist.<br>";
}

$conn->close();
echo "Database update completed.";
?>
