<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] != 0) {
    header("Location: ../index.php");
    exit;
}
include "../php/db.php";

// 오늘 매출
$today = date("Y-m-d");
$stmt = $conn->prepare("SELECT SUM(final_price) as total FROM orders WHERE DATE(created_at)=?");
$stmt->bind_param("s", $today);
$stmt->execute();
$todaySales = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
$stmt->close();

// 이번 달 매출
$thisMonth = date("Y-m");
$stmt = $conn->prepare("SELECT SUM(final_price) as total FROM orders WHERE DATE_FORMAT(created_at, '%Y-%m')=?");
$stmt->bind_param("s", $thisMonth);
$stmt->execute();
$monthSales = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
$stmt->close();

// 총 매출
$totalSales = $conn->query("SELECT SUM(final_price) as total FROM orders")->fetch_assoc()['total'] ?? 0;

// 신규 주문 (오늘 주문 수)
$stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM orders WHERE DATE(created_at)=?");
$stmt->bind_param("s", $today);
$stmt->execute();
$newOrders = $stmt->get_result()->fetch_assoc()['cnt'] ?? 0;
$stmt->close();

// 회원 수
$members = $conn->query("SELECT COUNT(*) as cnt FROM users")->fetch_assoc()['cnt'] ?? 0;

// 리뷰 수
$reviews = $conn->query("SELECT COUNT(*) as cnt FROM reviews")->fetch_assoc()['cnt'] ?? 0;

// 월별 매출 (최근 6개월)
$salesData = [];
$labels = [];
for ($i=5; $i>=0; $i--) {
    $month = date("Y-m", strtotime("-$i month"));
    $labels[] = date("n월", strtotime($month));
    $stmt = $conn->prepare("SELECT SUM(final_price) as total FROM orders WHERE DATE_FORMAT(created_at, '%Y-%m')=?");
    $stmt->bind_param("s", $month);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $salesData[] = $row['total'] ?? 0;
    $stmt->close();
}

// 주문 상태 분포
$statusResult = $conn->query("SELECT status, COUNT(*) as cnt FROM orders GROUP BY status");
$statusLabels = [];
$statusCounts = [];
while($row = $statusResult->fetch_assoc()){
    $statusLabels[] = $row['status'];
    $statusCounts[] = $row['cnt'];
}

// 카테고리별 상품 수
$catResult = $conn->query("
  SELECT c.name as category, COUNT(p.id) as cnt 
  FROM categories c 
  LEFT JOIN products p ON c.id = p.category_id 
  GROUP BY c.id
");
$catLabels = [];
$catCounts = [];
while($row = $catResult->fetch_assoc()){
    $catLabels[] = $row['category'];
    $catCounts[] = $row['cnt'];
}

// 카테고리별 상품 수, 재고, 평균가격
$catResult = $conn->query("
  SELECT c.name as category, 
         COUNT(p.id) as product_count, 
         COALESCE(SUM(p.stock),0) as total_stock,
         COALESCE(ROUND(AVG(p.price)),0) as avg_price
  FROM categories c 
  LEFT JOIN products p ON c.id = p.category_id 
  GROUP BY c.id
");

$catLabels = [];
$productCounts = [];
$totalStocks = [];
$avgPrices = [];

while($row = $catResult->fetch_assoc()){
    $catLabels[] = $row['category'];
    $productCounts[] = $row['product_count'];
    $totalStocks[] = $row['total_stock'];
    $avgPrices[] = $row['avg_price'];
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
  <meta charset="UTF-8">
  <title>관리자 대시보드 - 루미노아</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="../css/admin.css">
</head>
<body>
  <!-- 사이드바 -->
  <div class="overlay" onclick="toggleSidebar()"></div>
  <?php include "sidebar.php"; ?>

  <div class="admin-content">
    <div class="top-bar">
      <button class="sidebar-toggle" onclick="toggleSidebar()">☰</button>
      <h1>관리자 대시보드</h1>
      <span class="dark-toggle" onclick="toggleDarkMode()"><i class="fa-solid fa-moon"></i></span>
    </div>

    <p>안녕하세요, <strong><?= htmlspecialchars($_SESSION['user_name']) ?></strong>님 👋</p>
    
    <!-- 요약 카드 -->
    <div class="card-box">
      <div class="card"><i class="fa-solid fa-sack-dollar"></i><h5>오늘 매출</h5><p>₩<?= number_format($todaySales) ?></p></div>
      <div class="card"><i class="fa-solid fa-calendar"></i><h5>이번 달 매출</h5><p>₩<?= number_format($monthSales) ?></p></div>
      <div class="card"><i class="fa-solid fa-coins"></i><h5>총 매출</h5><p>₩<?= number_format($totalSales) ?></p></div>
      <div class="card"><i class="fa-solid fa-cart-shopping"></i><h5>신규 주문</h5><p><?= $newOrders ?>건</p></div>
      <div class="card"><i class="fa-solid fa-users"></i><h5>회원 수</h5><p><?= $members ?>명</p></div>
      <div class="card"><i class="fa-solid fa-comment"></i><h5>리뷰</h5><p><?= $reviews ?>개</p></div>
    </div>

    <!-- 차트 섹션 -->
    <div class="chart-container">
      <h5>월별 매출 현황</h5>
      <canvas id="salesChart"></canvas>
    </div>

    <div class="chart-container">
      <h5>주문 상태 분포</h5>
      <canvas id="statusChart"></canvas>
    </div>

    <div class="chart-container">
      <h5>카테고리별 상품 수</h5>
      <canvas id="categoryChart"></canvas>
    </div>
  </div>

<script>
function toggleSidebar(){
  document.querySelector(".sidebar").classList.toggle("active");
  document.querySelector(".overlay").classList.toggle("active");
}
function toggleDarkMode(){
  document.body.classList.toggle("dark");
}

// 월별 매출
new Chart(document.getElementById('salesChart'),{
  type:'line',
  data:{
    labels: <?= json_encode($labels) ?>,
    datasets:[{
      label:'매출',
      data: <?= json_encode($salesData) ?>,
      borderColor:'#D4AF37',
      backgroundColor:'rgba(212,175,55,0.2)',
      fill:true,
      tension:0.4,
      pointBackgroundColor:'#D4AF37'
    }]
  },
  options:{
    responsive:true,
    plugins:{ legend:{ display:false } },
    scales:{ y:{ ticks:{ callback: value => '₩'+value.toLocaleString() } } }
  }
});

// 주문 상태 분포
new Chart(document.getElementById('statusChart'),{
  type:'doughnut',
  data:{
    labels: <?= json_encode($statusLabels) ?>,
    datasets:[{
      data: <?= json_encode($statusCounts) ?>,
      backgroundColor:['#6c757d','#0dcaf0','#ffc107','#198754','#dc3545']
    }]
  },
  options:{ responsive:true }
});

// 카테고리별 지표 (복합 Bar/Line Chart)
new Chart(document.getElementById('categoryChart'),{
  type:'bar',
  data:{
    labels: <?= json_encode($catLabels) ?>,
    datasets:[
      {
        label:'상품 수',
        data: <?= json_encode($productCounts) ?>,
        backgroundColor:'#4f46e5'
      },
      {
        label:'총 재고',
        data: <?= json_encode($totalStocks) ?>,
        backgroundColor:'#0dcaf0'
      },
      {
        label:'평균 가격',
        data: <?= json_encode($avgPrices) ?>,
        type:'line',                 // 라인 차트로 표시
        borderColor:'#D4AF37',
        backgroundColor:'rgba(212,175,55,0.2)',
        yAxisID:'y2',                // 보조축
        tension:0.3
      }
    ]
  },
  options:{
    responsive:true,
    interaction:{ mode:'index', intersect:false },
    plugins:{ legend:{ position:'top' } },
    scales:{
      y:{ 
        beginAtZero:true, 
        title:{ display:true, text:'상품 수 / 재고' }
      },
      y2:{
        beginAtZero:true,
        position:'right',
        grid:{ drawOnChartArea:false },
        title:{ display:true, text:'평균 가격(₩)' },
        ticks:{ callback: v=>'₩'+v.toLocaleString() }
      }
    }
  }
});
</script>
</body>
</html>