<?php
session_start();
include "../php/db.php";
if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] != 0) {
    header("Location: ../index.php");
    exit;
}

$result = $conn->query("SELECT * FROM membership_levels ORDER BY min_spent ASC");
?>
<!DOCTYPE html>
<html lang="ko">
<head>
  <meta charset="UTF-8">
  <title>회원 등급 관리 - 루미노아 관리자</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="../css/admin.css">
</head>
<body>
  <div class="overlay" onclick="toggleSidebar()"></div>
  <?php include "sidebar.php"; ?>

  <div class="admin-content">
    <div class="top-bar">
      <button class="sidebar-toggle" onclick="toggleSidebar()">☰</button>
      <h1><i class="fa-solid fa-medal"></i> 회원 등급 관리</h1>
      <span class="dark-toggle" onclick="toggleDarkMode()"><i class="fa-solid fa-moon"></i></span>
    </div>

    <div class="d-flex justify-content-end mb-3">
      <a href="add_level.php" class="btn" style="background:var(--gold);color:#fff;">
        <i class="fa-solid fa-plus"></i> 새 등급 추가
      </a>
    </div>

    <div class="card">
      <table class="table table-hover align-middle">
        <thead style="background:var(--navy);color:#fff;">
          <tr>
            <th>등급명</th>
            <th>최소 구매액</th>
            <th>포인트 적립률</th>
            <th>혜택</th>
            <th>색상</th>
            <th>관리</th>
          </tr>
        </thead>
        <tbody>
          <?php while($row = $result->fetch_assoc()): ?>
          <tr>
            <td>
              <span class="badge-level" style="background:<?= htmlspecialchars($row['badge_color'] ?? '#999') ?>;
                   padding:6px 12px; border-radius:6px; color:#fff; font-weight:bold;">
                <?= htmlspecialchars($row['name']) ?>
              </span>
            </td>
            <td>₩<?= number_format($row['min_spent']) ?></td>
            <td><?= $row['point_rate'] ?>%</td>
            <td><?= nl2br(htmlspecialchars($row['benefits'])) ?></td>
            <td>
              <div style="width:30px;height:20px;background:<?= htmlspecialchars($row['badge_color'] ?? '#999') ?>;
                          border-radius:4px; border:1px solid #ddd;">
              </div>
            </td>
            <td>
              <a href="edit_level.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-warning">
                <i class="fa-solid fa-pen"></i> 수정
              </a>
              <a href="delete_level.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-danger"
                 onclick="return confirm('정말 삭제하시겠습니까?');">
                <i class="fa-solid fa-trash"></i> 삭제
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