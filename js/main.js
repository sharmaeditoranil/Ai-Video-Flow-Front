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

    // Close when clicking any nav link
    navLinks.querySelectorAll('a').forEach(link => {
      link.addEventListener('click', () => {
        navLinks.classList.remove('active');
        toggle.classList.remove('active');
        toggle.innerHTML = '☰';
        document.body.classList.remove('menu-open');
      });
    });

    // Close when clicking outside
    document.addEventListener('click', (e) => {
      if (navLinks.classList.contains('active') && !navLinks.contains(e.target) && !toggle.contains(e.target)) {
        navLinks.classList.remove('active');
        toggle.classList.remove('active');
        toggle.innerHTML = '☰';
        document.body.classList.remove('menu-open');
      }
    });

    // Close on Escape key
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && navLinks.classList.contains('active')) {
        navLinks.classList.remove('active');
        toggle.classList.remove('active');
        toggle.innerHTML = '☰';
        document.body.classList.remove('menu-open');
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
