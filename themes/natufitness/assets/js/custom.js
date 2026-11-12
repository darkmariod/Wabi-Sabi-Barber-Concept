/* NatuFitness child theme behaviour: home hero carousel and product carousel dots. */
(function () {
  'use strict';

  var HERO_INTERVAL_MS = 6000;

  function initHero(hero) {
    var slides = hero.querySelectorAll('.nf-hero__slide');
    var dotsBox = hero.querySelector('.js-nf-hero-dots');
    if (slides.length < 2 || !dotsBox) {
      return;
    }
    // Speed and pause-on-hover come from the ps_imageslider configuration.
    var interval = parseInt(hero.getAttribute('data-interval'), 10) || HERO_INTERVAL_MS;
    var pauseOnHover = hero.getAttribute('data-pause') === 'hover';
    var current = 0;
    var timer = null;
    var dots = [];

    function show(index) {
      current = (index + slides.length) % slides.length;
      slides.forEach(function (slide, i) {
        slide.classList.toggle('is-active', i === current);
      });
      dots.forEach(function (dot, i) {
        dot.classList.toggle('is-active', i === current);
      });
    }

    function restart() {
      window.clearInterval(timer);
      timer = window.setInterval(function () { show(current + 1); }, interval);
    }

    if (pauseOnHover) {
      hero.addEventListener('mouseenter', function () { window.clearInterval(timer); });
      hero.addEventListener('mouseleave', restart);
    }

    slides.forEach(function (slide, i) {
      var dot = document.createElement('button');
      dot.type = 'button';
      dot.setAttribute('aria-label', 'Ir al destacado ' + (i + 1));
      dot.addEventListener('click', function () { show(i); restart(); });
      dotsBox.appendChild(dot);
      dots.push(dot);
    });

    hero.querySelector('.js-nf-hero-prev').addEventListener('click', function () { show(current - 1); restart(); });
    hero.querySelector('.js-nf-hero-next').addEventListener('click', function () { show(current + 1); restart(); });

    show(0);
    restart();
  }

  function initProductCarousel(carousel) {
    var track = carousel.querySelector('.js-nf-carousel-track');
    var dotsBox = carousel.querySelector('.js-nf-carousel-dots');
    if (!track || !dotsBox) {
      return;
    }

    function pageCount() {
      return Math.max(1, Math.ceil(track.scrollWidth / track.clientWidth - 0.01));
    }

    function activeIndex() {
      var max = track.scrollWidth - track.clientWidth;
      if (max <= 0) {
        return 0;
      }
      return Math.round((track.scrollLeft / max) * (pageCount() - 1));
    }

    function render() {
      var pages = pageCount();
      dotsBox.innerHTML = '';
      if (pages < 2) {
        return;
      }
      for (var i = 0; i < pages; i += 1) {
        var dot = document.createElement('button');
        dot.type = 'button';
        dot.setAttribute('aria-label', 'Página ' + (i + 1));
        dot.addEventListener('click', (function (page) {
          return function () { track.scrollLeft = page * track.clientWidth; };
        })(i));
        dotsBox.appendChild(dot);
      }
      update();
    }

    function update() {
      var index = activeIndex();
      Array.prototype.forEach.call(dotsBox.children, function (dot, i) {
        dot.classList.toggle('is-active', i === index);
      });
    }

    track.addEventListener('scroll', update, { passive: true });
    window.addEventListener('resize', render);
    render();
  }

  // Sections fade in once they scroll into view (syf.ec style).
  function initReveal() {
    if (!('IntersectionObserver' in window)) { return; }
    var sections = document.querySelectorAll('.nf-section');
    var observer = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          entry.target.classList.add('is-visible');
          observer.unobserve(entry.target);
        }
      });
    }, { threshold: 0.12 });
    Array.prototype.forEach.call(sections, function (section) {
      section.classList.add('nf-reveal');
      observer.observe(section);
    });
  }

  function safely(fn) {
    try { fn(); } catch (error) { if (window.console) { console.warn('NatuFitness:', error); } }
  }

  function init() {
    safely(initReveal);
    document.querySelectorAll('.js-nf-hero').forEach(function (hero) { safely(function () { initHero(hero); }); });
    document.querySelectorAll('.js-nf-carousel').forEach(function (carousel) { safely(function () { initProductCarousel(carousel); }); });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
