<?php
session_start();
include "php/db.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$product_id = (int)$_POST['product_id'];
$rating = (int)$_POST['rating'];
$content = trim($_POST['content']);

if ($product_id && $rating && $content) {
    $stmt = $conn->prepare("INSERT INTO reviews (product_id, user_id, rating, content, created_at) VALUES (?,?,?,?,NOW())");
    $stmt->bind_param("iiis", $product_id, $user_id, $rating, $content);
    $stmt->execute();
    $stmt->close();
}

header("Location: product.php?id=$product_id");
exit;
?>