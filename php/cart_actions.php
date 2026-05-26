<?php
session_start();
include "db.php";

if(!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}
$user_id = $_SESSION['user_id'];

// ================= 장바구니 담기 =================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'add') {
    $product_id = intval($_POST['product_id']);
    $quantity = intval($_POST['quantity'] ?? 1);
    $options = $_POST['options'] ?? [];

    // 옵션 조합 텍스트 만들기
    $option_texts = [];
    $option_price = 0;
    foreach($options as $opt_id){
        $opt = $conn->query("SELECT value_name, add_price FROM option_values WHERE id=$opt_id")->fetch_assoc();
        if($opt){
            $option_texts[] = $opt['value_name'];
            $option_price += $opt['add_price'];
        }
    }
    $option_summary = implode(" / ", $option_texts);

    // 장바구니 담기
    $stmt = $conn->prepare("INSERT INTO cart (user_id, product_id, quantity, option_name, option_price) VALUES (?,?,?,?,?)");
    $stmt->bind_param("iiisi", $user_id, $product_id, $quantity, $option_summary, $option_price);
    $stmt->execute();
    $stmt->close();

    header("Location: ../cart.php");
    exit;
}

// ================= 장바구니 삭제 =================
if (isset($_GET['remove'])) {
    $cart_id = intval($_GET['remove']);
    $stmt = $conn->prepare("DELETE FROM cart WHERE id=? AND user_id=?");
    $stmt->bind_param("ii", $cart_id, $user_id);
    $stmt->execute();
    header("Location: ../cart.php");
    exit;
}

// ================= 수량 변경 (AJAX) =================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'update') {
    $cart_id  = intval($_POST['cart_id']);
    $quantity = max(1, intval($_POST['quantity']));

    // 수량 업데이트
    $stmt = $conn->prepare("UPDATE cart SET quantity=? WHERE id=? AND user_id=?");
    $stmt->bind_param("iii", $quantity, $cart_id, $user_id);
    $stmt->execute();
    $stmt->close();

    // 새 합계 계산
    $cart = $conn->query("SELECT c.id, p.price, c.quantity, c.option_price 
                          FROM cart c 
                          JOIN products p ON c.product_id=p.id 
                          WHERE c.user_id=$user_id");

    $total = 0; 
    $subtotal = 0;
    while($row = $cart->fetch_assoc()){
        $row_sub = ($row['price'] + $row['option_price']) * $row['quantity'];
        if($row['id'] == $cart_id) $subtotal = $row_sub;
        $total += $row_sub;
    }

    // JSON 응답 (AJAX에서 사용)
    echo json_encode([
        "success"  => true,
        "subtotal" => $subtotal,
        "total"    => $total
    ]);
    exit;
}
?>