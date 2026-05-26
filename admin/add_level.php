<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] != 0) {
    header("Location: ../index.php");
    exit;
}
include "../php/db.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'];
    $min_spent = (int)$_POST['min_spent'];
    $point_rate = (int)$_POST['point_rate'];
    $benefits = $_POST['benefits'];
    $badge_color = $_POST['badge_color'];

    $stmt = $conn->prepare("INSERT INTO membership_levels (name, min_spent, point_rate, benefits, badge_color) VALUES (?,?,?,?,?)");
    $stmt->bind_param("siiss", $name, $min_spent, $point_rate, $benefits, $badge_color);
    $stmt->execute();
    $stmt->close();

    header("Location: levels.php"); exit;
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
  <meta charset="UTF-8">
  <title>등급 추가 - 루미노아 관리자</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="../css/admin.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
  <div class="overlay" onclick="toggleSidebar()"></div>
  <?php include "sidebar.php"; ?>

  <div class="admin-content">
    <div class="top-bar">
      <button class="sidebar-toggle" onclick="toggleSidebar()">☰</button>
      <h1><i class="fa-solid fa-medal"></i> 새 등급 추가</h1>
      <span class="dark-toggle" onclick="toggleDarkMode()"><i class="fa-solid fa-moon"></i></span>
    </div>

    <div class="card" style="max-width:600px; margin:20px auto;">
      <form method="post">
        <div class="mb-3">
          <label class="form-label">등급명 <span style="color:red">*</span></label>
          <input type="text" name="name" class="form-control" placeholder="예: VIP, GOLD" required>
        </div>
        <div class="mb-3">
          <label class="form-label">최소 구매액 <span style="color:red">*</span></label>
          <input type="number" name="min_spent" class="form-control" placeholder="예: 500000" required>
        </div>
        <div class="mb-3">
          <label class="form-label">포인트 적립률 (%) <span style="color:red">*</span></label>
          <input type="number" name="point_rate" class="form-control" placeholder="예: 5" required>
        </div>
        <div class="mb-3">
          <label class="form-label">혜택</label>
          <textarea name="benefits" class="form-control" rows="3" placeholder="예: 무료배송, 전용 쿠폰 제공"></textarea>
        </div>
        <div class="mb-3">
          <label class="form-label">뱃지 색상</label>
          <input type="color" name="badge_color" class="form-control form-control-color" value="#D4AF37">
        </div>
        <div class="d-flex justify-content-between">
          <a href="levels.php" class="btn btn-secondary"><i class="fa-solid fa-arrow-left"></i> 취소</a>
          <button type="submit" class="btn" style="background:var(--gold);color:#fff;"><i class="fa-solid fa-save"></i> 저장</button>
        </div>
      </form>
    </div>
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