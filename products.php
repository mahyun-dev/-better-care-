<?php
session_start();
include "php/db.php";

$order = $_GET['order'] ?? 'created_at';
$keyword = $_GET['q'] ?? '';
$category_id = isset($_GET['category']) ? intval($_GET['category']) : 0;

// SQL 기본
$sql = "SELECT p.*, c.name as category_name 
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE p.name LIKE ?";
$params = [];
$types = "s"; // 첫 번째 파라미터는 keyword
$search = "%$keyword%";
$params[] = &$search;

// 카테고리 조건 추가
if ($category_id > 0) {
    $sql .= " AND p.category_id=?";
    $types .= "i";
    $params[] = &$category_id;
}

$sql .= " ORDER BY $order DESC";

$stmt = $conn->prepare($sql);

// 가변 파라미터 바인딩
call_user_func_array([$stmt, 'bind_param'], array_merge([$types], $params));

$stmt->execute();
$products = $stmt->get_result();
$stmt->close();

// 카테고리 목록 불러오기
$categories = $conn->query("SELECT * FROM categories ORDER BY id ASC");
$cat_rows = $categories->fetch_all(MYSQLI_ASSOC); // 배열로 저장
?>
<!DOCTYPE html>
<html lang="ko">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, user-scalable=no">
  <title>상품 목록 - 루미노아</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="css/styles.css">
  <style>
    .product-card img {
      height: 260px;
      object-fit: cover;
      border-radius: 6px;
    }
    .product-card {
      transition: all 0.2s ease;
    }
    .product-card:hover {
      transform: translateY(-4px);
      box-shadow: 0 6px 15px rgba(0,0,0,0.1);
    }
    .original-price {
      font-size: 0.9rem;
      text-decoration: line-through;
      color: #888;
      margin-bottom: 0;
    }
    .price {
      font-size: 1.1rem;
      font-weight: bold;
      margin-bottom: 0;
    }
    .discount {
      font-size: 0.9rem;
      color: green;
    }
  </style>
</head>
<body>
<?php include "php/header.php"; ?>

<!-- Hero Section -->
<section class="hero-luminoa text-center">
  <div class="container">
    <h1 class="fw-bold">✨ 모든 상품 ✨</h1>
    <p class="lead">루미노아의 빛나는 아이템을 만나보세요</p>
  </div>
</section>

<div class="container py-5">
  <!-- 검색 & 정렬 -->
  <form method="get" class="row mb-4">
    <div class="col-md-4 mb-2">
      <input type="text" name="q" class="form-control" placeholder="상품 검색" value="<?= htmlspecialchars($keyword) ?>">
    </div>
    <div class="col-md-3 mb-2">
      <select name="category" class="form-select" onchange="this.form.submit()">
        <option value="0">전체 카테고리</option>
        <?php foreach($cat_rows as $cat): ?>
          <option value="<?= $cat['id'] ?>" <?= $category_id==$cat['id']?'selected':'' ?>>
            <?= htmlspecialchars($cat['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-3 mb-2">
      <select name="order" class="form-select" onchange="this.form.submit()">
        <option value="created_at" <?= $order=='created_at'?'selected':'' ?>>최신순</option>
        <option value="price" <?= $order=='price'?'selected':'' ?>>가격순</option>
      </select>
    </div>
    <div class="col-md-2">
      <button type="submit" class="btn btn-luminoa w-100">검색</button>
    </div>
  </form>

    <!-- 상품 Grid -->
  <div class="row g-4">
    <?php while($row = $products->fetch_assoc()): ?>
      <?php 
        $imagePath = !empty($row['image']) ? htmlspecialchars($row['image']) : "img/logo.PNG";
        $original_price = $row['original_price'] ?? $row['price'];
        $discount_percent = $row['discount_percent'] ?? 0;

        // 할인 계산
        $final_price = $row['price'];
        if ($discount_percent > 0) {
            $final_price = floor($original_price * (100 - $discount_percent) / 100);
        }
      ?>
      <div class="col-6 col-md-3"> <!-- 모바일 2개 / PC 4개 -->
        <div class="product-card border rounded shadow-sm p-3 h-100">
          <a href="product.php?id=<?= $row['id'] ?>" class="text-decoration-none text-dark">
            <img src="<?= $imagePath ?>" class="w-100 mb-3" style="height:200px; object-fit:cover;" alt="<?= htmlspecialchars($row['name']) ?>">
            <h5 class="fw-bold"><?= htmlspecialchars($row['name']) ?></h5>
            <p class="original-price text-decoration-line-through mb-1">₩<?= number_format($original_price) ?></p>
            <p class="price text-danger fw-bold mb-1">₩<?= number_format($final_price) ?></p>
            <p class="discount text-success"><?= $discount_percent ?>% 할인</p>
          </a>
        </div>
      </div>
    <?php endwhile; ?>
  </div>

<?php include "php/footer.php"; ?>
<?php include "php/top-button.php"; ?>
</body>
</html>