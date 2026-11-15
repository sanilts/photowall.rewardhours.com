// The contributor's page: tabs on a phone, bulk upload with a running total, and the list of their photos.
(function () {
  'use strict';

  function el(tag, cls, text) {
    var n = document.createElement(tag);
    if (cls) n.className = cls;
    if (text) n.textContent = text;
    return n;
  }
  function byId(id) { return document.getElementById(id); }

  /* ---------- tabs: on a phone one section shows at a time, like the screens of an app ---------- */
  var app = byId('app');
  var tabbar = byId('tabbar');
  function show(name, scroll) {
    var found = false;
    Array.prototype.forEach.call(document.querySelectorAll('.screen'), function (s) {
      var on = s.dataset.screen === name;
      s.classList.toggle('on', on);
      found = found || on;
    });
    if (!found) return;
    Array.prototype.forEach.call(tabbar.querySelectorAll('a'), function (a) {
      if (a.dataset.tab === name) a.setAttribute('aria-current', 'page'); else a.removeAttribute('aria-current');
    });
    if (scroll) window.scrollTo(0, 0);
  }
  if (app && tabbar) {
    app.classList.add('js-tabs');
    tabbar.addEventListener('click', function (ev) {
      var a = ev.target.closest('a');
      if (!a) return;
      ev.preventDefault();
      show(a.dataset.tab, true);
      if (history.replaceState) history.replaceState(null, '', '#' + a.dataset.tab);
    });
    show({ '#mine': 'mine', '#info': 'info', '#account': 'account' }[location.hash] || 'add', false);
  }

  /* ---------- "Your photos": show only one status ---------- */
  var filters = byId('filters');
  var list = byId('mine-list');
  if (filters && list) {
    filters.addEventListener('click', function (ev) {
      var b = ev.target.closest('button');
      if (!b) return;
      Array.prototype.forEach.call(filters.querySelectorAll('button'), function (x) { x.setAttribute('aria-pressed', x === b ? 'true' : 'false'); });
      Array.prototype.forEach.call(list.children, function (li) { li.hidden = !!b.dataset.filter && li.dataset.status !== b.dataset.filter; });
    });
  }

  /* ---------- each photo has its own category box: save a change straight away ---------- */
  document.addEventListener('change', function (ev) {
    var sel = ev.target;
    var form = sel.form;
    if (!form || !form.classList.contains('shot-cat') || !sel.value || !window.fetch) return;
    fetch(form.action, { method: 'POST', body: new FormData(form), credentials: 'same-origin', headers: { 'X-Requested-With': 'fetch' } })
      .then(function (r) { return r.json(); })
      .then(function (j) {
        sel.classList.toggle('saved', !!j.ok);
        sel.classList.toggle('need', !j.ok);
      })
      .catch(function () { form.submit(); });
  });
  Array.prototype.forEach.call(document.querySelectorAll('.shot-cat .btn'), function (b) { b.hidden = true; });

  /* ---------- bulk upload: one photo at a time, with a running total ---------- */
  var box = byId('uploader');
  var input = byId('files');
  if (!box || !input) return;
  var queue = byId('queue');
  var picker = byId('picker');
  var category = byId('category');
  var runText = byId('run-text');
  var runBar = byId('run-bar');
  var runStop = byId('run-stop');
  var runKeep = byId('run-keep');
  var can = parseInt(box.dataset.can, 10) || 0;
  var maxBytes = (parseInt(box.dataset.maxMb, 10) || 60) * 1048576;
  var cats = [];
  try { cats = JSON.parse(box.dataset.cats || '[]'); } catch (e) { cats = []; }
  var busy = false;
  var waiting = [];
  var run = { total: 0, done: 0, ok: 0, bad: 0 };

  function plural(n, word) { return n + ' ' + word + (n === 1 ? '' : 's'); }

  function report() {
    var left = run.total - run.done;
    runBar.max = Math.max(1, run.total);
    runBar.value = run.done;
    if (left > 0) {
      runText.textContent = 'Checking photo ' + (run.done + 1) + ' of ' + run.total + '. ' + run.ok + ' accepted, ' + run.bad + ' not accepted so far.';
    } else {
      runText.textContent = plural(run.total, 'photo') + ' checked: ' + run.ok + ' accepted, ' + run.bad + ' not accepted.';
    }
    runStop.hidden = left <= 0;
    runKeep.hidden = left <= 0;
  }

  function addRow(file) {
    byId('results').hidden = false;
    var li = el('li', 'q-item');
    var body = el('div', 'q-body');
    var bar = el('progress', 'q-bar');
    bar.max = 100; bar.value = 0;
    var msg = el('span', 'q-msg', 'Waiting');
    body.appendChild(el('span', 'q-name', file.name)); body.appendChild(msg);
    li.appendChild(el('span', 'q-mark')); li.appendChild(body); li.appendChild(bar);
    queue.appendChild(li);
    return { li: li, bar: bar, msg: msg };
  }

  function finish(job, ok, text) {
    job.row.bar.remove();
    job.row.li.classList.add(ok ? 'q-ok' : 'q-bad');
    job.row.msg.textContent = text;
    run.done++;
    if (ok) run.ok++; else run.bad++;
    report();
  }

  function applyAllowance(a) {
    if (!a) return;
    can = a.can;
    var used = byId('used');
    var usedBar = byId('used-bar');
    var today = byId('today-left');
    if (used) used.textContent = a.used;
    if (usedBar) usedBar.value = a.used;
    if (today && a.today_left !== null) today.textContent = a.today_left;
    if (can <= 0 && picker) {
      picker.hidden = true;
      var more = byId('picker-more');
      if (more) more.hidden = true;
    }
  }

  function bump(id) {
    var n = byId(id);
    if (!n) return;
    n.textContent = (parseInt(n.textContent, 10) || 0) + 1;
    n.hidden = false;
  }

  // A newly accepted photo joins "Your photos" straight away, with its category ready to change.
  function addThumb(photo) {
    var none = byId('none-yet');
    if (none) none.remove();
    ['n-review', 'sum-review', 'sum-all', 'tab-n'].forEach(bump);
    var li = el('li', 'shot');
    li.dataset.status = 'pending';
    var img = el('img');
    img.src = photo.thumb; img.alt = 'Your uploaded photo'; img.width = 240; img.height = 240;
    li.appendChild(img);
    li.appendChild(el('span', 'chip chip-pending', 'In review'));
    var id = el('span', 'shot-id');
    id.appendChild(el('strong', '', photo.image_id));
    id.appendChild(document.createElement('br'));
    id.appendChild(document.createTextNode(photo.name || ''));
    li.appendChild(id);
    var form = el('form', 'shot-cat');
    form.method = 'post';
    form.action = box.dataset.url.replace(/upload$/, 'photo/' + photo.token + '/category');
    var csrf = el('input');
    csrf.type = 'hidden'; csrf.name = '_csrf'; csrf.value = box.dataset.csrf;
    var sel = el('select');
    sel.name = 'category';
    sel.setAttribute('aria-label', 'Category of this photo');
    cats.forEach(function (c) {
      var o = el('option', '', c);
      o.selected = c === photo.category;
      sel.appendChild(o);
    });
    form.appendChild(csrf); form.appendChild(sel);
    li.appendChild(form);
    list.insertBefore(li, list.firstChild);
  }

  function next() {
    if (busy) return;
    var job = waiting.shift();
    if (!job) return;
    if (can <= 0) {
      finish(job, false, 'Not uploaded: you have reached your limit for now.');
      return next();
    }
    if (job.file.size > maxBytes) {
      finish(job, false, 'This file is too large (limit ' + box.dataset.maxMb + ' MB).');
      return next();
    }
    busy = true;
    job.row.msg.textContent = 'Uploading';
    var xhr = new XMLHttpRequest();
    var data = new FormData();
    data.append('category', job.category);
    data.append('photo', job.file, job.file.name);
    xhr.open('POST', box.dataset.url);
    xhr.setRequestHeader('X-CSRF-Token', box.dataset.csrf);
    xhr.setRequestHeader('Accept', 'application/json');
    xhr.upload.onprogress = function (ev) {
      if (!ev.lengthComputable) return;
      job.row.bar.value = Math.round(ev.loaded / ev.total * 100);
      if (ev.loaded === ev.total) job.row.msg.textContent = 'Checking the photo';
    };
    xhr.onload = function () {
      var res = null;
      try { res = JSON.parse(xhr.responseText); } catch (e) { res = null; }
      if (!res) {
        finish(job, false, 'Something went wrong. Please try this photo again.');
      } else if (res.ok && res.photo) {
        finish(job, true, 'Accepted for review. Filed under ' + res.photo.category + '.');
        addThumb(res.photo);
      } else {
        finish(job, false, res.message);
        if (res.code === 'closed') { // the project closed while photos were waiting: say it once, not once per photo
          waiting.splice(0).forEach(function (j) {
            j.row.li.remove();
            run.total--;
          });
          report();
        }
      }
      if (res) applyAllowance(res.allow);
      busy = false;
      next();
    };
    xhr.onerror = function () {
      finish(job, false, 'The connection dropped. Please try this photo again.');
      busy = false;
      next();
    };
    xhr.send(data);
  }

  var picker = document.getElementById('picker');
  var many = !picker || picker.dataset.many !== '0'; // the project can switch off adding many photos at once
  var oneNote = document.getElementById('one-note');

  function addFiles(files) {
    if (!files || !files.length) return;
    if (!many) {
      if (oneNote) oneNote.hidden = files.length < 2;
      files = [files[0]];
    }
    var chosen = category ? category.value : 'auto'; // no box on the page: the server files each photo itself
    Array.prototype.forEach.call(files, function (file) {
      waiting.push({ file: file, row: addRow(file), category: chosen });
      run.total++;
    });
    report();
    next();
  }

  input.addEventListener('change', function () {
    addFiles(input.files);
    input.value = '';
  });

  // On a computer, photos can also be dragged onto the upload box.
  var drop = byId('drop');
  if (drop) {
    ['dragenter', 'dragover'].forEach(function (name) {
      drop.addEventListener(name, function (ev) { ev.preventDefault(); drop.classList.add('over'); });
    });
    ['dragleave', 'dragend'].forEach(function (name) {
      drop.addEventListener(name, function () { drop.classList.remove('over'); });
    });
    drop.addEventListener('drop', function (ev) {
      ev.preventDefault();
      drop.classList.remove('over');
      if (can > 0 && ev.dataTransfer) addFiles(ev.dataTransfer.files);
    });
    // A photo dropped beside the box must not make the browser leave the page to show it.
    ['dragover', 'drop'].forEach(function (name) {
      window.addEventListener(name, function (ev) { if (!ev.defaultPrevented) ev.preventDefault(); });
    });
  }

  // Stop: the photo being checked finishes; the ones still waiting are dropped.
  runStop.addEventListener('click', function () {
    waiting.splice(0).forEach(function (job) {
      job.row.li.remove();
      run.total--;
    });
    report();
  });

  // Leaving the page in the middle would lose the photos that are still waiting.
  window.addEventListener('beforeunload', function (ev) {
    if (!busy && !waiting.length) return;
    ev.preventDefault();
    ev.returnValue = '';
  });
})();
