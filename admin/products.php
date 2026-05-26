<?php
session_start();
include "../php/db.php";
if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] != 0) {
    header("Location: ../index.php");
    exit;
}

// ✅ 삭제 처리
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);

    // 메인 이미지 삭제
    $mainImg = $conn->query("SELECT image FROM products WHERE id=$id")->fetch_assoc();
    if ($mainImg && !empty($mainImg['image']) && file_exists("../".$mainImg['image'])) {
        unlink("../".$mainImg['image']);
    }

    // 서브 이미지 삭제
    $subImgs = $conn->query("SELECT image_path FROM product_images WHERE product_id=$id");
    while($img = $subImgs->fetch_assoc()){
        if (!empty($img['image_path']) && file_exists("../".$img['image_path'])) {
            unlink("../".$img['image_path']);
        }
    }

    $conn->query("DELETE FROM product_images WHERE product_id=$id");
    $conn->query("DELETE FROM products WHERE id=$id");

    header("Location: products.php");
    exit;
}

// ✅ 검색 & 필터
$search = $_GET['search'] ?? '';
$categoryFilter = $_GET['category'] ?? '';
$stockFilter = $_GET['stock'] ?? '';

$where = [];
if ($search !== '') {
    $s = "%".$conn->real_escape_string($search)."%";
    $where[] = "p.name LIKE '$s'";
}
if ($categoryFilter !== '') {
    $c = intval($categoryFilter);
    $where[] = "p.category_id=$c";
}
if ($stockFilter === 'low') {
    $where[] = "p.stock <= 10";
}
$whereSQL = $where ? "WHERE ".implode(" AND ", $where) : "";

// ✅ 상품 조회
$sql = "
    SELECT p.*, c.name AS category_name 
    FROM products p 
    LEFT JOIN categories c ON p.category_id=c.id 
    $whereSQL
    ORDER BY c.id ASC, p.created_at DESC
";
$result = $conn->query($sql);

// ✅ 카테고리 목록
$categories = $conn->query("SELECT * FROM categories ORDER BY id ASC");

// 카테고리 그룹 정리
$productsByCategory = [];
while ($p = $result->fetch_assoc()) {
    $catName = $p['category_name'] ?? "기타";
    $productsByCategory[$catName][] = $p;
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
  <meta charset="UTF-8">
  <title>상품 관리 - 루미노아 관리자</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <!-- Bootstrap -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

  <!-- 관리자 CSS -->
  <link rel="stylesheet" href="../css/admin.css">

  <!-- FontAwesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
  <div class="overlay" onclick="toggleSidebar()"></div>
  <?php include "sidebar.php"; ?>

  <div class="admin-content">
    <div class="top-bar">
      <button class="sidebar-toggle" onclick="toggleSidebar()">☰</button>
      <h1><i class="fa-solid fa-box"></i> 상품 관리</h1>
      <span class="dark-toggle" onclick="toggleDarkMode()"><i class="fa-solid fa-moon"></i></span>
    </div>

    <!-- 검색 & 필터 -->
    <form method="get" class="card p-3 mb-4">
      <div class="row g-2">
        <div class="col-md-4">
          <input type="text" name="search" class="form-control" placeholder="상품명 검색" value="<?= htmlspecialchars($search) ?>">
        </div>
        <div class="col-md-3">
          <select name="category" class="form-select">
            <option value="">전체 카테고리</option>
            <?php while($c=$categories->fetch_assoc()): ?>
              <option value="<?= $c['id'] ?>" <?= $categoryFilter==$c['id']?"selected":"" ?>>
                <?= htmlspecialchars($c['name']) ?>
              </option>
            <?php endwhile; ?>
          </select>
        </div>
        <div class="col-md-3">
          <select name="stock" class="form-select">
            <option value="">전체 재고</option>
            <option value="low" <?= $stockFilter=='low'?'selected':'' ?>>재고 부족(≤10)</option>
          </select>
        </div>
        <div class="col-md-2">
          <button type="submit" class="btn btn-primary w-100">
            <i class="fa-solid fa-search"></i> 검색
          </button>
        </div>
      </div>
    </form>

    <div class="d-flex justify-content-end mb-3">
      <a href="product_add.php" class="btn btn-success">
        <i class="fa-solid fa-plus"></i> 상품 등록
      </a>
    </div>

    <?php if(empty($productsByCategory)): ?>
      <div class="alert alert-info">검색 조건에 맞는 상품이 없습니다.</div>
    <?php else: ?>
      <?php foreach($productsByCategory as $category => $products): ?>
        <div class="card mb-5 shadow-sm">
          <div class="card-header bg-navy text-white fw-bold">
            <?= htmlspecialchars($category) ?>
          </div>
          <div class="card-body p-0">
            <div class="table-responsive">
              <table class="table table-hover mb-0 align-middle text-center">
                <thead class="table-light">
                  <tr>
                    <th>ID</th>
                    <th>이미지</th>
                    <th>상품명</th>
                    <th>정가</th>
                    <th>할인율</th>
                    <th>판매가</th>
                    <th>재고</th>
                    <th>추가 이미지</th>
                    <th>관리</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach($products as $p): ?>
                    <?php
                      $countImgs = $conn->query("SELECT COUNT(*) as cnt FROM product_images WHERE product_id={$p['id']}")->fetch_assoc()['cnt'];
                    ?>
                    <tr>
                      <td><?= $p['id'] ?></td>
                      <td>
                        <?php if(!empty($p['image'])): ?>
                          <div style="width:80px; height:80px; overflow:hidden; border-radius:6px; margin:auto;">
                            <img src="../<?= htmlspecialchars($p['image']) ?>" alt="상품 이미지" style="width:100%; height:100%; object-fit:cover; display:block;">
                          </div>
                        <?php else: ?>
                        <span class="text-muted">없음</span>
                      <?php endif; ?>
                    </td>
                      <td><?= htmlspecialchars($p['name']) ?></td>
                      <td><?= $p['original_price'] ? "₩".number_format($p['original_price']) : "-" ?></td>
                      <td><?= $p['discount_percent'] ? $p['discount_percent']."%" : "0%" ?></td>
                      <td class="fw-bold text-primary">₩<?= number_format($p['price']) ?></td>
                      <td><?= $p['stock'] ?></td>
                      <td><?= $countImgs ?>장</td>
                      <td>
                        <div class="d-flex gap-2 justify-content-center">
                          <a href="product_edit.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-warning">
                            <i class="fa-solid fa-pen"></i> 수정
                          </a>
                          <a href="products.php?delete=<?= $p['id'] ?>" 
                             class="btn btn-sm btn-danger"
                             onclick="return confirm('정말 삭제하시겠습니까?')">
                            <i class="fa-solid fa-trash"></i> 삭제
                          </a>
                        </div>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
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

<style>
  .bg-navy { background-color: #1A2A40; }
</style>
</body>
</html>