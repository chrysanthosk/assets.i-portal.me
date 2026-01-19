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
});
