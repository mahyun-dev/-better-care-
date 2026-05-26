<?php
session_start();
include "php/db.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $password_confirm = $_POST['password_confirm'];
    $phone = trim($_POST['phone']);
    $birthday = !empty($_POST['birthday']) ? $_POST['birthday'] : null;

    // 비밀번호 확인
    if ($password !== $password_confirm) {
        $error = "비밀번호와 비밀번호 확인이 일치하지 않습니다.";
    } else {
        $hashed_pw = password_hash($password, PASSWORD_DEFAULT);

        // 중복 이메일 체크
        $stmt = $conn->prepare("SELECT id FROM users WHERE email=? LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $exists = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($exists) {
            $error = "이미 사용 중인 이메일입니다.";
        } else {
            $stmt = $conn->prepare("
                INSERT INTO users (name, email, password, phone, birthday, created_at) 
                VALUES (?,?,?,?,?,NOW())
            ");
            $stmt->bind_param("sssss", $name, $email, $hashed_pw, $phone, $birthday);
            if ($stmt->execute()) {
                $new_user_id = $stmt->insert_id;
                $_SESSION['user_id'] = $new_user_id;
                $_SESSION['user_name'] = $name;

                // 웰컴 쿠폰 자동 지급
                $welcome_coupon = $conn->query("SELECT id FROM coupons WHERE code='WELCOME' LIMIT 1")->fetch_assoc();
                if ($welcome_coupon) {
                    $stmt2 = $conn->prepare("INSERT INTO user_coupons (user_id, coupon_id) VALUES (?, ?)");
                    $stmt2->bind_param("ii", $new_user_id, $welcome_coupon['id']);
                    $stmt2->execute();
                    $stmt2->close();
                }

                header("Location: index.php");
                exit;
            } else {
                $error = "회원가입 중 오류가 발생했습니다.";
            }
            $stmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, user-scalable=no">
  <title>회원가입 - 루미노아</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="css/styles.css">
</head>
<body>
<?php include "php/header.php"; ?>

<div class="container d-flex justify-content-center align-items-center" style="min-height:80vh;">
  <div class="login-wrapper w-100" style="max-width:480px;">
    <div class="login-box">
      <h2>회원가입</h2>
      <?php if(isset($error)) echo "<p style='color:red; text-align:center;'>$error</p>"; ?>
      <form method="post">
        <!-- 이름 -->
        <div class="mb-3">
          <label class="form-label">이름</label>
          <input type="text" name="name" class="form-control" required>
        </div>
        <!-- 이메일 -->
        <div class="mb-3">
          <label class="form-label">이메일</label>
          <input type="email" name="email" class="form-control" required>
        </div>
        <!-- 비밀번호 -->
        <div class="mb-3">
          <label class="form-label">비밀번호</label>
          <input type="password" name="password" class="form-control" required>
        </div>
        <!-- 비밀번호 확인 -->
        <div class="mb-3">
          <label class="form-label">비밀번호 확인</label>
          <input type="password" name="password_confirm" class="form-control" required>
        </div>
        <!-- 전화번호 -->
        <div class="mb-3">
          <label class="form-label">전화번호</label>
          <input type="tel" name="phone" class="form-control" placeholder="010-1234-5678" required>
        </div>
        <!-- 생일 -->
        <div class="mb-3">
          <label class="form-label">생일</label>
          <input type="date" name="birthday" class="form-control">
        </div>
        <!-- 버튼 -->
        <button type="submit" class="btn btn-luminoa w-100">회원가입</button>
      </form>
      <div class="extra-links">
        <p>이미 계정이 있으신가요? <a href="login.php" class="text-luminoa fw-bold">로그인</a></p>
      </div>
    </div>
  </div>
</div>

<?php include "php/footer.php"; ?>
</body>
</html>