/**
 * Care Connect SL — phone popup notifications for messages & referrals
 * Uses Notification API + Service Worker (works on Android Chrome; iOS needs Add to Home Screen).
 */
(function () {
  if (typeof window === 'undefined') return;

  var STORAGE_MSG = 'cc_last_msg_notify_id';
  var STORAGE_REF = 'cc_last_ref_notify_id';
  var STORAGE_PERM = 'cc_notify_asked';
  var POLL_MS = 12000;
  var swReg = null;

  function isLoggedInArea() {
    var p = location.pathname || '';
    return /\/(dashboard|admin)\//.test(p) || /messages\.php|provider-|patient-/.test(p);
  }

  function apiBase() {
    // Works from /dashboard/* and root pages
    if (location.pathname.indexOf('/dashboard/') !== -1) return '../api/notify-check.php';
    if (location.pathname.indexOf('/admin/') !== -1) return '../api/notify-check.php';
    if (location.pathname.indexOf('/pages/') !== -1) return '../api/notify-check.php';
    return '/api/notify-check.php';
  }

  function messagesUrl(convId) {
    var base = location.pathname.indexOf('/dashboard/') !== -1 ? 'messages.php' : '/dashboard/messages.php';
    return convId ? (base + '?c=' + convId) : base;
  }

  function referralsUrl() {
    return location.pathname.indexOf('/dashboard/') !== -1
      ? 'provider-referrals.php'
      : '/dashboard/provider-referrals.php';
  }

  function registerSW() {
    if (!('serviceWorker' in navigator)) return Promise.resolve(null);
    return navigator.serviceWorker.register('/sw.js').then(function (reg) {
      swReg = reg;
      return reg;
    }).catch(function () { return null; });
  }

  function ensurePermission() {
    if (!('Notification' in window)) return Promise.resolve('denied');
    if (Notification.permission === 'granted') return Promise.resolve('granted');
    if (Notification.permission === 'denied') return Promise.resolve('denied');
    // Ask once per browser profile after user is in app area
    if (sessionStorage.getItem(STORAGE_PERM) === '1') return Promise.resolve(Notification.permission);
    sessionStorage.setItem(STORAGE_PERM, '1');
    return Notification.requestPermission();
  }

  function showNative(title, body, options) {
    options = options || {};
    if (!('Notification' in window) || Notification.permission !== 'granted') return;

    var tag = options.tag || 'care-connect';
    var url = options.url || messagesUrl();

    // Prefer service worker notification (better on mobile)
    if (swReg && swReg.active) {
      swReg.active.postMessage({
        type: 'SHOW_NOTIFICATION',
        title: title,
        body: body,
        tag: tag,
        url: url,
        renotify: true,
        requireInteraction: !!options.requireInteraction
      });
      return;
    }

    if (navigator.serviceWorker && navigator.serviceWorker.ready) {
      navigator.serviceWorker.ready.then(function (reg) {
        reg.showNotification(title, {
          body: body,
          icon: '/images/icon-192.png',
          badge: '/images/icon-192.png',
          tag: tag,
          renotify: true,
          data: { url: url },
          vibrate: [120, 60, 120]
        });
      }).catch(function () {
        try {
          var n = new Notification(title, { body: body, tag: tag, icon: '/images/icon-192.png' });
          n.onclick = function () { window.focus(); location.href = url; n.close(); };
        } catch (e) {}
      });
      return;
    }

    try {
      var n2 = new Notification(title, { body: body, tag: tag, icon: '/images/icon-192.png' });
      n2.onclick = function () { window.focus(); location.href = url; n2.close(); };
    } catch (e) {}
  }

  function beep() {
    try {
      var Ctx = window.AudioContext || window.webkitAudioContext;
      if (!Ctx) return;
      var ctx = new Ctx();
      var o = ctx.createOscillator();
      var g = ctx.createGain();
      o.type = 'sine';
      o.frequency.value = 880;
      g.gain.value = 0.04;
      o.connect(g); g.connect(ctx.destination);
      o.start();
      setTimeout(function () { o.stop(); ctx.close(); }, 120);
    } catch (e) {}
  }

  function check() {
    if (document.hidden === undefined) {
      // still check
    }
    fetch(apiBase(), { credentials: 'same-origin', cache: 'no-store' })
      .then(function (r) { return r.ok ? r.json() : null; })
      .then(function (data) {
        if (!data || !data.ok) return;

        // Messages
        if (data.latest_message && data.messages_unread > 0) {
          var mid = String(data.latest_message.id);
          var prev = localStorage.getItem(STORAGE_MSG) || '';
          if (mid && mid !== prev) {
            localStorage.setItem(STORAGE_MSG, mid);
            // Don't notify if user is already viewing that chat
            var onMessages = /messages\.php/.test(location.pathname);
            var viewingSame = onMessages && location.search.indexOf('c=' + data.latest_message.conversation_id) !== -1;
            if (!viewingSame) {
              showNative(
                '💬 ' + (data.latest_message.from || 'New message'),
                data.latest_message.text || 'You have a new message',
                {
                  tag: 'cc-msg-' + mid,
                  url: messagesUrl(data.latest_message.conversation_id),
                  requireInteraction: true
                }
              );
              beep();
            }
          }
        }

        // Referrals (doctors)
        if (data.latest_referral && data.referrals_new > 0) {
          var rid = String(data.latest_referral.id);
          var prevR = localStorage.getItem(STORAGE_REF) || '';
          if (rid && rid !== prevR) {
            localStorage.setItem(STORAGE_REF, rid);
            var onRef = /provider-referrals\.php|manage-referrals\.php/.test(location.pathname);
            if (!onRef) {
              showNative(
                '📋 New referral',
                'Patient: ' + (data.latest_referral.patient_name || 'New patient') + ' — open referrals',
                {
                  tag: 'cc-ref-' + rid,
                  url: referralsUrl(),
                  requireInteraction: true
                }
              );
              beep();
            }
          }
        }
      })
      .catch(function () {});
  }

  function boot() {
    if (!isLoggedInArea()) return;

    // Link manifest if missing
    if (!document.querySelector('link[rel="manifest"]')) {
      var link = document.createElement('link');
      link.rel = 'manifest';
      link.href = '/manifest.json';
      document.head.appendChild(link);
    }

    registerSW().then(function () {
      return ensurePermission();
    }).then(function (perm) {
      if (perm === 'granted') {
        check();
        setInterval(check, POLL_MS);
        document.addEventListener('visibilitychange', function () {
          if (!document.hidden) check();
        });
      }
    });
  }

  // Public helper so pages can request permission with a button
  window.ccEnableNotifications = function () {
    return ensurePermission().then(function (perm) {
      if (perm === 'granted') {
        check();
        alert('Notifications enabled. You will get popups for new messages and referrals.');
      } else {
        alert('Notifications blocked. Enable them in your phone browser settings for this site.');
      }
      return perm;
    });
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
