<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] != 0) {
    header("Location: ../index.php");
    exit;
}
include "../php/db.php";

// 등급 목록
$levels = $conn->query("SELECT * FROM membership_levels ORDER BY min_spent ASC");

// 검색 & 필터
$level_filter = $_GET['level'] ?? '';
$keyword = trim($_GET['keyword'] ?? '');

$where = "WHERE 1=1";
$params = [];
$types = "";

if ($level_filter) {
    $where .= " AND membership_level=?";
    $params[] = $level_filter;
    $types .= "s";
}
if ($keyword) {
    $where .= " AND (name LIKE CONCAT('%',?,'%') OR email LIKE CONCAT('%',?,'%') OR phone LIKE CONCAT('%',?,'%'))";
    $params[] = $keyword;
    $params[] = $keyword;
    $params[] = $keyword;
    $types .= "sss";
}

$sql = "SELECT * FROM users $where ORDER BY created_at DESC";
$stmt = $conn->prepare($sql);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$users = $stmt->get_result();
$stmt->close();
?>
<!DOCTYPE html>
<html lang="ko">
<head>
  <meta charset="UTF-8">
  <title>회원 관리 - 루미노아 관리자</title>
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
      <h1><i class="fa-solid fa-users"></i> 회원 관리</h1>
      <span class="dark-toggle" onclick="toggleDarkMode()"><i class="fa-solid fa-moon"></i></span>
    </div>

    <!-- 검색 & 필터 -->
    <div class="card p-3 mb-3">
      <form method="get" class="row g-2">
        <div class="col-md-3 col-12">
          <select name="level" class="form-select" onchange="this.form.submit()">
            <option value="">전체 등급</option>
            <?php 
            $levels->data_seek(0);
            while($lv = $levels->fetch_assoc()): ?>
              <option value="<?= htmlspecialchars($lv['name']) ?>" <?= $level_filter==$lv['name']?"selected":"" ?>>
                <?= htmlspecialchars($lv['name']) ?>
              </option>
            <?php endwhile; ?>
          </select>
        </div>
        <div class="col-md-5 col-12">
          <input type="text" name="keyword" value="<?= htmlspecialchars($keyword) ?>" class="form-control" placeholder="이름 / 이메일 / 전화번호 검색">
        </div>
        <div class="col-md-2 col-12">
          <button class="btn btn-primary w-100"><i class="fa-solid fa-search"></i> 검색</button>
        </div>
      </form>
    </div>

    <!-- 회원 테이블 -->
    <div class="card p-3 table-responsive">
      <table class="table table-hover align-middle text-center">
        <thead style="background:var(--navy);color:#fff;">
          <tr>
            <th>ID</th>
            <th>이름</th>
            <th>이메일</th>
            <th>전화번호</th>
            <th>등급</th>
            <th>포인트</th>
            <th>가입일</th>
            <th>관리</th>
          </tr>
        </thead>
        <tbody>
          <?php while($u = $users->fetch_assoc()): ?>
            <tr>
              <td><?= $u['id'] ?></td>
              <td><?= htmlspecialchars($u['name']) ?></td>
              <td><?= htmlspecialchars($u['email']) ?></td>
              <td><?= htmlspecialchars($u['phone']) ?></td>
              <td>
                <?php if ($u['membership_level']): ?>
                  <?php
                  $badge = $conn->query("SELECT badge_color FROM membership_levels WHERE name='" . $conn->real_escape_string($u['membership_level']) . "'")->fetch_assoc();
                  $color = $badge['badge_color'] ?? '#999';
                  ?>
                  <span class="badge" style="background:<?= $color ?>;"><?= htmlspecialchars($u['membership_level']) ?></span>
                <?php else: ?>
                  -
                <?php endif; ?>
              </td>
              <td><?= number_format($u['points']) ?></td>
              <td><?= $u['created_at'] ?></td>
              <td>
                <a href="user_edit.php?id=<?= $u['id'] ?>" class="btn btn-sm btn-primary">
                  <i class="fa-solid fa-user-gear"></i> 관리
                </a>
              </td>
            </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
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