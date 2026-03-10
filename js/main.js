// ============================================================
//  Omnes MarketPlace — JavaScript principal
// ============================================================

document.addEventListener('DOMContentLoaded', () => {

  // ── Timers d'enchère ────────────────────────────────────
  document.querySelectorAll('.auction-timer[data-end]').forEach(el => {
    function tick() {
      const diff = new Date(el.dataset.end) - Date.now();
      if (diff <= 0) { el.textContent = '⏰ Terminée'; return; }
      const d = Math.floor(diff / 86400000);
      const h = Math.floor((diff % 86400000) / 3600000);
      const m = Math.floor((diff % 3600000) / 60000);
      const s = Math.floor((diff % 60000) / 1000);
      if (el.classList.contains('auction-timer') && el.children.length === 0) {
        el.textContent = `${d}j ${String(h).padStart(2,'0')}h ${String(m).padStart(2,'0')}m ${String(s).padStart(2,'0')}s`;
      }
    }
    tick(); setInterval(tick, 1000);
  });

  // ── Auto-dismiss alerts ─────────────────────────────────
  document.querySelectorAll('.alert').forEach(alert => {
    setTimeout(() => {
      alert.style.transition = 'opacity .5s';
      alert.style.opacity    = '0';
      setTimeout(() => alert.remove(), 500);
    }, 6000);
  });

  // ── Smooth scroll pour ancres ───────────────────────────
  document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', e => {
      const target = document.querySelector(anchor.getAttribute('href'));
      if (target) { e.preventDefault(); target.scrollIntoView({ behavior: 'smooth' }); }
    });
  });

  // ── Lazy load images ────────────────────────────────────
  if ('IntersectionObserver' in window) {
    const lazyImages = document.querySelectorAll('img[loading="lazy"]');
    const observer = new IntersectionObserver(entries => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          const img = entry.target;
          if (img.dataset.src) { img.src = img.dataset.src; }
          observer.unobserve(img);
        }
      });
    });
    lazyImages.forEach(img => observer.observe(img));
  }

  // ── Format numéro carte ─────────────────────────────────
  const cardInput = document.querySelector('input[name="num_carte"]');
  if (cardInput) {
    cardInput.addEventListener('input', function() {
      let v = this.value.replace(/\D/g, '').substring(0, 16);
      this.value = v.replace(/(.{4})/g, '$1 ').trim();
    });
  }

  // ── Expiration carte ────────────────────────────────────
  const expInput = document.querySelector('input[name="exp_carte"]');
  if (expInput) {
    expInput.addEventListener('input', function() {
      let v = this.value.replace(/\D/g, '').substring(0, 4);
      if (v.length >= 2) v = v.substring(0, 2) + '/' + v.substring(2);
      this.value = v;
    });
  }

  // ── Confirmation avant suppression ─────────────────────
  document.querySelectorAll('[data-confirm]').forEach(el => {
    el.addEventListener('click', e => {
      if (!confirm(el.dataset.confirm)) e.preventDefault();
    });
  });

  // ── Recherche live (filtre côté client) ─────────────────
  const searchInput = document.querySelector('#liveSearch');
  const searchTarget = document.querySelector('#searchTarget');
  if (searchInput && searchTarget) {
    searchInput.addEventListener('input', () => {
      const query = searchInput.value.toLowerCase();
      searchTarget.querySelectorAll('[data-searchable]').forEach(item => {
        const text = item.dataset.searchable.toLowerCase();
        item.style.display = text.includes(query) ? '' : 'none';
      });
    });
  }

  // ── Gestion mobile nav ──────────────────────────────────
  const mobileToggle = document.querySelector('#mobileNavToggle');
  const mobileNav    = document.querySelector('.nav');
  if (mobileToggle && mobileNav) {
    mobileToggle.addEventListener('click', () => {
      mobileNav.classList.toggle('mobile-open');
    });
  }

});

// ── Utilitaire flash notification ─────────────────────────
function showToast(message, type = 'success') {
  const toast = document.createElement('div');
  toast.className = `alert alert-${type}`;
  toast.style.cssText = 'position:fixed;bottom:24px;right:24px;z-index:9999;max-width:400px;animation:slideIn .3s ease';
  toast.innerHTML = `<span>${type === 'success' ? '✅' : type === 'error' ? '❌' : 'ℹ️'}</span> ${message}`;
  document.body.appendChild(toast);
  setTimeout(() => { toast.style.opacity = '0'; setTimeout(() => toast.remove(), 500); }, 4000);
}
