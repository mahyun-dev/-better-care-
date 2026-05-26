<?php
session_start();
include "php/db.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
$user_id = $_SESSION['user_id'];

// ================= 회원 정보 =================
$user_info = $conn->query("SELECT * FROM users WHERE id=$user_id")->fetch_assoc();
if(!$user_info){ die("회원 정보를 불러올 수 없습니다."); }

// ================= 주문내역 페이지네이션 =================
$ordersPerPage = 5;
$orderPage = isset($_GET['order_page']) ? max(1, intval($_GET['order_page'])) : 1;
$orderOffset = ($orderPage - 1) * $ordersPerPage;
$totalOrders = $conn->query("SELECT COUNT(*) as cnt FROM orders WHERE user_id=$user_id")->fetch_assoc()['cnt'];
$totalOrderPages = ceil($totalOrders / $ordersPerPage);
$orders = $conn->query("SELECT * FROM orders WHERE user_id=$user_id ORDER BY created_at DESC LIMIT $ordersPerPage OFFSET $orderOffset");

// ================= 리뷰 페이지네이션 =================
$reviewsPerPage = 5;
$reviewPage = isset($_GET['review_page']) ? max(1, intval($_GET['review_page'])) : 1;
$reviewOffset = ($reviewPage - 1) * $reviewsPerPage;
$totalReviews = $conn->query("SELECT COUNT(*) as cnt FROM reviews WHERE user_id=$user_id")->fetch_assoc()['cnt'];
$totalReviewPages = ceil($totalReviews / $reviewsPerPage);
$reviews = $conn->query("SELECT r.*, p.name AS product_name FROM reviews r JOIN products p ON r.product_id=p.id WHERE r.user_id=$user_id ORDER BY r.created_at DESC LIMIT $reviewsPerPage OFFSET $reviewOffset");

// ================= 쿠폰 페이지네이션 =================
$couponsPerPage = 5;
$couponPage = isset($_GET['coupon_page']) ? max(1, intval($_GET['coupon_page'])) : 1;
$couponOffset = ($couponPage - 1) * $couponsPerPage;
$totalCoupons = $conn->query("SELECT COUNT(*) as cnt FROM user_coupons WHERE user_id=$user_id")->fetch_assoc()['cnt'];
$totalCouponPages = ceil($totalCoupons / $couponsPerPage);
$myCoupons = $conn->query("SELECT uc.id, c.code, c.discount_type, c.value, c.min_order, c.expire_date, uc.is_used 
                           FROM user_coupons uc 
                           JOIN coupons c ON uc.coupon_id=c.id 
                           WHERE uc.user_id=$user_id
                           ORDER BY uc.id DESC
                           LIMIT $couponsPerPage OFFSET $couponOffset");

// ================= 배송지 =================
$addresses = $conn->query("SELECT * FROM user_addresses WHERE user_id=$user_id ORDER BY is_default DESC, id DESC");

// ================= 저장된 카드 =================
$cards = $conn->query("SELECT * FROM user_cards WHERE user_id=$user_id ORDER BY created_at DESC");

// ================= 회원 등급 =================
$levelInfo = null;
if (!empty($user_info['membership_level'])) {
    $stmt = $conn->prepare("SELECT * FROM membership_levels WHERE name=? LIMIT 1");
    $stmt->bind_param("s", $user_info['membership_level']);
    $stmt->execute();
    $levelInfo = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

// ✅ 탭 자동 활성화
if (isset($_GET['order_page'])) {
    $activeTab = "orders";
} elseif (isset($_GET['review_page'])) {
    $activeTab = "reviews";
} elseif (isset($_GET['coupon_page'])) {
    $activeTab = "coupons";
} elseif (isset($_GET['address'])) {
    $activeTab = "addresses";
} elseif (isset($_GET['cards'])) {
    $activeTab = "cards";
} else {
    $activeTab = "profile";
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, user-scalable=no">
  <title>마이페이지 - 루미노아</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="css/styles.css">
  <script src="https://js.stripe.com/v3/"></script>
</head>
<body>
<?php include "php/header.php"; ?>
<div class="container py-5">
  <h2>마이페이지</h2>

  <ul class="nav nav-tabs">
    <li class="nav-item"><a class="nav-link <?= $activeTab=="profile"?"active":"" ?>" data-bs-toggle="tab" href="#profile">내 정보</a></li>
    <li class="nav-item"><a class="nav-link <?= $activeTab=="orders"?"active":"" ?>" data-bs-toggle="tab" href="#orders">주문 내역</a></li>
    <li class="nav-item"><a class="nav-link <?= $activeTab=="reviews"?"active":"" ?>" data-bs-toggle="tab" href="#reviews">내 리뷰</a></li>
    <li class="nav-item"><a class="nav-link <?= $activeTab=="points"?"active":"" ?>" data-bs-toggle="tab" href="#points">포인트/등급</a></li>
    <li class="nav-item"><a class="nav-link <?= $activeTab=="coupons"?"active":"" ?>" data-bs-toggle="tab" href="#coupons">내 쿠폰함</a></li>
    <li class="nav-item"><a class="nav-link <?= $activeTab=="addresses"?"active":"" ?>" data-bs-toggle="tab" href="#addresses">배송지 관리</a></li>
    <li class="nav-item"><a class="nav-link <?= $activeTab=="cards"?"active":"" ?>" data-bs-toggle="tab" href="#cards">내 카드 관리</a></li>
  </ul>

  <div class="tab-content p-3 border border-top-0">
    <!-- 내 정보 -->
    <div class="tab-pane fade <?= $activeTab=="profile"?"show active":"" ?>" id="profile">
      <form method="post" action="php/update_profile.php">
        <div class="mb-3"><label>이름</label><input type="text" class="form-control" value="<?= htmlspecialchars($user_info['name']) ?>" readonly></div>
        <div class="mb-3"><label>이메일</label><input type="email" class="form-control" value="<?= htmlspecialchars($user_info['email']) ?>" readonly></div>
        <div class="mb-3"><label>전화번호</label><input type="tel" class="form-control" value="<?= htmlspecialchars($user_info['phone'] ?? '') ?>" readonly></div>
        <div class="mb-3"><label>생일</label><input type="text" class="form-control" value="<?= htmlspecialchars($user_info['birthday'] ?? '') ?>" readonly></div>
        <div class="mb-3"><label>비밀번호 변경</label><input type="password" name="password" class="form-control" placeholder="변경 시 입력"></div>
        <button class="btn btn-primary">비밀번호 변경</button>
      </form>
    </div>

    <!-- 주문 내역 -->
    <div class="tab-pane fade <?= $activeTab=="orders"?"show active":"" ?>" id="orders">
      <h5>주문 내역</h5>
      <?php if ($totalOrders > 0): ?>
      <div class="table-responsive">
        <table class="table table-hover align-middle">
          <thead class="table-light">
            <tr>
              <th>번호</th>
              <th>총액</th>
              <th>할인</th>
              <th>포인트</th>
              <th>최종</th>
              <th>날짜</th>
            </tr>
          </thead>
          <tbody>
            <?php while($o=$orders->fetch_assoc()): ?>
            <tr onclick="location.href='order_detail.php?id=<?= $o['id'] ?>'" style="cursor:pointer;">
              <td>#<?= $o['id'] ?></td>
              <td>₩<?= number_format($o['total_price']) ?></td>
              <td>-₩<?= number_format($o['discount']) ?></td>
              <td>-₩<?= number_format($o['used_points']) ?></td>
              <td class="text-success fw-bold">₩<?= number_format($o['final_price']) ?></td>
              <td><?= $o['created_at'] ?></td>
            </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      </div>
      <nav>
        <ul class="pagination">
          <?php for($i=1; $i<=$totalOrderPages; $i++): ?>
            <li class="page-item <?= ($i==$orderPage)?'active':'' ?>">
              <a class="page-link" href="?order_page=<?= $i ?>"><?= $i ?></a>
            </li>
          <?php endfor; ?>
        </ul>
      </nav>
      <?php else: ?><p>주문 내역 없음</p><?php endif; ?>
    </div>

    <!-- 리뷰 -->
    <div class="tab-pane fade <?= $activeTab=="reviews"?"show active":"" ?>" id="reviews">
      <h5>내 리뷰</h5>
      <?php if ($totalReviews > 0): ?>
        <?php while($r=$reviews->fetch_assoc()): ?>
          <div class="border p-3 mb-2"><strong><?= htmlspecialchars($r['product_name']) ?></strong> ★<?= $r['rating'] ?><br><?= nl2br(htmlspecialchars($r['content'])) ?></div>
        <?php endwhile; ?>
        <nav>
          <ul class="pagination">
            <?php for($i=1; $i<=$totalReviewPages; $i++): ?>
              <li class="page-item <?= ($i==$reviewPage)?'active':'' ?>">
                <a class="page-link" href="?review_page=<?= $i ?>"><?= $i ?></a>
              </li>
            <?php endfor; ?>
          </ul>
        </nav>
      <?php else: ?><p>작성한 리뷰 없음</p><?php endif; ?>
    </div>

    <!-- 포인트/등급 -->
    <div class="tab-pane fade <?= $activeTab=="points"?"show active":"" ?>" id="points">
      <p>포인트: <?= number_format($user_info['points']) ?>P</p>
      <p>누적 구매액: ₩<?= number_format($user_info['total_spent']) ?></p>
      <p>
        등급: 
        <?php
          $levelName = $levelInfo ? htmlspecialchars($levelInfo['name']) : "Normal";
          $color = $levelInfo && !empty($levelInfo['badge_color']) ? $levelInfo['badge_color'] : "#999";
        ?>
        <span class="badge-level" style="background:<?= $color ?>;"><?= $levelName ?></span>
      </p>
    </div>

    <!-- 내 쿠폰 -->
    <div class="tab-pane fade <?= $activeTab=="coupons"?"show active":"" ?>" id="coupons">
      <h5>보유 쿠폰</h5>
      <?php if ($totalCoupons > 0): ?>
        <table class="table">
          <tr><th>쿠폰명</th><th>혜택</th><th>최소 주문</th><th>만료일</th><th>상태</th></tr>
          <?php while($c=$myCoupons->fetch_assoc()): ?>
          <tr>
            <td><?= htmlspecialchars($c['code']) ?></td>
            <td><?= $c['discount_type']=='percent'?"{$c['value']}%" : "₩".number_format($c['value']) ?></td>
            <td>₩<?= number_format($c['min_order']) ?></td>
            <td><?= $c['expire_date'] ?: "제한 없음" ?></td>
            <td><?= $c['is_used']?"사용됨":"사용 가능" ?></td>
          </tr>
          <?php endwhile; ?>
        </table>
        <nav>
          <ul class="pagination">
            <?php for($i=1; $i<=$totalCouponPages; $i++): ?>
              <li class="page-item <?= ($i==$couponPage)?'active':'' ?>">
                <a class="page-link" href="?coupon_page=<?= $i ?>"><?= $i ?></a>
              </li>
            <?php endfor; ?>
          </ul>
        </nav>
      <?php else: ?><p>보유한 쿠폰 없음</p><?php endif; ?>
    </div>

    <!-- 배송지 관리 -->
    <div class="tab-pane fade <?= $activeTab=="addresses"?"show active":"" ?>" id="addresses">
      <h5>배송지 관리</h5>
      <?php if ($addresses->num_rows > 0): ?>
        <ul class="list-group mb-3">
          <?php while($a=$addresses->fetch_assoc()): ?>
            <li class="list-group-item d-flex justify-content-between align-items-center">
              <div>
                <strong><?= htmlspecialchars($a['recipient']) ?></strong> (<?= htmlspecialchars($a['phone']) ?>)<br>
                <?= htmlspecialchars($a['postal_code']) ?> <?= htmlspecialchars($a['address_line1']) ?> <?= htmlspecialchars($a['address_line2']) ?>
                <?php if ($a['is_default']): ?><span class="badge bg-success">기본</span><?php endif; ?>
              </div>
              <div><a href="php/address_delete.php?id=<?= $a['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('정말 삭제하시겠습니까?');">삭제</a></div>
            </li>
          <?php endwhile; ?>
        </ul>
      <?php else: ?><p>등록된 배송지가 없습니다.</p><?php endif; ?>

      <!-- 배송지 추가 -->
      <button class="btn btn-sm btn-success mb-3" data-bs-toggle="collapse" data-bs-target="#addAddressForm">+ 배송지 추가</button>
      <div id="addAddressForm" class="collapse">
        <form method="post" action="php/address_add.php" class="border p-3 mb-3">
          <div class="mb-2"><label>받는 사람</label><input type="text" name="recipient" class="form-control" placeholder="홍길동" required></div>
          <div class="mb-2"><label>전화번호</label><input type="tel" name="phone" class="form-control" placeholder="010-1234-5678" required></div>
          <div class="mb-2">
            <label>우편번호</label>
            <div class="input-group">
              <input type="text" id="postcode" name="postal_code" class="form-control" readonly required>
              <button type="button" class="btn btn-outline-primary" onclick="execDaumPostcode()">주소 검색</button>
            </div>
          </div>
          <div class="mb-2"><label>기본 주소</label><input type="text" id="address" name="address_line1" class="form-control" readonly required></div>
          <div class="mb-2"><label>상세 주소</label><input type="text" id="detailAddress" name="address_line2" class="form-control" placeholder="상세 주소 입력"></div>
          <div class="form-check mb-2">
            <input type="checkbox" class="form-check-input" name="is_default" value="1"><label class="form-check-label">기본 배송지로 설정</label>
          </div>
          <button class="btn btn-primary w-100">저장</button>
        </form>
      </div>
    </div>

    <!-- 내 카드 관리 -->
    <div class="tab-pane fade <?= $activeTab=="cards"?"show active":"" ?>" id="cards">
      <h5>내 카드 관리</h5>
      <?php if ($cards->num_rows > 0): ?>
        <ul class="list-group mb-3">
          <?php while($c=$cards->fetch_assoc()): ?>
            <li class="list-group-item d-flex justify-content-between align-items-center">
              <div>
                <?= ucfirst($c['brand']) ?> **** <?= $c['last4'] ?> (<?= $c['exp_month'] ?>/<?= $c['exp_year'] ?>)
              </div>
              <div>
                <a href="php/card_delete.php?id=<?= $c['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('정말 삭제하시겠습니까?');">삭제</a>
              </div>
            </li>
          <?php endwhile; ?>
        </ul>
      <?php else: ?><p>저장된 카드가 없습니다.</p><?php endif; ?>

      <!-- 새 카드 추가 -->
      <div class="card border p-3">
        <h6>새 카드 추가</h6>
        <form method="post" action="php/card_add.php" id="add-card-form">
          <div class="mb-3">
            <label>카드 번호</label>
            <div id="card-number" class="form-control"></div>
          </div>
          <div class="row">
            <div class="col-md-6 mb-3">
              <label>만료일</label>
              <div id="card-expiry" class="form-control"></div>
            </div>
            <div class="col-md-6 mb-3">
              <label>CVC</label>
              <div id="card-cvc" class="form-control"></div>
            </div>
          </div>
          <div id="card-errors" class="text-danger small mb-2"></div>
          <button type="submit" class="btn btn-primary">카드 추가하기</button>
        </form>
      </div>
    </div>
  </div>
</div>
<?php include "php/footer.php"; ?>

<!-- 다음 주소 검색 API -->
<script src="//t1.daumcdn.net/mapjsapi/bundle/postcode/prod/postcode.v2.js"></script>
<script>
function execDaumPostcode() {
  new daum.Postcode({
    oncomplete: function(data) {
      document.getElementById("postcode").value = data.zonecode;
      document.getElementById("address").value = data.address;
      document.getElementById("detailAddress").focus();
    }
  }).open();
}
</script>

<!-- Stripe 카드 추가 -->
<script>
var stripe = Stripe("pk_test_당신의PublishableKey");
var elements = stripe.elements();
var style = { base: { fontSize: '16px', color: '#32325d' }};
var cardNumber = elements.create('cardNumber', { style: style }); cardNumber.mount('#card-number');
var cardExpiry = elements.create('cardExpiry', { style: style }); cardExpiry.mount('#card-expiry');
var cardCvc = elements.create('cardCvc', { style: style }); cardCvc.mount('#card-cvc');

var form = document.getElementById('add-card-form');
form.addEventListener('submit', function(e) {
  e.preventDefault();
  stripe.createToken(cardNumber).then(function(result) {
    if (result.error) {
      document.getElementById('card-errors').textContent = result.error.message;
    } else {
      var hiddenInput = document.createElement('input');
      hiddenInput.type = 'hidden';
      hiddenInput.name = 'stripeToken';
      hiddenInput.value = result.token.id;
      form.appendChild(hiddenInput);
      form.submit();
    }
  });
});
</script>
</body>
</html>