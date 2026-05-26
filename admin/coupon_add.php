<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] != 0) {
    header("Location: ../index.php");
    exit;
}
include "../php/db.php";

if($_SERVER['REQUEST_METHOD'] === 'POST'){
    $code = trim($_POST['code']);
    $type = $_POST['discount_type'];
    $value = (int)$_POST['value'];
    $min_order = (int)$_POST['min_order'];
    $max_use = (int)$_POST['max_use'];

    // ✅ 만료일 처리
    $expire_option = $_POST['expire_option'] ?? 'none';
    $expire_date = null;

    if ($expire_option === '1m') {
        $expire_date = date("Y-m-d", strtotime("+1 month"));
    } elseif ($expire_option === '3m') {
        $expire_date = date("Y-m-d", strtotime("+3 months"));
    } elseif ($expire_option === '6m') {
        $expire_date = date("Y-m-d", strtotime("+6 months"));
    } elseif ($expire_option === '12m') {
        $expire_date = date("Y-m-d", strtotime("+12 months"));
    } elseif ($expire_option === 'custom' && !empty($_POST['expire_date_custom'])) {
        $expire_date = $_POST['expire_date_custom'];
    }
    // 'none' 선택 시 → null (기한 없음)

    $stmt = $conn->prepare("INSERT INTO coupons 
      (code, discount_type, value, min_order, expire_date, max_use, used_count) 
      VALUES (?,?,?,?,?,?,0)");
    $stmt->bind_param("ssiisi", $code, $type, $value, $min_order, $expire_date, $max_use);
    $stmt->execute();
    $stmt->close();

    header("Location: coupons.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
  <meta charset="UTF-8">
  <title>쿠폰 추가 - 루미노아 관리자</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <!-- Bootstrap -->
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
      <h1><i class="fa-solid fa-plus"></i> 쿠폰 추가</h1>
      <span class="dark-toggle" onclick="toggleDarkMode()"><i class="fa-solid fa-moon"></i></span>
    </div>

    <div class="card p-4" style="max-width:600px; margin:auto;">
      <form method="post" class="row g-3">
        <!-- 쿠폰 코드 -->
        <div class="col-12">
          <label class="form-label">쿠폰 코드</label>
          <input type="text" name="code" class="form-control" required>
        </div>

        <!-- 할인 타입 -->
        <div class="col-md-6">
          <label class="form-label">할인 타입</label>
          <select name="discount_type" class="form-select">
            <option value="percent">퍼센트(%)</option>
            <option value="fixed">고정금액</option>
          </select>
        </div>

        <!-- 할인 값 -->
        <div class="col-md-6">
          <label class="form-label">할인 값</label>
          <input type="number" name="value" class="form-control" required>
        </div>

        <!-- 최소 주문 금액 -->
        <div class="col-md-6">
          <label class="form-label">최소 주문 금액</label>
          <input type="number" name="min_order" class="form-control" value="0">
        </div>

        <!-- 최대 사용 횟수 -->
        <div class="col-md-6">
          <label class="form-label">최대 사용 횟수</label>
          <input type="number" name="max_use" class="form-control" value="1">
        </div>

        <!-- 만료일 옵션 -->
        <div class="col-12">
          <label class="form-label">만료일 설정</label>
          <select name="expire_option" class="form-select" onchange="toggleCustomDate(this.value)">
            <option value="none">기한 없음</option>
            <option value="1m">1개월</option>
            <option value="3m">3개월</option>
            <option value="6m">6개월</option>
            <option value="12m">12개월</option>
            <option value="custom">직접 입력</option>
          </select>
        </div>

        <!-- 만료일 직접 입력 -->
        <div class="col-12" id="customDateBox" style="display:none;">
          <label class="form-label">만료일 직접 입력</label>
          <input type="date" name="expire_date_custom" class="form-control">
        </div>

        <!-- 버튼 -->
        <div class="col-12">
          <button type="submit" class="btn btn-success w-100">
            <i class="fa-solid fa-plus"></i> 쿠폰 추가
          </button>
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
function toggleCustomDate(value){
  document.getElementById("customDateBox").style.display = (value === "custom") ? "block" : "none";
}
</script>
</body>
</html>