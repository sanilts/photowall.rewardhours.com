// The camera viewfinder on the landing page: every few seconds it "takes a photo" and moves to the
// next kind of subject, the way the iPhone camera's mode strip slides. Purely decorative.
(function () {
  'use strict';
  var vf = document.getElementById('viewfinder');
  if (!vf) return;
  var slides = vf.querySelectorAll('.vf-slide');
  var modes = vf.querySelectorAll('.vf-mode');
  var track = vf.querySelector('.vf-track');
  var index = 0;
  var timer = null;

  function centre() {
    var m = modes[index];
    if (!m || !track) return;
    // The track starts at the middle of the strip; shift it so the current word sits there.
    var offset = m.offsetLeft - parseFloat(getComputedStyle(track).paddingLeft) + m.offsetWidth / 2;
    track.style.transform = 'translateX(' + (-offset) + 'px)';
  }

  function show(next) {
    slides[index].classList.remove('is-on');
    modes[index].classList.remove('is-on');
    index = next;
    slides[index].classList.add('is-on');
    modes[index].classList.add('is-on');
    centre();
  }

  function pulse(cls, ms) {
    vf.classList.remove(cls);
    void vf.offsetWidth; // restart the animation
    vf.classList.add(cls);
    setTimeout(function () { vf.classList.remove(cls); }, ms);
  }

  function tick() {
    pulse('is-snap', 450);
    setTimeout(function () {
      show((index + 1) % slides.length);
      pulse('is-lock', 950);
    }, 180);
  }

  function start() {
    if (timer || slides.length < 2) return;
    timer = setInterval(tick, 3000);
  }
  function stop() {
    clearInterval(timer);
    timer = null;
  }

  centre();
  window.addEventListener('resize', centre);
  if (document.fonts && document.fonts.ready) document.fonts.ready.then(centre);

  // People who ask their device for less motion get a still picture.
  if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

  if ('IntersectionObserver' in window) {
    new IntersectionObserver(function (entries) {
      entries[0].isIntersecting && !document.hidden ? start() : stop();
    }, { threshold: 0.25 }).observe(vf);
  } else {
    start();
  }
  document.addEventListener('visibilitychange', function () {
    document.hidden ? stop() : start();
  });
  setTimeout(function () { pulse('is-lock', 950); }, 900);
})();
