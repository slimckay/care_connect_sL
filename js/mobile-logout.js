/**
 * Adds a sticky bottom Logout button on mobile for logged-in pages.
 * Safe to include on any page that has a logout link in the header.
 */
(function () {
  function isMobile() {
    return window.matchMedia && window.matchMedia('(max-width: 768px)').matches;
  }

  function findLogoutHref() {
    var links = document.querySelectorAll('a[href*="logout"]');
    if (links.length) return links[0].getAttribute('href');
    return null;
  }

  function ensureBar() {
    var href = findLogoutHref();
    if (!href) return;

    var existing = document.getElementById('mobileLogoutBar');
    if (!isMobile()) {
      if (existing) existing.remove();
      document.body.classList.remove('has-mobile-logout');
      return;
    }

    if (existing) return;

    var bar = document.createElement('div');
    bar.id = 'mobileLogoutBar';
    bar.className = 'mobile-logout-bar';
    bar.innerHTML =
      '<a href="' + href + '" class="mobile-logout-btn" aria-label="Log out">' +
      '<span class="mobile-logout-icon">🚪</span>' +
      '<span>Log out</span>' +
      '</a>';
    document.body.appendChild(bar);
    document.body.classList.add('has-mobile-logout');
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', ensureBar);
  } else {
    ensureBar();
  }

  window.addEventListener('resize', ensureBar);
})();
