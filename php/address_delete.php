<?php
session_start();
include "db.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$address_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($address_id > 0) {
    // 해당 배송지가 현재 로그인한 유저 소유인지 확인
    $stmt = $conn->prepare("SELECT id FROM user_addresses WHERE id=? AND user_id=?");
    $stmt->bind_param("ii", $address_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($result) {
        // 배송지 삭제
        $stmt = $conn->prepare("DELETE FROM user_addresses WHERE id=? AND user_id=?");
        $stmt->bind_param("ii", $address_id, $user_id);
        $stmt->execute();
        $stmt->close();
    }
}

// 삭제 후 다시 마이페이지 → 배송지 탭으로 이동
header("Location: ../mypage.php?address=1");
exit;