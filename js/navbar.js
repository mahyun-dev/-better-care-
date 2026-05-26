window.addEventListener("scroll", function() {
  const navbar = document.querySelector(".navbar");
  if (!navbar) return; // navbar 없으면 그냥 종료 (에러 방지)

  if (window.scrollY > 50) {
    navbar.classList.add("scrolled");
  } else {
    navbar.classList.remove("scrolled");
  }
});