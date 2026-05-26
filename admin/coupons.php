<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] != 0) {
    header("Location: ../index.php");
    exit;
}
include "../php/db.php";

// ================= 검색 & 필터 =================
$keyword = trim($_GET['keyword'] ?? '');
$type_filter = $_GET['type'] ?? '';
$expire_filter = $_GET['expire'] ?? '';

// ================= 페이지네이션 =================
$perPage = 10;
$page = max(1, intval($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

$where = "WHERE 1=1";
$params = [];
$types = "";

// 코드 검색
if ($keyword) {
    $where .= " AND code LIKE CONCAT('%',?,'%')";
    $params[] = $keyword;
    $types .= "s";
}

// 타입 필터
if ($type_filter) {
    $where .= " AND discount_type=?";
    $params[] = $type_filter;
    $types .= "s";
}

// 만료 상태 필터
if ($expire_filter == "valid") {
    $where .= " AND (expire_date IS NULL OR expire_date >= CURDATE())";
} elseif ($expire_filter == "expired") {
    $where .= " AND expire_date < CURDATE()";
}

// ================= 전체 쿠폰 수 =================
$count_sql = "SELECT COUNT(*) as cnt FROM coupons $where";
$stmt = $conn->prepare($count_sql);
if ($params) { $stmt->bind_param($types, ...$params); }
$stmt->execute();
$totalCoupons = $stmt->get_result()->fetch_assoc()['cnt'];
$stmt->close();
$totalPages = ceil($totalCoupons / $perPage);

// ================= 쿠폰 목록 =================
$sql = "SELECT * FROM coupons $where ORDER BY id DESC LIMIT ? OFFSET ?";
$stmt = $conn->prepare($sql);
if ($params) {
    $bindTypes = $types . "ii";
    $params[] = $perPage;
    $params[] = $offset;
    $stmt->bind_param($bindTypes, ...$params);
} else {
    $stmt->bind_param("ii", $perPage, $offset);
}
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();
?>
<!DOCTYPE html>
<html lang="ko">
<head>
  <meta charset="UTF-8">
  <title>쿠폰 관리 - 루미노아 관리자</title>
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
      <h1><i class="fa-solid fa-ticket"></i> 쿠폰 관리</h1>
      <span class="dark-toggle" onclick="toggleDarkMode()"><i class="fa-solid fa-moon"></i></span>
    </div>

    <!-- 검색 & 필터 -->
    <div class="card p-3 mb-3">
      <form method="get" class="row g-2">
        <div class="col-md-3 col-12">
          <input type="text" name="keyword" value="<?= htmlspecialchars($keyword) ?>" class="form-control" placeholder="쿠폰 코드 검색">
        </div>
        <div class="col-md-3 col-6">
          <select name="type" class="form-select" onchange="this.form.submit()">
            <option value="">전체 타입</option>
            <option value="percent" <?= $type_filter=="percent"?"selected":"" ?>>비율</option>
            <option value="amount" <?= $type_filter=="amount"?"selected":"" ?>>금액</option>
          </select>
        </div>
        <div class="col-md-3 col-6">
          <select name="expire" class="form-select" onchange="this.form.submit()">
            <option value="">전체 상태</option>
            <option value="valid" <?= $expire_filter=="valid"?"selected":"" ?>>유효</option>
            <option value="expired" <?= $expire_filter=="expired"?"selected":"" ?>>만료</option>
          </select>
        </div>
        <div class="col-md-2 col-12">
          <button class="btn btn-primary w-100"><i class="fa-solid fa-search"></i> 검색</button>
        </div>
      </form>
    </div>

    <div class="card p-3 mb-3 d-flex gap-2 flex-wrap">
      <a href="coupon_add.php" class="btn btn-success"><i class="fa-solid fa-plus"></i> 쿠폰 추가</a>
      <a href="coupon_assign.php" class="btn btn-primary"><i class="fa-solid fa-envelope"></i> 쿠폰 발급</a>
    </div>

    <!-- 쿠폰 테이블 -->
    <div class="card p-3 table-responsive">
      <table class="table table-hover align-middle text-center">
        <thead style="background:var(--navy); color:#fff;">
          <tr>
            <th>ID</th>
            <th>코드</th>
            <th>타입</th>
            <th>값</th>
            <th>최소 주문</th>
            <th>만료일</th>
            <th>사용 횟수</th>
            <th>최대 사용</th>
            <th>관리</th>
          </tr>
        </thead>
        <tbody>
          <?php while($c = $result->fetch_assoc()): ?>
          <tr>
            <td><?= $c['id'] ?></td>
            <td><code><?= htmlspecialchars($c['code']) ?></code></td>
            <td><?= $c['discount_type']=="percent" ? "비율" : "금액" ?></td>
            <td><?= $c['discount_type']=="percent" ? $c['value']."%" : "₩".number_format($c['value']) ?></td>
            <td><?= number_format($c['min_order']) ?> 원</td>
            <td><?= $c['expire_date'] ?: "제한 없음" ?></td>
            <td><?= $c['used_count'] ?></td>
            <td><?= $c['max_use'] ?></td>
            <td class="d-flex gap-2 justify-content-center">
              <a href="coupon_edit.php?id=<?= $c['id'] ?>" class="btn btn-sm btn-warning">
                <i class="fa-solid fa-pen"></i> 수정
              </a>
              <a href="coupon_delete.php?id=<?= $c['id'] ?>" 
                 onclick="return confirm('정말 삭제하시겠습니까?')" 
                 class="btn btn-sm btn-danger">
                <i class="fa-solid fa-trash"></i> 삭제
              </a>
            </td>
          </tr>
          <?php endwhile; ?>
          <?php if($totalCoupons == 0): ?>
          <tr><td colspan="9">쿠폰이 없습니다.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- 페이지네이션 -->
    <?php if($totalPages > 1): ?>
    <nav class="mt-3">
      <ul class="pagination justify-content-center">
        <?php for($i=1; $i <= $totalPages; $i++): ?>
          <li class="page-item <?= ($i==$page)?'active':'' ?>">
            <a class="page-link" href="?page=<?= $i ?>&keyword=<?= urlencode($keyword) ?>&type=<?= $type_filter ?>&expire=<?= $expire_filter ?>"><?= $i ?></a>
          </li>
        <?php endfor; ?>
      </ul>
    </nav>
    <?php endif; ?>
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