// Uploads the chosen photos one at a time and shows the result of the checks for each.
(function () {
  'use strict';
  var box = document.getElementById('uploader');
  var input = document.getElementById('files');
  if (!box || !input) return;
  var queue = document.getElementById('queue');
  var picker = document.getElementById('picker');
  var can = parseInt(box.dataset.can, 10) || 0;
  var maxBytes = (parseInt(box.dataset.maxMb, 10) || 60) * 1048576;
  var busy = false;
  var waiting = [];
  var category = document.getElementById('category');
  var catHelp = document.getElementById('cat-help');
  var catHelpText = catHelp ? catHelp.textContent : '';

  // The file chooser only opens once a category is chosen.
  function needCategory(ev) {
    if (!category || category.value) return;
    ev.preventDefault();
    category.classList.add('need');
    if (catHelp) catHelp.textContent = 'Choose a category first, then choose the photos.';
    category.focus();
  }
  var drop = document.querySelector('label[for=files]');
  if (drop) drop.addEventListener('click', needCategory);
  input.addEventListener('click', needCategory);
  if (category) category.addEventListener('change', function () {
    category.classList.remove('need');
    if (catHelp) catHelp.textContent = catHelpText;
  });

  // Each uploaded photo has its own category box: save a change straight away.
  document.addEventListener('change', function (ev) {
    var sel = ev.target;
    var form = sel.form;
    if (!form || !form.classList.contains('shot-cat') || !sel.value || !window.fetch) return;
    var data = new FormData(form);
    fetch(form.action, { method: 'POST', body: data, credentials: 'same-origin', headers: { 'X-Requested-With': 'fetch' } })
      .then(function (r) { return r.json(); })
      .then(function (j) { sel.classList.toggle('saved', !!j.ok); sel.classList.toggle('need', !j.ok); })
      .catch(function () { form.submit(); });
  });
  Array.prototype.forEach.call(document.querySelectorAll('.shot-cat .btn'), function (b) { b.hidden = true; });

  function el(tag, cls, text) {
    var n = document.createElement(tag);
    if (cls) n.className = cls;
    if (text) n.textContent = text;
    return n;
  }

  function addRow(file) {
    var li = el('li', 'q-item');
    var name = el('span', 'q-name', file.name);
    var bar = el('progress', 'q-bar');
    bar.max = 100; bar.value = 0;
    var msg = el('span', 'q-msg', 'Waiting');
    li.appendChild(name); li.appendChild(bar); li.appendChild(msg);
    queue.appendChild(li);
    return { li: li, bar: bar, msg: msg };
  }

  function finish(row, ok, text) {
    row.bar.remove();
    row.li.classList.add(ok ? 'q-ok' : 'q-bad');
    row.msg.textContent = text;
  }

  function applyAllowance(a) {
    if (!a) return;
    can = a.can;
    var used = document.getElementById('used');
    var usedBar = document.getElementById('used-bar');
    var today = document.getElementById('today-left');
    if (used) used.textContent = a.used;
    if (usedBar) usedBar.value = a.used;
    if (today && a.today_left !== null) today.textContent = a.today_left;
    if (can <= 0 && picker) picker.hidden = true;
  }

  function addThumb(photo) {
    var grid = document.getElementById('mine');
    var none = document.getElementById('none-yet');
    if (none) none.remove();
    var li = el('li', 'shot');
    var img = el('img');
    img.src = photo.thumb; img.alt = 'Your uploaded photo'; img.width = 240; img.height = 240;
    li.appendChild(img);
    li.appendChild(el('span', 'chip chip-pending', 'Received, in review'));
    if (photo.image_id) {
      var id = el('span', 'shot-id');
      id.appendChild(el('strong', '', photo.image_id));
      id.appendChild(document.createElement('br'));
      id.appendChild(document.createTextNode(photo.name || ''));
      li.appendChild(id);
    }
    if (photo.category) li.appendChild(el('span', 'shot-kind', photo.category));
    grid.insertBefore(li, grid.firstChild);
  }

  function next() {
    if (busy) return;
    var job = waiting.shift();
    if (!job) return;
    if (can <= 0) {
      finish(job.row, false, 'Not uploaded: you have reached your limit for now.');
      return next();
    }
    if (job.file.size > maxBytes) {
      finish(job.row, false, 'This file is too large (limit ' + box.dataset.maxMb + ' MB).');
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
        finish(job.row, false, 'Something went wrong. Please try this photo again.');
      } else {
        finish(job.row, !!res.ok, res.message);
        applyAllowance(res.allow);
        if (res.ok && res.photo) addThumb(res.photo);
      }
      busy = false;
      next();
    };
    xhr.onerror = function () {
      finish(job.row, false, 'The connection dropped. Please try this photo again.');
      busy = false;
      next();
    };
    xhr.send(data);
  }

  input.addEventListener('change', function () {
    Array.prototype.forEach.call(input.files, function (file) {
      waiting.push({ file: file, row: addRow(file), category: category ? category.value : '' });
    });
    input.value = '';
    next();
  });
})();
