<?php
session_start();
include "../php/db.php";
$id = (int)$_GET['id'];

$stmt = $conn->prepare("DELETE FROM membership_levels WHERE id=?");
$stmt->bind_param("i", $id);
$stmt->execute();
$stmt->close();

header("Location: levels.php");
exit;
?>