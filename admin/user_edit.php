<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] != 0) {
    header("Location: ../index.php");
    exit;
}
include "../php/db.php";

$user_id = intval($_GET['id']);
$user = $conn->query("SELECT * FROM users WHERE id=$user_id")->fetch_assoc();
if(!$user) { die("회원이 존재하지 않습니다."); }

// 등급 목록
$levels = $conn->query("SELECT * FROM membership_levels ORDER BY min_spent ASC");

// 페이지네이션 설정
$ordersPerPage = 5; $reviewsPerPage = 5; $couponsPerPage = 5;
$orderPage = max(1, intval($_GET['order_page'] ?? 1));
$reviewPage = max(1, intval($_GET['review_page'] ?? 1));
$couponPage = max(1, intval($_GET['coupon_page'] ?? 1));
$orderOffset = ($orderPage-1)*$ordersPerPage;
$reviewOffset = ($reviewPage-1)*$reviewsPerPage;
$couponOffset = ($couponPage-1)*$couponsPerPage;

// 주문내역
$totalOrders = $conn->query("SELECT COUNT(*) as cnt FROM orders WHERE user_id=$user_id")->fetch_assoc()['cnt'];
$orders = $conn->query("SELECT * FROM orders WHERE user_id=$user_id ORDER BY created_at DESC LIMIT $ordersPerPage OFFSET $orderOffset");

// 쿠폰내역
$totalCoupons = $conn->query("SELECT COUNT(*) as cnt FROM user_coupons WHERE user_id=$user_id")->fetch_assoc()['cnt'];
$coupons = $conn->query("SELECT uc.*, c.code, c.discount_type, c.value, c.expire_date 
                         FROM user_coupons uc 
                         JOIN coupons c ON uc.coupon_id=c.id 
                         WHERE uc.user_id=$user_id 
                         ORDER BY uc.id DESC LIMIT $couponsPerPage OFFSET $couponOffset");

// 리뷰내역
$totalReviews = $conn->query("SELECT COUNT(*) as cnt FROM reviews WHERE user_id=$user_id")->fetch_assoc()['cnt'];
$reviews = $conn->query("SELECT r.*, p.name as product_name 
                         FROM reviews r 
                         JOIN products p ON r.product_id=p.id 
                         WHERE r.user_id=$user_id 
                         ORDER BY r.created_at DESC LIMIT $reviewsPerPage OFFSET $reviewOffset");

// 총 구매금액
$totalSpent = $conn->query("SELECT SUM(final_price) as total FROM orders WHERE user_id=$user_id")->fetch_assoc()['total'] ?? 0;

// 회원 정보 수정
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['update_user'])){
    $stmt = $conn->prepare("UPDATE users SET name=?, email=?, phone=?, membership_level=?, points=? WHERE id=?");
    $stmt->bind_param("ssssii", $_POST['name'], $_POST['email'], $_POST['phone'], $_POST['membership_level'], intval($_POST['points']), $user_id);
    $stmt->execute(); $stmt->close();
    header("Location: user_edit.php?id=$user_id"); exit;
}

// 쿠폰 지급
if(isset($_POST['give_coupon'])){
    $stmt = $conn->prepare("INSERT INTO user_coupons (user_id, coupon_id, is_used) VALUES (?,?,0)");
    $stmt->bind_param("ii", $user_id, intval($_POST['coupon_id']));
    $stmt->execute(); $stmt->close();
    header("Location: user_edit.php?id=$user_id&tab=coupons"); exit;
}

// 쿠폰 삭제
if(isset($_GET['delete_coupon'])){
    $stmt = $conn->prepare("DELETE FROM user_coupons WHERE id=? AND user_id=?");
    $stmt->bind_param("ii", intval($_GET['delete_coupon']), $user_id);
    $stmt->execute(); $stmt->close();
    header("Location: user_edit.php?id=$user_id&tab=coupons"); exit;
}

// 지급 가능한 쿠폰 목록
$allCoupons = $conn->query("SELECT * FROM coupons ORDER BY id DESC");
?>
<!DOCTYPE html>
<html lang="ko">
<head>
  <meta charset="UTF-8">
  <title>회원 수정 - 루미노아 관리자</title>
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
      <h1><i class="fa-solid fa-user-gear"></i> 회원 정보 수정 (#<?= $user['id'] ?>)</h1>
      <span class="dark-toggle" onclick="toggleDarkMode()"><i class="fa-solid fa-moon"></i></span>
    </div>

    <!-- 회원 정보 수정 -->
    <div class="card p-3 mb-4">
      <form method="post">
        <input type="hidden" name="update_user" value="1">
        <div class="row mb-3">
          <div class="col-md-4"><label>이름</label>
            <input type="text" name="name" value="<?= htmlspecialchars($user['name']) ?>" class="form-control"></div>
          <div class="col-md-4"><label>이메일</label>
            <input type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" class="form-control"></div>
          <div class="col-md-4"><label>전화번호</label>
            <input type="text" name="phone" value="<?= htmlspecialchars($user['phone']) ?>" class="form-control"></div>
        </div>
        <div class="row mb-3">
          <div class="col-md-4"><label>등급</label>
            <select name="membership_level" class="form-select">
              <?php while($lv=$levels->fetch_assoc()): ?>
                <option value="<?= $lv['name'] ?>" <?= $user['membership_level']==$lv['name']?"selected":"" ?>>
                  <?= htmlspecialchars($lv['name']) ?>
                </option>
              <?php endwhile; ?>
            </select></div>
          <div class="col-md-4"><label>포인트</label>
            <input type="number" name="points" value="<?= $user['points'] ?>" class="form-control"></div>
          <div class="col-md-4"><label>총 구매금액</label>
            <input type="text" class="form-control" value="₩<?= number_format($totalSpent) ?>" disabled></div>
        </div>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save"></i> 수정 완료</button>
      </form>
    </div>

    <!-- 탭 -->
    <ul class="nav nav-tabs">
      <li class="nav-item"><a class="nav-link <?= !isset($_GET['tab'])||$_GET['tab']=='orders'?'active':'' ?>" href="?id=<?= $user_id ?>&tab=orders">주문내역</a></li>
      <li class="nav-item"><a class="nav-link <?= isset($_GET['tab'])&&$_GET['tab']=='coupons'?'active':'' ?>" href="?id=<?= $user_id ?>&tab=coupons">쿠폰</a></li>
      <li class="nav-item"><a class="nav-link <?= isset($_GET['tab'])&&$_GET['tab']=='reviews'?'active':'' ?>" href="?id=<?= $user_id ?>&tab=reviews">리뷰</a></li>
    </ul>

    <div class="tab-content p-3 border border-top-0">
      <!-- 주문 내역 -->
      <?php if(!isset($_GET['tab']) || $_GET['tab']=='orders'): ?>
        <div class="card p-3">
          <?php if($totalOrders>0): ?>
            <table class="table table-hover">
              <tr><th>ID</th><th>최종 결제금액</th><th>주문일</th><th>상태</th></tr>
              <?php while($o=$orders->fetch_assoc()): ?>
                <tr>
                  <td>#<?= $o['id'] ?></td>
                  <td>₩<?= number_format($o['final_price']) ?></td>
                  <td><?= $o['created_at'] ?></td>
                  <td><?= htmlspecialchars($o['status']) ?></td>
                </tr>
              <?php endwhile; ?>
            </table>
            <nav><ul class="pagination">
              <?php for($i=1;$i<=ceil($totalOrders/$ordersPerPage);$i++): ?>
                <li class="page-item <?= $i==$orderPage?'active':'' ?>">
                  <a class="page-link" href="?id=<?= $user_id ?>&tab=orders&order_page=<?= $i ?>"><?= $i ?></a>
                </li>
              <?php endfor; ?>
            </ul></nav>
          <?php else: ?><p>주문 내역 없음</p><?php endif; ?>
        </div>
      <?php endif; ?>

      <!-- 쿠폰 -->
      <?php if(isset($_GET['tab']) && $_GET['tab']=='coupons'): ?>
        <div class="card p-3">
          <form method="post" class="mb-3 d-flex gap-2">
            <select name="coupon_id" class="form-select">
              <?php while($c=$allCoupons->fetch_assoc()): ?>
                <option value="<?= $c['id'] ?>">[<?= $c['code'] ?>] <?= $c['discount_type']=='percent'?$c['value']."%":"₩".$c['value'] ?></option>
              <?php endwhile; ?>
            </select>
            <button type="submit" name="give_coupon" class="btn btn-success"><i class="fa-solid fa-gift"></i> 쿠폰 지급</button>
          </form>
          <?php if($totalCoupons>0): ?>
            <table class="table table-hover">
              <tr><th>쿠폰</th><th>혜택</th><th>만료일</th><th>사용 여부</th><th>관리</th></tr>
              <?php while($c=$coupons->fetch_assoc()): ?>
                <tr>
                  <td><?= htmlspecialchars($c['code']) ?></td>
                  <td><?= $c['discount_type']=='percent'?$c['value']."%":"₩".$c['value'] ?></td>
                  <td><?= $c['expire_date'] ?: "제한 없음" ?></td>
                  <td><?= $c['is_used']?"사용됨":"사용 가능" ?></td>
                  <td>
                    <a href="?id=<?= $user_id ?>&tab=coupons&delete_coupon=<?= $c['id'] ?>" 
                       class="btn btn-sm btn-danger"
                       onclick="return confirm('정말 삭제하시겠습니까?');">
                       <i class="fa-solid fa-trash"></i> 삭제</a>
                  </td>
                </tr>
              <?php endwhile; ?>
            </table>
            <nav><ul class="pagination">
              <?php for($i=1;$i<=ceil($totalCoupons/$couponsPerPage);$i++): ?>
                <li class="page-item <?= $i==$couponPage?'active':'' ?>">
                  <a class="page-link" href="?id=<?= $user_id ?>&tab=coupons&coupon_page=<?= $i ?>"><?= $i ?></a>
                </li>
              <?php endfor; ?>
            </ul></nav>
          <?php else: ?><p>보유 쿠폰 없음</p><?php endif; ?>
        </div>
      <?php endif; ?>

      <!-- 리뷰 -->
      <?php if(isset($_GET['tab']) && $_GET['tab']=='reviews'): ?>
        <div class="card p-3">
          <?php if($totalReviews>0): ?>
            <?php while($r=$reviews->fetch_assoc()): ?>
              <div class="border-bottom py-2">
                <strong><?= htmlspecialchars($r['product_name']) ?></strong> ★<?= $r['rating'] ?><br>
                <?= nl2br(htmlspecialchars($r['content'])) ?>
                <div class="text-muted small"><?= $r['created_at'] ?></div>
              </div>
            <?php endwhile; ?>
            <nav><ul class="pagination">
              <?php for($i=1;$i<=ceil($totalReviews/$reviewsPerPage);$i++): ?>
                <li class="page-item <?= $i==$reviewPage?'active':'' ?>">
                  <a class="page-link" href="?id=<?= $user_id ?>&tab=reviews&review_page=<?= $i ?>"><?= $i ?></a>
                </li>
              <?php endfor; ?>
            </ul></nav>
          <?php else: ?><p>작성한 리뷰 없음</p><?php endif; ?>
        </div>
      <?php endif; ?>
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