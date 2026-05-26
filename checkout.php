<?php
session_start();
include "php/db.php";

// ✅ Stripe 라이브러리 불러오기
require __DIR__ . "/stripe/init.php";
\Stripe\Stripe::setApiKey("sk_test_51S1IJCDiZhG2gj01gpNrAZaFszMtcF6yKLAWOrVTa5UktLDFnA9F2td4zUVkNtD2azxF8NcheVwkUpQeJoWvIYNg00Vlkv1Oyw"); // Secret Key

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
$user_id = $_SESSION['user_id'];

// ✅ 장바구니 불러오기
$sql = "SELECT c.id as cart_id, p.id as product_id, p.name, p.price, c.quantity, 
               c.option_name, c.option_price
        FROM cart c
        JOIN products p ON c.product_id = p.id
        WHERE c.user_id=?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$cart = $stmt->get_result();
$stmt->close();

$total_price = 0;
$cart_items = [];
while ($row = $cart->fetch_assoc()) {
    $row['subtotal'] = ($row['price'] + $row['option_price']) * $row['quantity'];
    $total_price += $row['subtotal'];
    $cart_items[] = $row;
}

$discount = 0;
$used_points = 0;
$final_price = $total_price;
$msg = "";

// ✅ 유저 보유 포인트
$user = $conn->query("SELECT points FROM users WHERE id=$user_id")->fetch_assoc();
$user_points = intval($user['points']);

// ✅ 유저 보유 쿠폰
$coupons = $conn->query("
    SELECT c.*
    FROM user_coupons uc
    JOIN coupons c ON uc.coupon_id = c.id
    WHERE uc.user_id=$user_id
      AND (c.expire_date IS NULL OR c.expire_date >= NOW())
      AND uc.is_used = 0
");

// ✅ 사용자 저장 카드 목록
$cards = $conn->query("SELECT * FROM user_cards WHERE user_id=$user_id");

// ================= Stripe 결제 처리 =================
if (isset($_POST['action']) && $_POST['action'] === 'checkout') {
    try {
        $address_id = intval($_POST['address_id']);
        $addr = $conn->query("SELECT * FROM user_addresses WHERE id=$address_id AND user_id=$user_id")->fetch_assoc();
        if (!$addr) throw new Exception("배송지를 선택해주세요.");

        // ✅ 쿠폰/포인트 반영
        $coupon_id = $_POST['coupon_id'] ?? "";
        $use_points = intval($_POST['use_points'] ?? 0);

        if ($coupon_id) {
         $coupon = $conn->query("SELECT c.* FROM user_coupons uc JOIN coupons c ON uc.coupon_id = c.id WHERE uc.user_id=$user_id AND c.id=$coupon_id AND uc.is_used=0")->fetch_assoc();
            if ($coupon) {
                if ($coupon['discount_type'] == 'percent') {
                    $discount = floor($total_price * ($coupon['value'] / 100));
                } else {
                    $discount = intval($coupon['value']);
                }
            }
        }
        if ($use_points > $user_points) $use_points = $user_points;
        $used_points = $use_points;

        $final_price = $total_price - $discount - $used_points;
        if ($final_price < 0) $final_price = 0;

        // ✅ Stripe 결제 실행
        if (!empty($_POST['saved_card_id'])) {
            $card_id = intval($_POST['saved_card_id']);
            $card = $conn->query("SELECT * FROM user_cards WHERE id=$card_id AND user_id=$user_id")->fetch_assoc();
            if (!$card) throw new Exception("선택한 카드가 없습니다.");

            $paymentMethodId = $card['stripe_payment_method_id'];
            $paymentIntent = \Stripe\PaymentIntent::create([
                'amount' => $final_price,
                'currency' => 'usd',
                'payment_method' => $paymentMethodId,
                'confirm' => true,
                'automatic_payment_methods' => ['enabled' => true, 'allow_redirects' => 'never']
            ]);
        } else {
            if (empty($_POST['payment_method_id'])) {
                throw new Exception("결제 수단이 없습니다.");
            }
            $paymentMethodId = $_POST['payment_method_id'];
            $paymentIntent = \Stripe\PaymentIntent::create([
                'amount' => $final_price,
                'currency' => 'usd',
                'payment_method' => $paymentMethodId,
                'confirm' => true,
                'automatic_payment_methods' => ['enabled' => true, 'allow_redirects' => 'never']
            ]);

            if (!empty($_POST['save_card'])) {
                $pm = \Stripe\PaymentMethod::retrieve($paymentMethodId);
                $conn->query("INSERT INTO user_cards (user_id, stripe_payment_method_id, brand, last4, exp_month, exp_year) 
                              VALUES (
                                $user_id,
                                '".$pm->id."',
                                '".$pm->card->brand."',
                                '".$pm->card->last4."',
                                ".$pm->card->exp_month.",
                                ".$pm->card->exp_year."
                              )");
            }
        }

        if ($paymentIntent->status !== "succeeded") {
            throw new Exception("결제 실패. 상태: ".$paymentIntent->status);
        }

        // ✅ 주문 저장
        $recipient   = $addr['recipient'];
        $phone       = $addr['phone'];
        $postal_code = $addr['postal_code'];
        $address1    = $addr['address_line1'];
        $address2    = $addr['address_line2'];

        $stmt = $conn->prepare("INSERT INTO orders 
            (user_id, total_price, discount, used_points, final_price, created_at,
             recipient, phone, postal_code, address_line1, address_line2, status) 
            VALUES (?,?,?,?,?,NOW(),?,?,?,?,?,?)");
        $payment_status = "대기";
        $stmt->bind_param(
            "iiiisssssss",
            $user_id, $total_price, $discount, $used_points, $final_price,
            $recipient, $phone, $postal_code, $address1, $address2, $payment_status
        );
        $stmt->execute();
        $order_id = $stmt->insert_id;
        $stmt->close();

        foreach ($cart_items as $item) {
            $stmt = $conn->prepare("INSERT INTO order_items 
                (order_id, product_id, quantity, price, option_name, option_price) 
                VALUES (?,?,?,?,?,?)");
            $stmt->bind_param(
                "iiiisi",
                $order_id,
                $item['product_id'],
                $item['quantity'],
                $item['price'],
                $item['option_name'],
                $item['option_price']
            );
            $stmt->execute();
            $stmt->close();
        }

        // ✅ 포인트 차감 & 쿠폰 사용 처리
        if ($used_points > 0) {
            $conn->query("UPDATE users SET points = points - $used_points WHERE id=$user_id");
        }
        if ($coupon_id) {
            $conn->query("UPDATE coupons SET used_count=used_count + 1 WHERE id=$coupon_id");
            $conn->query("UPDATE user_coupons SET is_used = 1 WHERE user_id = $user_id AND coupon_id = $coupon_id");
        }

        $conn->query("DELETE FROM cart WHERE user_id=$user_id");

        header("Location: order_detail.php?id=".$order_id);
        exit;

    } catch (Exception $e) {
        $msg = "❌ 결제 실패: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, user-scalable=no">
  <title>결제하기 - 루미노아</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="css/styles.css">
  <script src="https://js.stripe.com/v3/"></script>
</head>
<body>
<?php include "php/header.php"; ?>
<div class="container py-5">
  <h2>💳 결제하기</h2>
  <?php if ($msg): ?><div class="alert alert-info"><?= $msg ?></div><?php endif; ?>

  <!-- 결제 요약 -->
  <div class="card mb-4">
    <div class="card-body">
      <table class="table mb-0" id="summary-table">
        <tr><th>총 금액</th><td id="sum-total">₩<?= number_format($total_price) ?></td></tr>
        <tr><th>쿠폰 할인</th><td id="sum-coupon">-₩0</td></tr>
        <tr><th>포인트 사용</th><td id="sum-points">-₩0</td></tr>
        <tr><th>최종 결제금액</th><td class="fw-bold text-success" id="sum-final">₩<?= number_format($final_price) ?></td></tr>
      </table>
    </div>
  </div>

  <form method="post" id="payment-form">
    <input type="hidden" name="action" value="checkout">

    <!-- 배송지 -->
    <div class="mb-3">
      <label>배송지 선택</label>
      <select name="address_id" class="form-select" required>
        <option value="">배송지를 선택하세요</option>
        <?php
        $addresses = $conn->query("SELECT * FROM user_addresses WHERE user_id=$user_id ORDER BY is_default DESC, id DESC");
        while($a = $addresses->fetch_assoc()): ?>
          <option value="<?= $a['id'] ?>"><?= htmlspecialchars($a['recipient']) ?> <?= htmlspecialchars($a['address_line1']) ?></option>
        <?php endwhile; ?>
      </select>
    </div>

    <!-- 쿠폰 선택 -->
    <div class="mb-3">
      <label>쿠폰 선택</label>
      <select name="coupon_id" id="couponSelect" class="form-select">
        <option value="" data-type="none" data-value="0">사용 안함</option>
        <?php while($c = $coupons->fetch_assoc()): ?>
          <option value="<?= $c['id'] ?>" 
                  data-type="<?= $c['discount_type'] ?>" 
                  data-value="<?= $c['value'] ?>">
            <?= htmlspecialchars($c['code']) ?> 
            (<?= $c['discount_type']=='percent' ? $c['value'].'%' : number_format($c['value']).'원' ?> 할인)
          </option>
        <?php endwhile; ?>
      </select>
    </div>

    <!-- 포인트 사용 -->
    <div class="mb-3">
      <label>포인트 사용 (보유: <?= number_format($user_points) ?>)</label>
      <input type="number" name="use_points" id="usePoints" 
             class="form-control" min="0" max="<?= $user_points ?>" value="0">
    </div>

    <!-- 저장된 카드 -->
    <?php if ($cards->num_rows > 0): ?>
    <div class="mb-3">
      <label>저장된 카드</label>
      <select name="saved_card_id" class="form-select">
        <option value="">새 카드 입력</option>
        <?php while($c=$cards->fetch_assoc()): ?>
          <option value="<?= $c['id'] ?>">
            <?= ucfirst($c['brand']) ?> **** <?= $c['last4'] ?> (<?= $c['exp_month'] ?>/<?= $c['exp_year'] ?>)
          </option>
        <?php endwhile; ?>
      </select>
    </div>
    <?php endif; ?>

    <!-- 새 카드 입력 -->
    <div id="new-card-fields">
      <div id="card-number" class="form-control mb-2"></div>
      <div id="card-expiry" class="form-control mb-2"></div>
      <div id="card-cvc" class="form-control mb-2"></div>
      <div id="card-errors" class="text-danger"></div>
      <div class="form-check mt-2">
        <input type="checkbox" name="save_card" class="form-check-input" id="saveCard">
        <label for="saveCard" class="form-check-label">이 카드를 저장하기</label>
      </div>
    </div>

    <button type="submit" class="btn btn-success w-100">✅ 결제하기</button>
  </form>
</div>
<?php include "php/footer.php"; ?>

<script>
const totalPrice = <?= $total_price ?>;
const couponSelect = document.getElementById('couponSelect');
const usePointsInput = document.getElementById('usePoints');
const sumCoupon = document.getElementById('sum-coupon');
const sumPoints = document.getElementById('sum-points');
const sumFinal = document.getElementById('sum-final');

function updateSummary() {
  let couponDiscount = 0;
  let selected = couponSelect.options[couponSelect.selectedIndex];
  if (selected.value) {
    let type = selected.getAttribute('data-type');
    let value = parseInt(selected.getAttribute('data-value'));
    if (type === 'percent') {
      couponDiscount = Math.floor(totalPrice * (value / 100));
    } else {
      couponDiscount = value;
    }
  }
  let usedPoints = parseInt(usePointsInput.value) || 0;
  if (usedPoints < 0) usedPoints = 0;
  if (usedPoints > <?= $user_points ?>) usedPoints = <?= $user_points ?>;

  let final = totalPrice - couponDiscount - usedPoints;
  if (final < 0) final = 0;

  sumCoupon.textContent = "-₩" + couponDiscount.toLocaleString();
  sumPoints.textContent = "-₩" + usedPoints.toLocaleString();
  sumFinal.textContent = "₩" + final.toLocaleString();
}

couponSelect.addEventListener('change', updateSummary);
usePointsInput.addEventListener('input', updateSummary);
updateSummary();
</script>

<script>
var stripe = Stripe("pk_test_51S1IJCDiZhG2gj01RCQNX5wk6VXmzJq3aFQq3FmJJwIQJ0oRxIkKkLuvmh0zwAxihbf86GkpIuTdCYNs4qnxYJWL00dcdbWpuH");
var elements = stripe.elements();
var cardNumber = elements.create('cardNumber'); cardNumber.mount('#card-number');
var cardExpiry = elements.create('cardExpiry'); cardExpiry.mount('#card-expiry');
var cardCvc = elements.create('cardCvc'); cardCvc.mount('#card-cvc');

var form = document.getElementById('payment-form');
form.addEventListener('submit', function(event) {
  var savedCardSelect = document.querySelector("[name='saved_card_id']");
  if(savedCardSelect && savedCardSelect.value) return;

  event.preventDefault();
  stripe.createPaymentMethod({
    type: 'card',
    card: cardNumber
  }).then(function(result) {
    if (result.error) {
      document.getElementById('card-errors').textContent = result.error.message;
    } else {
      var hiddenInput = document.createElement('input');
      hiddenInput.type = 'hidden';
      hiddenInput.name = 'payment_method_id';
      hiddenInput.value = result.paymentMethod.id;
      form.appendChild(hiddenInput);
      form.submit();
    }
  });
});
</script>
</body>
</html>