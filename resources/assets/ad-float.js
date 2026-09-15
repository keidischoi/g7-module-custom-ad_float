(function () {
  'use strict';

  if (window.__g7CustomAdFloatBooted) return;
  window.__g7CustomAdFloatBooted = true;

  var API = '/api/modules/custom-ad_float/payload';
  var TRACK_API = '/api/modules/custom-ad_float/track';
  var ROOT_ID = 'g7-custom-ad-float';
  var MOUNT_ID = 'g7_custom_ad_float_mount';

  function pagePath() {
    var path = (window.location.pathname || '/').replace(/\/+$/, '');
    return path === '' ? '/' : path;
  }

  function isHomePath() {
    return pagePath() === '/';
  }

  function sendTrack(type, itemId) {
    var id = Number(itemId || 0);
    if (!id || (type !== 'impression' && type !== 'click')) return;
    var body = JSON.stringify({
      type: type,
      item_id: id,
      page_path: pagePath()
    });
    try {
      if (navigator.sendBeacon) {
        navigator.sendBeacon(TRACK_API, new Blob([body], { type: 'application/json' }));
        return;
      }
    } catch (e) {}
    try {
      fetch(TRACK_API, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body: body,
        keepalive: true,
        credentials: 'same-origin'
      }).catch(function () {});
    } catch (e2) {}
  }

  function impressionKey(itemId) {
    return 'caf_imp_' + itemId + '_' + pagePath();
  }

  function trackImpression(itemId) {
    var id = Number(itemId || 0);
    if (!id) return;
    try {
      if (sessionStorage.getItem(impressionKey(id))) return;
      sessionStorage.setItem(impressionKey(id), '1');
    } catch (e) {}
    sendTrack('impression', id);
  }

  function trackClick(itemId) {
    sendTrack('click', itemId);
  }

  function isAdVisible(root) {
    if (!root || !root.isConnected) return false;
    try {
      var style = window.getComputedStyle(root);
      if (style.display === 'none' || style.visibility === 'hidden') return false;
      var rect = root.getBoundingClientRect();
      return rect.width > 0 && rect.height > 0;
    } catch (e) {
      return true;
    }
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

  function num(value, fallback) {
    var n = Number(value);
    return isFinite(n) ? n : fallback;
  }

  function verticalAlign(value) {
    var v = String(value || '').toLowerCase();
    return v === 'top' || v === 'bottom' ? v : 'middle';
  }

  var CONTENT_SELECTORS = [
    '#main_content',
    '#main_content_area [id="main_content"]',
    '[id="main_content"]',
    '[id*="main_content"]',
    '[id*="main-content"]',
    '#container',
    '#wrapper',
    '#content',
    'main',
    '#user_layout_root .container',
    '.container',
    '[class*="container"]'
  ];

  function isAdNode(el) {
    if (!el) return true;
    if (el.id === ROOT_ID || el.id === MOUNT_ID) return true;
    try {
      if (el.closest && el.closest('#' + ROOT_ID)) return true;
    } catch (e) {}
    return false;
  }

  function isUsableBox(el) {
    if (!el || isAdNode(el)) return false;
    var tag = (el.tagName || '').toLowerCase();
    if (tag === 'header' || tag === 'footer' || tag === 'nav' || tag === 'script' || tag === 'style' || tag === 'svg') {
      return false;
    }
    var id = el.id || '';
    if (/^(header|footer|nav|mobile_|desktop_header|user_global_)/i.test(id) && !/content/i.test(id)) {
      return false;
    }
    var rect;
    try {
      rect = el.getBoundingClientRect();
    } catch (e2) {
      return false;
    }
    return rect.width >= 200 && rect.height >= 40;
  }

  function isFullBleed(el) {
    var vw = window.innerWidth || 0;
    if (!vw) return false;
    try {
      return el.getBoundingClientRect().width >= vw - 16;
    } catch (e) {
      return false;
    }
  }

  function pickCenteredChild(root) {
    if (!root || !root.querySelectorAll) return null;
    var nodes;
    try {
      nodes = root.querySelectorAll('div, main, section, article');
    } catch (e) {
      return null;
    }
    var vw = window.innerWidth || 0;
    var best = null;
    var bestScore = 0;
    var limit = Math.min(nodes.length, 400);
    for (var i = 0; i < limit; i++) {
      var el = nodes[i];
      if (!isUsableBox(el) || isFullBleed(el)) continue;
      var rect = el.getBoundingClientRect();
      var style;
      try {
        style = window.getComputedStyle(el);
      } catch (e2) {
        continue;
      }
      var maxW = style.maxWidth;
      var hasMax = maxW && maxW !== 'none' && parseFloat(maxW) > 0;
      var mxAuto = style.marginLeft === 'auto' && style.marginRight === 'auto';
      var centered = vw > 0 && Math.abs((vw - rect.width) / 2 - rect.left) < 32;
      var id = el.id || '';
      var cls = (el.className && el.className.toString) ? el.className.toString() : '';
      var score = rect.width + Math.min(rect.height, 800) * 0.15;
      if (hasMax) score += 400;
      if (mxAuto || centered) score += 300;
      if (/content|container|wrapper/i.test(id) || /max-w-|container|mx-auto/i.test(cls)) score += 500;
      if (id === 'main_content') score += 1000;
      if (score > bestScore) {
        bestScore = score;
        best = el;
      }
    }
    return best;
  }

  function considerBox(el) {
    if (!isUsableBox(el)) return null;
    if (isFullBleed(el)) {
      return pickCenteredChild(el) || el;
    }
    return el;
  }

  function findContentBox() {
    var i, el, found;
    for (i = 0; i < CONTENT_SELECTORS.length; i++) {
      try {
        el = document.querySelector(CONTENT_SELECTORS[i]);
      } catch (e) {
        el = null;
      }
      found = considerBox(el);
      if (found) return found;
    }
    var root = document.getElementById('user_layout_root') || document.body;
    return pickCenteredChild(root);
  }

  function clamp(n, min, max) {
    return Math.min(max, Math.max(min, n));
  }

  function applyPlacement(root, config) {
    if (!root) return;
    var pos = config.position || 'right';
    var gap = Math.max(0, num(config.offset_px, 24));
    var valign = verticalAlign(config.vertical_align);
    var voff = num(config.vertical_offset_px, 24);
    var adW = root.offsetWidth || Math.max(80, num(config.width_px, 180));
    var vw = window.innerWidth || document.documentElement.clientWidth || 0;

    root.style.left = '';
    root.style.right = '';
    root.style.top = '';
    root.style.bottom = '';
    root.style.transform = '';

    if (pos === 'top' || pos === 'bottom') {
      root.style.left = '50%';
      root.style.transform = 'translateX(-50%)';
      if (pos === 'top') root.style.top = gap + 'px';
      else root.style.bottom = gap + 'px';
      return;
    }

    if (valign === 'top') {
      root.style.top = Math.max(0, voff) + 'px';
    } else if (valign === 'bottom') {
      root.style.bottom = Math.max(0, voff) + 'px';
    } else if (voff) {
      root.style.top = '50%';
      root.style.transform = 'translateY(calc(-50% + ' + voff + 'px))';
    } else {
      root.style.top = '50%';
      root.style.transform = 'translateY(-50%)';
    }

    var box = findContentBox();
    var leftPx;
    if (box) {
      var rect = box.getBoundingClientRect();
      if (pos === 'left') {
        leftPx = rect.left - gap - adW;
      } else {
        leftPx = rect.right + gap;
      }
    } else if (pos === 'left') {
      leftPx = gap;
    } else {
      leftPx = vw - gap - adW;
    }

    leftPx = clamp(leftPx, 0, Math.max(0, vw - adW));
    root.style.left = Math.round(leftPx) + 'px';
    root.style.right = 'auto';
  }

  function watchPlacement(root, config) {
    var raf = 0;
    function schedule() {
      if (raf) return;
      raf = window.requestAnimationFrame(function () {
        raf = 0;
        applyPlacement(root, config);
      });
    }
    applyPlacement(root, config);
    window.addEventListener('resize', schedule);
    window.addEventListener('scroll', schedule, { passive: true });
    window.addEventListener('orientationchange', schedule);
    if (window.ResizeObserver) {
      try {
        var ro = new ResizeObserver(schedule);
        ro.observe(document.documentElement);
        var box = findContentBox();
        if (box) ro.observe(box);
      } catch (e) {}
    }
    [100, 300, 1000, 3000].forEach(function (ms) {
      window.setTimeout(schedule, ms);
    });
  }

  function ensureStyle() {
    if (document.getElementById('g7-custom-ad-float-style')) return;
    var style = document.createElement('style');
    style.id = 'g7-custom-ad-float-style';
    style.textContent = [
      '#g7-custom-ad-float{--g7-ad-width:180px;--g7-ad-height:180px;--g7-ad-offset:24px;--g7-ad-v-offset:24px;--g7-ad-z:9990;--g7-ad-radius:10px;position:fixed;z-index:var(--g7-ad-z);box-sizing:border-box;display:none;font-family:inherit}',
      '#g7-custom-ad-float.is-ready{display:block}',
      '#g7-custom-ad-float.is-left,#g7-custom-ad-float.is-right{left:var(--g7-ad-offset)}',
      '#g7-custom-ad-float.is-v-middle{top:50%;transform:translateY(calc(-50% + var(--g7-ad-v-offset, 0px)))}',
      '#g7-custom-ad-float.is-v-top{top:var(--g7-ad-v-offset, 24px)}',
      '#g7-custom-ad-float.is-v-bottom{bottom:var(--g7-ad-v-offset, 24px);top:auto}',
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
    var pos = config.position || 'right';
    var valign = verticalAlign(config.vertical_align);
    root.style.setProperty('--g7-ad-width', Math.max(80, num(config.width_px, 180)) + 'px');
    root.style.setProperty('--g7-ad-height', Math.max(80, num(config.height_px, 180)) + 'px');
    root.style.setProperty('--g7-ad-offset', Math.max(0, num(config.offset_px, 24)) + 'px');
    root.style.setProperty('--g7-ad-v-offset', num(config.vertical_offset_px, 24) + 'px');
    root.style.setProperty('--g7-ad-z', String(Math.max(100, num(config.z_index, 9990))));
    root.style.setProperty('--g7-ad-radius', Math.max(0, num(config.radius_px, 10)) + 'px');
    root.classList.add('is-' + pos);
    if (pos === 'left' || pos === 'right') {
      root.classList.add('is-v-' + valign);
    }
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
      if (item.id) link.setAttribute('data-item-id', String(item.id));
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
      if (isAdVisible(root) && items[index] && items[index].id) {
        trackImpression(items[index].id);
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

    root.addEventListener('click', function (event) {
      var link = event.target.closest('.g7-ad-link');
      if (!link) return;
      var id = Number(link.getAttribute('data-item-id') || (items[index] && items[index].id) || 0);
      trackClick(id);
    }, true);

    paint();
    start();
    watchPlacement(root, config);
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
