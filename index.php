<?php
session_start();
include "php/db.php";
$products = $conn->query("SELECT * FROM products ORDER BY created_at DESC LIMIT 5");
$rand = $conn->query("SELECT * FROM products ORDER BY RAND() DESC LIMIT 5");
$categorie = $conn->query("SELECT * FROM categories ORDER BY id ASC LIMIT 5");
?>
<!DOCTYPE html>
<html lang="ko">
<head>
  <link rel="manifest" href="manifest.json">
  <meta name="theme-color" content="#333333">
  <link rel="apple-touch-icon" href="lcon.png">
  
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, user-scalable=no">
  <title>Luminoa Shop</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="css/styles.css">
</head>
<body>
<?php include "php/header.php"; ?>

<!-- Hero Section -->
<section class="bg-light py-5 text-center hero-luminoa">
  <div class="container">
    <h1 class="fw-bold">빛과 지혜가 만나는 공간, 루미노아</h1>
    <p class="lead">창의성과 통찰을 담은 새로운 시즌 컬렉션을 만나보세요.</p>
    <a href="products.php" class="btn btn-luminoa btn-lg">상품 보러가기</a>
  </div>
</section>
<?php include "php/products-container.php"; ?>
<?php include "php/footer.php"; ?>
<?php include "php/top-button.php"; ?>
</body>
</html>