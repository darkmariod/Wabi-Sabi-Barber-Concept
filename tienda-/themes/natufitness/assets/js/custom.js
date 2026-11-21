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

  // Cookie notice: remember the visitor's choice for 180 days.
  function initCookieNotice() {
    var notice = document.getElementById('nf-cookies');
    if (!notice) { return; }
    var match = document.cookie.match(/(?:^|; )nf_cookie_consent=([^;]+)/);
    window.nfCookieConsent = match ? decodeURIComponent(match[1]) : null;
    if (window.nfCookieConsent) { return; }
    notice.hidden = false;
    notice.addEventListener('click', function (event) {
      var choice = event.target.getAttribute('data-nf-consent');
      if (!choice) { return; }
      var secure = location.protocol === 'https:' ? '; Secure' : '';
      document.cookie = 'nf_cookie_consent=' + choice + '; Max-Age=' + (180 * 24 * 60 * 60) + '; Path=/; SameSite=Lax' + secure;
      window.nfCookieConsent = choice;
      notice.hidden = true;
    });
  }

  /**
   * syf.ec style mega menu (modules/ps_mainmenu/ps_mainmenu.tpl). Delegated listeners,
   * because classic moves the menu into the mobile drawer on small screens.
   * Desktop: hover or click opens a panel; hovering a category shows its subcategories.
   * Phone: the trigger and the category arrows work as an accordion.
   */
  function initMegaMenu() {
    var desktop = window.matchMedia('(min-width: 768px)');

    function setOpen(item, open) {
      item.classList.toggle('is-open', open);
      var trigger = item.querySelector('.nf-mega__trigger');
      if (trigger && trigger.tagName === 'BUTTON') { trigger.setAttribute('aria-expanded', open ? 'true' : 'false'); }
    }
    function closeAll(except) {
      document.querySelectorAll('.js-nf-mega.is-open').forEach(function (item) { if (item !== except) { setOpen(item, false); } });
    }

    document.addEventListener('mouseover', function (event) {
      if (!desktop.matches) { return; }
      var item = event.target.closest('.js-nf-mega');
      if (item && !item.classList.contains('is-open')) { closeAll(item); setOpen(item, true); }
      var cat = event.target.closest('.js-nf-mega-cat');
      if (cat && !cat.classList.contains('is-active')) {
        cat.parentNode.querySelectorAll('.js-nf-mega-cat').forEach(function (c) { c.classList.toggle('is-active', c === cat); });
      }
    });
    document.addEventListener('mouseout', function (event) {
      if (!desktop.matches) { return; }
      var item = event.target.closest('.js-nf-mega');
      if (item && !item.contains(event.relatedTarget)) { setOpen(item, false); }
    });
    document.addEventListener('click', function (event) {
      var trigger = event.target.closest('.js-nf-mega > .nf-mega__trigger');
      if (trigger) {
        var item = trigger.parentNode;
        var open = !item.classList.contains('is-open');
        closeAll(item);
        setOpen(item, open);
        return;
      }
      var toggle = event.target.closest('.js-nf-mega-toggle');
      if (toggle) {
        var cat = toggle.closest('.js-nf-mega-cat');
        var expanded = !cat.classList.contains('is-expanded');
        cat.classList.toggle('is-expanded', expanded);
        toggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
        return;
      }
      if (desktop.matches && !event.target.closest('.js-nf-mega')) { closeAll(null); }
    });
    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape') { closeAll(null); }
    });
  }

  /**
   * Footer bank logos open the payment dialog (footer.tpl #nf-pay): the account lines that
   * mention the clicked bank are highlighted (all lines show when none matches); the DeUna
   * button puts the QR first.
   */
  function initPayDialog() {
    var dialog = document.getElementById('nf-pay');
    if (!dialog || typeof dialog.showModal !== 'function') { return; }
    var title = dialog.querySelector('.nf-pay__title');
    var lines = Array.prototype.slice.call(dialog.querySelectorAll('[data-nf-pay-line]'));
    var transfer = dialog.querySelector('[data-nf-pay-section="transfer"]');
    var qr = dialog.querySelector('[data-nf-pay-section="qr"]');

    document.addEventListener('click', function (event) {
      var button = event.target.closest('.js-nf-pay');
      if (!button) { return; }
      var bank = button.getAttribute('data-nf-bank');
      var key = (button.getAttribute('data-nf-bank-key') || '').toLowerCase();
      var isQr = button.hasAttribute('data-nf-pay-qr');
      title.textContent = isQr ? 'Paga con DeUna' : 'Paga por transferencia · ' + bank;
      var matches = key ? lines.filter(function (li) { return li.textContent.toLowerCase().indexOf(key) !== -1; }) : [];
      lines.forEach(function (li) { li.classList.toggle('is-match', matches.indexOf(li) !== -1); });
      if (isQr) { dialog.insertBefore(qr, transfer); } else { dialog.insertBefore(transfer, qr); }
      dialog.showModal();
    });
    dialog.addEventListener('click', function (event) {
      if (event.target === dialog) { dialog.close(); } // click on the backdrop
    });
  }

  function safely(fn) {
    try { fn(); } catch (error) { if (window.console) { console.warn('NatuFitness:', error); } }
  }

  function init() {
    safely(initCookieNotice);
    safely(initReveal);
    safely(initMegaMenu);
    safely(initPayDialog);
    document.querySelectorAll('.js-nf-hero').forEach(function (hero) { safely(function () { initHero(hero); }); });
    document.querySelectorAll('.js-nf-carousel').forEach(function (carousel) { safely(function () { initProductCarousel(carousel); }); });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
