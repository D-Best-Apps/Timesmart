<?php
header('Content-Type: application/json');
error_reporting(0); // Suppress all PHP errors for clean JSON output

require_once '../db.php';

if (isset($_GET['tag_id'])) {
    $tag_id = $_GET['tag_id'];
    // Use the $conn variable directly from db.php
    $stmt = $conn->prepare("SELECT FirstName FROM users WHERE tag_id = ?");
    $stmt->bind_param("s", $tag_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($user = $result->fetch_assoc()) {
        echo json_encode(['success' => true, 'name' => $user['FirstName']]);
    } else {
        echo json_encode(['success' => false, 'message' => 'User not found.']);
    }
    $stmt->close();
    $conn->close();
} else {
    echo json_encode(['success' => false, 'message' => 'No tag ID provided.']);
}
?>