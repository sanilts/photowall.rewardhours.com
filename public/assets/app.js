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

  // Details form: a note when the chosen country is not where the connection is. It informs; it does not stop anyone.
  var country = document.getElementById('country');
  var countryNote = document.getElementById('country-note');
  if (country && countryNote && country.dataset.here) {
    var tell = function () { countryNote.hidden = !country.value || country.value === country.dataset.here; };
    country.addEventListener('change', tell);
    tell();
  }

  // Charts: each bar's height comes from data-h (a percentage), and pointing at a day shows its numbers.
  Array.prototype.forEach.call(document.querySelectorAll('.chart-bars'), function (bars) {
    var tip = null;
    Array.prototype.forEach.call(bars.children, function (li) {
      var bar = li.querySelector('.chart-bar');
      var h = parseFloat(li.dataset.h || '0');
      if (bar) bar.style.height = (h > 0 ? Math.max(h, 1) : 0) + '%'; // a day with visitors always shows at least a sliver
      function show(x, y) {
        if (!tip) { tip = document.createElement('div'); tip.className = 'chart-tip'; tip.setAttribute('role', 'status'); document.body.appendChild(tip); }
        tip.textContent = li.dataset.tip || '';
        tip.hidden = false;
        var w = tip.offsetWidth;
        tip.style.left = Math.max(8, Math.min(x - w / 2, window.innerWidth - w - 8)) + 'px';
        tip.style.top = Math.max(8, y - tip.offsetHeight - 14) + 'px';
        li.classList.add('is-on');
      }
      function hide() { if (tip) tip.hidden = true; li.classList.remove('is-on'); }
      li.addEventListener('pointermove', function (ev) { show(ev.clientX, ev.clientY); });
      li.addEventListener('pointerleave', hide);
      li.addEventListener('pointerdown', function (ev) { show(ev.clientX, ev.clientY); });
    });
    window.addEventListener('scroll', function () { if (tip) tip.hidden = true; }, { passive: true });
  });

  // Tabs: a [data-tabs] box holds links a[data-tab] and parts [data-panel]. One part shows at a time.
  // The address after # chooses the part, also when it names something inside a part (so links and
  // the page a form returns to still land in the right place). Without JavaScript every part shows.
  Array.prototype.forEach.call(document.querySelectorAll('[data-tabs]'), function (box) {
    var links = box.querySelectorAll('a[data-tab]');
    var panels = box.querySelectorAll('[data-panel]');
    if (!links.length || !panels.length) return;
    function open(name) {
      Array.prototype.forEach.call(panels, function (p) { p.hidden = p.dataset.panel !== name; });
      Array.prototype.forEach.call(links, function (a) {
        if (a.dataset.tab === name) a.setAttribute('aria-current', 'page'); else a.removeAttribute('aria-current');
      });
    }
    function fromAddress() {
      var el = location.hash.length > 1 ? document.getElementById(decodeURIComponent(location.hash.slice(1))) : null;
      var panel = el && el.closest('[data-panel]');
      if (!panel || !box.contains(panel)) return null;
      return { name: panel.dataset.panel, inner: el !== panel ? el : null };
    }
    function show() {
      var to = fromAddress();
      open(to ? to.name : links[0].dataset.tab);
      // a message from the form just sent sits at the top of the page: keep it in view
      if (document.querySelector('.flashes') || !to || !to.inner) window.scrollTo(0, 0); else to.inner.scrollIntoView();
    }
    box.classList.add('tabs-on');
    show();
    Array.prototype.forEach.call(links, function (a) {
      a.addEventListener('click', function (ev) {
        ev.preventDefault();
        open(a.dataset.tab);
        if (history.replaceState) history.replaceState(null, '', '#' + a.dataset.tab);
      });
    });
    window.addEventListener('hashchange', show);
    // the browser moves to the #part once the page has loaded; put the view back where it belongs
    window.addEventListener('load', function () { if (location.hash) show(); });
  });

  // Email settings: only the details of the chosen way of sending are shown.
  var driver = document.querySelector('[data-choice="driver"]');
  if (driver) {
    var forDriver = function () {
      Array.prototype.forEach.call(document.querySelectorAll('[data-for-driver]'), function (el) { el.hidden = el.dataset.forDriver !== driver.value; });
    };
    driver.addEventListener('change', forDriver);
    forDriver();
  }

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
  // The language list closes when the reader taps elsewhere or presses Escape.
  var lang = document.getElementById('lang');
  if (lang) {
    document.addEventListener('click', function (ev) { if (lang.open && !lang.contains(ev.target)) lang.open = false; });
    document.addEventListener('keydown', function (ev) { if (ev.key === 'Escape' && lang.open) { lang.open = false; lang.querySelector('summary').focus(); } });
  }

  // The button bar along the bottom of a project page (phones) steps aside while the sign-in form is on screen.
  var cta = document.querySelector('[data-cta]');
  var form = document.querySelector('.start-card');
  if (cta && form && 'IntersectionObserver' in window) {
    new IntersectionObserver(function (seen) {
      cta.classList.toggle('is-away', seen[0].isIntersecting);
    }, { rootMargin: '0px 0px -120px 0px' }).observe(form);
  }

  document.addEventListener('click', function (ev) {
    var btn = ev.target.closest('[data-reveal]');
    if (!btn) return;
    var holder = btn.closest('.veil');
    if (holder) holder.classList.remove('veil');
    btn.remove();
  });
})();
