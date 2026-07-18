// Reusable Dark Mode Script + mobile logout helper
(function() {
  const html = document.documentElement;

  function setTheme(theme) {
    html.setAttribute('data-theme', theme);
    localStorage.setItem('theme', theme);
  }

  // Load saved theme
  const savedTheme = localStorage.getItem('theme');
  if (savedTheme) {
    html.setAttribute('data-theme', savedTheme);
  } else if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
    html.setAttribute('data-theme', 'dark');
  }

  // Toggle function (can be called from buttons)
  window.toggleDarkMode = function() {
    const current = html.getAttribute('data-theme');
    const newTheme = current === 'dark' ? 'light' : 'dark';
    setTheme(newTheme);
  };

  // Optional: Add keyboard shortcut (Ctrl/Cmd + Shift + D)
  document.addEventListener('keydown', function(e) {
    if ((e.ctrlKey || e.metaKey) && e.shiftKey && e.key.toLowerCase() === 'd') {
      e.preventDefault();
      window.toggleDarkMode();
    }
  });
})();

// ===== Easy mobile logout =====
(function () {
  var STYLE_ID = 'cc-mobile-logout-style';
  var BAR_ID = 'mobileLogoutBar';

  function injectStyles() {
    if (document.getElementById(STYLE_ID)) return;
    var style = document.createElement('style');
    style.id = STYLE_ID;
    style.textContent = [
      '/* Keep logout visible in header on phones */',
      '@media (max-width: 768px) {',
      '  .nav-actions a[href*="logout"],',
      '  .nav-actions .btn-logout {',
      '    display: inline-flex !important;',
      '    align-items: center;',
      '    justify-content: center;',
      '    min-height: 40px;',
      '    padding: 8px 14px !important;',
      '    font-size: 0.82rem !important;',
      '    font-weight: 700;',
      '    border-radius: 999px;',
      '    border: 2px solid #DC2626 !important;',
      '    color: #DC2626 !important;',
      '    background: #FEF2F2 !important;',
      '    white-space: nowrap;',
      '  }',
      '  .mobile-logout-bar {',
      '    position: fixed;',
      '    left: 0; right: 0; bottom: 0;',
      '    z-index: 99998;',
      '    display: flex;',
      '    justify-content: center;',
      '    padding: 10px 14px calc(10px + env(safe-area-inset-bottom, 0px));',
      '    background: rgba(255,255,255,0.96);',
      '    backdrop-filter: blur(12px);',
      '    -webkit-backdrop-filter: blur(12px);',
      '    border-top: 1px solid #E5E7EB;',
      '    box-shadow: 0 -6px 20px rgba(15,23,42,0.08);',
      '  }',
      '  .mobile-logout-btn {',
      '    display: inline-flex;',
      '    align-items: center;',
      '    justify-content: center;',
      '    gap: 8px;',
      '    width: 100%;',
      '    max-width: 420px;',
      '    min-height: 48px;',
      '    border-radius: 999px;',
      '    background: #DC2626;',
      '    color: #fff !important;',
      '    font-weight: 700;',
      '    font-size: 1rem;',
      '    text-decoration: none !important;',
      '    box-shadow: 0 6px 16px rgba(220,38,38,0.28);',
      '  }',
      '  body.has-mobile-logout {',
      '    padding-bottom: 78px !important;',
      '  }',
      '  [data-theme="dark"] .mobile-logout-bar {',
      '    background: rgba(15,23,42,0.96);',
      '    border-top-color: #334155;',
      '  }',
      '  [data-theme="dark"] .nav-actions a[href*="logout"] {',
      '    background: #450a0a !important;',
      '    color: #FECACA !important;',
      '    border-color: #F87171 !important;',
      '  }',
      '}',
      '@media (min-width: 769px) {',
      '  .mobile-logout-bar { display: none !important; }',
      '}'
    ].join('\n');
    document.head.appendChild(style);
  }

  function isMobile() {
    return window.matchMedia && window.matchMedia('(max-width: 768px)').matches;
  }

  function findLogoutHref() {
    var links = document.querySelectorAll('a[href*="logout"]');
    if (!links.length) return null;
    return links[0].getAttribute('href');
  }

  function ensureBar() {
    injectStyles();
    var href = findLogoutHref();
    var existing = document.getElementById(BAR_ID);

    if (!href || !isMobile()) {
      if (existing) existing.remove();
      document.body.classList.remove('has-mobile-logout');
      return;
    }

    // Also label header logout clearly on mobile
    document.querySelectorAll('a[href*="logout"]').forEach(function (a) {
      if (!a.classList.contains('btn-logout')) a.classList.add('btn-logout');
      if ((a.textContent || '').trim().toLowerCase() === 'logout') {
        a.textContent = 'Log out';
      }
    });

    if (existing) return;

    var bar = document.createElement('div');
    bar.id = BAR_ID;
    bar.className = 'mobile-logout-bar';
    bar.innerHTML =
      '<a href="' + href + '" class="mobile-logout-btn" aria-label="Log out of Care Connect">' +
      '<span aria-hidden="true">🚪</span><span>Log out</span></a>';
    document.body.appendChild(bar);
    document.body.classList.add('has-mobile-logout');
  }

  function boot() {
    ensureBar();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
  window.addEventListener('resize', ensureBar);
})();
