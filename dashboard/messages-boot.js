/* Extra boot for messages page notifications */
(function () {
  // Ensure SW + notify script
  var s = document.createElement('script');
  s.src = '../js/push-notify.js';
  s.defer = true;
  document.head.appendChild(s);

  function addBell() {
    var header = document.querySelector('.side-header');
    if (!header || document.getElementById('ccNotifyBell')) return;
    var btn = document.createElement('button');
    btn.id = 'ccNotifyBell';
    btn.className = 'icon-btn';
    btn.type = 'button';
    btn.title = 'Enable phone notifications';
    btn.textContent = '🔔';
    btn.onclick = function () {
      if (window.ccEnableNotifications) window.ccEnableNotifications();
      else alert('Loading notifications... tap again in a second.');
    };
    header.appendChild(btn);
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', addBell);
  else addBell();
})();
