/* =========================================================
   AddToMar - Main JS
========================================================= */
document.addEventListener('DOMContentLoaded', function () {

  // ---------- Sidebar toggle (mobile) ----------
  const sidebar = document.getElementById('appSidebar');
  const overlay = document.getElementById('sidebarOverlay');
  const toggleBtn = document.getElementById('sidebarToggle');
  if (toggleBtn && sidebar) {
    toggleBtn.addEventListener('click', () => {
      sidebar.classList.toggle('show');
      overlay.classList.toggle('show');
    });
  }
  if (overlay) {
    overlay.addEventListener('click', () => {
      sidebar.classList.remove('show');
      overlay.classList.remove('show');
    });
  }

  // ---------- Dark mode (disabled on hero + auth pages — always light) ----------
  const darkBtn = document.getElementById('darkModeToggle');
  const isHeroLayout = document.body.classList.contains('hero-dashboard');
  const isAuthPage = document.body.classList.contains('auth-page');

  if (isHeroLayout || isAuthPage) {
    document.body.classList.remove('dark-mode');
    localStorage.setItem('addtomar_theme', 'light');
    if (darkBtn) darkBtn.style.display = 'none';
  } else {
    const savedMode = localStorage.getItem('addtomar_theme');
    if (savedMode === 'dark') document.body.classList.add('dark-mode');
    if (darkBtn) {
      darkBtn.addEventListener('click', () => {
        document.body.classList.toggle('dark-mode');
        localStorage.setItem('addtomar_theme', document.body.classList.contains('dark-mode') ? 'dark' : 'light');
      });
    }
  }

  // ---------- Password toggle ----------
  document.querySelectorAll('.toggle-password').forEach(btn => {
    btn.addEventListener('click', function () {
      const target = this.getAttribute('data-target');
      const input = target ? document.querySelector(target) : null;
      if (!input) return;
      const isPw = input.type === 'password';
      input.type = isPw ? 'text' : 'password';
      const icon = this.querySelector('i');
      if (icon) {
        icon.className = isPw ? 'bi bi-eye-slash' : 'bi bi-eye';
      }
    });
  });

  // ---------- Image preview upload ----------
  document.querySelectorAll('.image-upload-input').forEach(input => {
    input.addEventListener('change', function () {
      const preview = document.querySelector(this.dataset.preview);
      if (preview && this.files && this.files[0]) {
        preview.src = URL.createObjectURL(this.files[0]);
      }
    });
  });

  // ---------- Bootstrap tooltips ----------
  document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => new bootstrap.Tooltip(el));
});

/* ---------- Toast helper (SweetAlert2) ---------- */
function showToast(icon, title) {
  Swal.fire({
    toast: true, position: 'top-end', icon: icon, title: title,
    showConfirmButton: false, timer: 2600, timerProgressBar: true,
  });
}

/* ---------- Confirm dialog helper ---------- */
function confirmAction(opts) {
  return Swal.fire({
    title: opts.title || 'Are you sure?',
    text: opts.text || '',
    icon: opts.icon || 'warning',
    showCancelButton: true,
    confirmButtonColor: '#10B981',
    cancelButtonColor: '#94A3B8',
    confirmButtonText: opts.confirmText || 'Yes, proceed',
  });
}

/* ---------- Generic AJAX POST (form-data safe) ---------- */
async function ajaxPost(url, data, isFormData = false) {
  const options = { method: 'POST' };
  if (isFormData) {
    options.body = data;
  } else {
    options.headers = { 'Content-Type': 'application/x-www-form-urlencoded' };
    options.body = new URLSearchParams(data).toString();
  }
  const res = await fetch(url, options);
  return res.json();
}

/* ---------- Cart badge live update ---------- */
function updateCartBadge(count) {
  const badge = document.querySelector('.btn-icon .badge-dot');
  // Cart icon is the one with bi-cart3
  document.querySelectorAll('.btn-icon').forEach(btn => {
    if (btn.querySelector('.bi-cart3')) {
      let dot = btn.querySelector('.badge-dot');
      if (count > 0) {
        if (!dot) {
          dot = document.createElement('span');
          dot.className = 'badge-dot';
          btn.appendChild(dot);
        }
        dot.textContent = count;
      } else if (dot) {
        dot.remove();
      }
    }
  });
}
