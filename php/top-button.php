<!-- php/top-button.php -->
<button id="topBtn">▲</button>

<style>
  #topBtn {
    position: fixed;
    bottom: 20px;
    right: 20px;
    padding: 10px 15px;
    background: linear-gradient(135deg, #64d2c3, #ffd166);
    color: #fff;
    border: none;
    border-radius: 50%;
    cursor: pointer;
    display: none; /* 기본은 숨김 */
    z-index: 1000; /* 다른 요소보다 위에 */
  }
</style>

<script>
  // 스크롤 시 버튼 표시/숨김
  window.addEventListener("scroll", () => {
    const btn = document.getElementById("topBtn");
    if (window.scrollY > 200) {
      btn.style.display = "block";
    } else {
      btn.style.display = "none";
    }
  });

  // 버튼 클릭 시 상단 이동
  document.addEventListener("DOMContentLoaded", () => {
    document.getElementById("topBtn").addEventListener("click", () => {
      window.scrollTo({ top: 0, behavior: "smooth" });
    });
  });
</script>