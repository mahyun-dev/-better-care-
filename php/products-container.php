<!-- 신상품 -->
<section class="py-5">
  <div class="container">
    <h2 class="mb-4">신상품</h2>
    <div class="product-carousel d-flex overflow-auto gap-3 pb-3">
      <?php while($row = $products->fetch_assoc()): ?>
        <?php 
          $imagePath = !empty($row['image']) ? htmlspecialchars($row['image']) : "img/logo.PNG";
          $original_price = $row['original_price'] ?? $row['price'];
          $discount_percent = $row['discount_percent'] ?? 0;
          $final_price = $row['price'];
          if ($discount_percent > 0) {
              $final_price = floor($original_price * (100 - $discount_percent) / 100);
          }
        ?>
        <a href="product.php?id=<?= $row['id'] ?>" class="text-decoration-none text-dark">
          <div class="card shadow-sm flex-shrink-0" style="width: 250px; cursor:pointer;">
            <img src="<?= $imagePath ?>" class="card-img-top" alt="<?= htmlspecialchars($row['name']) ?>" style="height: 200px; object-fit: cover;">
            <div class="card-body text-center">
              <h6 class="card-title text-truncate"><?= htmlspecialchars($row['name']) ?></h6>
              <p class="text-muted text-decoration-line-through mb-1">₩<?= number_format($original_price) ?></p>
              <p class="fw-bold text-danger mb-1">₩<?= number_format($final_price) ?> <small class="text-success">(<?= $discount_percent ?>%↓)</small></p>
            </div>
          </div>
        </a>
      <?php endwhile; ?>
      <!-- 마지막에 추가 -->
<a href="products.php" class="text-decoration-none text-dark">
  <div id="more-link" class="card shadow-sm flex-shrink-0 d-flex align-items-center justify-content-center"
       style="width: 70px; height: 320px; cursor:pointer; background-color:#f8f9fa;">
    <p class="fw-bold" style="text-align : center;">더보기 →</p>
  </div>
</a>

    </div>
  </div>
  <!-- 추천상품 -->
  <div class="container">
    <h2 class="mb-4">추천상품</h2>
    <div class="product-carousel d-flex overflow-auto gap-3 pb-3">
      <?php while($row = $rand->fetch_assoc()): ?>
        <?php 
          $imagePath = !empty($row['image']) ? htmlspecialchars($row['image']) : "img/logo.PNG";
          $original_price = $row['original_price'] ?? $row['price'];
          $discount_percent = $row['discount_percent'] ?? 0;
          $final_price = $row['price'];
          if ($discount_percent > 0) {
              $final_price = floor($original_price * (100 - $discount_percent) / 100);
          }
        ?>
        <a href="product.php?id=<?= $row['id'] ?>" class="text-decoration-none text-dark">
          <div class="card shadow-sm flex-shrink-0" style="width: 250px; cursor:pointer;">
            <img src="<?= $imagePath ?>" class="card-img-top" alt="<?= htmlspecialchars($row['name']) ?>" style="height: 200px; object-fit: cover;">
            <div class="card-body text-center">
              <h6 class="card-title text-truncate"><?= htmlspecialchars($row['name']) ?></h6>
              <p class="text-muted text-decoration-line-through mb-1">₩<?= number_format($original_price) ?></p>
              <p class="fw-bold text-danger mb-1">₩<?= number_format($final_price) ?> <small class="text-success">(<?= $discount_percent ?>%↓)</small></p>
            </div>
          </div>
        </a>
      <?php endwhile; ?>
    </div>
  </div>
  <!-- 인기상품 -->
  <!-- <div class="container">
    <h2 class="mb-4">인기상품</h2>
    <div class="product-carousel d-flex overflow-auto gap-3 pb-3">
      <?php $arr = array("상품1", "상품2", "상푸3"); foreach($arr as $val): ?>
        <a href="product.php?id=<?= $row['id'] ?>" class="text-decoration-none text-dark">
          <div class="card shadow-sm flex-shrink-0" style="width: 250px; cursor:pointer;">
            <p><?= $val ?></p>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
  -->
  <!-- 카테고리  -->
  <div class="container">
    <?php while($cat = $categorie->fetch_assoc()): ?>
      <?php 
        $cat_id = intval($cat['id']);
        $cat_products = $conn->query("SELECT * FROM products WHERE category_id = $cat_id ORDER BY created_at DESC LIMIT 10");
        if ($cat_products->num_rows === 0) continue; 
      ?>
      <h2 class="mb-4"><?= htmlspecialchars($cat['name']) ?></h2>
      <div class="product-carousel d-flex overflow-auto gap-3 pb-3">
      <?php while($row = $cat_products->fetch_assoc()): ?>
        <?php 
          $imagePath = !empty($row['image']) ? htmlspecialchars($row['image']) : "img/logo.PNG";
          $original_price = $row['original_price'] ?? $row['price'];
          $discount_percent = $row['discount_percent'] ?? 0;
          $final_price = $row['price'];
          if ($discount_percent > 0) {
              $final_price = floor($original_price * (100 - $discount_percent) / 100);
          }
        ?>
        <a href="product.php?id=<?= $row['id'] ?>" class="text-decoration-none text-dark">
          <div class="card shadow-sm flex-shrink-0" style="width: 250px; cursor:pointer;">
            <img src="<?= $imagePath ?>" class="card-img-top" alt="<?= htmlspecialchars($row['name']) ?>" style="height: 200px; object-fit: cover;">
            <div class="card-body text-center">
              <h6 class="card-title text-truncate"><?= htmlspecialchars($row['name']) ?></h6>
                <p class="text-muted text-decoration-line-through mb-1">₩<?= number_format($original_price) ?></p>
                <p class="fw-bold text-danger mb-1">₩<?= number_format($final_price) ?> <small class="text-success">(<?= $discount_percent ?>%↓)</small></p>
            </div>
          </div>
        </a>
      <?php endwhile; ?>
    </div>
  <?php endwhile; ?>
</div>
</section>
<script>
  document.addEventListener('DOMContentLoaded', () => {
    const moreLink = document.getElementById('more-link');

    if (moreLink) {
      const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
          if (entry.isIntersecting) {
            // 페이지 자동 이동
            setTimeout(() => {
            window.location.href = "products.php";
            }, 300);
          }
        });
      }, {
        root: document.querySelector('.product-carousel'), // 슬라이드 영역만 감지
        threshold: 1, // 0.5 절반 이상 보이면 이동
      });

      observer.observe(moreLink);
    }
  });
</script>
