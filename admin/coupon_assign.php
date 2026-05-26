<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] != 0) {
    header("Location: ../index.php");
    exit;
}
include "../php/db.php";

// 쿠폰 목록
$coupons = $conn->query("SELECT * FROM coupons ORDER BY id DESC");
// 유저 목록
$users = $conn->query("SELECT id, name, email FROM users ORDER BY id DESC");
// 멤버십 레벨
$levels = $conn->query("SELECT name FROM membership_levels ORDER BY min_spent ASC");

$msg = "";

if($_SERVER['REQUEST_METHOD'] === 'POST'){
    $coupon_id = intval($_POST['coupon_id']);
    $target = $_POST['target'];
    
    if($target === "all"){
        $stmt = $conn->prepare("INSERT INTO user_coupons (user_id, coupon_id) SELECT id, ? FROM users");
        $stmt->bind_param("i", $coupon_id);
        $stmt->execute(); $stmt->close();
        $msg = "✅ 전체 유저에게 쿠폰이 발급되었습니다.";

    } elseif($target === "registered"){
        $stmt = $conn->prepare("INSERT INTO user_coupons (user_id, coupon_id) SELECT DISTINCT user_id, ? FROM orders");
        $stmt->bind_param("i", $coupon_id);
        $stmt->execute(); $stmt->close();
        $msg = "✅ 주문 이력이 있는 유저들에게 쿠폰이 발급되었습니다.";

    } elseif(strpos($target, "level:") === 0){
        $level = substr($target, 6);
        $stmt = $conn->prepare("INSERT INTO user_coupons (user_id, coupon_id) SELECT id, ? FROM users WHERE membership_level=?");
        $stmt->bind_param("is", $coupon_id, $level);
        $stmt->execute(); $stmt->close();
        $msg = "✅ '{$level}' 등급 유저들에게 쿠폰이 발급되었습니다.";

    } elseif($target === "single" && !empty($_POST['user_id'])){
        $user_id = intval($_POST['user_id']);
        $stmt = $conn->prepare("INSERT INTO user_coupons (user_id, coupon_id) VALUES (?,?)");
        $stmt->bind_param("ii", $user_id, $coupon_id);
        $stmt->execute(); $stmt->close();
        $msg = "✅ 선택한 유저에게 쿠폰이 발급되었습니다.";
    }
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
  <meta charset="UTF-8">
  <title>쿠폰 발급 - 루미노아 관리자</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <!-- Bootstrap -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

  <!-- 관리자 CSS -->
  <link rel="stylesheet" href="../css/admin.css">

  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
  <div class="overlay" onclick="toggleSidebar()"></div>
  <?php include "sidebar.php"; ?>

  <div class="admin-content">
    <div class="top-bar">
      <button class="sidebar-toggle" onclick="toggleSidebar()">☰</button>
      <h1><i class="fa-solid fa-envelope"></i> 쿠폰 발급</h1>
      <span class="dark-toggle" onclick="toggleDarkMode()"><i class="fa-solid fa-moon"></i></span>
    </div>

    <div class="card p-4" style="max-width:600px; margin:auto;">
      <?php if($msg): ?>
        <div class="alert alert-info"><?= $msg ?></div>
      <?php endif; ?>

      <form method="post">
        <!-- 쿠폰 선택 -->
        <div class="mb-3">
          <label class="form-label">쿠폰 선택</label>
          <select name="coupon_id" class="form-select" required>
            <option value="">쿠폰을 선택하세요</option>
            <?php while($c = $coupons->fetch_assoc()): ?>
              <option value="<?= $c['id'] ?>">
                <?= htmlspecialchars($c['code']) ?> (<?= $c['discount_type']=="percent" ? $c['value']."%" : "₩".number_format($c['value']) ?>)
              </option>
            <?php endwhile; ?>
          </select>
        </div>

        <!-- 발급 대상 -->
        <div class="mb-3">
          <label class="form-label">발급 대상</label>
          <select name="target" class="form-select" onchange="toggleUserSelect(this.value)" required>
            <option value="all">전체 유저</option>
            <option value="registered">주문 이력 있는 유저</option>
            <?php while($l = $levels->fetch_assoc()): ?>
              <option value="level:<?= $l['name'] ?>"><?= htmlspecialchars($l['name']) ?> 등급</option>
            <?php endwhile; ?>
            <option value="single">특정 유저</option>
          </select>
        </div>

        <!-- 특정 유저 선택 -->
        <div id="userSelectBox" style="display:none;">
          <label class="form-label">특정 유저 선택</label>
          <select name="user_id" class="form-select mb-3">
            <?php while($u = $users->fetch_assoc()): ?>
              <option value="<?= $u['id'] ?>">
                <?= htmlspecialchars($u['name']) ?> (<?= $u['email'] ?>)
              </option>
            <?php endwhile; ?>
          </select>
        </div>

        <button type="submit" class="btn btn-primary w-100">
          <i class="fa-solid fa-paper-plane"></i> 발급하기
        </button>
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
function toggleUserSelect(val){
  document.getElementById('userSelectBox').style.display = (val === "single") ? "block" : "none";
}
</script>
</body>
</html>