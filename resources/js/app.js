import 'bootstrap';
import 'admin-lte';

// Make zxcvbn available globally for the password meters in Blade
import zxcvbn from 'zxcvbn';
window.zxcvbn = zxcvbn;

// Ensure AdminLTE treeview works reliably with Vite
document.addEventListener('DOMContentLoaded', () => {
  // AdminLTE auto-inits by data-lte-toggle attributes,
  // but Vite hot reload / caching sometimes needs a nudge.
  // This forces a reflow and keeps sidebar treeview responsive.
  document.body.dispatchEvent(new Event('adminlte:init'));

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
