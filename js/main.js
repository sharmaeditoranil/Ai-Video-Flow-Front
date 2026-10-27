// AI Video Flow - Main Interactive Script
document.addEventListener('DOMContentLoaded', () => {
  // 1. Detect User OS for Primary Hero Download Button
  detectUserOS();

  // 2. FAQ Accordion Logic
  initFAQ();

  // 3. Mobile Navigation Menu
  initMobileMenu();

  // 4. Smooth Anchor Scrolling
  initSmoothScroll();
});

function detectUserOS() {
  const ua = navigator.userAgent.toLowerCase();
  const btnMac = document.getElementById('hero-download-mac');
  const btnWin = document.getElementById('hero-download-win');
  const osBadge = document.getElementById('detected-os-badge');

  if (ua.includes('mac')) {
    if (btnMac) btnMac.classList.add('btn-primary');
    if (btnWin) btnWin.classList.add('btn-secondary');
    if (osBadge) osBadge.textContent = 'Detected: macOS (Apple Silicon & Intel)';
  } else {
    if (btnWin) btnWin.classList.add('btn-primary');
    if (btnMac) btnMac.classList.add('btn-secondary');
    if (osBadge) osBadge.textContent = 'Detected: Windows 10/11 64-bit';
  }
}

function initFAQ() {
  const items = document.querySelectorAll('.faq-item');
  items.forEach(item => {
    const question = item.querySelector('.faq-question');
    question.addEventListener('click', () => {
      const isActive = item.classList.contains('active');
      items.forEach(i => i.classList.remove('active'));
      if (!isActive) {
        item.classList.add('active');
      }
    });
  });
}

function initMobileMenu() {
  const toggle = document.querySelector('.mobile-toggle');
  const navLinks = document.querySelector('.nav-links');
  if (toggle && navLinks) {
    toggle.addEventListener('click', (e) => {
      e.stopPropagation();
      const isActive = navLinks.classList.toggle('active');
      toggle.classList.toggle('active', isActive);
      toggle.innerHTML = isActive ? '✕' : '☰';
      document.body.classList.toggle('menu-open', isActive);
    });

    // Mobile accordion for dropdown menus
    const dropdownParents = navLinks.querySelectorAll('.has-dropdown');
    dropdownParents.forEach(parent => {
      const parentLink = parent.querySelector(':scope > a');
      if (parentLink) {
        parentLink.addEventListener('click', (e) => {
          if (window.innerWidth <= 992) {
            e.preventDefault();
            e.stopPropagation();
            const isOpen = parent.classList.contains('mobile-open');
            // Close other dropdowns for a clean accordion effect
            dropdownParents.forEach(p => {
              if (p !== parent) p.classList.remove('mobile-open');
            });
            parent.classList.toggle('mobile-open', !isOpen);
          }
        });
      }
    });

    // Close when clicking any sublink or standalone link
    navLinks.querySelectorAll('a').forEach(link => {
      link.addEventListener('click', () => {
        // Do not close drawer if tapping the top parent dropdown trigger on mobile
        if (link.parentElement && link.parentElement.classList.contains('has-dropdown') && window.innerWidth <= 992) {
          return;
        }
        navLinks.classList.remove('active');
        toggle.classList.remove('active');
        toggle.innerHTML = '☰';
        document.body.classList.remove('menu-open');
        dropdownParents.forEach(p => p.classList.remove('mobile-open'));
      });
    });

    // Close when clicking outside
    document.addEventListener('click', (e) => {
      if (navLinks.classList.contains('active') && !navLinks.contains(e.target) && !toggle.contains(e.target)) {
        navLinks.classList.remove('active');
        toggle.classList.remove('active');
        toggle.innerHTML = '☰';
        document.body.classList.remove('menu-open');
        dropdownParents.forEach(p => p.classList.remove('mobile-open'));
      }
    });

    // Close on Escape key
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && navLinks.classList.contains('active')) {
        navLinks.classList.remove('active');
        toggle.classList.remove('active');
        toggle.innerHTML = '☰';
        document.body.classList.remove('menu-open');
        dropdownParents.forEach(p => p.classList.remove('mobile-open'));
      }
    });
  }
}

function initSmoothScroll() {
  document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
      const targetId = this.getAttribute('href');
      if (targetId && targetId !== '#') {
        const target = document.querySelector(targetId);
        if (target) {
          e.preventDefault();
          target.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
      }
    });
  });
}

// 5. Pricing Tab Switcher (AI Editing vs Smart AI Editing)
function switchPricingTab(tabKey) {
  const tabAI = document.getElementById('tab-ai-editing');
  const tabSmart = document.getElementById('tab-smart-ai');
  const panelAI = document.getElementById('panel-ai-editing');
  const panelSmart = document.getElementById('panel-smart-ai');
  const caption = document.getElementById('pricing-tab-caption');

  if (!tabAI || !tabSmart || !panelAI || !panelSmart) return;

  if (tabKey === 'ai-editing') {
    tabAI.classList.add('active');
    tabAI.setAttribute('aria-selected', 'true');
    tabSmart.classList.remove('active');
    tabSmart.setAttribute('aria-selected', 'false');

    panelAI.classList.add('active');
    panelSmart.classList.remove('active');

    if (caption) {
      caption.textContent = 'Automatic trimming, beat sync and silence cutting — everything runs on your computer.';
    }
  } else {
    tabSmart.classList.add('active');
    tabSmart.setAttribute('aria-selected', 'true');
    tabAI.classList.remove('active');
    tabAI.setAttribute('aria-selected', 'false');

    panelSmart.classList.add('active');
    panelAI.classList.remove('active');

    if (caption) {
      caption.textContent = 'Smart AI watches your footage, picks the best moments and edits on your command. Unlimited AI Editing included.';
    }
  }
}

// 6. Interactive Hero NLE Workflow Switcher
function switchHeroNLE(editorKey) {
  const cards = document.querySelectorAll('.nle-bridge-card');
  const mockupImg = document.getElementById('hero-nle-mockup-img');
  const mockupTitle = document.getElementById('hero-nle-mockup-title');
  const badgeRight = document.getElementById('hero-badge-nle');

  if (!cards.length || !mockupImg) return;

  cards.forEach(card => card.classList.remove('active'));

  const activeCard = document.querySelector(`.nle-bridge-card[data-editor="${editorKey}"]`);
  if (activeCard) activeCard.classList.add('active');

  if (editorKey === 'premiere') {
    mockupImg.src = 'assets/premiere-pro-multicam.jpg';
    mockupImg.alt = 'AI Video Flow + Adobe Premiere Pro CEP Bridge';
    if (mockupTitle) mockupTitle.textContent = 'AI VIDEO FLOW ➔ ADOBE PREMIERE PRO (NATIVE CEP MULTICAM)';
    if (badgeRight) badgeRight.innerHTML = '<span>🎬 Native CEP Sequence Created in Premiere Pro</span>';
  } else if (editorKey === 'edius') {
    mockupImg.src = 'assets/edius-wedding-workflow.jpg';
    mockupImg.alt = 'AI Video Flow + Grass Valley EDIUS 25fps Workflow';
    if (mockupTitle) mockupTitle.textContent = 'AI VIDEO FLOW ➔ GRASS VALLEY EDIUS 9/X/11 (25fps PAL SEQUENCE)';
    if (badgeRight) badgeRight.innerHTML = '<span>⚡ 25fps PAL Timeline Exported to EDIUS</span>';
  } else if (editorKey === 'davinci') {
    mockupImg.src = 'assets/davinci-resolve-workflow.jpg';
    mockupImg.alt = 'AI Video Flow + DaVinci Resolve 10-bit Color Grade Workflow';
    if (mockupTitle) mockupTitle.textContent = 'AI VIDEO FLOW ➔ DAVINCI RESOLVE (FCPXML 10-BIT COLOR GRADE)';
    if (badgeRight) badgeRight.innerHTML = '<span>🎨 FCPXML Timeline Ready for DaVinci Color Grade</span>';
  }
}
