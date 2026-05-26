/**
 * 더 나음 (better & care) - 상품/데이터 정의
 * 보안: 모든 데이터는 읽기 전용 const로 선언, XSS 방지를 위해 렌더링 시 textContent 사용
 */
'use strict';

const BETTER_CARE_DATA = Object.freeze({

  quickNav: Object.freeze([
    { id: 'qn-best',     icon: '🏆', label: 'BEST 코디',   href: 'pages.html?section=best' },
    { id: 'qn-category', icon: '👗', label: '카테고리별',   href: 'pages.html?section=category' },
    { id: 'qn-recommend',icon: '💡', label: '추천 코디',    href: 'pages.html?section=special' },
    { id: 'qn-cart',     icon: '🛒', label: '장바구니',     href: 'pages.html?section=cart' },
    { id: 'qn-job',      icon: '💼', label: '직종별 코디',  href: 'pages.html?section=occupation' },
    { id: 'qn-care',     icon: '♿', label: '케어 의류',    href: 'pages.html?section=care' },
  ]),

  bestCodi: Object.freeze([
    { id: 'bc-01', rank: 1, emoji: '🧥', name: '봄 데일리 코디',      category: '아우터', price: '₩ 59,000', period: '/ 주', wishCount: 342, tag: '인기' },
    { id: 'bc-02', rank: 2, emoji: '👔', name: '여름 오피스 코디',    category: '상의',   price: '₩ 69,000', period: '/ 주', wishCount: 278, tag: '' },
    { id: 'bc-03', rank: 3, emoji: '👗', name: '데이트 스페셜 코디',  category: '원피스', price: '₩ 80,000', period: '/ 회', wishCount: 215, tag: '신규' },
    { id: 'bc-04', rank: 4, emoji: '🖤', name: '하객룩 스페셜 코디',  category: '아우터', price: '₩ 89,000', period: '/ 회', wishCount: 190, tag: '' },
    { id: 'bc-05', rank: 5, emoji: '👖', name: '캐주얼 데일리 코디',  category: '하의',   price: '₩ 59,000', period: '/ 주', wishCount: 167, tag: '' },
  ]),

  categories: Object.freeze([
    { id: 'cat-outer',  emoji: '🧥', label: '아우터',  href: 'pages.html?category=아우터' },
    { id: 'cat-top',    emoji: '👕', label: '상의',    href: 'pages.html?category=상의' },
    { id: 'cat-bottom', emoji: '👖', label: '하의',    href: 'pages.html?category=하의' },
    { id: 'cat-dress',  emoji: '👗', label: '원피스',  href: 'pages.html?category=원피스' },
    { id: 'cat-inner',  emoji: '🩱', label: '이너웨어',href: 'pages.html?category=이너웨어' },
    { id: 'cat-shoes',  emoji: '👟', label: '신발',    href: 'pages.html?category=신발' },
    { id: 'cat-bag',    emoji: '👜', label: '가방',    href: 'pages.html?category=가방' },
    { id: 'cat-acc',    emoji: '💍', label: '액세서리',href: 'pages.html?category=액세서리' },
    { id: 'cat-all',    emoji: '🗂️', label: '전체보기',href: 'pages.html?category=all', isAll: true },
  ]),

  careCards: Object.freeze([
    {
      id: 'care-01',
      colorClass: 'care-card__img--1',
      emoji: '🔒',
      title: '혼자 입기 쉬운 디자인',
      desc: '지퍼, 자석, 벨크로 등 편리한 개폐 구조 의류. Easy-Wear Level 1~3 표기',
      href: 'pages.html?care=easy-wear',
    },
    {
      id: 'care-02',
      colorClass: 'care-card__img--2',
      emoji: '♿',
      title: '휠체어 사용자 맞춤',
      desc: '압박 없이 편안한 착용감, 엉덩이·동체 배김 없는 특수 패턴 설계',
      href: 'pages.html?care=wheelchair',
    },
    {
      id: 'care-03',
      colorClass: 'care-card__img--3',
      emoji: '🩹',
      title: '절단·환부 맞춤 의류',
      desc: '의수/의족 사용 및 환부 보호·노출을 고려한 전문 디자인',
      href: 'pages.html?care=rehab',
    },
    {
      id: 'care-04',
      colorClass: 'care-card__img--4',
      emoji: '📋',
      title: '다양한 상황별 의류',
      desc: '수술 직후, 재활 운동, 통원 치료, 일상 생활 등 단계별 매칭',
      href: 'pages.html?care=situation',
    },
  ]),

  occupations: Object.freeze([
    { id: 'occ-nurse',    emoji: '👩‍⚕️', label: '간호사',    href: 'pages.html?occupation=간호사' },
    { id: 'occ-teacher',  emoji: '👩‍🏫', label: '교사',      href: 'pages.html?occupation=교사' },
    { id: 'occ-office',   emoji: '💼',   label: '사무직',    href: 'pages.html?occupation=사무직' },
    { id: 'occ-service',  emoji: '🛎️',   label: '서비스직',  href: 'pages.html?occupation=서비스직' },
    { id: 'occ-caregiver',emoji: '🧡',   label: '요양보호사',href: 'pages.html?occupation=요양보호사' },
  ]),

  specialCodi: Object.freeze([
    { id: 'sp-01', colorClass: 'special-card__img--1', emoji: '💐', label: '결혼식 하객룩', href: 'pages.html?special=sp-01' },
    { id: 'sp-02', colorClass: 'special-card__img--2', emoji: '💛', label: '데이트룩',     href: 'pages.html?special=sp-02' },
    { id: 'sp-03', colorClass: 'special-card__img--3', emoji: '📎', label: '면접/PT룩',    href: 'pages.html?special=sp-03' },
    { id: 'sp-04', colorClass: 'special-card__img--4', emoji: '🎉', label: '기념일룩',     href: 'pages.html?special=sp-04' },
  ]),
});
