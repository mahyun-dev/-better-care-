<?php
session_start();
include "php/db.php";

if(!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
$user_id = $_SESSION['user_id'];

$sql = "SELECT c.id as cart_id, p.image, p.name, p.price, c.quantity, c.option_name, c.option_price 
        FROM cart c 
        JOIN products p ON c.product_id=p.id 
        WHERE c.user_id=?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$items = $stmt->get_result();
$total = 0;
?>
<!DOCTYPE html>
<html lang="ko">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, user-scalable=no">
  <title>장바구니 - 루미노아</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="css/styles.css">
  <style>
    .cart-item {
      position: relative;
      display: flex;
      align-items: center;
      gap: 20px;
      padding: 20px;
      border: 1px solid #eee;
      background: #fff;
      border-radius: 12px;
      margin-bottom: 15px;
      box-shadow: 0 3px 10px rgba(0,0,0,0.05);
      transition: transform 0.2s ease;
    }
    .cart-item:hover { 
      transform: translateY(-3px); 
    }
    .cart-item img { 
      width: 90px; 
      height: 90px; 
      object-fit: cover; 
      border-radius: 8px;
   }
    .item-info { 
      flex: 1; 
   }
    .item-title { 
      font-weight: 600; 
      font-size: 1.1rem; 
      margin-bottom: 6px; 
    }
    .item-option { 
      font-size: 14px; 
      color: #777; 
      margin-bottom: 8px; 
    }
    .item-price { 
      font-size: 17px; 
      font-weight: bold; 
      color: #dc3545;
    }
    .item-price small { 
      display: block; 
      font-size: 13px; 
      color: #888; 
      font-weight: normal; 
    }
    .qty-control { 
      display: flex; 
      align-items: center; 
      gap: 8px; 
    }
    .qty-control button {
      width: 34px; 
      height: 34px;
      border: none; 
      border-radius: 50%;
      background: #f1f1f1; 
      font-weight: bold; 
      font-size: 18px;
      line-height: 1; 
      cursor: pointer;
      transition: background 0.2s;
    }
    .qty-control button:hover { 
      background: #e0e0e0; 
    }
    .qty-control span { 
      min-width: 28px; 
      text-align: center; 
      font-weight: 600; 
    }

    /* ❌ 삭제 버튼 우측 상단 */
    .cart-remove {
      position: absolute;
      top: 8px;
      right: 10px;
      font-size: 18px;
      font-weight: bold;
      color: #dc3545;
      text-decoration: none;
      cursor: pointer;
      transition: color 0.2s;
    }
    .cart-remove:hover { 
      color: #a71d2a; 
    }

    .cart-footer {
      padding: 25px;
      text-align: right;
      border-top: 2px solid #eee;
      margin-top: 25px;
    }
    .cart-footer h4 { 
      font-weight: bold; 
    }
    .btn-purple {
      background: linear-gradient(135deg, #64d2c3, #ffd166) !important;
      color: #fff; 
      border: none; 
      padding: 12px 25px;
      font-size: 1.1rem; 
      border-radius: 8px;
      transition: 0.2s;
    }
    .btn-purple:hover { 
      opacity: 0.9; 
      transform: translateY(-2px); 
    }
  </style>
</head>
<body>
<?php include "php/header.php"; ?>

<div class="container py-5">
  <h2 class="mb-4">🛒 장바구니</h2>

  <div id="cart-container">
    <?php if($items->num_rows > 0): ?>
      <?php while($row = $items->fetch_assoc()):
        $subtotal = ($row['price'] + $row['option_price']) * $row['quantity'];
        $total += $subtotal;
        $image = !empty($row['image']) ? htmlspecialchars($row['image']) : "img/logo.PNG";
      ?>
        <div class="cart-item" data-id="<?= $row['cart_id'] ?>">
          <!-- ❌ 삭제 버튼 -->
          <a href="php/cart_actions.php?remove=<?= $row['cart_id'] ?>" class="cart-remove">&times;</a>

          <img src="<?= $image ?>" alt="<?= htmlspecialchars($row['name']) ?>">
          <div class="item-info">
            <div class="item-title"><?= htmlspecialchars($row['name']) ?></div>
            <?php if(!empty($row['option_name'])): ?>
              <div class="item-option">옵션: <?= htmlspecialchars($row['option_name']) ?></div>
            <?php endif; ?>
            <div class="item-price">
              ₩<?= number_format($subtotal) ?>
              <small>(₩<?= number_format($row['price'] + $row['option_price']) ?>/개)</small>
            </div>
          </div>
          <div class="qty-control">
            <button class="qty-minus">−</button>
            <span class="qty"><?= $row['quantity'] ?></span>
            <button class="qty-plus">+</button>
          </div>
        </div>
      <?php endwhile; ?>

      <div class="cart-footer">
        <h4>총합: <span id="cart-total" class="text-success">₩<?= number_format($total) ?></span></h4>
        <a href="checkout.php" class="btn btn-purple btn-lg mt-3">주문하기</a>
      </div>
    <?php else: ?>
      <p class="text-muted">장바구니가 비어있습니다.</p>
    <?php endif; ?>
  </div>
</div>

<?php include "php/footer.php"; ?>

<script>
document.querySelectorAll(".cart-item").forEach(item => {
  const cartId = item.dataset.id;
  const qtyElem = item.querySelector(".qty");
  const priceElem = item.querySelector(".item-price");

  function updateCart(newQty) {
    if(newQty < 1) return;
    fetch("php/cart_actions.php", {
      method: "POST",
      headers: {"Content-Type": "application/x-www-form-urlencoded"},
      body: "action=update&cart_id=" + cartId + "&quantity=" + newQty
    }).then(res => res.json()).then(data => {
      if(data.success){
        qtyElem.textContent = newQty;
        priceElem.innerHTML = "₩" + data.subtotal.toLocaleString() +
          "<small>(₩" + (data.subtotal/newQty).toLocaleString() + "/개)</small>";
        document.getElementById("cart-total").textContent = "₩" + data.total.toLocaleString();
      }
    });
  }

  item.querySelector(".qty-minus").addEventListener("click", () => {
    let current = parseInt(qtyElem.textContent);
    updateCart(current - 1);
  });
  item.querySelector(".qty-plus").addEventListener("click", () => {
    let current = parseInt(qtyElem.textContent);
    updateCart(current + 1);
  });
});
</script>
</body>
</html>