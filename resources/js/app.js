import 'bootstrap';
import 'admin-lte';

// Make zxcvbn available globally for the password meters in Blade
import zxcvbn from 'zxcvbn';
window.zxcvbn = zxcvbn;
