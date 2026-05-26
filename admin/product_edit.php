<?php
session_start();
include "../php/db.php";
if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] != 0) {
  header("Location: ../index.php");
  exit;
}

$id = intval($_GET['id']);
$product = $conn->query("SELECT * FROM products WHERE id=$id")->fetch_assoc();
if(!$product) { die("상품을 찾을 수 없습니다."); }

$categories = $conn->query("SELECT * FROM categories ORDER BY name ASC");

// ✅ 삭제 요청 처리 (추가 이미지)
if (isset($_GET['delete_img'])) {
  $img_id = intval($_GET['delete_img']);
  $img = $conn->query("SELECT * FROM product_images WHERE id=$img_id AND product_id=$id")->fetch_assoc();
  if ($img) {
    $filePath = "../" . $img['image_path'];
    if (file_exists($filePath)) unlink($filePath);
    $conn->query("DELETE FROM product_images WHERE id=$img_id");
  }
  header("Location: product_edit.php?id=$id");
  exit;
}

// ✅ 수정 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $name        = $_POST['name'];
  $desc        = $_POST['description'];
  $original    = intval($_POST['original_price']);
  $discount    = intval($_POST['discount_percent']);
  $price       = intval($_POST['price']); // hidden input
  $stock       = intval($_POST['stock']);
  $category_id = intval($_POST['category_id']);
  $mainImage   = $product['image'];

  // 대표 이미지 업로드
  if (!empty($_FILES['main_image']['name'])) {
    $uploadDir = "../uploads/";
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
    $fileName   = uniqid("main_", true) . "_" . basename($_FILES['main_image']['name']);
    $targetPath = $uploadDir . $fileName;
    if (move_uploaded_file($_FILES['main_image']['tmp_name'], $targetPath)) {
      $mainImage = "uploads/" . $fileName;
    }
  }

  $stmt = $conn->prepare("UPDATE products 
    SET name=?, description=?, price=?, original_price=?, discount_percent=?, stock=?, image=?, category_id=? 
    WHERE id=?");
  $stmt->bind_param("ssiiiisii", $name, $desc, $price, $original, $discount, $stock, $mainImage, $category_id, $id);
  $stmt->execute();
  $stmt->close();

  // 추가 이미지 업로드
  if (!empty($_FILES['sub_images']['name'][0])) {
    $uploadDir = "../uploads/";
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
    foreach ($_FILES['sub_images']['tmp_name'] as $key => $tmpName) {
      if ($_FILES['sub_images']['error'][$key] !== UPLOAD_ERR_OK) continue;
      $fileName   = uniqid("sub_", true) . "_" . basename($_FILES['sub_images']['name'][$key]);
      $targetPath = $uploadDir . $fileName;
      if (move_uploaded_file($tmpName, $targetPath)) {
        $filePath = "uploads/" . $fileName;
        $stmt = $conn->prepare("INSERT INTO product_images (product_id, image_path) VALUES (?,?)");
        $stmt->bind_param("is", $id, $filePath);
        $stmt->execute();
        $stmt->close();
      }
    }
  }

  // 옵션 그룹 초기화 후 재등록
  $conn->query("DELETE FROM option_groups WHERE product_id=$id");
  $groups = $_POST['option_groups'] ?? [];
  foreach($groups as $idx => $group_name) {
    if (trim($group_name) === "") continue;
    $stmt = $conn->prepare("INSERT INTO option_groups (product_id, group_name) VALUES (?,?)");
    $stmt->bind_param("is", $id, $group_name);
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

  header("Location: products.php");
  exit;
}

// 옵션 & 이미지 불러오기
$option_groups = $conn->query("SELECT * FROM option_groups WHERE product_id=$id");
$groups_data = [];
while($g = $option_groups->fetch_assoc()) {
  $gid = $g['id'];
  $values = $conn->query("SELECT * FROM option_values WHERE group_id=$gid");
  $g['values'] = [];
  while($v = $values->fetch_assoc()) $g['values'][] = $v;
  $groups_data[] = $g;
}
$subImages = $conn->query("SELECT * FROM product_images WHERE product_id=$id");
?>
<!DOCTYPE html>
<html lang="ko">
<head>
  <meta charset="UTF-8">
  <title>상품 수정 - 루미노아 관리자</title>
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
          <label>옵션 그룹명</label>
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
        <div class="d-flex mb-2">
          <input type="text" name="option_values[${idx}][${vIdx}]" class="form-control me-2" placeholder="옵션 값 (예: L)">
          <input type="number" name="option_prices[${idx}][${vIdx}]" class="form-control" placeholder="추가금액 (원)">
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
      <h1><i class="fa-solid fa-pen-to-square"></i> 상품 수정</h1>
      <span class="dark-toggle" onclick="toggleDarkMode()"><i class="fa-solid fa-moon"></i></span>
    </div>

    <!-- 본문 -->
    <div class="card p-4 shadow-sm mt-3">
      <form method="post" enctype="multipart/form-data">
        <div class="mb-3">
          <label class="form-label">상품명</label>
          <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($product['name']) ?>" required>
        </div>

        <div class="mb-3">
          <label class="form-label">설명</label>
          <textarea name="description" class="form-control"><?= htmlspecialchars($product['description']) ?></textarea>
        </div>

        <div class="row">
          <div class="col-md-4 mb-3">
            <label class="form-label">정가</label>
            <input type="number" name="original_price" class="form-control" value="<?= $product['original_price'] ?>" required oninput="calcPrice()">
          </div>
          <div class="col-md-4 mb-3">
            <label class="form-label">할인율 (%)</label>
            <input type="number" name="discount_percent" class="form-control" value="<?= $product['discount_percent'] ?>" min="0" max="100" oninput="calcPrice()">
          </div>
          <div class="col-md-4 mb-3">
            <?php $price = intval($product['original_price'] * (100 - $product['discount_percent']) / 100); ?>
            <label class="form-label text-success fw-bold">판매가 (자동)</label>
            <input type="number" id="price_display" class="form-control fw-bold text-success" readonly value="<?= $price ?>">
            <input type="hidden" name="price" id="price_hidden" value="<?= $price ?>">
          </div>
        </div>

        <div class="row">
          <div class="col-md-6 mb-3">
            <label class="form-label">재고</label>
            <input type="number" name="stock" class="form-control" value="<?= $product['stock'] ?>" required>
          </div>
          <div class="col-md-6 mb-3">
            <label class="form-label">카테고리</label>
            <select name="category_id" class="form-select" required>
              <option value="">카테고리 선택</option>
              <?php while($cat = $categories->fetch_assoc()): ?>
                <option value="<?= $cat['id'] ?>" <?= ($cat['id']==$product['category_id'])?'selected':'' ?>>
                  <?= htmlspecialchars($cat['name']) ?>
                </option>
              <?php endwhile; ?>
            </select>
          </div>
        </div>

        <div class="mb-3">
          <label class="form-label">대표 이미지</label>
          <input type="file" name="main_image" class="form-control mb-2" accept="image/*">
          <?php if(!empty($product['image'])): ?>
            <p>현재 대표 이미지: <img src="../<?= htmlspecialchars($product['image']) ?>" style="max-height:80px;"></p>
          <?php endif; ?>
        </div>

        <div class="mb-3">
          <label class="form-label">추가 이미지</label>
          <input type="file" name="sub_images[]" class="form-control mb-2" multiple accept="image/*">
          <div class="mt-2">
            <?php while($img = $subImages->fetch_assoc()): ?>
              <div class="d-inline-block text-center me-2">
                <img src="../<?= htmlspecialchars($img['image_path']) ?>" style="max-height:80px; display:block; margin-bottom:5px;">
                <a href="?id=<?= $id ?>&delete_img=<?= $img['id'] ?>" class="btn btn-sm btn-danger">❌ 삭제</a>
              </div>
            <?php endwhile; ?>
          </div>
        </div>

        <hr>
        <h4 class="mb-3">옵션 그룹</h4>
        <div id="option-groups">
          <?php foreach($groups_data as $g_idx => $g): ?>
            <div class="card p-3 mb-3">
              <label>옵션 그룹명</label>
              <input type="text" name="option_groups[<?= $g_idx ?>]" class="form-control mb-2" value="<?= htmlspecialchars($g['group_name']) ?>">
              <div class="option-values">
                <?php foreach($g['values'] as $v_idx => $v): ?>
                  <div class="d-flex mb-2">
                    <input type="text" name="option_values[<?= $g_idx ?>][<?= $v_idx ?>]" class="form-control me-2" value="<?= htmlspecialchars($v['value_name']) ?>">
                    <input type="number" name="option_prices[<?= $g_idx ?>][<?= $v_idx ?>]" class="form-control" value="<?= $v['add_price'] ?>">
                  </div>
                <?php endforeach; ?>
              </div>
              <button type="button" onclick="addValue(this, <?= $g_idx ?>)" class="btn btn-sm btn-secondary">옵션 값 추가</button>
            </div>
          <?php endforeach; ?>
        </div>
        <button type="button" onclick="addGroup()" class="btn btn-sm btn-outline-primary mt-2">+ 옵션 그룹 추가</button>

        <div class="mt-4">
          <button type="submit" class="btn btn-success">수정 완료</button>
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