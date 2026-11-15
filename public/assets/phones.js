// The phone slider in the hero: one accepted iPhone at a time, sliding to the next every few seconds.
// The arrows move it by hand. It loops without a jump by keeping a copy of the first slide at the end.
(function () {
  'use strict';
  var box = document.getElementById('phones');
  if (!box) return;
  var track = box.querySelector('.slider-track');
  var name = box.querySelector('.slider-name');
  var real = track.children.length;
  if (real < 2) return;

  var copy = track.children[0].cloneNode(true);
  copy.setAttribute('aria-hidden', 'true');
  track.appendChild(copy);

  var index = 0;
  var timer = null;
  var still = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  function place(animate) {
    track.classList.toggle('no-anim', !animate);
    track.style.transform = 'translateX(' + (-index * 100) + '%)';
    name.textContent = track.children[index % real].dataset.name;
    if (!animate) void track.offsetWidth; // apply the jump before any later slide
  }

  function go(step) {
    if (index === real && step > 0) { index = 0; place(false); }   // on the copy: hop to the real first slide
    if (index === 0 && step < 0) { index = real; place(false); }    // going back from the first: start from the copy
    index += step;
    place(!still);
  }

  track.addEventListener('transitionend', function () {
    if (index === real) { index = 0; place(false); }
  });

  function start() {
    if (timer || still) return;
    timer = setInterval(function () { go(1); }, 2600);
  }
  function stop() {
    clearInterval(timer);
    timer = null;
  }

  box.querySelectorAll('.slider-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
      stop();
      go(btn.dataset.next ? 1 : -1);
      start();
    });
  });
  box.addEventListener('mouseenter', stop);
  box.addEventListener('mouseleave', start);
  box.addEventListener('focusin', stop);
  box.addEventListener('focusout', start);
  document.addEventListener('visibilitychange', function () { document.hidden ? stop() : start(); });

  if ('IntersectionObserver' in window) {
    new IntersectionObserver(function (entries) {
      entries[0].isIntersecting && !document.hidden ? start() : stop();
    }, { threshold: 0.3 }).observe(box);
  } else {
    start();
  }
})();
