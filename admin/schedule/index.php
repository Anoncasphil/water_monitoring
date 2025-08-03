<?php
session_start();

// Check if user is not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login/index.php');
    exit;
}

// Redirect to the main schedule page
header('Location: schedule.php');
exit;
?>
