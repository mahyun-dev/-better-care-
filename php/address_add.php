<?php
session_start();
include "db.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// 입력값 받기
$recipient = trim($_POST['recipient']);
$phone = trim($_POST['phone']);
$postal_code = trim($_POST['postal_code']);
$address_line1 = trim($_POST['address_line1']);
$address_line2 = trim($_POST['address_line2']);
$is_default = isset($_POST['is_default']) ? 1 : 0;

// 기본 배송지 해제
if ($is_default) {
    $stmt = $conn->prepare("UPDATE user_addresses SET is_default=0 WHERE user_id=?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->close();
}

// 배송지 추가
$stmt = $conn->prepare("
    INSERT INTO user_addresses 
    (user_id, recipient, phone, postal_code, address_line1, address_line2, is_default) 
    VALUES (?,?,?,?,?,?,?)
");
$stmt->bind_param("isssssi", $user_id, $recipient, $phone, $postal_code, $address_line1, $address_line2, $is_default);

if ($stmt->execute()) {
    header("Location: ../mypage.php?address=1");
    exit;
} else {
    echo "❌ 배송지 추가 실패";
}
$stmt->close();
?>