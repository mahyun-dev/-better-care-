<?php
session_start();
include "php/db.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT id, name, password FROM users WHERE email=? LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        header("Location: index.php");
        exit;
    } else {
        $error = "이메일 또는 비밀번호가 올바르지 않습니다.";
    }
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, user-scalable=no">
  <title>로그인 - 루미노아</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="css/styles.css">
</head>
<body>
<?php include "php/header.php"; ?>

<div class="container d-flex justify-content-center align-items-center" style="min-height:70vh;">
  <div class="login-wrapper">
    <div class="login-box">
      <h2>로그인</h2>
      <?php if(isset($error)) echo "<p style='color:red; text-align:center;'>$error</p>"; ?>
      <form method="post">
        <div class="mb-3">
          <label class="form-label">이메일</label>
          <input type="email" name="email" class="form-control" required>
        </div>

        <div class="mb-3">
          <label class="form-label">비밀번호</label>
          <input type="password" name="password" class="form-control" required>
        </div>

        <button type="submit" class="btn btn-luminoa w-100">로그인</button>
      </form>
      <div class="extra-links">
        <p>아직 회원이 아니신가요? <a href="register.php" class="text-luminoa fw-bold">회원가입</a></p>
      </div>
    </div>
  </div>
</div>

<?php include "php/footer.php"; ?>
</body>
</html>