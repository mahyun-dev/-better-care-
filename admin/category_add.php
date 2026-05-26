<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] != 0) {
    header("Location: ../index.php");
    exit;
}
include "../php/db.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    if ($name !== "") {
        $stmt = $conn->prepare("INSERT INTO categories (name, created_at) VALUES (?, NOW())");
        $stmt->bind_param("s", $name);
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
  <title>카테고리 추가 - 루미노아 관리자</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <!-- Bootstrap 먼저 -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

  <!-- 그 다음에 관리자 공통 CSS -->
  <link rel="stylesheet" href="../css/admin.css">

  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
  <div class="overlay" onclick="toggleSidebar()"></div>
  <?php include "sidebar.php"; ?>

  <div class="admin-content">
    <div class="top-bar">
      <button class="sidebar-toggle" onclick="toggleSidebar()">☰</button>
      <h1><i class="fa-solid fa-plus"></i> 카테고리 추가</h1>
      <span class="dark-toggle" onclick="toggleDarkMode()"><i class="fa-solid fa-moon"></i></span>
    </div>

    <div class="card" style="max-width:600px; margin:20px auto;">
      <?php if(isset($error)): ?>
        <p class="text-danger"><?= $error ?></p>
      <?php endif; ?>

      <form method="post">
        <div class="mb-3">
          <label class="form-label">카테고리명 <span style="color:red">*</span></label>
          <input type="text" name="name" class="form-control" placeholder="예: 아우터, 티셔츠, 팬츠" required>
        </div>
        <div class="d-flex justify-content-between">
          <a href="categories.php" class="btn btn-secondary">
            <i class="fa-solid fa-arrow-left"></i> 취소
          </a>
          <button type="submit" class="btn" style="background:var(--gold);color:#fff;">
            <i class="fa-solid fa-save"></i> 저장
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