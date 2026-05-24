/**
 * 더 나음 (better & care) - Main JavaScript
 * 보안: XSS 방지(textContent/createElement 사용, innerHTML 최소화)
 *            localStorage 데이터 검증, CSP 준수, 입력 새니타이징
 */
'use strict';

/* ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
   1. 유틸리티
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */

/** Toast 알림 */
function showToast(message, duration = 2800) {
  const toast = document.getElementById('toastMsg');
  if (!toast) return;
  toast.textContent = String(message);
  toast.classList.add('show');
  toast.removeAttribute('aria-hidden');
  clearTimeout(toast._timer);
  toast._timer = setTimeout(() => {
    toast.classList.remove('show');
    toast.setAttribute('aria-hidden', 'true');
  }, duration);
}

/** localStorage 안전 read/write */
const Storage = {
  get(key, fallback = null) {
    try {
      const raw = localStorage.getItem(key);
      if (raw === null) return fallback;
      return JSON.parse(raw);
    } catch { return fallback; }
  },
  set(key, value) {
    try { localStorage.setItem(key, JSON.stringify(value)); return true; }
    catch { return false; }
  },
  // key 화이트리스트 검증
  _allowedKeys: new Set(['bc_cart', 'bc_wishlist', 'bc_cartCount', 'bc_users', 'bc_session', 'bc_admin_data_v1']),
  safeGet(key, fallback = null) {
    if (!this._allowedKeys.has(key)) return fallback;
    return this.get(key, fallback);
  },
  safeSet(key, value) {
    if (!this._allowedKeys.has(key)) return false;
    return this.set(key, value);
  },
};

const ADMIN_STORE_KEY = 'bc_admin_data_v1';
let runtimeData = null;

function safeImageSrc(src) {
  const value = String(src || '').trim();
  if (/^data:image\/[a-zA-Z0-9.+-]+;base64,[A-Za-z0-9+/=]+$/.test(value)) return value;
  if (/^images\/[A-Za-z0-9_\-./]+$/.test(value)) return value;
  return '';
}

function buildRuntimeData() {
  const base = BETTER_CARE_DATA;
  const admin = Storage.safeGet(ADMIN_STORE_KEY, null);

  const quickNav = (admin && Array.isArray(admin.icons)
    ? admin.icons
        .filter(icon => icon && icon.visible)
        .slice(0, 7)
        .map((icon, idx) => ({
          id: String(icon.id || `admin-icon-${idx}`),
          href: '#',
          label: String(icon.name || '아이콘'),
          iconMode: icon.iconMode === '이미지' ? '이미지' : '문자',
          emoji: String(icon.icon || '•'),
          image: safeImageSrc(icon.image),
        }))
    : []);

  const bestCodi = (admin && Array.isArray(admin.products)
    ? admin.products
        .filter(product => product && product.status !== '숨김')
        .slice(0, 5)
        .map((product, idx) => {
          const digits = String(product.price ?? '').replace(/[^0-9]/g, '');
          const amount = digits ? parseInt(digits, 10) : 0;
          return {
            id: String(product.id || `admin-product-${idx}`),
            rank: idx + 1,
            emoji: '👕',
            image: safeImageSrc(product.image),
            name: String(product.name || `상품 ${idx + 1}`),
            price: `₩ ${amount.toLocaleString('ko-KR')}`,
            period: '/ 회',
          };
        })
    : []);

  const banners = (admin && Array.isArray(admin.banners)
    ? admin.banners
        .filter(banner => banner && banner.active)
        .slice(0, 6)
        .map((banner, idx) => ({
          id: String(banner.id || `admin-banner-${idx}`),
          name: String(banner.name || `배너 ${idx + 1}`),
          image: safeImageSrc(banner.image),
          period: String(banner.period || ''),
        }))
    : []);

  return {
    quickNav: quickNav.length > 0 ? quickNav : base.quickNav,
    bestCodi: bestCodi.length > 0 ? bestCodi : base.bestCodi,
    banners,
    categories: base.categories,
    careCards: base.careCards,
    occupations: base.occupations,
    specialCodi: base.specialCodi,
  };
}

/** 숫자 카운트업 애니메이션 */
function countUp(el, target, isDecimal = false, duration = 1800) {
  const start = performance.now();
  const step = (now) => {
    const elapsed = Math.min((now - start) / duration, 1);
    const eased = 1 - Math.pow(1 - elapsed, 3); // ease-out cubic
    const current = isDecimal
      ? (eased * target).toFixed(1)
      : Math.round(eased * target).toLocaleString('ko-KR');
    el.textContent = current;
    if (elapsed < 1) requestAnimationFrame(step);
  };
  requestAnimationFrame(step);
}

/** 엘리먼트 생성 헬퍼 */
function createElement(tag, attrs = {}, children = []) {
  const el = document.createElement(tag);
  for (const [key, val] of Object.entries(attrs)) {
    if (key === 'className') el.className = val;
    else if (key === 'textContent') el.textContent = val;  // XSS 안전
    else if (key === 'ariaLabel') el.setAttribute('aria-label', val);
    else if (key === 'role') el.setAttribute('role', val);
    else if (key === 'href') {
      // href는 '#' 또는 상대경로만 허용
      const safe = /^(#|\/|\.\/|[a-zA-Z0-9\-_.~/])[^<>"'`\s]*$/.test(val) ? val : '#';
      el.href = safe;
    }
    else el.setAttribute(key, val);
  }
  for (const child of children) {
    if (typeof child === 'string') el.appendChild(document.createTextNode(child));
    else if (child instanceof Node) el.appendChild(child);
  }
  return el;
}

/* ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
   2. 장바구니 상태 관리
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
const Cart = {
  items: [],
  init() {
    const saved = Storage.safeGet('bc_cart', []);
    // 배열인지 검증
    this.items = Array.isArray(saved) ? saved.filter(
      item => item && typeof item === 'object' && typeof item.id === 'string'
    ) : [];
    this.updateDisplay();
  },
  add(productId, productName) {
    // productId는 영숫자와 하이픈만 허용
    if (!/^[a-zA-Z0-9\-_]{1,32}$/.test(productId)) return;
    const existing = this.items.find(i => i.id === productId);
    if (existing) { existing.qty += 1; }
    else { this.items.push({ id: productId, qty: 1 }); }
    Storage.safeSet('bc_cart', this.items);
    this.updateDisplay();
    this.emitUpdated();
    showToast(`🛒 "${productName}" 장바구니에 담았어요!`);
  },
  removeOne(productId) {
    if (!/^[a-zA-Z0-9\-_]{1,32}$/.test(productId)) return;
    const idx = this.items.findIndex(i => i.id === productId);
    if (idx < 0) return;
    this.items[idx].qty -= 1;
    if (this.items[idx].qty <= 0) this.items.splice(idx, 1);
    Storage.safeSet('bc_cart', this.items);
    this.updateDisplay();
    this.emitUpdated();
  },
  clear() {
    this.items = [];
    Storage.safeSet('bc_cart', this.items);
    this.updateDisplay();
    this.emitUpdated();
  },
  get count() { return this.items.reduce((sum, i) => sum + i.qty, 0); },
  emitUpdated() {
    window.dispatchEvent(new CustomEvent('cart:updated'));
  },
  updateDisplay() {
    const el = document.getElementById('cartCount');
    if (!el) return;
    const count = this.count;
    el.textContent = count > 99 ? '99+' : String(count);
    el.style.display = count === 0 ? 'none' : 'flex';
  },
};

/* ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
   3. 위시리스트 상태 관리
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
const Wishlist = {
  ids: new Set(),
  init() {
    const saved = Storage.safeGet('bc_wishlist', []);
    const validated = Array.isArray(saved)
      ? saved.filter(id => typeof id === 'string' && /^[a-zA-Z0-9\-_]{1,32}$/.test(id))
      : [];
    this.ids = new Set(validated);
  },
  toggle(productId) {
    if (!/^[a-zA-Z0-9\-_]{1,32}$/.test(productId)) return false;
    const isWished = this.ids.has(productId);
    if (isWished) this.ids.delete(productId);
    else this.ids.add(productId);
    Storage.safeSet('bc_wishlist', [...this.ids]);
    return !isWished;
  },
  has(productId) { return this.ids.has(productId); },
};

/* ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
   4. 렌더러: 퀵 메뉴
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
function renderQuickNav() {
  const container = document.getElementById('quickNavList');
  if (!container || !runtimeData?.quickNav) return;

  container.textContent = '';

  const fragment = document.createDocumentFragment();

  for (const item of runtimeData.quickNav) {
    const li = document.createElement('li');
    li.setAttribute('role', 'listitem');

    const a = createElement('a', { className: 'quick-nav__item', href: item.href });

    const iconDiv = createElement('div', { className: 'quick-nav__icon', 'aria-hidden': 'true' });
    if (item.iconMode === '이미지' && safeImageSrc(item.image)) {
      const img = createElement('img', { className: 'quick-nav__icon-img', src: item.image, alt: '' });
      iconDiv.appendChild(img);
    } else {
      iconDiv.textContent = item.emoji;
    }

    const label = createElement('span', { className: 'quick-nav__label', textContent: item.label });

    a.appendChild(iconDiv);
    a.appendChild(label);
    li.appendChild(a);
    fragment.appendChild(li);
  }
  container.appendChild(fragment);
}

/* ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
   5. 렌더러: BEST 코디
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
function renderBestCodi() {
  const container = document.getElementById('bestCodiGrid');
  if (!container || !runtimeData?.bestCodi) return;

  container.textContent = '';

  const fragment = document.createDocumentFragment();

  for (const item of runtimeData.bestCodi) {
    const article = createElement('article', {
      className: 'product-card',
      role: 'listitem',
      'data-id': item.id,
      'data-animate': '',
      'aria-label': `${item.rank}위 ${item.name} ${item.price}${item.period}`,
    });

    // 순위 배지
    const rankClass = item.rank <= 3
      ? `product-card__rank product-card__rank--${item.rank}`
      : 'product-card__rank product-card__rank--other';
    const rankBadge = createElement('span', { className: rankClass });
    rankBadge.setAttribute('aria-hidden', 'true');
    rankBadge.textContent = String(item.rank);

    // 이미지 영역
    const imgDiv = createElement('div', { className: 'product-card__img', role: 'img', ariaLabel: `${item.name} 상품 이미지` });
    if (safeImageSrc(item.image)) {
      const img = createElement('img', { className: 'product-card__img-file', src: item.image, alt: '' });
      imgDiv.appendChild(img);
    } else {
      const emojiSpan = createElement('span', { 'aria-hidden': 'true' });
      emojiSpan.textContent = item.emoji;
      imgDiv.appendChild(emojiSpan);
    }

    // 찜하기 버튼
    const isWished = Wishlist.has(item.id);
    const wishBtn = createElement('button', {
      className: `product-card__wish${isWished ? ' active' : ''}`,
      type: 'button',
      ariaLabel: `${item.name} 찜하기`,
    });
    const heartSpan = document.createElement('span');
    heartSpan.setAttribute('aria-hidden', 'true');
    heartSpan.textContent = isWished ? '❤️' : '🤍';
    wishBtn.appendChild(heartSpan);
    wishBtn.addEventListener('click', (e) => {
      e.stopPropagation();
      const wished = Wishlist.toggle(item.id);
      wishBtn.classList.toggle('active', wished);
      heartSpan.textContent = wished ? '❤️' : '🤍';
      showToast(wished ? `❤️ "${item.name}" 찜 목록에 추가되었어요!` : `🤍 "${item.name}" 찜 목록에서 제거되었어요.`);
    });
    imgDiv.appendChild(wishBtn);

    // 상품 정보
    const infoDiv = document.createElement('div');
    infoDiv.className = 'product-card__info';

    const name = createElement('p', { className: 'product-card__name', textContent: item.name });
    const price = createElement('p', { className: 'product-card__price' });
    price.textContent = `${item.price} ${item.period}`;

    infoDiv.appendChild(name);
    infoDiv.appendChild(price);

    article.appendChild(rankBadge);
    article.appendChild(imgDiv);
    article.appendChild(infoDiv);

    // 카드 클릭 → 장바구니 추가 (실제 서비스에선 상품 상세로 이동)
    article.addEventListener('click', () => Cart.add(item.id, item.name));

    fragment.appendChild(article);
  }
  container.appendChild(fragment);
}

/* ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
   6. 렌더러: 카테고리
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
function renderCategories() {
  const container = document.getElementById('categoryList');
  if (!container || !runtimeData?.categories) return;
  const fragment = document.createDocumentFragment();

  for (const item of runtimeData.categories) {
    const li = document.createElement('li');
    li.setAttribute('role', 'listitem');

    const a = createElement('a', {
      className: 'category-item',
      href: item.href,
      ariaLabel: `${item.label} 카테고리 보기`,
    });
    if (item.isAll) a.classList.add('category-item--all');

    const iconDiv = createElement('div', { className: 'category-item__icon', 'aria-hidden': 'true' });
    iconDiv.textContent = item.emoji;

    const label = createElement('span', { className: 'category-item__label', textContent: item.label });

    a.appendChild(iconDiv);
    a.appendChild(label);
    li.appendChild(a);
    fragment.appendChild(li);
  }
  container.appendChild(fragment);
}

/* ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
   7. 렌더러: 케어 카드
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
function renderCareCards() {
  const container = document.getElementById('careCards');
  if (!container || !runtimeData?.careCards) return;
  const fragment = document.createDocumentFragment();

  for (const item of runtimeData.careCards) {
    const a = createElement('a', {
      className: 'care-card',
      href: item.href,
      role: 'listitem',
      'data-animate': '',
      ariaLabel: `${item.title} - ${item.desc}`,
    });

    const imgDiv = createElement('div', { className: `care-card__img ${item.colorClass}`, 'aria-hidden': 'true' });
    imgDiv.textContent = item.emoji;

    const infoDiv = document.createElement('div');
    infoDiv.className = 'care-card__info';

    const title = createElement('h3', { className: 'care-card__title', textContent: item.title });
    const desc  = createElement('p',  { className: 'care-card__desc',  textContent: item.desc  });

    infoDiv.appendChild(title);
    infoDiv.appendChild(desc);
    a.appendChild(imgDiv);
    a.appendChild(infoDiv);
    fragment.appendChild(a);
  }
  container.appendChild(fragment);
}

/* ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
   8. 렌더러: 직종별
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
function renderOccupations() {
  const container = document.getElementById('occupationList');
  if (!container || !runtimeData?.occupations) return;
  const fragment = document.createDocumentFragment();

  for (const item of runtimeData.occupations) {
    const li = document.createElement('li');
    li.setAttribute('role', 'listitem');

    const a = createElement('a', { className: 'occupation-item', href: item.href, ariaLabel: `${item.label} 직종별 코디` });

    const icon = createElement('span', { className: 'occupation-item__icon', 'aria-hidden': 'true' });
    icon.textContent = item.emoji;

    const label = createElement('span', { className: 'occupation-item__label', textContent: item.label });

    a.appendChild(icon);
    a.appendChild(label);
    li.appendChild(a);
    fragment.appendChild(li);
  }
  container.appendChild(fragment);
}

/* ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
   9. 렌더러: 스페셜 코디
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
function renderSpecialCodi() {
  const container = document.getElementById('specialGrid');
  if (!container || !runtimeData?.specialCodi) return;
  const fragment = document.createDocumentFragment();

  for (const item of runtimeData.specialCodi) {
    const a = createElement('a', {
      className: 'special-card',
      href: item.href,
      role: 'listitem',
      'data-animate': '',
      ariaLabel: `${item.label} 스페셜 코디`,
    });

    const imgDiv = createElement('div', { className: `special-card__img ${item.colorClass}` });
    const emojiSpan = createElement('span', { 'aria-hidden': 'true' });
    emojiSpan.textContent = item.emoji;
    imgDiv.appendChild(emojiSpan);

    const label = createElement('span', { className: 'special-card__label', textContent: item.label });
    imgDiv.appendChild(label);
    a.appendChild(imgDiv);
    fragment.appendChild(a);
  }
  container.appendChild(fragment);
}

function renderHeroBanner() {
  const host = document.getElementById('heroBannerHost');
  const heroSection = document.querySelector('.hero');
  if (!host || !heroSection) return;

  host.textContent = '';
  heroSection.classList.remove('hero--has-banner');

  const banner = (runtimeData?.banners || [])[0];
  if (!banner) return;

  if (safeImageSrc(banner.image)) {
    const img = createElement('img', {
      className: 'hero__banner-bg',
      src: banner.image,
      alt: '',
      loading: 'lazy',
    });
    host.appendChild(img);
    host.appendChild(createElement('div', { className: 'hero__banner-dim' }));
    heroSection.classList.add('hero--has-banner');
  }
}

function refreshAdminDrivenSections(showMessage = false) {
  runtimeData = buildRuntimeData();
  renderQuickNav();
  renderBestCodi();
  renderHeroBanner();
  if (showMessage) showToast('관리자 변경사항이 반영되었습니다.');
}

function getBestProductById(productId) {
  return runtimeData?.bestCodi?.find(item => item.id === productId) || null;
}

function parsePriceToNumber(priceText) {
  const raw = String(priceText || '').replace(/[^0-9]/g, '');
  return raw ? parseInt(raw, 10) : 0;
}

/* ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
   11. 인증 상태 관리 (로그인/회원가입)
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
const Auth = {
  users: [],
  session: null,
  init() {
    const users = Storage.safeGet('bc_users', []);
    const session = Storage.safeGet('bc_session', null);
    this.users = Array.isArray(users)
      ? users.filter(this._isValidUser).map(user => ({
          ...user,
          role: typeof user.role === 'string' ? user.role : '일반',
          status: typeof user.status === 'string' ? user.status : '활성',
          joinedAt: typeof user.joinedAt === 'string' ? user.joinedAt : new Date().toISOString().slice(0, 10),
        }))
      : [];
    this.session = (session && typeof session.email === 'string') ? session : null;
    this.updateStatusText();
  },
  reloadUsers() {
    this.init();
  },
  _isValidUser(user) {
    return Boolean(
      user && typeof user === 'object' &&
      typeof user.name === 'string' &&
      typeof user.email === 'string' &&
      typeof user.passwordHash === 'string'
    );
  },
  normalizeEmail(email) {
    return String(email || '').trim().toLowerCase();
  },
  isValidEmail(email) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(email);
  },
  async hashPassword(password) {
    const value = String(password);
    if (window.crypto?.subtle && window.TextEncoder) {
      const data = new TextEncoder().encode(value);
      const hashBuffer = await window.crypto.subtle.digest('SHA-256', data);
      const hashArray = Array.from(new Uint8Array(hashBuffer));
      return hashArray.map(b => b.toString(16).padStart(2, '0')).join('');
    }
    return btoa(unescape(encodeURIComponent(value)));
  },
  async signup(name, email, password, confirmPassword) {
    const safeName = String(name || '').trim();
    const safeEmail = this.normalizeEmail(email);
    const safePassword = String(password || '');
    const safeConfirm = String(confirmPassword || '');

    if (safeName.length < 2 || safeName.length > 30) return { ok: false, message: '이름은 2~30자로 입력해주세요.' };
    if (!this.isValidEmail(safeEmail)) return { ok: false, message: '이메일 형식이 올바르지 않습니다.' };
    if (safePassword.length < 8 || safePassword.length > 64) return { ok: false, message: '비밀번호는 8~64자로 입력해주세요.' };
    if (safePassword !== safeConfirm) return { ok: false, message: '비밀번호 확인이 일치하지 않습니다.' };
    if (this.users.some(u => u.email === safeEmail)) return { ok: false, message: '이미 가입된 이메일입니다.' };

    const passwordHash = await this.hashPassword(safePassword);
    const newUser = {
      name: safeName,
      email: safeEmail,
      passwordHash,
      role: '일반',
      status: '활성',
      joinedAt: new Date().toISOString().slice(0, 10),
    };
    this.users.push(newUser);
    Storage.safeSet('bc_users', this.users);
    this.session = { email: safeEmail, name: safeName };
    Storage.safeSet('bc_session', this.session);
    this.updateStatusText();
    return { ok: true, message: `${safeName}님, 회원가입이 완료되었습니다.` };
  },
  async login(email, password) {
    const safeEmail = this.normalizeEmail(email);
    const safePassword = String(password || '');
    if (!this.isValidEmail(safeEmail)) return { ok: false, message: '이메일 형식이 올바르지 않습니다.' };
    if (safePassword.length < 8 || safePassword.length > 64) return { ok: false, message: '비밀번호를 확인해주세요.' };

    const user = this.users.find(u => u.email === safeEmail);
    if (!user) return { ok: false, message: '가입되지 않은 이메일입니다.' };

    const passwordHash = await this.hashPassword(safePassword);
    if (passwordHash !== user.passwordHash) return { ok: false, message: '비밀번호가 올바르지 않습니다.' };

    this.session = { email: user.email, name: user.name };
    Storage.safeSet('bc_session', this.session);
    this.updateStatusText();
    return { ok: true, message: `${user.name}님, 로그인되었습니다.` };
  },
  isLoggedIn() {
    return Boolean(this.session && typeof this.session.email === 'string');
  },
  logout() {
    this.session = null;
    Storage.safeSet('bc_session', null);
    this.updateStatusText();
  },
  updateStatusText() {
    const status = document.getElementById('authUserStatus');
    if (status) {
      status.textContent = this.isLoggedIn()
      ? `현재 로그인: ${this.session.name} (${this.session.email})`
      : '현재 로그인 상태가 아닙니다.';
    }

    const mobileAuthBtn = document.getElementById('mobileAuthBtn');
    if (mobileAuthBtn) {
      mobileAuthBtn.textContent = this.isLoggedIn() ? '마이페이지' : '로그인/회원가입';
    }

    const authBtn = document.getElementById('authBtn');
    if (authBtn) {
      authBtn.setAttribute('aria-label', this.isLoggedIn() ? '마이페이지' : '로그인 및 회원가입');
    }

    const title = document.getElementById('authModalTitle');
    if (title) {
      title.textContent = this.isLoggedIn() ? '마이페이지' : '로그인 / 회원가입';
    }

    const profileName = document.getElementById('authProfileName');
    const profileEmail = document.getElementById('authProfileEmail');
    if (profileName && profileEmail) {
      profileName.textContent = this.isLoggedIn() ? `${this.session.name} 님` : '';
      profileEmail.textContent = this.isLoggedIn() ? this.session.email : '';
    }
  },
};

/* ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
   12. 장바구니 모달
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
function initCartModal() {
  const cartBtn = document.getElementById('cartBtn');
  const mobileCartBtn = document.getElementById('mobileCartBtn');
  const modal = document.getElementById('cartModal');
  const overlay = document.getElementById('cartModalOverlay');
  const closeBtn = document.getElementById('cartModalClose');
  const listEl = document.getElementById('cartModalList');
  const emptyEl = document.getElementById('cartModalEmpty');
  const totalQtyEl = document.getElementById('cartTotalQty');
  const totalPriceEl = document.getElementById('cartTotalPrice');
  const clearBtn = document.getElementById('cartClearBtn');
  const checkoutBtn = document.getElementById('cartCheckoutBtn');

  if (!cartBtn || !modal || !overlay) return;

  const renderCartModal = () => {
    if (!listEl || !emptyEl || !totalQtyEl || !totalPriceEl) return;
    listEl.textContent = '';

    let totalQty = 0;
    let totalPrice = 0;

    for (const cartItem of Cart.items) {
      const product = getBestProductById(cartItem.id);
      if (!product) continue;
      const unit = parsePriceToNumber(product.price);
      const subtotal = unit * cartItem.qty;
      totalQty += cartItem.qty;
      totalPrice += subtotal;

      const row = createElement('div', { className: 'cart-item', role: 'listitem' });
      const info = createElement('div', { className: 'cart-item__info' });
      const name = createElement('p', { className: 'cart-item__name', textContent: product.name });
      const meta = createElement('p', { className: 'cart-item__meta', textContent: `${product.price} x ${cartItem.qty}개` });
      info.appendChild(name);
      info.appendChild(meta);

      const right = createElement('div', { className: 'cart-item__right' });
      const price = createElement('strong', { className: 'cart-item__subtotal', textContent: `₩ ${subtotal.toLocaleString('ko-KR')}` });
      const removeBtn = createElement('button', {
        className: 'cart-item__remove',
        type: 'button',
        ariaLabel: `${product.name} 수량 1개 줄이기`,
      });
      removeBtn.textContent = '-1';
      removeBtn.addEventListener('click', () => Cart.removeOne(product.id));
      right.appendChild(price);
      right.appendChild(removeBtn);

      row.appendChild(info);
      row.appendChild(right);
      listEl.appendChild(row);
    }

    const hasItems = totalQty > 0;
    emptyEl.style.display = hasItems ? 'none' : 'block';
    totalQtyEl.textContent = String(totalQty);
    totalPriceEl.textContent = `₩ ${totalPrice.toLocaleString('ko-KR')}`;
    clearBtn.disabled = !hasItems;
    checkoutBtn.disabled = !hasItems;
  };

  const open = () => {
    renderCartModal();
    modal.classList.add('open');
    overlay.classList.add('active');
    modal.setAttribute('aria-hidden', 'false');
    overlay.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
    closeBtn?.focus();
  };
  const close = () => {
    modal.classList.remove('open');
    overlay.classList.remove('active');
    modal.setAttribute('aria-hidden', 'true');
    overlay.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
  };

  const handleOpenClick = (e) => {
    e.preventDefault();
    open();
  };

  cartBtn.addEventListener('click', handleOpenClick);
  mobileCartBtn?.addEventListener('click', handleOpenClick);
  closeBtn?.addEventListener('click', close);
  overlay.addEventListener('click', close);

  clearBtn?.addEventListener('click', () => {
    Cart.clear();
    showToast('장바구니를 비웠습니다.');
  });

  checkoutBtn?.addEventListener('click', () => {
    if (!Auth.isLoggedIn()) {
      close();
      showToast('구매를 위해 로그인 해주세요.');
      document.getElementById('authBtn')?.click();
      return;
    }
    showToast('주문 프로세스를 준비 중입니다.');
  });

  window.addEventListener('cart:updated', renderCartModal);
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && modal.classList.contains('open')) close();
  });
}

/* ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
   13. 로그인/회원가입 모달
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
function initAuthModal() {
  const authBtn = document.getElementById('authBtn');
  const mobileAuthBtn = document.getElementById('mobileAuthBtn');
  const modal = document.getElementById('authModal');
  const overlay = document.getElementById('authModalOverlay');
  const closeBtn = document.getElementById('authModalClose');
  const loginForm = document.getElementById('loginForm');
  const signupForm = document.getElementById('signupForm');
  const profileView = document.getElementById('authProfileView');
  const logoutBtn = document.getElementById('authLogoutBtn');
  const tabLogin = document.getElementById('authTabLogin');
  const tabSignup = document.getElementById('authTabSignup');
  if (!authBtn || !modal || !overlay || !loginForm || !signupForm || !profileView) return;

  const setTab = (type) => {
    const isLogin = type === 'login';
    const isSignup = type === 'signup';
    const isProfile = type === 'profile';
    tabLogin?.classList.toggle('active', isLogin);
    tabSignup?.classList.toggle('active', isSignup);
    tabLogin?.setAttribute('aria-selected', isLogin ? 'true' : 'false');
    tabSignup?.setAttribute('aria-selected', isSignup ? 'true' : 'false');
    tabLogin?.classList.toggle('auth-tabs__btn--hidden', isProfile);
    tabSignup?.classList.toggle('auth-tabs__btn--hidden', isProfile);
    tabLogin?.setAttribute('aria-hidden', isProfile ? 'true' : 'false');
    tabSignup?.setAttribute('aria-hidden', isProfile ? 'true' : 'false');
    loginForm.classList.toggle('auth-form--hidden', !isLogin);
    signupForm.classList.toggle('auth-form--hidden', !isSignup);
    profileView.classList.toggle('auth-form--hidden', !isProfile);
  };

  const open = (tab = 'login') => {
    const view = Auth.isLoggedIn() ? 'profile' : tab;
    Auth.updateStatusText();
    setTab(view);
    modal.classList.add('open');
    overlay.classList.add('active');
    modal.setAttribute('aria-hidden', 'false');
    overlay.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
    if (view === 'login') document.getElementById('loginEmail')?.focus();
    else if (view === 'signup') document.getElementById('signupName')?.focus();
    else logoutBtn?.focus();
  };
  const close = () => {
    modal.classList.remove('open');
    overlay.classList.remove('active');
    modal.setAttribute('aria-hidden', 'true');
    overlay.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
  };

  const handleOpenLogin = (e) => {
    e.preventDefault();
    open(Auth.isLoggedIn() ? 'profile' : 'login');
  };

  authBtn.addEventListener('click', handleOpenLogin);
  mobileAuthBtn?.addEventListener('click', handleOpenLogin);
  closeBtn?.addEventListener('click', close);
  overlay.addEventListener('click', close);
  tabLogin?.addEventListener('click', () => setTab('login'));
  tabSignup?.addEventListener('click', () => setTab('signup'));

  logoutBtn?.addEventListener('click', () => {
    Auth.logout();
    showToast('로그아웃되었습니다.');
    setTab('login');
  });

  loginForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    const email = document.getElementById('loginEmail')?.value || '';
    const password = document.getElementById('loginPassword')?.value || '';
    const result = await Auth.login(email, password);
    showToast(result.message);
    if (result.ok) close();
  });

  signupForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    const name = document.getElementById('signupName')?.value || '';
    const email = document.getElementById('signupEmail')?.value || '';
    const password = document.getElementById('signupPassword')?.value || '';
    const confirm = document.getElementById('signupPasswordConfirm')?.value || '';
    const result = await Auth.signup(name, email, password, confirm);
    showToast(result.message);
    if (result.ok) close();
  });

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && modal.classList.contains('open')) close();
  });
}

/* ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
   14. Intersection Observer: 스크롤 애니메이션 + 카운트업
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
function initAnimations() {
  // 스크롤 입장 애니메이션
  const animObserver = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        entry.target.classList.add('visible');
        animObserver.unobserve(entry.target);
      }
    });
  }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });

  document.querySelectorAll('[data-animate]').forEach(el => animObserver.observe(el));

  // 숫자 카운트업
  const countObserver = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        const el = entry.target;
        const target = parseFloat(el.dataset.target);
        const isDecimal = el.dataset.decimal === 'true';
        countUp(el, target, isDecimal);
        countObserver.unobserve(el);
      }
    });
  }, { threshold: 0.5 });

  document.querySelectorAll('.trust-stat__num[data-target]').forEach(el => countObserver.observe(el));
}

/* ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
   12. GNB: 검색 토글
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
function initSearch() {
  const searchBtn   = document.getElementById('searchBtn');
  const searchBar   = document.getElementById('searchBar');
  const searchClose = document.getElementById('searchClose');
  const searchInput = document.getElementById('searchInput');
  const searchForm  = document.getElementById('searchForm');
  if (!searchBtn || !searchBar) return;

  const openSearch = () => {
    searchBar.classList.add('active');
    searchBar.removeAttribute('aria-hidden');
    searchBtn.setAttribute('aria-expanded', 'true');
    searchInput?.focus();
  };
  const closeSearch = () => {
    searchBar.classList.remove('active');
    searchBar.setAttribute('aria-hidden', 'true');
    searchBtn.setAttribute('aria-expanded', 'false');
    searchBtn.focus();
  };

  searchBtn.addEventListener('click', () => {
    searchBar.classList.contains('active') ? closeSearch() : openSearch();
  });
  searchClose?.addEventListener('click', closeSearch);

  // 검색 폼 제출: 입력값 새니타이즈 후 처리
  searchForm?.addEventListener('submit', (e) => {
    e.preventDefault();
    const raw = searchInput?.value?.trim() ?? '';
    // 길이 및 특수문자 기본 검증
    if (raw.length === 0) { showToast('검색어를 입력해주세요.'); return; }
    if (raw.length > 100) { showToast('검색어는 100자 이내로 입력해주세요.'); return; }
    // 현재는 프로토타입이므로 toast로 안내
    showToast(`🔍 "${raw}" 검색 결과를 준비 중이에요!`);
    closeSearch();
  });

  // ESC 키로 닫기
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && searchBar.classList.contains('active')) closeSearch();
  });
}

/* ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
   13. GNB: 모바일 메뉴 토글
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
function initMobileMenu() {
  const hamburgerBtn   = document.getElementById('hamburgerBtn');
  const mobileMenu     = document.getElementById('mobileMenu');
  const mobileOverlay  = document.getElementById('mobileOverlay');
  const mobileMenuClose= document.getElementById('mobileMenuClose');
  const mobileLinks    = mobileMenu?.querySelectorAll('.mobile-menu__link, .mobile-menu__util-link');
  if (!hamburgerBtn || !mobileMenu) return;

  let lastFocused = null;

  const openMenu = () => {
    lastFocused = document.activeElement;
    mobileMenu.classList.add('open');
    mobileMenu.removeAttribute('aria-hidden');
    mobileOverlay?.classList.add('active');
    hamburgerBtn.setAttribute('aria-expanded', 'true');
    document.body.style.overflow = 'hidden'; // 스크롤 잠금
    mobileMenuClose?.focus();
  };
  const closeMenu = () => {
    mobileMenu.classList.remove('open');
    mobileMenu.setAttribute('aria-hidden', 'true');
    mobileOverlay?.classList.remove('active');
    hamburgerBtn.setAttribute('aria-expanded', 'false');
    document.body.style.overflow = '';
    lastFocused?.focus();
  };

  hamburgerBtn.addEventListener('click', () => {
    mobileMenu.classList.contains('open') ? closeMenu() : openMenu();
  });
  mobileMenuClose?.addEventListener('click', closeMenu);
  mobileOverlay?.addEventListener('click', closeMenu);

  // 링크 클릭 시 메뉴 닫기
  mobileLinks?.forEach(link => link.addEventListener('click', closeMenu));

  // ESC 키
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && mobileMenu.classList.contains('open')) closeMenu();
  });

  // 포커스 트랩 (접근성)
  mobileMenu.addEventListener('keydown', (e) => {
    if (e.key !== 'Tab') return;
    const focusable = mobileMenu.querySelectorAll(
      'button, a, [tabindex]:not([tabindex="-1"])'
    );
    const first = focusable[0];
    const last  = focusable[focusable.length - 1];
    if (e.shiftKey && document.activeElement === first) {
      e.preventDefault(); last.focus();
    } else if (!e.shiftKey && document.activeElement === last) {
      e.preventDefault(); first.focus();
    }
  });
}

/* ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
   14. GNB 스크롤 효과
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
function initGnbScroll() {
  const gnb = document.getElementById('gnb');
  if (!gnb) return;
  let lastY = 0;
  window.addEventListener('scroll', () => {
    const y = window.scrollY;
    if (y > 80) gnb.classList.add('scrolled');
    else gnb.classList.remove('scrolled');
    lastY = y;
  }, { passive: true });
}

/* ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
   15. 케어 필터 버튼 인터랙션
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
function initCareFilters() {
  const filterBtns = document.querySelectorAll('.care-filter-btn');
  filterBtns.forEach(btn => {
    btn.addEventListener('click', () => {
      filterBtns.forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      showToast(`🔍 "${btn.textContent.trim()}" 필터를 적용했어요!`);
    });
  });
}

/* ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
   16. 스무스 앵커 스크롤 (GNB 높이 보정)
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
function initSmoothScroll() {
  document.addEventListener('click', (e) => {
    const anchor = e.target.closest('a[href^="#"]');
    if (!anchor) return;
    const targetId = anchor.getAttribute('href').slice(1);
    if (!targetId) return;
    // ID 화이트리스트 패턴
    if (!/^[a-zA-Z0-9\-_]{1,50}$/.test(targetId)) return;
    const target = document.getElementById(targetId);
    if (!target) return;
    e.preventDefault();
    const gnbH = document.getElementById('gnb')?.offsetHeight ?? 72;
    const top = target.getBoundingClientRect().top + window.scrollY - gnbH - 16;
    window.scrollTo({ top, behavior: 'smooth' });
  });
}

/* ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
   17. 앱 초기화
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
function init() {
  // 데이터 유효성 확인
  if (typeof BETTER_CARE_DATA === 'undefined') {
    console.error('[더 나음] 데이터 로드 실패: products.js가 로드되지 않았습니다.');
    return;
  }

  // 상태 초기화
  runtimeData = buildRuntimeData();
  Cart.init();
  Wishlist.init();
  Auth.init();

  // 렌더링
  refreshAdminDrivenSections();
  renderCategories();
  renderCareCards();
  renderOccupations();
  renderSpecialCodi();

  // UI 인터랙션 초기화
  initSearch();
  initMobileMenu();
  initGnbScroll();
  initCareFilters();
  initSmoothScroll();
  initCartModal();
  initAuthModal();

  // 관리자 대시보드 변경사항 실시간 반영
  window.addEventListener('storage', (event) => {
    if (event.key === ADMIN_STORE_KEY) {
      refreshAdminDrivenSections(true);
    }
    if (event.key === 'bc_users' || event.key === 'bc_session') {
      Auth.reloadUsers();
    }
  });

  // 애니메이션 (렌더링 완료 후)
  requestAnimationFrame(initAnimations);
}

// DOM 준비 완료 후 실행
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', init);
} else {
  init();
}
