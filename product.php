<?php
session_start();
include "php/db.php";

$id = (int)$_GET['id'];

// 상품 정보
$stmt = $conn->prepare("SELECT p.*, c.name as category_name 
                        FROM products p 
                        LEFT JOIN categories c ON p.category_id=c.id 
                        WHERE p.id=? LIMIT 1");
$stmt->bind_param("i", $id);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$product) {
  echo "상품을 찾을 수 없습니다.";
  exit;
}

// 추가 이미지
$subImages = $conn->query("SELECT * FROM product_images WHERE product_id=$id");

// 리뷰
$stmt = $conn->prepare("SELECT r.*, u.name 
                        FROM reviews r 
                        JOIN users u ON r.user_id=u.id 
                        WHERE r.product_id=? ORDER BY r.created_at DESC");
$stmt->bind_param("i", $id);
$stmt->execute();
$reviews = $stmt->get_result();
$stmt->close();

// 평균 평점
$stmt = $conn->prepare("SELECT AVG(rating) as avg_rating FROM reviews WHERE product_id=?");
$stmt->bind_param("i", $id);
$stmt->execute();
$avg_rating = round($stmt->get_result()->fetch_assoc()['avg_rating'], 1);
$stmt->close();
?>
<!DOCTYPE html>
<html lang="ko">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($product['name']) ?> - 루미노아</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="css/styles.css">
  <style>
    .product-main img { max-height: 400px; object-fit: contain; }
    .thumbnail-list img { cursor: pointer; transition: 0.3s; }
    .thumbnail-list img:hover { transform: scale(1.05); border: 2px solid #0d6efd; }
    .price-old { text-decoration: line-through; color:#888; margin-right:8px; }
    .price-final { font-size:1.5rem; font-weight:bold; color:#d63384; }
    .discount-badge { background:#d63384; color:#fff; padding:3px 8px; border-radius:5px; font-size:0.8rem; }
    .review-card { border:1px solid #eee; border-radius:10px; padding:15px; margin-bottom:15px; background:#fff; }
    .review-stars { color:#f5c518; }
  </style>
</head>
<body class="bg-light">
<?php include "php/header.php"; ?>

<main class="container py-5">

  <div class="row g-5">
    <!-- 상품 이미지 -->
    <div class="col-md-6 text-center">
      <?php $imagePath = !empty($product['image']) ? htmlspecialchars($product['image']) : "img/logo.PNG"; ?>
      <img id="mainImage" src="<?= $imagePath ?>" class="img-fluid rounded shadow product-main" alt="<?= htmlspecialchars($product['name']) ?>">

      <?php if ($subImages->num_rows > 0): ?>
        <div class="d-flex flex-wrap justify-content-center gap-2 mt-3 thumbnail-list">
          <?php while($si = $subImages->fetch_assoc()): ?>
            <img src="<?= htmlspecialchars($si['image_path']) ?>" 
                 class="img-thumbnail" 
                 style="width:70px; height:70px; object-fit:cover;" 
                 onclick="changeMainImage(this)">
          <?php endwhile; ?>
        </div>
      <?php endif; ?>
    </div>

    <!-- 상품 상세 -->
    <div class="col-md-6">
      <h2 class="fw-bold"><?= htmlspecialchars($product['name']) ?></h2>
      <p class="text-muted mb-2">카테고리: <?= htmlspecialchars($product['category_name'] ?? "미분류") ?></p>

      <!-- 가격 -->
      <?php
        $original = $product['original_price'] ?? 0;
        $price = $product['price'];
        $discount_percent = $product['discount_percent'] ?? 0;
      ?>
      <div class="mb-3">
        <?php if ($discount_percent > 0 && $original > $price): ?>
          <span class="price-old">₩<?= number_format($original) ?></span>
          <span class="price-final">₩<?= number_format($price) ?></span>
          <span class="discount-badge">-<?= $discount_percent ?>%</span>
        <?php else: ?>
          <span class="price-final">₩<?= number_format($price) ?></span>
        <?php endif; ?>
      </div>

      <!-- 장바구니 폼 -->
      <form method="post" action="php/cart_actions.php" class="border rounded p-3 bg-white shadow-sm">
        <input type="hidden" name="action" value="add">
        <input type="hidden" name="product_id" value="<?= $product['id'] ?>">

        <?php
        $groups = $conn->query("SELECT * FROM option_groups WHERE product_id={$id}");
        while($g = $groups->fetch_assoc()):
            $values = $conn->query("SELECT * FROM option_values WHERE group_id={$g['id']}");
        ?>
          <div class="form-floating mb-3">
            <select name="options[<?= $g['id'] ?>]" class="form-select" required>
              <option value="">선택하세요</option>
              <?php while($v = $values->fetch_assoc()): ?>
                <option value="<?= $v['id'] ?>">
                  <?= htmlspecialchars($v['value_name']) ?>
                  <?php if($v['add_price'] > 0): ?>(+₩<?= number_format($v['add_price']) ?>)<?php endif; ?>
                </option>
              <?php endwhile; ?>
            </select>
            <label><?= htmlspecialchars($g['group_name']) ?></label>
          </div>
        <?php endwhile; ?>

        <div class="form-floating mb-3" style="max-width:150px;">
          <input type="number" name="quantity" class="form-control" value="1" min="1">
          <label>수량</label>
        </div>

        <button type="submit" class="btn btn-lg btn-primary w-100">
          <i class="fa-solid fa-cart-plus"></i> 장바구니 담기
        </button>
      </form>
    </div>
  </div>

  <!-- 상품 설명 -->
  <section class="mt-5">
    <h4 class="fw-bold mb-3">상품 상세 설명</h4>
    <div class="p-4 bg-white border rounded shadow-sm">
      <?= !empty($product['description']) ? nl2br(htmlspecialchars($product['description'])) : "<p class='text-muted'>상품 설명이 없습니다.</p>" ?>
    </div>
  </section>

  <!-- 리뷰 -->
  <section class="mt-5">
    <h4 class="fw-bold mb-3">
      리뷰 <small class="text-muted">(평균 평점: <?= $avg_rating ?: "0" ?>/5)</small>
    </h4>

    <?php if ($reviews->num_rows > 0): ?>
      <?php while($r = $reviews->fetch_assoc()): ?>
        <div class="review-card">
          <div class="d-flex align-items-center mb-2">
            <div class="me-2 rounded-circle bg-secondary text-white d-flex align-items-center justify-content-center" style="width:40px;height:40px;">
              <i class="fa-solid fa-user"></i>
            </div>
            <div>
              <span class="fw-bold"><?= htmlspecialchars($r['name']) ?></span><br>
              <span class="review-stars">★<?= $r['rating'] ?></span>
            </div>
          </div>
          <p><?= nl2br(htmlspecialchars($r['content'])) ?></p>
          <small class="text-muted"><?= $r['created_at'] ?></small>
        </div>
      <?php endwhile; ?>
    <?php else: ?>
      <p class="text-muted">아직 작성된 리뷰가 없습니다.</p>
    <?php endif; ?>
  </section>

</main>

<?php include "php/footer.php"; ?>
<script>
  function changeMainImage(thumb) {
    document.getElementById("mainImage").src = thumb.src;
  }
</script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/js/all.min.js"></script>
</body>
</html>