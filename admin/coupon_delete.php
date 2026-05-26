<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] != 0) {
    header("Location: ../index.php");
    exit;
}
include "../php/db.php";

$id = (int)$_GET['id'];
$conn->query("DELETE FROM coupons WHERE id=$id");

header("Location: coupons.php");
exit;