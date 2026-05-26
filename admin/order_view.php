<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] != 0) {
    header("Location: ../index.php");
    exit;
}
include "../php/db.php";

$order_id = intval($_GET['id'] ?? 0);

// ================== 상태 변경 처리 ==================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['status'])) {
    $status = $_POST['status'];
    $stmt = $conn->prepare("UPDATE orders SET status=? WHERE id=?");
    $stmt->bind_param("si", $status, $order_id);
    $stmt->execute();
    $stmt->close();

    header("Location: order_view.php?id=$order_id");
    exit;
}

// ================== 주문 기본 정보 + 유저 정보 ==================
$stmt = $conn->prepare("
  SELECT o.*, u.name as user_name, u.email 
  FROM orders o 
  JOIN users u ON o.user_id=u.id 
  WHERE o.id=?
");
$stmt->bind_param("i", $order_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) {
    die("존재하지 않는 주문입니다.");
}

// ================== 주문 상품 ==================
$stmt = $conn->prepare("
  SELECT oi.*, p.name 
  FROM order_items oi 
  JOIN products p ON oi.product_id=p.id 
  WHERE oi.order_id=?
");
$stmt->bind_param("i", $order_id);
$stmt->execute();
$items = $stmt->get_result();
$stmt->close();

// 상태 목록
$statuses = ["대기","처리중","배송중","완료","취소"];
?>
<!DOCTYPE html>
<html lang="ko">
<head>
  <meta charset="UTF-8">
  <title>주문 상세보기 - 루미노아 관리자</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <!-- Bootstrap 먼저 -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

  <!-- 관리자 전용 CSS -->
  <link rel="stylesheet" href="../css/admin.css">

  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
  <div class="overlay" onclick="toggleSidebar()"></div>
  <?php include "sidebar.php"; ?>

  <div class="admin-content">
    <div class="top-bar">
      <button class="sidebar-toggle" onclick="toggleSidebar()">☰</button>
      <h1><i class="fa-solid fa-file-invoice"></i> 주문 상세 (#<?= $order['id'] ?>)</h1>
      <span class="dark-toggle" onclick="toggleDarkMode()"><i class="fa-solid fa-moon"></i></span>
    </div>

    <!-- 회원 & 주문 기본 정보 -->
    <div class="card p-3 mb-4">
      <p><strong>회원:</strong> <?= htmlspecialchars($order['user_name']) ?> (<?= htmlspecialchars($order['email']) ?>)</p>
      <p><strong>주문일:</strong> <?= $order['created_at'] ?></p>
      
      <form method="post" class="d-flex align-items-center gap-2">
        <label class="fw-bold">상태:</label>
        <select name="status" class="form-select w-auto">
          <?php foreach($statuses as $s): ?>
            <option value="<?= $s ?>" <?= ($order['status'] == $s ? "selected" : "") ?>><?= $s ?></option>
          <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-sm btn-primary">
          <i class="fa-solid fa-save"></i> 저장
        </button>
      </form>
    </div>

    <!-- 배송지 정보 -->
    <div class="card p-3 mb-4">
      <h5><i class="fa-solid fa-truck"></i> 배송지 정보</h5>
      <p><strong>받는 사람:</strong> <?= htmlspecialchars($order['recipient'] ?? '-') ?></p>
      <p><strong>전화번호:</strong> <?= htmlspecialchars($order['phone'] ?? '-') ?></p>
      <p><strong>주소:</strong> 
        [<?= htmlspecialchars($order['postal_code'] ?? '-') ?>] 
        <?= htmlspecialchars($order['address_line1'] ?? '') ?> 
        <?= htmlspecialchars($order['address_line2'] ?? '') ?>
      </p>
    </div>

    <!-- 상품 내역 -->
    <div class="card p-3 mb-4 table-responsive">
      <h5><i class="fa-solid fa-box"></i> 상품 내역</h5>
      <table class="table table-hover align-middle text-center">
        <thead style="background:var(--navy);color:#fff;">
          <tr><th>상품명</th><th>옵션</th><th>수량</th><th>가격</th></tr>
        </thead>
        <tbody>
          <?php while($it = $items->fetch_assoc()): ?>
            <tr>
              <td><?= htmlspecialchars($it['name']) ?></td>
              <td><?= htmlspecialchars($it['option_name']) ?></td>
              <td><?= $it['quantity'] ?></td>
              <td>₩<?= number_format(($it['price'] + $it['option_price']) * $it['quantity']) ?></td>
            </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </div>

    <!-- 결제 요약 -->
    <div class="card p-3 mb-4">
      <h5><i class="fa-solid fa-credit-card"></i> 결제 요약</h5>
      <p>총액: ₩<?= number_format($order['total_price']) ?></p>
      <p>할인: -₩<?= number_format($order['discount']) ?></p>
      <p>포인트 사용: -₩<?= number_format($order['used_points']) ?></p>
      <p class="text-success fw-bold">최종 결제: ₩<?= number_format($order['final_price']) ?></p>
    </div>

    <a href="orders.php" class="btn btn-secondary">
      <i class="fa-solid fa-arrow-left"></i> 돌아가기
    </a>
  </div>

<script>
function toggleSidebar(){
  document.querySelector(".sidebar").classList.toggle("active");
  document.querySelector(".overlay").classList.toggle("active");
}
function toggleDarkMode(){
  document.body.classList.toggle("dark");
}
</script>
</body>
</html>