<?php
http_response_code(404);
session_start();
include "php/db.php";
?>
<!DOCTYPE html>
<html lang="ko">
<head>
  <meta charset="UTF-8">
  <title>페이지를 찾을 수 없습니다 - 루미노아</title>
  <meta name="viewport" content="width=device-width, initial-scale=1, user-scalable=no">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="css/styles.css">
  <style>
    body {
      display: flex;
      align-items: center;
      justify-content: center;
      height: 100vh;
      margin: 0;
      font-family: 'Pretendard', sans-serif;
      text-align: center;
    }
    .error-box {
      background: linear-gradient(135deg, #64d2c3, #ffd166) !important; /* 민트-옐로우 */
      color: #fff !important;
      
      padding: 40px;
      border-radius: 15px;
      box-shadow: 0 5px 20px rgba(0,0,0,0.2);
    }
    .error-code {
      font-size: 6rem;
      font-weight: bold;
      margin-bottom: 20px;
    }
    .error-msg {
      font-size: 1.3rem;
      margin-bottom: 30px;
    }
    .btn-home {
      background: #fff;
      color: black;
      font-weight: bold;
      padding: 12px 25px;
      border-radius: 30px;
      text-decoration: none;
      transition: 0.3s;
    }
    .btn-home:hover {
      background: #f1f1f1;
      color: #f107a3;
    }
  </style>
</head>
<body>
  <?php include "php/header.php"; ?>
  <div class="error-box">
    <div class="error-code">404</div>
    <div class="error-msg">죄송합니다 😢<br>찾으시는 페이지가 존재하지 않습니다.</div>
    <a href="index.php" class="btn-home">🏠 메인으로 돌아가기</a>
  </div>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  <script src="js/navbar.js"></script>
</body>
</html>