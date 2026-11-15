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

  // Slow forms (data-busy="message"): show the message on the button and stop a second click.
  document.addEventListener('submit', function (ev) {
    var form = ev.target;
    if (!form.dataset || !form.dataset.busy || ev.defaultPrevented) return;
    var btn = form.querySelector('button[type=submit]');
    if (!btn) return;
    setTimeout(function () { btn.disabled = true; btn.textContent = form.dataset.busy; }, 0);
  });

  // Example photos: "Create all photos" sends one request per kind of photo, one after the other, and reports as it goes.
  var makeAll = document.getElementById('make-all');
  if (makeAll && window.fetch) {
    makeAll.addEventListener('click', function () {
      var form = document.getElementById('make-form');
      var out = document.getElementById('make-progress');
      if (!form.reportValidity()) return;
      var words = form.elements.prompt.value;
      var todo = Array.prototype.filter.call(form.elements.slug.options, function (o) { return !o.dataset.draft; });
      var buttons = form.querySelectorAll('button');
      function say(text, bad) {
        out.hidden = false;
        out.textContent = text;
        out.classList.toggle('note-bad', !!bad);
        out.classList.toggle('note-ok', !bad);
      }
      if (!todo.length) { say('Every kind of photo already has a picture waiting below. Use or discard those first.'); return; }
      buttons.forEach(function (b) { b.disabled = true; });
      (function next(i) {
        if (i >= todo.length) {
          say('All ' + todo.length + ' pictures are created. Loading them now.');
          location.hash = 'make';
          location.reload();
          return;
        }
        var name = todo[i].textContent;
        say('Creating picture ' + (i + 1) + ' of ' + todo.length + ': ' + name + '. Keep this page open.');
        var data = new FormData();
        data.append('_csrf', form.elements._csrf.value);
        data.append('slug', todo[i].value);
        data.append('prompt', words);
        fetch(form.action, { method: 'POST', body: data, credentials: 'same-origin', headers: { 'X-Requested-With': 'fetch' } })
          .then(function (r) { return r.json(); })
          .then(function (j) {
            if (!j.ok) throw new Error(j.message);
            todo[i].dataset.draft = '1';
            next(i + 1);
          })
          .catch(function (err) {
            buttons.forEach(function (b) { b.disabled = false; });
            say('Stopped at ' + name + ' after ' + i + (i === 1 ? ' picture. ' : ' pictures. ') + (err.message || 'The server did not answer.') + ' Reload the page to see the pictures made so far, then press the button again to continue.', true);
          });
      })(0);
    });
  }

  // "Copy" buttons (data-copy="id of the field"): put the field's text on the clipboard and say so.
  document.addEventListener('click', function (ev) {
    var btn = ev.target.closest('[data-copy]');
    if (!btn) return;
    var field = document.getElementById(btn.dataset.copy);
    if (!field) return;
    field.select();
    var done = function () {
      var was = btn.textContent;
      btn.textContent = 'Copied';
      setTimeout(function () { btn.textContent = was; }, 1600);
    };
    if (navigator.clipboard && navigator.clipboard.writeText) navigator.clipboard.writeText(field.value).then(done, function () {});
    else if (document.execCommand && document.execCommand('copy')) done();
  });

  // Email settings: picking a security type fills in its usual port (unless a custom port was typed).
  var secure = document.getElementById('smtp_secure');
  var port = document.getElementById('smtp_port');
  if (secure && port) {
    secure.addEventListener('change', function () {
      var usual = { tls: '587', ssl: '465', none: '25' };
      if (['587', '465', '25', ''].indexOf(port.value) !== -1) port.value = usual[secure.value];
    });
  }

  // Photos flagged by the safety scan stay blurred until a member of staff chooses to look.
  document.addEventListener('click', function (ev) {
    var btn = ev.target.closest('[data-reveal]');
    if (!btn) return;
    var holder = btn.closest('.veil');
    if (holder) holder.classList.remove('veil');
    btn.remove();
  });
})();
