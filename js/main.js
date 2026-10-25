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
    toggle.addEventListener('click', () => {
      const isVisible = navLinks.style.display === 'flex';
      navLinks.style.display = isVisible ? 'none' : 'flex';
      navLinks.style.flexDirection = 'column';
      navLinks.style.position = 'absolute';
      navLinks.style.top = '100%';
      navLinks.style.left = '0';
      navLinks.style.width = '100%';
      navLinks.style.background = '#07090e';
      navLinks.style.padding = '20px';
      navLinks.style.borderBottom = '1px solid rgba(255,255,255,0.1)';
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
