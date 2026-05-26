<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] != 0) {
    header("Location: ../index.php");
    exit;
}
include "../php/db.php";

// ================== 상태 변경 처리 ==================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'], $_POST['status'])) {
    $id = intval($_POST['id']);
    $status = $_POST['status'];

    $stmt = $conn->prepare("UPDATE orders SET status=? WHERE id=?");
    $stmt->bind_param("si", $status, $id);
    $stmt->execute();
    $stmt->close();

    // 새로고침 (현재 페이지 유지 + 검색 조건 유지)
    $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
    $queryString = $_SERVER['QUERY_STRING'] ? "&" . $_SERVER['QUERY_STRING'] : "";
    header("Location: orders.php?page=$page$queryString");
    exit;
}

// ================== 검색 / 필터 ==================
$where = "WHERE 1=1";
$params = [];
$types = "";

if (!empty($_GET['user_id'])) {
    $where .= " AND o.user_id = ?";
    $params[] = intval($_GET['user_id']);
    $types .= "i";
}
if (!empty($_GET['status'])) {
    $where .= " AND o.status = ?";
    $params[] = $_GET['status'];
    $types .= "s";
}

// ================== 페이지네이션 ==================
$perPage = 10;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $perPage;

// 전체 주문 수
$countSql = "SELECT COUNT(*) as cnt FROM orders o $where";
$stmt = $conn->prepare($countSql);
if ($params) $stmt->bind_param($types, ...$params);
$stmt->execute();
$totalOrders = $stmt->get_result()->fetch_assoc()['cnt'];
$stmt->close();

$totalPages = ceil($totalOrders / $perPage);

// 주문 목록 + 주문 상품 join
$sql = "SELECT o.*, 
        GROUP_CONCAT(CONCAT(p.name, ' (', oi.option_name, ') x', oi.quantity) SEPARATOR '<br>') as items
        FROM orders o
        LEFT JOIN order_items oi ON o.id = oi.order_id
        LEFT JOIN products p ON oi.product_id = p.id
        $where
        GROUP BY o.id
        ORDER BY o.created_at DESC
        LIMIT ?, ?";
$stmt = $conn->prepare($sql);

if ($params) {
    $bindTypes = $types . "ii";
    $params[] = $offset;
    $params[] = $perPage;
    $stmt->bind_param($bindTypes, ...$params);
} else {
    $stmt->bind_param("ii", $offset, $perPage);
}

$stmt->execute();
$orders = $stmt->get_result();

// 상태 뱃지 CSS 클래스 반환 함수
function getStatusBadgeClass($status) {
    switch ($status) {
        case "대기": return "badge bg-secondary";
        case "처리중": return "badge bg-info";
        case "배송중": return "badge bg-warning text-dark";
        case "완료": return "badge bg-success";
        case "취소": return "badge bg-danger";
        default: return "badge bg-secondary";
    }
}
$statuses = ["대기","처리중","배송중","완료","취소"];
?>
<!DOCTYPE html>
<html lang="ko">
<head>
  <meta charset="UTF-8">
  <title>주문 관리 - 루미노아 관리자</title>
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
      <h1><i class="fa-solid fa-receipt"></i> 주문 관리</h1>
      <span class="dark-toggle" onclick="toggleDarkMode()"><i class="fa-solid fa-moon"></i></span>
    </div>

    <!-- 검색/필터 -->
    <form method="get" class="row g-2 mb-3">
      <div class="col-md-3 col-6">
        <input type="text" name="user_id" value="<?= htmlspecialchars($_GET['user_id'] ?? '') ?>" class="form-control" placeholder="회원 ID 검색">
      </div>
      <div class="col-md-3 col-6">
        <select name="status" class="form-select">
          <option value="">전체 상태</option>
          <?php foreach($statuses as $s): ?>
            <option value="<?= $s ?>" <?= (($_GET['status']??'')==$s)?'selected':'' ?>><?= $s ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2 col-12">
        <button class="btn btn-primary w-100"><i class="fa-solid fa-search"></i> 검색</button>
      </div>
    </form>

    <div class="card p-3 table-responsive">
      <table class="table table-hover align-middle text-center">
        <thead style="background:var(--navy);color:#fff;">
          <tr>
            <th>ID</th>
            <th>회원ID</th>
            <th>상품명</th>
            <th>총액</th>
            <th>할인</th>
            <th>포인트 사용</th>
            <th>최종 금액</th>
            <th>상태</th>
            <th>주문일</th>
            <th>상세</th>
          </tr>
        </thead>
        <tbody>
          <?php while($o = $orders->fetch_assoc()): ?>
          <tr>
            <td>#<?= $o['id'] ?></td>
            <td><?= $o['user_id'] ?></td>
            <td class="text-start"><?= $o['items'] ?: '-' ?></td>
            <td>₩<?= number_format($o['total_price']) ?></td>
            <td>-₩<?= number_format($o['discount']) ?></td>
            <td>-₩<?= number_format($o['used_points']) ?></td>
            <td class="fw-bold text-success">₩<?= number_format($o['final_price']) ?></td>
            <td>
              <form method="post" class="d-flex align-items-center gap-1 justify-content-center">
                <span class="<?= getStatusBadgeClass($o['status']) ?>"><?= htmlspecialchars($o['status']) ?></span>
                <input type="hidden" name="id" value="<?= $o['id'] ?>">
                <input type="hidden" name="page" value="<?= $page ?>">
                <select name="status" class="form-select form-select-sm" onchange="this.form.submit()" style="width:90px;">
                  <?php foreach($statuses as $s): ?>
                    <option value="<?= $s ?>" <?= ($o['status'] == $s ? "selected" : "") ?>><?= $s ?></option>
                  <?php endforeach; ?>
                </select>
              </form>
            </td>
            <td><?= $o['created_at'] ?></td>
            <td>
              <a href="order_view.php?id=<?= $o['id'] ?>" class="btn btn-sm btn-info">
                <i class="fa-solid fa-eye"></i>
              </a>
            </td>
          </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </div>

    <!-- 페이지네이션 -->
    <nav class="mt-3">
      <ul class="pagination justify-content-center">
        <?php for($i=1; $i <= $totalPages; $i++): 
          $qs = $_GET; $qs['page']=$i; $link = http_build_query($qs); ?>
          <li class="page-item <?= ($i==$page)?'active':'' ?>">
            <a class="page-link" href="?<?= $link ?>"><?= $i ?></a>
          </li>
        <?php endfor; ?>
      </ul>
    </nav>
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