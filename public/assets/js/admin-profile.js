/* Administrator employee profile: photo fallback */
(function () {
  'use strict';
  document.querySelectorAll('img[data-photo-fallback]').forEach((img) => {
    img.addEventListener('error', () => {
      const ph = document.createElement('div');
      ph.className = 'profile-photo';
      ph.style.cssText = img.style.cssText;
      ph.innerHTML = '<i class="fa-solid fa-user" aria-hidden="true"></i>';
      img.replaceWith(ph);
    }, { once: true });
  });
})();
