// Small helpers shared by every page. No pop-up alerts: confirmations use an in-page dialog.
(function () {
  'use strict';
  var dialog = document.getElementById('confirm');

  // Forms (or their buttons) with data-confirm ask first.
  document.addEventListener('submit', function (ev) {
    var form = ev.target;
    var submitter = ev.submitter;
    var text = (submitter && submitter.dataset && submitter.dataset.confirm) || (form.dataset && form.dataset.confirm);
    if (!text || form.dataset.confirmed === '1') return;
    if (!dialog || typeof dialog.showModal !== 'function') return;
    ev.preventDefault();
    document.getElementById('confirm-text').textContent = text;
    dialog.returnValue = 'no';
    dialog.showModal();
    dialog.addEventListener('close', function once() {
      dialog.removeEventListener('close', once);
      if (dialog.returnValue !== 'yes') return;
      form.dataset.confirmed = '1';
      if (submitter && form.requestSubmit) form.requestSubmit(submitter); else form.submit();
      form.dataset.confirmed = '';
    });
  });

  // "Select all" boxes: data-check-all="name of the group".
  document.addEventListener('change', function (ev) {
    var box = ev.target;
    if (box.dataset && box.dataset.checkAll) {
      document.querySelectorAll('input[type=checkbox][data-group="' + box.dataset.checkAll + '"]').forEach(function (c) { c.checked = box.checked; });
    }
    if (box.dataset && (box.dataset.group || box.dataset.checkAll)) {
      var n = document.querySelectorAll('input[type=checkbox][data-group]:checked').length;
      document.querySelectorAll('[data-count]').forEach(function (out) { out.textContent = n; });
    }
  });

  // Photos flagged by the safety scan stay blurred until a member of staff chooses to look.
  document.addEventListener('click', function (ev) {
    var btn = ev.target.closest('[data-reveal]');
    if (!btn) return;
    var holder = btn.closest('.veil');
    if (holder) holder.classList.remove('veil');
    btn.remove();
  });
})();
