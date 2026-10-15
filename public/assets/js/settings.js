/* Account Settings: change own password */
(function () {
  'use strict';
  const { EMS } = window;
  const form = document.getElementById('passwordForm');
  if (!form) return;
  EMS.ajaxForm(form, {
    path: 'api/account/password',
    busyLabel: 'Updating…',
    validate: (f) => {
      const e = EMS.validateNewPassword(f, 'new_password', 'confirm_password');
      if (!f.current_password.value) e.current_password = 'Enter your current password.';
      else if (f.current_password.value === f.new_password.value) e.new_password = 'Your new password must be different from your current password.';
      return e;
    },
    onSuccess: (res, f) => {
      f.reset();
      f.querySelectorAll('input').forEach((i) => i.dispatchEvent(new Event('input')));
      Swal.fire({ icon: 'success', title: 'Password updated', text: res.message });
    },
  });
})();
