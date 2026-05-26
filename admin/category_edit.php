<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] != 0) {
    header("Location: ../index.php");
    exit;
}
include "../php/db.php";

$id = intval($_GET['id']);
$category = $conn->query("SELECT * FROM categories WHERE id=$id")->fetch_assoc();
if (!$category) { die("존재하지 않는 카테고리입니다."); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    if ($name !== "") {
        $stmt = $conn->prepare("UPDATE categories SET name=? WHERE id=?");
        $stmt->bind_param("si", $name, $id);
        $stmt->execute();
        $stmt->close();
        header("Location: categories.php");
        exit;
    } else {
        $error = "카테고리명을 입력해주세요.";
    }
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
  <meta charset="UTF-8">
  <title>카테고리 수정 - 루미노아 관리자</title>
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
      <h1><i class="fa-solid fa-pen-to-square"></i> 카테고리 수정</h1>
      <span class="dark-toggle" onclick="toggleDarkMode()"><i class="fa-solid fa-moon"></i></span>
    </div>

    <div class="card" style="max-width:600px; margin:20px auto;">
      <?php if(isset($error)): ?>
        <p class="text-danger"><?= $error ?></p>
      <?php endif; ?>

      <form method="post">
        <div class="mb-3">
          <label class="form-label">카테고리명 <span style="color:red">*</span></label>
          <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($category['name']) ?>" required>
        </div>
        <div class="d-flex justify-content-between">
          <a href="categories.php" class="btn btn-secondary">
            <i class="fa-solid fa-arrow-left"></i> 취소
          </a>
          <button type="submit" class="btn btn-warning">
            <i class="fa-solid fa-pen"></i> 수정 완료
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
</script>
</body>
</html>