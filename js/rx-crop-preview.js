(function () {
  const rxPreviewCache = Object.create(null);

  function preloadRxPreview(url) {
    const src = String(url || '').trim();
    if (!src || rxPreviewCache[src]) return rxPreviewCache[src];
    const entry = { objectUrl: '', type: '' };
    entry.promise = fetch(src, { credentials: 'same-origin', cache: 'force-cache' })
      .then(function (response) {
        if (!response.ok) throw new Error('rx');
        entry.type = response.headers.get('content-type') || '';
        return response.blob();
      })
      .then(function (blob) {
        entry.type = entry.type || blob.type || '';
        entry.objectUrl = URL.createObjectURL(blob);
        return entry;
      })
      .catch(function () {
        delete rxPreviewCache[src];
        return null;
      });
    rxPreviewCache[src] = entry;
    return entry;
  }

  function rxPreviewReadyUrl(url) {
    const src = String(url || '').trim();
    const entry = rxPreviewCache[src];
    return entry && entry.objectUrl ? entry.objectUrl : '';
  }

  function rxPreviewLooksPdf(url) {
    const src = String(url || '').trim();
    if (/\.pdf($|\?)/i.test(src)) return true;
    const entry = rxPreviewCache[src];
    return !!(entry && /pdf/i.test(entry.type || ''));
  }

  window.preloadRxPreview = preloadRxPreview;
  window.rxPreviewReadyUrl = rxPreviewReadyUrl;
  window.rxPreviewLooksPdf = rxPreviewLooksPdf;
  window.preloadRxPreviewList = function (urls) {
    (urls || []).forEach(function (url) { preloadRxPreview(url); });
  };

  function bindRxCropPreview(root) {
    if (!root || root.dataset.cropBound === '1') return;
    const stage = root.querySelector('.rx-crop-stage');
    const media = root.querySelector('.rx-crop-media');
    const slider = root.querySelector('.rx-crop-zoom');
    if (!stage || !media || !slider) return;
    root.dataset.cropBound = '1';

    const state = {
      scale: 1,
      x: 0,
      y: 0,
      drag: false,
      startX: 0,
      startY: 0,
      originX: 0,
      originY: 0,
    };

    function apply() {
      const rect = stage.getBoundingClientRect();
      const extraX = Math.max(0, (rect.width * state.scale - rect.width) / 2);
      const extraY = Math.max(0, (rect.height * state.scale - rect.height) / 2);
      if (state.scale <= 1.01) {
        state.x = 0;
        state.y = 0;
      } else {
        state.x = Math.min(extraX, Math.max(-extraX, state.x));
        state.y = Math.min(extraY, Math.max(-extraY, state.y));
      }
      media.style.transform = 'translate(' + state.x + 'px, ' + state.y + 'px) scale(' + state.scale + ')';
      stage.classList.toggle('is-zoomable', state.scale > 1.01);
      if (state.scale <= 1.01) stage.classList.remove('is-panning');
    }

    root._rxCrop = {
      setScale: function (value) {
        state.scale = Number(value) || 1;
        apply();
      },
      reset: function (flags) {
        state.scale = 1;
        state.x = 0;
        state.y = 0;
        state.drag = false;
        slider.value = '1';
        media.style.transition = '';
        if (stage) {
          stage.classList.toggle('is-pdf', !!(flags && flags.pdf));
          stage.classList.toggle('is-empty', !!(flags && flags.empty));
        }
        apply();
      },
    };

    slider.addEventListener('input', function () {
      root._rxCrop.setScale(slider.value);
    });

    stage.addEventListener('pointerdown', function (event) {
      if (state.scale <= 1.01 || event.button) return;
      state.drag = true;
      state.startX = event.clientX;
      state.startY = event.clientY;
      state.originX = state.x;
      state.originY = state.y;
      media.style.transition = 'none';
      stage.classList.add('is-panning');
      if (stage.setPointerCapture) stage.setPointerCapture(event.pointerId);
      event.preventDefault();
    });

    stage.addEventListener('pointermove', function (event) {
      if (!state.drag) return;
      state.x = state.originX + (event.clientX - state.startX);
      state.y = state.originY + (event.clientY - state.startY);
      apply();
    });

    function endDrag() {
      if (!state.drag) return;
      state.drag = false;
      media.style.transition = '';
      stage.classList.remove('is-panning');
    }

    stage.addEventListener('pointerup', endDrag);
    stage.addEventListener('pointercancel', endDrag);
    stage.addEventListener('lostpointercapture', endDrag);
  }

  function resetRxCropPreview(root, flags) {
    if (!root) return;
    bindRxCropPreview(root);
    if (root._rxCrop) root._rxCrop.reset(flags || {});
  }

  window.bindRxCropPreview = bindRxCropPreview;
  window.resetRxCropPreview = resetRxCropPreview;
})();
