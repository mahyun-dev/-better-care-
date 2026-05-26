<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] != 0) {
    header("Location: ../index.php");
    exit;
}
include "../php/db.php";

// 삭제 처리 (POST 방식으로 변경)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $id = intval($_POST['delete_id']);
    $stmt = $conn->prepare("DELETE FROM categories WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    header("Location: categories.php");
    exit;
}

$result = $conn->query("SELECT * FROM categories ORDER BY id ASC");
?>
<!DOCTYPE html>
<html lang="ko">
<head>
  <meta charset="UTF-8">
  <title>카테고리 관리 - 루미노아 관리자</title>
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
      <h1><i class="fa-solid fa-folder"></i> 카테고리 관리</h1>
      <span class="dark-toggle" onclick="toggleDarkMode()"><i class="fa-solid fa-moon"></i></span>
    </div>

    <div class="d-flex justify-content-end mb-3">
      <a href="category_add.php" class="btn" style="background:var(--gold);color:#fff;">
        <i class="fa-solid fa-plus"></i> 카테고리 추가
      </a>
    </div>

    <div class="card">
      <table class="table table-hover align-middle">
        <thead style="background:var(--navy);color:#fff;">
          <tr>
            <th>ID</th>
            <th>카테고리명</th>
            <th>생성일</th>
            <th>관리</th>
          </tr>
        </thead>
        <tbody>
          <?php while($row = $result->fetch_assoc()): ?>
            <tr>
              <td><?= $row['id'] ?></td>
              <td><?= htmlspecialchars($row['name']) ?></td>
              <td><?= $row['created_at'] ?></td>
              <td>
                <a href="category_edit.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-warning">
                  <i class="fa-solid fa-pen"></i> 수정
                </a>
                <form method="post" action="categories.php" style="display:inline;" onsubmit="return confirm('정말 삭제하시겠습니까?');">
                  <input type="hidden" name="delete_id" value="<?= $row['id'] ?>">
                  <button type="submit" class="btn btn-sm btn-danger">
                    <i class="fa-solid fa-trash"></i> 삭제
                  </button>
                </form>
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