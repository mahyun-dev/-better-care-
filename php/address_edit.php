<?php
session_start();
include "db.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$address_id = intval($_POST['address_id']);

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

// 수정
$stmt = $conn->prepare("
    UPDATE user_addresses 
    SET recipient=?, phone=?, postal_code=?, address_line1=?, address_line2=?, is_default=? 
    WHERE id=? AND user_id=?
");
$stmt->bind_param("ssssiiii", $recipient, $phone, $postal_code, $address_line1, $address_line2, $is_default, $address_id, $user_id);

if ($stmt->execute()) {
    header("Location: ../mypage.php?address=1");
    exit;
} else {
    echo "❌ 배송지 수정 실패";
}
$stmt->close();
?>