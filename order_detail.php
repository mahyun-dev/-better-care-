<?php
session_start();
include "php/db.php";

if (!isset($_SESSION['user_id'])) {
  header("Location: login.php");
  exit;
}

$user_id = $_SESSION['user_id'];
$order_id = intval($_GET['id']);

// 주문 기본 정보 가져오기
$order = $conn->query("SELECT * FROM orders WHERE id=$order_id AND user_id=$user_id")->fetch_assoc();
if(!$order){
  die("잘못된 접근입니다.");
}

// 주문 상품 목록 가져오기
$items = $conn->query("
  SELECT oi.*, p.name, p.image 
  FROM order_items oi
  JOIN products p ON oi.product_id = p.id
  WHERE oi.order_id=$order_id
");
?>
<!DOCTYPE html>
<html lang="ko">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, user-scalable=no">
  <title>주문 상세보기</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="css/styles.css">
</head>
<body>
<?php include "php/header.php"; ?>

<div class="container py-5">
  <h2>주문 상세보기 #<?= $order['id'] ?></h2>
  <p><b>주문일자:</b> <?= $order['created_at'] ?></p>
  <p><b>상태:</b> <?= $order['status'] ?></p>
  <p><b>총액:</b> ₩<?= number_format($order['total_price']) ?></p>
  <p><b>할인:</b> -₩<?= number_format($order['discount']) ?></p>
  <p><b>포인트 사용:</b> -₩<?= number_format($order['used_points']) ?></p>
  <p><b>최종 결제:</b> ₩<?= number_format($order['final_price']) ?></p>
  <hr>

  <h5>상품 목록</h5>
  <table class="table">
    <thead><tr><th>이미지</th><th>상품명</th><th>수량</th><th>가격</th></tr></thead>
    <tbody>
      <?php while($i=$items->fetch_assoc()): ?>
      <tr>
        <td><img src="<?= htmlspecialchars($i['image']) ?>" width="50"></td>
        <td><?= htmlspecialchars($i['name']) ?></td>
        <td><?= $i['quantity'] ?></td>
        <td>₩<?= number_format($i['price']) ?></td>
      </tr>
      <?php endwhile; ?>
    </tbody>
  </table>

  <a href="mypage.php#orders" class="btn btn-secondary">← 돌아가기</a>
</div>

<?php include "php/footer.php"; ?>
</body>
</html>