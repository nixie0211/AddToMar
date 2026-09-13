(function () {
  'use strict';

  const modal = document.getElementById('map-area-modal');
  const messageEl = document.getElementById('map-area-modal-message');
  if (!modal) return;

  function closeMapArea() {
    modal.hidden = true;
    const locationOpen = document.getElementById('google-location-modal')?.hidden === false;
    if (!locationOpen) {
      document.body.classList.remove('modal-open');
    }
  }

  window.showMapAreaPopup = function (message) {
    if (messageEl && message) {
      messageEl.textContent = message;
    }
    document.body.appendChild(modal);
    modal.hidden = false;
    modal.style.zIndex = '200000';
    document.body.classList.add('modal-open');
  };

  modal.querySelectorAll('[data-map-area-close]').forEach((el) => {
    el.addEventListener('click', closeMapArea);
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && !modal.hidden) {
      event.preventDefault();
      closeMapArea();
    }
  });
})();
