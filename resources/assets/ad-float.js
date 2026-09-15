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

  function sortCarouselItems(items) {
    var list = Array.isArray(items) ? items.slice() : [];
    function key(it) {
      var g = it && it.carousel_group ? String(it.carousel_group) : '';
      return g ? 'g:' + g : 'id:' + (it && it.id ? it.id : '');
    }
    var groupMin = {};
    list.forEach(function (it, idx) {
      var k = key(it);
      var so = Number(it && it.sort_order);
      if (!isFinite(so)) so = idx;
      var id = Number(it && it.id) || 0;
      if (!groupMin[k] || so < groupMin[k].so || (so === groupMin[k].so && id < groupMin[k].id)) {
        groupMin[k] = { so: so, id: id };
      }
    });
    list.sort(function (a, b) {
      var ka = key(a);
      var kb = key(b);
      var ga = groupMin[ka] || { so: 0, id: 0 };
      var gb = groupMin[kb] || { so: 0, id: 0 };
      if (ga.so !== gb.so) return ga.so - gb.so;
      if (ga.id !== gb.id) return ga.id - gb.id;
      if (ka !== kb) return ka < kb ? -1 : 1;
      var sa = Number(a && a.sort_order) || 0;
      var sb = Number(b && b.sort_order) || 0;
      if (sa !== sb) return sa - sb;
      return (Number(a && a.id) || 0) - (Number(b && b.id) || 0);
    });
    return list;
  }

  function verticalAlign(value) {
    var v = String(value || '').toLowerCase();
    return v === 'top' || v === 'bottom' ? v : 'middle';
  }

  function isAdNode(el) {
    if (!el) return true;
    if (el.id === ROOT_ID || el.id === MOUNT_ID) return true;
    try {
      if (el.closest && el.closest('#' + ROOT_ID)) return true;
    } catch (e) {}
    return false;
  }

  function classNameOf(el) {
    if (!el || !el.className) return '';
    return el.className.toString ? el.className.toString() : String(el.className);
  }

  function viewportWidth() {
    return window.innerWidth || document.documentElement.clientWidth || 0;
  }

  /**
   * A content column must be narrower than the viewport so side ads can sit
   * in the leftover gutters. Full-bleed wrappers (#main_content_area, home
   * slot Containers with only px-*) must never win — using them + clamp is
   * what pins ads to the viewport walls.
   */
  function isContentColumn(el) {
    if (!el || isAdNode(el)) return false;
    var tag = (el.tagName || '').toLowerCase();
    if (tag === 'header' || tag === 'footer' || tag === 'nav' || tag === 'script' || tag === 'style' || tag === 'svg' || tag === 'html' || tag === 'body') {
      return false;
    }
    var id = el.id || '';
    var cls = classNameOf(el);
    if (/^(header|footer|nav|mobile_|desktop_header|user_global_)/i.test(id) && !/content/i.test(id)) {
      return false;
    }
    if (/\b(fixed|sticky)\b/.test(cls) && !/content|container|max-w-/i.test(cls + id)) {
      return false;
    }
    var rect;
    try {
      rect = el.getBoundingClientRect();
    } catch (e) {
      return false;
    }
    var vw = viewportWidth();
    if (rect.width < 280 || rect.height < 40) return false;
    if (vw > 0 && rect.width >= vw - 32) return false;
    return true;
  }

  function columnScore(el) {
    if (!isContentColumn(el)) return 0;
    var vw = viewportWidth();
    var rect = el.getBoundingClientRect();
    var style;
    try {
      style = window.getComputedStyle(el);
    } catch (e) {
      return 0;
    }
    var cls = classNameOf(el);
    var id = el.id || '';
    var score = rect.width;
    var maxW = style.maxWidth;
    if (maxW && maxW !== 'none' && parseFloat(maxW) > 0) score += 900;
    if (/max-w-7xl/.test(cls)) score += 2500;
    if (/max-w-6xl|max-w-5xl|max-w-4xl|max-w-screen/.test(cls)) score += 1800;
    if (/max-w-/.test(cls)) score += 500;
    if (/mx-auto/.test(cls) || (style.marginLeft === 'auto' && style.marginRight === 'auto')) score += 500;
    if (vw > 0 && Math.abs((vw - rect.width) / 2 - rect.left) < 48) score += 400;
    if (id === 'main_content') score += 2000;
    else if (/main_content|main-content/.test(id)) score += 1200;
    if (/header|footer|drawer|overlay|toast/i.test(id + ' ' + cls) && !/content/i.test(id)) score -= 2500;
    try {
      if (el.closest && el.closest('#main_content, #main_content_area, #user_layout_root')) score += 250;
    } catch (e2) {}
    return score;
  }

  function collectScope(el, into) {
    if (!el || into.indexOf(el) !== -1) return;
    into.push(el);
  }

  function findContentBox() {
    var scopes = [];
    var selectors = [
      '#main_content',
      '[id="main_content"]',
      '[data-id="main_content"]',
      '[data-layout-id="main_content"]',
      '#main_content_area',
      '[id*="main_content"]',
      '[id*="main-content"]',
      '#user_layout_root',
      'main'
    ];
    var i;
    for (i = 0; i < selectors.length; i++) {
      try {
        collectScope(document.querySelector(selectors[i]), scopes);
      } catch (e) {}
    }
    if (scopes.length === 0) {
      collectScope(document.body, scopes);
    }

    var candidates = [];
    function add(el) {
      if (!el || isAdNode(el) || candidates.indexOf(el) !== -1) return;
      candidates.push(el);
    }

    var classSel = '[class*="max-w-7xl"],[class*="max-w-6xl"],[class*="max-w-5xl"],[class*="max-w-4xl"],[class*="max-w-screen"],[class*="mx-auto"]';
    for (i = 0; i < scopes.length; i++) {
      add(scopes[i]);
      try {
        var tagged = scopes[i].querySelectorAll(classSel);
        var t;
        for (t = 0; t < tagged.length && t < 80; t++) add(tagged[t]);
      } catch (e2) {}
      try {
        var nodes = scopes[i].querySelectorAll('div, main, section, article, [class*="container"]');
        var n = Math.min(nodes.length, 220);
        var j;
        for (j = 0; j < n; j++) add(nodes[j]);
      } catch (e3) {}
    }

    var best = null;
    var bestScore = 0;
    for (i = 0; i < candidates.length; i++) {
      var score = columnScore(candidates[i]);
      if (score > bestScore) {
        bestScore = score;
        best = candidates[i];
      }
    }
    return bestScore > 0 ? best : null;
  }

  function clamp(n, min, max) {
    return Math.min(max, Math.max(min, n));
  }

  function applyPlacement(root, config) {
    if (!root) return;
    var pos = config.position || 'right';
    var gap = num(config.offset_px, 24);
    var valign = verticalAlign(config.vertical_align);
    var voff = num(config.vertical_offset_px, 24);
    var adW = root.offsetWidth || Math.max(80, num(config.width_px, 180));
    var adH = root.offsetHeight || Math.max(80, num(config.height_px, 180));
    var vw = viewportWidth();
    var vh = window.innerHeight || document.documentElement.clientHeight || 0;

    root.style.top = '';
    root.style.bottom = '';
    root.style.transform = '';
    root.style.removeProperty('left');
    root.style.removeProperty('right');

    if (pos === 'top' || pos === 'bottom') {
      root.style.left = '50%';
      root.style.right = 'auto';
      root.style.transform = 'translateX(-50%)';
      if (pos === 'top') root.style.top = gap + 'px';
      else root.style.bottom = gap + 'px';
      root.removeAttribute('data-caf-box');
      keepPartiallyOnScreen(root, adW, adH, vw, vh);
      return;
    }

    if (valign === 'top') {
      root.style.setProperty('top', voff + 'px', 'important');
      root.style.setProperty('bottom', 'auto', 'important');
      root.style.setProperty('transform', 'none', 'important');
    } else if (valign === 'bottom') {
      root.style.setProperty('bottom', voff + 'px', 'important');
      root.style.setProperty('top', 'auto', 'important');
      root.style.setProperty('transform', 'none', 'important');
    } else {
      root.style.setProperty('top', '50%', 'important');
      root.style.setProperty('bottom', 'auto', 'important');
      root.style.setProperty('transform', 'translateY(calc(-50% + ' + voff + 'px))', 'important');
    }

    var box = findContentBox();
    var leftPx;
    if (box) {
      var rect = box.getBoundingClientRect();
      root.setAttribute('data-caf-box', box.id || box.className || 'column');
      // Positive gap = outside into the side margin; negative = inward over the content.
      if (pos === 'left') {
        leftPx = rect.left - gap - adW;
      } else {
        leftPx = rect.right + gap;
      }
    } else {
      root.setAttribute('data-caf-box', 'viewport');
      leftPx = pos === 'left' ? gap : (vw - gap - adW);
    }

    var minVis = 32;
    leftPx = clamp(leftPx, minVis - adW, Math.max(minVis - adW, vw - minVis));
    root.style.setProperty('left', Math.round(leftPx) + 'px', 'important');
    root.style.setProperty('right', 'auto', 'important');
    keepPartiallyOnScreen(root, adW, adH, vw, vh);
  }

  function keepPartiallyOnScreen(root, adW, adH, vw, vh) {
    try {
      var r = root.getBoundingClientRect();
      var minVis = 32;
      var dx = 0;
      var dy = 0;
      if (r.right < minVis) dx = minVis - r.right;
      else if (r.left > vw - minVis) dx = (vw - minVis) - r.left;
      if (r.bottom < minVis) dy = minVis - r.bottom;
      else if (r.top > vh - minVis) dy = (vh - minVis) - r.top;
      if (!dx && !dy) return;
      // getBoundingClientRect includes transforms, so drop the axis we just wrote as pixels.
      var hadTx = (root.style.transform || '').indexOf('translateX') !== -1;
      if (dx) {
        root.style.setProperty('left', Math.round(r.left + dx) + 'px', 'important');
        root.style.setProperty('right', 'auto', 'important');
      }
      if (dy) {
        root.style.setProperty('top', Math.round(r.top + dy) + 'px', 'important');
        root.style.setProperty('bottom', 'auto', 'important');
      }
      if (dx && dy) {
        root.style.setProperty('transform', 'none', 'important');
      } else if (dy) {
        root.style.setProperty('transform', hadTx ? 'translateX(-50%)' : 'none', 'important');
      } else if (hadTx) {
        root.style.setProperty('transform', 'none', 'important');
      }
    } catch (e) {}
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
    window.addEventListener('orientationchange', schedule);
    window.addEventListener('scroll', schedule, { passive: true });
    if (window.ResizeObserver) {
      try {
        var ro = new ResizeObserver(schedule);
        ro.observe(document.documentElement);
        var box = findContentBox();
        if (box) ro.observe(box);
      } catch (e) {}
    }
    if (window.MutationObserver && document.body) {
      try {
        var mo = new MutationObserver(schedule);
        mo.observe(document.body, { childList: true, subtree: true });
        window.setTimeout(function () { try { mo.disconnect(); } catch (e2) {} }, 5000);
      } catch (e3) {}
    }
    [50, 150, 400, 1000, 2500].forEach(function (ms) {
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
      '#g7-custom-ad-float.is-left,#g7-custom-ad-float.is-right{left:auto;right:auto}',
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
    root.style.setProperty('--g7-ad-offset', num(config.offset_px, 24) + 'px');
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

    items = sortCarouselItems(items);
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
