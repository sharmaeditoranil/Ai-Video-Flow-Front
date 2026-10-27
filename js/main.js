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

// 7. Interactive Workflow Studio UI (Screenshots 1 & 2)
let currentStudioWf = 'tutorial';
let currentStudioDest = 'davinci';

const studioWfData = {
  wedding: {
    title: 'Wedding Film Suite',
    sub: 'Teaser · Highlight · Full film (2–8 Cams)',
    speed: '14 Secs',
    cuts: '120+ Cuts',
    nleOutput: 'Multi-cam XML sequence with ceremony markers & ritual tags',
    saving: 'Save 10–14 Hours'
  },
  reels: {
    title: 'Reels & Shorts Viral Engine',
    sub: '9:16 vertical, fast cuts & audio beat transient tracking',
    speed: '8 Secs',
    cuts: '45 Snappy Cuts',
    nleOutput: '9:16 vertical 1080x1920 sequence ready for IG & YouTube',
    saving: 'Save 3–5 Hours'
  },
  youtube: {
    title: 'YouTube Creator Suite',
    sub: 'Main long-form timeline + automatically generated Shorts',
    speed: '12 Secs',
    cuts: '80+ Cuts',
    nleOutput: 'Primary 16:9 narrative timeline + vertical teaser sub-sequences',
    saving: 'Save 6–8 Hours'
  },
  music: {
    title: 'Music Video Montage',
    sub: 'Beat-synced montage snapped to audio waveforms',
    speed: '9 Secs',
    cuts: '60+ Beat Cuts',
    nleOutput: 'Rhythmic jump cuts mapped on every musical transient',
    saving: 'Save 4–6 Hours'
  },
  podcast: {
    title: 'Podcast & Interview Master',
    sub: 'Cut dead silences + 9:16 viral video shorts',
    speed: '11 Secs',
    cuts: '150+ Pause Cuts',
    nleOutput: 'Dynamic speaker switcher track with zero dead air',
    saving: 'Save 5–7 Hours'
  },
  tutorial: {
    title: 'Tutorial / Class Suite',
    sub: 'Cut dead air + auto chapters & screen recording sync',
    speed: '12 Secs',
    cuts: '95+ Gap Cuts',
    nleOutput: 'Cleaned master track with chapter markers for export',
    saving: 'Save 6–9 Hours'
  }
};

const studioDestNames = {
  davinci: 'DaVinci Resolve (10-Bit FCPXML)',
  premiere: 'Adobe Premiere Pro (Native CEP Bridge)',
  edius: 'Grass Valley EDIUS (25fps PAL Project)',
  all: 'All Supported NLEs (Universal XML & FCPXML)'
};

function selectStudioWorkflow(wfKey) {
  currentStudioWf = wfKey;
  document.querySelectorAll('.wf-choice-card').forEach(card => {
    card.classList.toggle('active', card.getAttribute('data-wf') === wfKey);
  });
  updateStudioTelemetry();
}

function selectStudioDestination(destKey) {
  currentStudioDest = destKey;
  document.querySelectorAll('.sendto-card').forEach(card => {
    card.classList.toggle('active', card.getAttribute('data-dest') === destKey);
  });
  updateStudioTelemetry();
}

function updateStudioTelemetry() {
  const data = studioWfData[currentStudioWf] || studioWfData.tutorial;
  const destName = studioDestNames[currentStudioDest] || studioDestNames.davinci;

  const titleEl = document.getElementById('wf-telemetry-wf-title');
  const speedEl = document.getElementById('wf-telemetry-speed');
  const cutsEl = document.getElementById('wf-telemetry-cuts');
  const saveEl = document.getElementById('wf-telemetry-save');
  const logDestEl = document.getElementById('wf-sim-dest-name');
  const logDescEl = document.getElementById('wf-sim-desc');

  if (titleEl) titleEl.textContent = data.title;
  if (speedEl) speedEl.textContent = data.speed;
  if (cutsEl) cutsEl.textContent = data.cuts;
  if (saveEl) saveEl.textContent = data.saving;
  if (logDestEl) logDestEl.textContent = destName;
  if (logDescEl) logDescEl.textContent = data.nleOutput;
}

function triggerTimelineBuild() {
  const btn = document.getElementById('btn-build-timelines');
  const bar = document.getElementById('wf-sim-progress-fill');
  const statusEl = document.getElementById('wf-sim-status-text');

  if (!btn) return;
  
  const originalText = btn.innerHTML;
  btn.disabled = true;
  btn.style.opacity = '0.9';
  btn.innerHTML = '⚡ Building Multi-cam Timelines...';

  if (bar) bar.style.width = '0%';
  if (statusEl) statusEl.innerHTML = '<span class="progress">⏳ AI analyzing multi-track footage &amp; audio waveforms...</span>';

  let step = 0;
  const interval = setInterval(() => {
    step += 25;
    if (bar) bar.style.width = step + '%';

    if (step === 25 && statusEl) {
      statusEl.innerHTML = '<span class="progress">⚡ Zero-Audio optical sync locked across all angles...</span>';
    } else if (step === 50 && statusEl) {
      statusEl.innerHTML = '<span class="progress">✂️ Smart In-Clip trimmer removing blurry pans &amp; dead air...</span>';
    } else if (step === 75 && statusEl) {
      statusEl.innerHTML = '<span class="progress">🎬 Rendering native sequence for ' + (studioDestNames[currentStudioDest] || 'Editor') + '...</span>';
    } else if (step >= 100) {
      clearInterval(interval);
      if (bar) bar.style.width = '100%';
      if (statusEl) {
        statusEl.innerHTML = '<span class="check">✓ Success: ' + (studioWfData[currentStudioWf]?.title || 'Timeline') + ' ready! Open directly in ' + (studioDestNames[currentStudioDest] || 'NLE') + '.</span>';
      }
      btn.innerHTML = '✓ Timelines Built Successfully!';
      btn.style.background = 'linear-gradient(135deg, #10b981 0%, #059669 100%)';

      setTimeout(() => {
        btn.innerHTML = originalText;
        btn.style.background = '';
        btn.disabled = false;
        btn.style.opacity = '1';
      }, 3200);
    }
  }, 350);
}

