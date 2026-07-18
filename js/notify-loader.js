/** Loads push-notify.js for logged-in dashboard areas */
(function () {
  var s = document.createElement('script');
  s.src = (location.pathname.indexOf('/dashboard/') !== -1 || location.pathname.indexOf('/admin/') !== -1)
    ? '../js/push-notify.js'
    : '/js/push-notify.js';
  s.defer = true;
  document.head.appendChild(s);
})();
