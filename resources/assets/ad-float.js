(function () {
  'use strict';

  if (window.__g7CustomAdFloatBooted) return;
  window.__g7CustomAdFloatBooted = true;

  var API = '/api/modules/custom-ad_float/payload';
  var ROOT_ID = 'g7-custom-ad-float';
  var MOUNT_ID = 'g7_custom_ad_float_mount';

  function isHomePath() {
    var path = (window.location.pathname || '/').replace(/\/+$/, '');
    return path === '' || path === '/';
  }

  function alreadyClosed(key) {
    try {
      if (localStorage.getItem(key) === '1') return true;
    } catch (e) {}
    try {
      var m = document.cookie.match(new RegExp('(?:^|; )' + key.replace(/[$()*+.?[\\\]^{|}]/g, '\\$&') + '=([^;]*)'));
      if (m && m[1] === '1') return true;
    } catch (e2) {}
    return false;
  }

  function markClosed(key) {
    try { localStorage.setItem(key, '1'); } catch (e) {}
    try {
      document.cookie = key + '=1; path=/; max-age=' + (60 * 60 * 24 * 30) + '; SameSite=Lax';
    } catch (e2) {}
  }

  function ensureStyle() {
    if (document.getElementById('g7-custom-ad-float-style')) return;
    var style = document.createElement('style');
    style.id = 'g7-custom-ad-float-style';
    style.textContent = [
      '#g7-custom-ad-float{--g7-ad-width:180px;--g7-ad-height:180px;--g7-ad-offset:24px;--g7-ad-z:9990;--g7-ad-radius:10px;position:fixed;z-index:var(--g7-ad-z);box-sizing:border-box;display:none;font-family:inherit}',
      '#g7-custom-ad-float.is-ready{display:block}',
      '#g7-custom-ad-float.is-left{left:var(--g7-ad-offset);top:50%;transform:translateY(-50%)}',
      '#g7-custom-ad-float.is-right{right:var(--g7-ad-offset);top:50%;transform:translateY(-50%)}',
      '#g7-custom-ad-float.is-top{top:var(--g7-ad-offset);left:50%;transform:translateX(-50%)}',
      '#g7-custom-ad-float.is-bottom{bottom:var(--g7-ad-offset);left:50%;transform:translateX(-50%)}',
      '#g7-custom-ad-float .g7-ad-frame{position:relative;width:var(--g7-ad-width);height:var(--g7-ad-height);overflow:hidden;background:#fff;border:1px solid rgba(0,0,0,.10);border-radius:var(--g7-ad-radius);box-shadow:0 8px 30px rgba(0,0,0,.18)}',
      '#g7-custom-ad-float .g7-ad-track{width:100%;height:100%;display:flex;transition:transform .45s ease}',
      '#g7-custom-ad-float .g7-ad-track.vertical{flex-direction:column}',
      '#g7-custom-ad-float .g7-ad-slide{flex:0 0 100%;width:100%;height:100%;display:block}',
      '#g7-custom-ad-float .g7-ad-slide img{display:block;width:100%;height:100%;object-fit:cover}',
      '#g7-custom-ad-float .g7-ad-nav{position:absolute;inset:0;pointer-events:none}',
      '#g7-custom-ad-float button{position:absolute;top:50%;width:28px;height:28px;margin-top:-14px;border:0;border-radius:50%;background:rgba(0,0,0,.48);color:#fff;cursor:pointer;line-height:28px;text-align:center;padding:0;pointer-events:auto;font-size:16px}',
      '#g7-custom-ad-float .g7-ad-prev{left:7px}',
      '#g7-custom-ad-float .g7-ad-next{right:7px}',
      '#g7-custom-ad-float .g7-ad-dots{position:absolute;left:50%;bottom:7px;transform:translateX(-50%);display:flex;gap:5px}',
      '#g7-custom-ad-float .g7-ad-dot{position:static;width:7px;height:7px;margin:0;padding:0;border-radius:50%;background:rgba(255,255,255,.55)}',
      '#g7-custom-ad-float .g7-ad-dot.is-active{background:#fff}',
      '#g7-custom-ad-float .g7-ad-close{top:6px;right:6px;left:auto;margin:0;width:24px;height:24px;line-height:24px;z-index:3}',
      '@media (max-width:767px){#g7-custom-ad-float.g7-mobile-hide{display:none!important}#g7-custom-ad-float.g7-mobile-show .g7-ad-frame{max-width:calc(100vw - 24px);max-height:calc(100vh - 48px)}}'
    ].join('');
    document.head.appendChild(style);
  }

  function render(payload) {
    if (document.getElementById(ROOT_ID)) return;

    var config = (payload && payload.settings) || {};
    var items = Array.isArray(payload && payload.items) ? payload.items : [];
    if (!config.enabled || !items.length) return;
    if (config.home_only && !isHomePath()) return;

    var closeKey = config.close_cookie_key || 'g7_custom_ad_float_closed';
    if (alreadyClosed(closeKey)) return;

    ensureStyle();

    var root = document.createElement('div');
    root.id = ROOT_ID;
    root.className = 'g7-custom-ad-float';
    root.style.setProperty('--g7-ad-width', Math.max(80, Number(config.width_px || 180)) + 'px');
    root.style.setProperty('--g7-ad-height', Math.max(80, Number(config.height_px || 180)) + 'px');
    root.style.setProperty('--g7-ad-offset', Math.max(0, Number(config.offset_px || 24)) + 'px');
    root.style.setProperty('--g7-ad-z', String(Math.max(100, Number(config.z_index || 9990))));
    root.style.setProperty('--g7-ad-radius', Math.max(0, Number(config.radius_px || 10)) + 'px');
    root.classList.add('is-' + (config.position || 'right'));
    root.classList.add('g7-mobile-' + (config.mobile_mode || 'hide'));

    var frame = document.createElement('div');
    frame.className = 'g7-ad-frame';

    var track = document.createElement('div');
    track.className = 'g7-ad-track ' + ((config.direction || 'horizontal') === 'vertical' ? 'vertical' : '');

    items.forEach(function (item) {
      var slide = document.createElement('div');
      slide.className = 'g7-ad-slide';
      var link = document.createElement(item.target_url ? 'a' : 'div');
      link.className = 'g7-ad-link';
      if (item.target_url) {
        link.href = item.target_url;
        if (config.open_new_tab) {
          link.target = '_blank';
          link.rel = 'noopener noreferrer';
        }
      }
      var img = document.createElement('img');
      img.src = item.image_url;
      img.alt = item.alt_text || item.title || '';
      img.loading = 'lazy';
      link.appendChild(img);
      slide.appendChild(link);
      track.appendChild(slide);
    });
    frame.appendChild(track);

    var prevBtn, nextBtn, dotsWrap;
    if (items.length > 1 && config.show_arrows) {
      var nav = document.createElement('div');
      nav.className = 'g7-ad-nav';
      prevBtn = document.createElement('button');
      prevBtn.type = 'button';
      prevBtn.className = 'g7-ad-prev';
      prevBtn.setAttribute('aria-label', '이전 광고');
      prevBtn.textContent = '‹';
      nextBtn = document.createElement('button');
      nextBtn.type = 'button';
      nextBtn.className = 'g7-ad-next';
      nextBtn.setAttribute('aria-label', '다음 광고');
      nextBtn.textContent = '›';
      nav.appendChild(prevBtn);
      nav.appendChild(nextBtn);
      frame.appendChild(nav);
    }

    if (items.length > 1 && config.show_dots) {
      dotsWrap = document.createElement('div');
      dotsWrap.className = 'g7-ad-dots';
      items.forEach(function (_, index) {
        var dot = document.createElement('button');
        dot.type = 'button';
        dot.className = 'g7-ad-dot' + (index === 0 ? ' is-active' : '');
        dot.setAttribute('aria-label', (index + 1) + '번 광고');
        dot.dataset.index = String(index);
        dotsWrap.appendChild(dot);
      });
      frame.appendChild(dotsWrap);
    }

    if (config.show_close !== false) {
      var close = document.createElement('button');
      close.type = 'button';
      close.className = 'g7-ad-close';
      close.setAttribute('aria-label', '광고 닫기');
      close.textContent = '×';
      close.addEventListener('click', function () {
        root.remove();
        markClosed(closeKey);
      });
      frame.appendChild(close);
    }

    root.appendChild(frame);
    root.classList.add('is-ready');

    var mount = document.getElementById(MOUNT_ID);
    if (mount && mount.parentNode) {
      mount.parentNode.insertBefore(root, mount.nextSibling);
    } else {
      document.body.appendChild(root);
    }

    var index = 0;
    var timer = null;
    var vertical = config.direction === 'vertical';

    function paint() {
      var offset = -(index * 100);
      track.style.transform = vertical
        ? 'translate3d(0,' + offset + '%,0)'
        : 'translate3d(' + offset + '%,0,0)';
      if (dotsWrap) {
        Array.prototype.forEach.call(dotsWrap.children, function (dot, i) {
          dot.classList.toggle('is-active', i === index);
        });
      }
    }

    function go(delta) {
      index = (index + delta + items.length) % items.length;
      paint();
    }

    function stop() {
      if (timer) {
        window.clearTimeout(timer);
        timer = null;
      }
    }

    function start() {
      if (!config.autoplay || items.length < 2) return;
      stop();
      var seconds = Number(items[index] && items[index].display_seconds) || (Number(config.interval_ms || 4000) / 1000);
      timer = window.setTimeout(function () {
        go(1);
        start();
      }, Math.max(1000, seconds * 1000));
    }

    if (prevBtn) prevBtn.addEventListener('click', function () { go(-1); start(); });
    if (nextBtn) nextBtn.addEventListener('click', function () { go(1); start(); });
    if (dotsWrap) {
      dotsWrap.addEventListener('click', function (event) {
        var dot = event.target.closest('.g7-ad-dot');
        if (!dot) return;
        index = Number(dot.dataset.index || 0);
        paint();
        start();
      });
    }
    if (config.pause_on_hover) {
      frame.addEventListener('mouseenter', stop);
      frame.addEventListener('mouseleave', start);
    }

    paint();
    start();
  }

  function boot() {
    if (document.getElementById(ROOT_ID)) return;
    fetch(API, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
      .then(function (r) { return r.json(); })
      .then(function (json) {
        var data = (json && json.data) ? json.data : json;
        render(data || {});
      })
      .catch(function () {});
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
