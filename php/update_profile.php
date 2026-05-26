<?php
session_start();
include "db.php";

if (!isset($_SESSION['user_id'])) { exit("로그인 필요"); }

$user_id = $_SESSION['user_id'];
$email = trim($_POST['email']);
$password = $_POST['password'];

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    exit("잘못된 이메일 형식입니다.");
}

if (!empty($password)) {
    $hashed = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("UPDATE users SET email=?, password=? WHERE id=?");
    $stmt->bind_param("ssi", $email, $hashed, $user_id);
} else {
    $stmt = $conn->prepare("UPDATE users SET email=? WHERE id=?");
    $stmt->bind_param("si", $email, $user_id);
}
$stmt->execute();
$stmt->close();

header("Location: ../mypage.php");
exit;
?>