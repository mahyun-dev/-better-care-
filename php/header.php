<?php
if (session_status() == PHP_SESSION_NONE) {
  session_start();
}

// 카테고리 목록 불러오기
$categories = $conn->query("SELECT * FROM categories ORDER BY id ASC");
?>
<header>
  <nav class="navbar navbar-expand-lg fixed-top navbar-light bg-white shadow-sm">
    <div class="container">
      <!-- 브랜드 로고 -->
      <a class="navbar-brand fw-bold text-luminoa" href="/index.php">
        ✨ Luminoa
      </a>

      <!-- 모바일 메뉴 버튼 -->
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
        <span class="navbar-toggler-icon"></span>
      </button>

      <!-- 메뉴 -->
      <div class="collapse navbar-collapse" id="navbarNav">
        <ul class="navbar-nav ms-auto mb-2 mb-lg-0 align-items-lg-center">
          <!-- 상품 + 카테고리 드롭다운 -->
          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle fw-semibold" href="/products.php" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
              상품
            </a>
            <ul class="dropdown-menu" aria-labelledby="navbarDropdown">
              <li><a class="dropdown-item" href="/products.php">전체 상품</a></li>
              <?php while($cat = $categories->fetch_assoc()): ?>
                <li>
                  <a class="dropdown-item" href="/products.php?category=<?= $cat['id'] ?>">
                    <?= htmlspecialchars($cat['name']) ?>
                  </a>
                </li>
              <?php endwhile; ?>
            </ul>
          </li>

          <li class="nav-item"><a class="nav-link fw-semibold" href="/about.php">브랜드</a></li>
          <li class="nav-item"><a class="nav-link fw-semibold" href="/contact.php">문의</a></li>
          
          <!-- 로그인 상태 -->
          <li class="nav-item">
            <?php if(isset($_SESSION['user_id'])): ?>
              <a class="nav-link fw-semibold" href="mypage.php">마이페이지</a>
              <a class="nav-link fw-semibold" href="/logout.php">로그아웃</a>
            <?php else: ?>
              <a class="nav-link fw-semibold" href="/login.php">로그인</a>
            <?php endif; ?>
          </li>
          
          <!-- 관리자 대시보드 -->
          <?php if(isset($_SESSION['user_id']) && $_SESSION['user_id'] == 0): ?>
            <li class="nav-item">
              <a class="nav-link fw-semibold" href="/admin/dashboard.php">관리자 대시보드</a>
            </li>
          <?php endif ?>

          <!-- 장바구니 버튼 -->
          <li class="nav-item ms-lg-3">
            <a class="btn btn-luminoa px-3 fw-bold" href="/cart.php">🛒 장바구니</a>
          </li>
        </ul>
      </div>
    </div>
  </nav>
</header>