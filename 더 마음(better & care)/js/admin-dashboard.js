'use strict';

const STORE_KEY = 'bc_admin_data_v1';
const USERS_KEY = 'bc_users';

const defaults = {
  products: [
    { id: crypto.randomUUID(), name: '봄 데일리 코디', image: '', category: '상의', price: 59000, status: '판매중' },
    { id: crypto.randomUUID(), name: '휠체어 케어 팬츠', image: '', category: '하의', price: 69000, status: '판매중' },
    { id: crypto.randomUUID(), name: '재활 소프트 자켓', image: '', category: '아우터', price: 89000, status: '품절' }
  ],
  categories: [
    { id: crypto.randomUUID(), label: '아우터', emoji: '🧥', visible: true },
    { id: crypto.randomUUID(), label: '상의', emoji: '👕', visible: true },
    { id: crypto.randomUUID(), label: '하의', emoji: '👖', visible: true },
    { id: crypto.randomUUID(), label: '원피스', emoji: '👗', visible: true },
    { id: crypto.randomUUID(), label: '이너웨어', emoji: '🩱', visible: true },
    { id: crypto.randomUUID(), label: '신발', emoji: '👟', visible: true },
    { id: crypto.randomUUID(), label: '가방', emoji: '👜', visible: true },
    { id: crypto.randomUUID(), label: '액세서리', emoji: '💍', visible: true }
  ],
  banners: [
    { id: crypto.randomUUID(), name: '메인 히어로 배너', image: '', position: '메인 상단', period: '2026-05-01 ~ 2026-08-31', active: true },
    { id: crypto.randomUUID(), name: '케어룩 프로모션', image: '', position: '중단 배너', period: '2026-05-15 ~ 2026-06-30', active: false }
  ],
  icons: [
    { id: crypto.randomUUID(), iconMode: '문자', icon: '🏆', image: '', name: 'BEST 코디', menu: '퀵메뉴', visible: true },
    { id: crypto.randomUUID(), iconMode: '문자', icon: '♿', image: '', name: '케어 의류', menu: '퀵메뉴', visible: true },
    { id: crypto.randomUUID(), iconMode: '문자', icon: '💼', image: '', name: '직종별 코디', menu: '퀵메뉴', visible: true }
  ],
  members: [
    { id: crypto.randomUUID(), name: '강주아', email: 'jua@bettercare.com', role: '관리자', joinedAt: '2026-05-24', status: '활성' }
  ]
};

let state = loadState();
let activeTab = 'products';
let modalContext = null;

syncMembersFromUsers();
let modalUploads = {};

function loadState() {
  try {
    const raw = localStorage.getItem(STORE_KEY);
    if (!raw) return structuredClone(defaults);
    const parsed = JSON.parse(raw);
    return {
      products: Array.isArray(parsed.products)
        ? parsed.products.map(p => ({ ...p, image: typeof p.image === 'string' ? p.image : '' }))
        : structuredClone(defaults.products),
      categories: Array.isArray(parsed.categories)
        ? parsed.categories
            .filter(c => c && typeof c.label === 'string')
            .map(c => ({
              id: c.id || crypto.randomUUID(),
              label: String(c.label || '').trim() || '기타',
              emoji: typeof c.emoji === 'string' && c.emoji.trim() ? c.emoji.trim() : '📦',
              visible: c.visible !== false,
            }))
        : structuredClone(defaults.categories),
      banners: Array.isArray(parsed.banners)
        ? parsed.banners.map(b => ({ ...b, image: typeof b.image === 'string' ? b.image : '' }))
        : structuredClone(defaults.banners),
      icons: Array.isArray(parsed.icons)
        ? parsed.icons.map(i => ({
            ...i,
            iconMode: i.iconMode === '이미지' ? '이미지' : '문자',
            icon: typeof i.icon === 'string' ? i.icon : '',
            image: typeof i.image === 'string' ? i.image : ''
          }))
        : structuredClone(defaults.icons),
      members: Array.isArray(parsed.members) ? parsed.members : structuredClone(defaults.members)
    };
  } catch {
    return structuredClone(defaults);
  }
}

function loadAuthUsers() {
  try {
    const raw = localStorage.getItem(USERS_KEY);
    if (!raw) return [];
    const parsed = JSON.parse(raw);
    return Array.isArray(parsed)
      ? parsed.filter(user => user && typeof user.email === 'string' && typeof user.passwordHash === 'string')
      : [];
  } catch {
    return [];
  }
}

function syncMembersFromUsers() {
  const users = loadAuthUsers();
  if (users.length === 0) return;
  state.members = users.map(user => ({
    id: user.id || crypto.randomUUID(),
    name: user.name || '이름없음',
    email: user.email,
    role: user.role || '일반',
    joinedAt: user.joinedAt || new Date().toISOString().slice(0, 10),
    status: user.status || '활성'
  }));
}

function persistMembersToUsers() {
  const users = loadAuthUsers();
  if (users.length === 0) return;

  const memberByEmail = new Map(
    state.members.map(member => [String(member.email || '').toLowerCase(), member])
  );

  const nextUsers = users
    .filter(user => memberByEmail.has(String(user.email || '').toLowerCase()))
    .map(user => {
      const member = memberByEmail.get(String(user.email || '').toLowerCase());
      return {
        ...user,
        name: member.name,
        role: member.role,
        status: member.status,
        joinedAt: member.joinedAt,
      };
    });

  localStorage.setItem(USERS_KEY, JSON.stringify(nextUsers));
}

function saveState() {
  localStorage.setItem(STORE_KEY, JSON.stringify(state));
}

function toast(msg) {
  const el = document.getElementById('adminToast');
  el.textContent = msg;
  el.classList.add('show');
  clearTimeout(el._timer);
  el._timer = setTimeout(() => el.classList.remove('show'), 1800);
}

function money(n) {
  return `₩ ${Number(n).toLocaleString('ko-KR')}`;
}

function badge(status) {
  if (status === '판매중' || status === '활성' || status === true || status === '노출') return '<span class="badge badge--ok">활성</span>';
  if (status === '품절') return '<span class="badge badge--warn">품절</span>';
  return '<span class="badge badge--off">비활성</span>';
}

function safeImageSrc(src) {
  const value = String(src || '').trim();
  if (/^data:image\/[a-zA-Z0-9.+-]+;base64,[A-Za-z0-9+/=]+$/.test(value)) return value;
  if (/^images\/[A-Za-z0-9_\-./]+$/.test(value)) return value;
  return '';
}

function imageCellHtml(src, alt = '미리보기') {
  const safe = safeImageSrc(src);
  if (!safe) return '<span class="thumb-placeholder">미등록</span>';
  return `<img class="thumb" src="${safe}" alt="${escapeHtml(alt)}" loading="lazy" />`;
}

function readFileAsDataURL(file) {
  return new Promise((resolve, reject) => {
    const reader = new FileReader();
    reader.onload = () => resolve(String(reader.result || ''));
    reader.onerror = () => reject(new Error('이미지 파일을 읽을 수 없습니다.'));
    reader.readAsDataURL(file);
  });
}

function renderKPIs() {
  document.getElementById('kpiProducts').textContent = state.products.length;
  document.getElementById('kpiCategories').textContent = state.categories.filter(v => v.visible).length;
  document.getElementById('kpiBanners').textContent = state.banners.filter(v => v.active).length;
  document.getElementById('kpiIcons').textContent = state.icons.length;
  document.getElementById('kpiMembers').textContent = state.members.length;
}

function renderProducts() {
  const tbody = document.querySelector('#productsTable tbody');
  const query = document.getElementById('globalSearch').value.trim().toLowerCase();
  const status = document.getElementById('productStatusFilter').value;
  const list = state.products.filter(p => {
    const matchQ = !query || [p.name, p.category].join(' ').toLowerCase().includes(query);
    const matchS = status === 'all' || p.status === status;
    return matchQ && matchS;
  });
  tbody.innerHTML = list.map(p => `
    <tr>
      <td>${escapeHtml(p.name)}</td>
      <td>${imageCellHtml(p.image, `${p.name} 이미지`)}</td>
      <td>${escapeHtml(p.category)}</td>
      <td>${money(p.price)}</td>
      <td>${badge(p.status === '판매중' ? '활성' : p.status)}</td>
      <td class="row-actions">
        <button data-act="toggle-product" data-id="${p.id}">${p.status === '판매중' ? '숨김' : '판매'}</button>
        <button data-act="edit-product" data-id="${p.id}">수정</button>
        <button class="btn--danger" data-act="del-product" data-id="${p.id}">삭제</button>
      </td>
    </tr>
  `).join('');
}

function renderBanners() {
  const tbody = document.querySelector('#bannersTable tbody');
  const query = document.getElementById('globalSearch').value.trim().toLowerCase();
  const list = state.banners.filter(b => !query || [b.name, b.position].join(' ').toLowerCase().includes(query));
  tbody.innerHTML = list.map(b => `
    <tr>
      <td>${escapeHtml(b.name)}</td>
      <td>${imageCellHtml(b.image, `${b.name} 배너`)}</td>
      <td>${escapeHtml(b.position)}</td>
      <td>${escapeHtml(b.period)}</td>
      <td>${badge(b.active ? '활성' : '비활성')}</td>
      <td class="row-actions">
        <button data-act="toggle-banner" data-id="${b.id}">${b.active ? '중지' : '노출'}</button>
        <button data-act="edit-banner" data-id="${b.id}">수정</button>
        <button class="btn--danger" data-act="del-banner" data-id="${b.id}">삭제</button>
      </td>
    </tr>
  `).join('');
}

function renderCategoriesAdmin() {
  const tbody = document.querySelector('#categoriesTable tbody');
  const query = document.getElementById('globalSearch').value.trim().toLowerCase();
  const list = state.categories.filter(c => {
    return !query || [c.label, c.emoji, c.visible ? '노출' : '숨김'].join(' ').toLowerCase().includes(query);
  });

  tbody.innerHTML = list.map(c => {
    const productCount = state.products.filter(p => p.categoryId === c.id || p.category === c.label).length;
    return `
      <tr>
        <td><span class="category-emoji" aria-hidden="true">${escapeHtml(c.emoji)}</span></td>
        <td>${escapeHtml(c.label)}</td>
        <td>${badge(c.visible ? '노출' : '비활성')}</td>
        <td>${productCount.toLocaleString('ko-KR')}</td>
        <td class="row-actions">
          <button data-act="toggle-category" data-id="${c.id}">${c.visible ? '숨김' : '노출'}</button>
          <button data-act="edit-category" data-id="${c.id}">수정</button>
          <button class="btn--danger" data-act="del-category" data-id="${c.id}">삭제</button>
        </td>
      </tr>
    `;
  }).join('');
}

function renderIcons() {
  const tbody = document.querySelector('#iconsTable tbody');
  const query = document.getElementById('globalSearch').value.trim().toLowerCase();
  const list = state.icons.filter(i => !query || [i.name, i.menu].join(' ').toLowerCase().includes(query));
  tbody.innerHTML = list.map(i => `
    <tr>
      <td>${i.iconMode === '이미지' ? imageCellHtml(i.image, `${i.name} 아이콘`) : escapeHtml(i.icon)}</td>
      <td>${escapeHtml(i.iconMode || '문자')}</td>
      <td>${escapeHtml(i.name)}</td>
      <td>${escapeHtml(i.menu)}</td>
      <td>${badge(i.visible ? '노출' : '비활성')}</td>
      <td class="row-actions">
        <button data-act="toggle-icon" data-id="${i.id}">${i.visible ? '숨김' : '노출'}</button>
        <button data-act="edit-icon" data-id="${i.id}">수정</button>
        <button class="btn--danger" data-act="del-icon" data-id="${i.id}">삭제</button>
      </td>
    </tr>
  `).join('');
}

function renderMembers() {
  const tbody = document.querySelector('#membersTable tbody');
  const query = document.getElementById('globalSearch').value.trim().toLowerCase();
  const role = document.getElementById('memberRoleFilter').value;
  const list = state.members.filter(m => {
    const matchQ = !query || [m.name, m.email].join(' ').toLowerCase().includes(query);
    const matchR = role === 'all' || m.role === role;
    return matchQ && matchR;
  });
  tbody.innerHTML = list.map(m => `
    <tr>
      <td>${escapeHtml(m.name)}</td>
      <td>${escapeHtml(m.email)}</td>
      <td>${escapeHtml(m.role)}</td>
      <td>${escapeHtml(m.joinedAt)}</td>
      <td>${badge(m.status === '활성' ? '활성' : '비활성')}</td>
      <td class="row-actions">
        <button data-act="toggle-member" data-id="${m.id}">${m.status === '활성' ? '휴면' : '활성'}</button>
        <button data-act="edit-member" data-id="${m.id}">수정</button>
        <button class="btn--danger" data-act="del-member" data-id="${m.id}">삭제</button>
      </td>
    </tr>
  `).join('');
}

function renderAll() {
  renderKPIs();
  renderProducts();
  renderCategoriesAdmin();
  renderBanners();
  renderIcons();
  renderMembers();
}

function escapeHtml(value) {
  return String(value)
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#39;');
}

function switchTab(tab) {
  activeTab = tab;
  document.querySelectorAll('.menu__item').forEach(btn => btn.classList.toggle('is-active', btn.dataset.tab === tab));
  document.querySelectorAll('.panel').forEach(p => p.classList.toggle('is-active', p.id === `tab-${tab}`));
}

function removeById(arrName, id) {
  state[arrName] = state[arrName].filter(v => v.id !== id);
}

function openModal(mode, type, id = null) {
  const modal = document.getElementById('adminModal');
  const title = document.getElementById('modalTitle');
  const fields = document.getElementById('modalFields');
  modalContext = { mode, type, id };
  modalUploads = {};

  const item = id ? state[type].find(v => v.id === id) : null;
  title.textContent = `${mode === 'add' ? '추가' : '수정'} - ${labelOf(type)}`;

  fields.innerHTML = schema(type).map(f => {
    const rawValue = item ? item[f.key] : '';
    const v = (type === 'categories' && f.key === 'visible')
      ? (rawValue === false ? '숨김' : '노출')
      : rawValue;
    if (f.type === 'select') {
      const options = Array.isArray(f.options) ? [...f.options] : [];
      const current = String(v || '');
      if (current && !options.includes(current)) options.push(current);
      return `<label data-field="${f.key}">${f.label}<select name="${f.key}">${options.map(o => `<option value="${o}" ${current === o ? 'selected' : ''}>${o}</option>`).join('')}</select></label>`;
    }
    if (f.type === 'image') {
      const safe = safeImageSrc(v);
      return `<label data-field="${f.key}">${f.label}<input name="${f.key}" type="file" accept="image/*" /><small class="hint">JPG/PNG/WEBP, 최대 2MB</small><div class="preview ${safe ? '' : 'is-empty'}" data-preview="${f.key}">${safe ? `<img src="${safe}" alt="${escapeHtml(f.label)} 미리보기" />` : '이미지 없음'}</div></label>`;
    }
    return `<label data-field="${f.key}">${f.label}<input name="${f.key}" type="${f.type}" value="${escapeHtml(v)}" maxlength="${f.max || 120}" ${f.required === false ? '' : 'required'} /></label>`;
  }).join('');

  bindModalImageInputs();
  bindIconModeFields();

  modal.showModal();
}

function bindModalImageInputs() {
  const fields = document.getElementById('modalFields');
  fields.querySelectorAll('input[type="file"][name]').forEach(input => {
    input.addEventListener('change', async (e) => {
      const file = e.target.files && e.target.files[0];
      const key = e.target.name;
      if (!file) return;
      if (!file.type.startsWith('image/')) {
        toast('이미지 파일만 업로드할 수 있습니다.');
        e.target.value = '';
        return;
      }
      if (file.size > 5 * 1024 * 1024) {
        toast('이미지 용량은 5MB 이하만 가능합니다.');
        e.target.value = '';
        return;
      }
      try {
        const dataUrl = await readFileAsDataURL(file);
        modalUploads[key] = dataUrl;
        const preview = fields.querySelector(`[data-preview="${key}"]`);
        if (preview) {
          preview.classList.remove('is-empty');
          preview.innerHTML = `<img src="${dataUrl}" alt="업로드 미리보기" />`;
        }
      } catch {
        toast('이미지를 처리하는 중 오류가 발생했습니다.');
      }
    });
  });
}

function bindIconModeFields() {
  if (!modalContext || modalContext.type !== 'icons') return;
  const fields = document.getElementById('modalFields');
  const modeSelect = fields.querySelector('select[name="iconMode"]');
  if (!modeSelect) return;

  const iconField = fields.querySelector('[data-field="icon"]');
  const imageField = fields.querySelector('[data-field="image"]');
  const iconInput = fields.querySelector('input[name="icon"]');

  const sync = () => {
    const isImage = modeSelect.value === '이미지';
    iconField?.classList.toggle('is-hidden', isImage);
    imageField?.classList.toggle('is-hidden', !isImage);
    if (iconInput) iconInput.required = !isImage;
  };

  sync();
  modeSelect.addEventListener('change', sync);
}

function labelOf(type) {
  return ({ products: '상품', categories: '카테고리', banners: '배너', icons: '아이콘', members: '회원' })[type];
}

function getProductCategoryOptions() {
  const options = state.categories.map(c => String(c.label || '').trim()).filter(Boolean);
  return options.length > 0 ? options : ['기타'];
}

function schema(type) {
  if (type === 'products') return [
    { key: 'name', label: '상품명', type: 'text', max: 40 },
    { key: 'image', label: '상품 이미지', type: 'image' },
    { key: 'category', label: '카테고리', type: 'select', options: getProductCategoryOptions() },
    { key: 'price', label: '가격', type: 'number' },
    { key: 'status', label: '상태', type: 'select', options: ['판매중', '품절', '숨김'] }
  ];
  if (type === 'categories') return [
    { key: 'label', label: '카테고리명', type: 'text', max: 20 },
    { key: 'emoji', label: '카테고리 아이콘', type: 'text', max: 4 },
    { key: 'visible', label: '노출 상태', type: 'select', options: ['노출', '숨김'] },
  ];
  if (type === 'banners') return [
    { key: 'name', label: '배너명', type: 'text', max: 40 },
    { key: 'image', label: '배너 이미지', type: 'image' },
    { key: 'position', label: '노출 위치', type: 'text', max: 20 },
    { key: 'period', label: '기간', type: 'text', max: 40 }
  ];
  if (type === 'icons') return [
    { key: 'iconMode', label: '표시 방식', type: 'select', options: ['문자', '이미지'] },
    { key: 'icon', label: '아이콘 문자', type: 'text', max: 2, required: false },
    { key: 'image', label: '아이콘 이미지', type: 'image' },
    { key: 'name', label: '아이콘명', type: 'text', max: 20 },
    { key: 'menu', label: '연결 메뉴', type: 'text', max: 20 }
  ];
  return [
    { key: 'name', label: '이름', type: 'text', max: 30 },
    { key: 'email', label: '이메일', type: 'email', max: 60 },
    { key: 'role', label: '권한', type: 'select', options: ['일반', '보호자', '관리자'] },
    { key: 'joinedAt', label: '가입일', type: 'date' }
  ];
}

function submitModal() {
  if (!modalContext) return;
  const form = document.getElementById('adminForm');
  const data = new FormData(form);
  const payload = Object.fromEntries(data.entries());
  const existing = modalContext.id ? state[modalContext.type].find(v => v.id === modalContext.id) : null;

  schema(modalContext.type)
    .filter(f => f.type === 'image')
    .forEach(f => {
      payload[f.key] = modalUploads[f.key] || (existing ? existing[f.key] : '') || '';
    });

  if (modalContext.type === 'products') {
    payload.price = Number(payload.price || 0);
    const categoryName = String(payload.category || '').trim();
    const matchedCategory = state.categories.find(c => c.label === categoryName);
    payload.category = categoryName || '기타';
    payload.categoryId = matchedCategory ? matchedCategory.id : '';
  }
  if (modalContext.type === 'categories') {
    payload.label = String(payload.label || '').trim();
    payload.emoji = String(payload.emoji || '').trim() || '📦';
    payload.visible = payload.visible !== '숨김';

    if (!payload.label) {
      toast('카테고리명을 입력해주세요.');
      return;
    }

    const normalized = payload.label.toLowerCase();
    const duplicated = state.categories.some(c => c.id !== modalContext.id && c.label.toLowerCase() === normalized);
    if (duplicated) {
      toast('같은 이름의 카테고리가 이미 있습니다.');
      return;
    }
  }
  if (modalContext.type === 'banners' && modalContext.mode === 'add') payload.active = true;
  if (modalContext.type === 'icons') {
    payload.iconMode = payload.iconMode === '이미지' ? '이미지' : '문자';
    if (payload.iconMode === '문자') {
      payload.image = '';
      if (!String(payload.icon || '').trim()) {
        toast('아이콘 문자를 입력해주세요.');
        return;
      }
    } else {
      payload.icon = '';
      if (!String(payload.image || '').trim()) {
        toast('아이콘 이미지를 업로드해주세요.');
        return;
      }
    }
    if (modalContext.mode === 'add') payload.visible = true;
  }
  if (modalContext.type === 'members' && modalContext.mode === 'add') payload.status = '활성';

  if (modalContext.mode === 'add') {
    if (modalContext.type === 'members') {
      toast('회원은 홈페이지에서 실제 회원가입으로 생성해주세요.');
      return;
    }
    state[modalContext.type].unshift({ id: crypto.randomUUID(), ...payload });
    toast(`${labelOf(modalContext.type)} 추가 완료`);
  } else {
    if (modalContext.type === 'categories' && existing && existing.label !== payload.label) {
      state.products = state.products.map(product => {
        const isTarget = product.categoryId === existing.id || product.category === existing.label;
        return isTarget
          ? { ...product, category: payload.label, categoryId: existing.id }
          : product;
      });
    }
    state[modalContext.type] = state[modalContext.type].map(v => v.id === modalContext.id ? { ...v, ...payload } : v);
    toast(`${labelOf(modalContext.type)} 수정 완료`);
  }

  saveState();
  if (modalContext.type === 'members') persistMembersToUsers();
  renderAll();
}

function bindEvents() {
  document.querySelectorAll('.menu__item').forEach(btn => {
    btn.addEventListener('click', () => switchTab(btn.dataset.tab));
  });

  document.getElementById('globalSearch').addEventListener('input', renderAll);
  document.getElementById('productStatusFilter').addEventListener('change', renderProducts);
  document.getElementById('memberRoleFilter').addEventListener('change', renderMembers);

  document.getElementById('saveAllBtn').addEventListener('click', () => {
    saveState();
    toast('전체 저장되었습니다.');
  });

  document.getElementById('addProductBtn').addEventListener('click', () => openModal('add', 'products'));
  document.getElementById('addCategoryBtn').addEventListener('click', () => openModal('add', 'categories'));
  document.getElementById('addBannerBtn').addEventListener('click', () => openModal('add', 'banners'));
  document.getElementById('addIconBtn').addEventListener('click', () => openModal('add', 'icons'));

  document.body.addEventListener('click', (e) => {
    const btn = e.target.closest('button[data-act]');
    if (!btn) return;
    const { act, id } = btn.dataset;

    if (act === 'toggle-product') state.products = state.products.map(v => v.id === id ? { ...v, status: v.status === '판매중' ? '숨김' : '판매중' } : v);
    if (act === 'edit-product') openModal('edit', 'products', id);
    if (act === 'del-product') removeById('products', id);

    if (act === 'toggle-category') state.categories = state.categories.map(v => v.id === id ? { ...v, visible: !v.visible } : v);
    if (act === 'edit-category') openModal('edit', 'categories', id);
    if (act === 'del-category') {
      const target = state.categories.find(v => v.id === id);
      if (target) {
        const inUse = state.products.some(product => product.categoryId === target.id || product.category === target.label);
        if (inUse) {
          toast('이 카테고리를 사용하는 상품이 있어 삭제할 수 없습니다.');
          return;
        }
      }
      removeById('categories', id);
    }

    if (act === 'toggle-banner') state.banners = state.banners.map(v => v.id === id ? { ...v, active: !v.active } : v);
    if (act === 'edit-banner') openModal('edit', 'banners', id);
    if (act === 'del-banner') removeById('banners', id);

    if (act === 'toggle-icon') state.icons = state.icons.map(v => v.id === id ? { ...v, visible: !v.visible } : v);
    if (act === 'edit-icon') openModal('edit', 'icons', id);
    if (act === 'del-icon') removeById('icons', id);

    if (act === 'toggle-member') state.members = state.members.map(v => v.id === id ? { ...v, status: v.status === '활성' ? '휴면' : '활성' } : v);
    if (act === 'edit-member') openModal('edit', 'members', id);
    if (act === 'del-member') removeById('members', id);

    saveState();
    if (act === 'toggle-member' || act === 'del-member') persistMembersToUsers();
    renderAll();
  });

  document.getElementById('adminForm').addEventListener('submit', (e) => {
    const isCancel = e.submitter && e.submitter.value === 'cancel';
    if (isCancel) return;
    e.preventDefault();
    submitModal();
    document.getElementById('adminModal').close();
  });
}

function init() {
  bindEvents();
  window.addEventListener('storage', (event) => {
    if (event.key === USERS_KEY) {
      syncMembersFromUsers();
      renderAll();
    }
  });
  renderAll();
}

init();
