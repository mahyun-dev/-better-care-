<?php
session_start();
include "../php/db.php";
if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] != 0) {
  header("Location: ../index.php");
  exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $name        = $_POST['name'];
  $desc        = $_POST['description'];
  $original    = intval($_POST['original_price']);
  $discount    = intval($_POST['discount_percent']);
  $stock       = intval($_POST['stock']);
  $category_id = intval($_POST['category_id']);
  $price       = intval($_POST['price']); // hidden input 값 사용

  $mainImage = "";
  $uploadedImages = [];

  if (!empty($_FILES['images']['name'][0])) {
    $uploadDir = "../uploads/";
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

    foreach ($_FILES['images']['tmp_name'] as $key => $tmpName) {
      if ($_FILES['images']['error'][$key] !== UPLOAD_ERR_OK) continue;
      $fileName   = uniqid("img_", true) . "_" . basename($_FILES['images']['name'][$key]);
      $targetPath = $uploadDir . $fileName;
      if (move_uploaded_file($tmpName, $targetPath)) {
        $filePath = "uploads/" . $fileName;
        $uploadedImages[] = $filePath;
      }
    }
  }
  if (!empty($uploadedImages)) $mainImage = $uploadedImages[0];

  $stmt = $conn->prepare("INSERT INTO products 
      (name, description, price, original_price, discount_percent, stock, image, category_id, created_at) 
      VALUES (?,?,?,?,?,?,?,?,NOW())");
  $stmt->bind_param("ssiiiisi", $name, $desc, $price, $original, $discount, $stock, $mainImage, $category_id);

  if ($stmt->execute()) {
    $product_id = $stmt->insert_id;
    $stmt->close();

    if (count($uploadedImages) > 1) {
      foreach (array_slice($uploadedImages, 1) as $img) {
        $stmt = $conn->prepare("INSERT INTO product_images (product_id, image_path) VALUES (?, ?)");
        $stmt->bind_param("is", $product_id, $img);
        $stmt->execute();
        $stmt->close();
      }
    }

    // 옵션 저장
    if (!empty($_POST['option_groups'])) {
      foreach($_POST['option_groups'] as $idx => $group_name) {
        if (trim($group_name) === "") continue;
        $stmt = $conn->prepare("INSERT INTO option_groups (product_id, group_name) VALUES (?,?)");
        $stmt->bind_param("is", $product_id, $group_name);
        $stmt->execute();
        $group_id = $stmt->insert_id;
        $stmt->close();

        if (!empty($_POST['option_values'][$idx])) {
          foreach($_POST['option_values'][$idx] as $v_idx => $value_name) {
            $add_price = intval($_POST['option_prices'][$idx][$v_idx] ?? 0);
            if (trim($value_name) === "") continue;
            $stmt = $conn->prepare("INSERT INTO option_values (group_id, value_name, add_price) VALUES (?,?,?)");
            $stmt->bind_param("isi", $group_id, $value_name, $add_price);
            $stmt->execute();
            $stmt->close();
          }
        }
      }
    }

    header("Location: products.php");
    exit;
  } else {
    $error = "❌ 상품 등록 실패: " . $conn->error;
  }
}

$categories = $conn->query("SELECT * FROM categories ORDER BY id ASC");
?>
<!DOCTYPE html>
<html lang="ko">
<head>
  <meta charset="UTF-8">
  <title>상품 등록 - 루미노아 관리자</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <!-- Bootstrap -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- 관리자 CSS -->
  <link rel="stylesheet" href="../css/admin.css">
  <!-- 아이콘 -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

  <script>
    function addGroup() {
      const container = document.getElementById("option-groups");
      const idx = container.children.length;
      const groupHtml = `
        <div class="card p-3 mb-3">
          <label class="fw-bold">옵션 그룹명</label>
          <input type="text" name="option_groups[${idx}]" class="form-control mb-2" placeholder="예: 사이즈">
          <div class="option-values"></div>
          <button type="button" onclick="addValue(this, ${idx})" class="btn btn-sm btn-secondary">+ 옵션 값 추가</button>
        </div>
      `;
      container.insertAdjacentHTML("beforeend", groupHtml);
    }
    function addValue(btn, idx) {
      const valuesDiv = btn.parentElement.querySelector(".option-values");
      const vIdx = valuesDiv.children.length;
      const valueHtml = `
        <div class="d-flex mb-2 align-items-center">
          <input type="text" name="option_values[${idx}][${vIdx}]" class="form-control me-2" placeholder="옵션 값 (예: L)">
          <input type="number" name="option_prices[${idx}][${vIdx}]" class="form-control me-2" placeholder="추가금액 (원)">
          <button type="button" onclick="this.parentElement.remove()" class="btn btn-sm btn-danger">삭제</button>
        </div>
      `;
      valuesDiv.insertAdjacentHTML("beforeend", valueHtml);
    }
    function calcPrice() {
      const original = document.querySelector("input[name='original_price']").value;
      const discount = document.querySelector("input[name='discount_percent']").value;
      let price = 0;
      if(original) price = Math.floor(original * (100 - (discount || 0)) / 100);
      document.getElementById("price_display").value = price;
      document.getElementById("price_hidden").value = price;
    }
  </script>
</head>
<body>
  <div class="overlay" onclick="toggleSidebar()"></div>
  <?php include "sidebar.php"; ?>

  <div class="admin-content">
    <!-- 상단바 -->
    <div class="top-bar">
      <button class="sidebar-toggle" onclick="toggleSidebar()">☰</button>
      <h1><i class="fa-solid fa-plus"></i> 상품 등록</h1>
      <span class="dark-toggle" onclick="toggleDarkMode()"><i class="fa-solid fa-moon"></i></span>
    </div>

    <!-- 본문 -->
    <div class="card p-4 shadow-sm mt-3">
      <?php if(isset($error)): ?>
        <div class="alert alert-danger"><?= $error ?></div>
      <?php endif; ?>

      <form method="post" enctype="multipart/form-data">
        <div class="mb-3">
          <label class="form-label">상품명</label>
          <input type="text" name="name" class="form-control" required>
        </div>

        <div class="mb-3">
          <label class="form-label">설명</label>
          <textarea name="description" class="form-control"></textarea>
        </div>

        <div class="row">
          <div class="col-md-4 mb-3">
            <label class="form-label">정가</label>
            <input type="number" name="original_price" class="form-control" required oninput="calcPrice()">
          </div>
          <div class="col-md-4 mb-3">
            <label class="form-label">할인율 (%)</label>
            <input type="number" name="discount_percent" class="form-control" value="0" min="0" max="100" oninput="calcPrice()">
          </div>
          <div class="col-md-4 mb-3">
            <label class="form-label text-success fw-bold">판매가 (자동)</label>
            <input type="number" id="price_display" class="form-control fw-bold text-success" readonly>
            <input type="hidden" name="price" id="price_hidden">
          </div>
        </div>

        <div class="row">
          <div class="col-md-6 mb-3">
            <label class="form-label">재고</label>
            <input type="number" name="stock" class="form-control" required>
          </div>
          <div class="col-md-6 mb-3">
            <label class="form-label">카테고리</label>
            <select name="category_id" class="form-select" required>
              <option value="">카테고리 선택</option>
              <?php while($cat = $categories->fetch_assoc()): ?>
                <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
              <?php endwhile; ?>
            </select>
          </div>
        </div>

        <div class="mb-3">
          <label class="form-label">상품 이미지 (여러 장 가능)</label>
          <input type="file" name="images[]" class="form-control" multiple accept="image/*">
          <div class="form-text">첫 번째 이미지는 대표 이미지로 설정됩니다.</div>
        </div>

        <hr>
        <h4 class="mb-3">옵션 그룹</h4>
        <div id="option-groups"></div>
        <button type="button" onclick="addGroup()" class="btn btn-sm btn-outline-primary mt-2">+ 옵션 그룹 추가</button>

        <div class="mt-4">
          <button type="submit" class="btn btn-success">등록 완료</button>
          <a href="products.php" class="btn btn-secondary">취소</a>
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