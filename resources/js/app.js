import 'bootstrap';

// Make zxcvbn available globally for the password meters in Blade
import zxcvbn from 'zxcvbn';
window.zxcvbn = zxcvbn;

// ---------------------------------------------------------------------------
// Theme (persisted in localStorage; <html data-bs-theme> drives everything)
// ---------------------------------------------------------------------------
const html = document.documentElement;

function applyTheme(theme) {
  html.setAttribute('data-bs-theme', theme);
  try { localStorage.setItem('theme', theme); } catch (e) { /* private mode */ }
  document.querySelectorAll('[data-theme-icon]').forEach((el) => {
    el.className = 'bi ' + (theme === 'dark' ? 'bi-sun' : 'bi-moon-stars');
  });
  document.querySelectorAll('[data-theme-toggle]').forEach((el) => {
    el.setAttribute('aria-label', theme === 'dark' ? 'Switch to light mode' : 'Switch to dark mode');
    el.setAttribute('title', theme === 'dark' ? 'Light mode' : 'Dark mode');
  });
  const label = document.getElementById('themeLabel');
  if (label) label.textContent = theme === 'dark' ? 'Light' : 'Dark';
}

window.toggleTheme = function () {
  applyTheme(html.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark');
};

let saved = 'dark';
try { saved = localStorage.getItem('theme') || 'dark'; } catch (e) { /* ignore */ }
applyTheme(saved);

// ---------------------------------------------------------------------------
// Sidebar: off-canvas on small screens, collapsible rail on desktop
// ---------------------------------------------------------------------------
const body = document.body;

function setMini(on) {
  body.classList.toggle('sidebar-mini', on);
  try { localStorage.setItem('sidebar-mini', on ? '1' : '0'); } catch (e) { /* ignore */ }
}

try { if (localStorage.getItem('sidebar-mini') === '1') body.classList.add('sidebar-mini'); } catch (e) { /* ignore */ }

document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('[data-theme-toggle]').forEach((el) => el.addEventListener('click', window.toggleTheme));
  document.querySelectorAll('[data-theme-icon]').forEach(() => applyTheme(html.getAttribute('data-bs-theme')));

  document.querySelectorAll('[data-sidebar-toggle]').forEach((el) =>
    el.addEventListener('click', () => body.classList.toggle('sidebar-open')));
  document.querySelectorAll('[data-sidebar-close]').forEach((el) =>
    el.addEventListener('click', () => body.classList.remove('sidebar-open')));
  document.querySelectorAll('[data-sidebar-mini]').forEach((el) =>
    el.addEventListener('click', () => setMini(!body.classList.contains('sidebar-mini'))));
  document.addEventListener('keydown', (e) => { if (e.key === 'Escape') body.classList.remove('sidebar-open'); });

  // Expanding a group while the rail is collapsed should widen it again
  document.querySelectorAll('.sidebar [data-bs-toggle="collapse"]').forEach((el) =>
    el.addEventListener('click', () => { if (body.classList.contains('sidebar-mini')) setMini(false); }));

  // Disable submit buttons on submit to prevent double-submission and give
  // visual feedback. Opt out with data-no-loading on the form.
  document.querySelectorAll('form').forEach((form) => {
    form.addEventListener('submit', () => {
      if (form.hasAttribute('data-no-loading') || !form.checkValidity()) return;
      form.querySelectorAll('button[type="submit"], input[type="submit"]').forEach((btn) => {
        btn.disabled = true;
        btn.classList.add('disabled');
        if (btn.tagName === 'BUTTON' && !btn.dataset.originalHtml) {
          btn.dataset.originalHtml = btn.innerHTML;
          btn.innerHTML =
            '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>' +
            (btn.dataset.loadingText || 'Working…');
        }
      });
    });
  });
});

// ---------------------------------------------------------------------------
// Password strength meters (inputs with data-password-meter="<id>")
// ---------------------------------------------------------------------------
function strengthToLabel(score) { return ['Very weak', 'Weak', 'Fair', 'Good', 'Strong'][score] || 'Very weak'; }
function strengthToWidth(score) { return [10, 25, 50, 75, 100][score] || 10; }

window.initPasswordMeters = function () {
  document.querySelectorAll('input[data-password-meter]').forEach((input) => {
    const id = input.getAttribute('data-password-meter');
    const textEl = document.getElementById(id + '-text');
    const barEl = document.getElementById(id + '-bar');
    if (!textEl || !barEl) return;

    const update = () => {
      const val = input.value || '';
      if (!val.length) { textEl.textContent = ''; barEl.style.width = '0%'; barEl.className = 'progress-bar'; return; }
      const r = window.zxcvbn(val);
      textEl.textContent = strengthToLabel(r.score);
      barEl.style.width = strengthToWidth(r.score) + '%';
      barEl.className = 'progress-bar ' + (r.score <= 1 ? 'bg-danger' : r.score === 2 ? 'bg-warning' : r.score === 3 ? 'bg-info' : 'bg-success');
    };
    input.addEventListener('input', update);
    update();
  });
};
document.addEventListener('DOMContentLoaded', () => window.initPasswordMeters());
