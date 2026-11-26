(function () {
  'use strict';
  var $ = function (s, r) { return (r || document).querySelector(s); };
  var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };

  // Mobile sidebar
  $$('[data-nav-toggle]').forEach(function (b) {
    b.addEventListener('click', function () { var open = document.body.classList.toggle('nav-open'); b.setAttribute('aria-expanded', open ? 'true' : 'false'); });
  });
  $$('.backdrop').forEach(function (b) {
    b.addEventListener('click', function () { document.body.classList.remove('nav-open'); });
  });

  // Public mobile menu
  var pm = $('[data-public-menu]');
  if (pm) pm.addEventListener('click', function () { var open = $('.nav-public').classList.toggle('open'); pm.setAttribute('aria-expanded', open ? 'true' : 'false'); });

  // Sticky header border
  var tp = $('.topbar-public');
  if (tp) {
    var onScroll = function () { tp.classList.toggle('scrolled', window.scrollY > 8); };
    window.addEventListener('scroll', onScroll, { passive: true }); onScroll();
  }

  // Flash auto-hide
  // Errors stay until dismissed; other messages fade after 8 s unless the pointer or focus is on them (WCAG 2.2.1)
  $$('.flash-stack .alert').forEach(function (a, i) {
    a.addEventListener('click', function () { a.remove(); });
    if (a.classList.contains('alert-error')) return;
    var hold = false;
    a.addEventListener('mouseenter', function () { hold = true; });
    a.addEventListener('mouseleave', function () { hold = false; });
    var fade = function () { if (hold) { setTimeout(fade, 2000); return; } a.style.transition = 'opacity .4s'; a.style.opacity = '0'; setTimeout(function () { a.remove(); }, 450); };
    setTimeout(fade, 8000 + i * 600);
  });

  // Accessibility helpers: tie each visible label to its field, name icon-only buttons and placeholder-only inputs
  (function () {
    var n = 0;
    $$('.field').forEach(function (f) {
      var lab = f.querySelector('label.label');
      var ctl = f.querySelector('input:not([type=hidden]):not([type=checkbox]):not([type=radio]), select, textarea');
      if (!lab || !ctl || lab.getAttribute('for') || lab.contains(ctl)) return;
      if (!ctl.id) ctl.id = 'f-' + (++n) + '-' + (ctl.name || 'x').replace(/[^a-z0-9_-]/gi, '');
      lab.setAttribute('for', ctl.id);
      var hint = f.querySelector('.hint');
      if (hint && !ctl.getAttribute('aria-describedby')) { if (!hint.id) hint.id = ctl.id + '-hint'; ctl.setAttribute('aria-describedby', hint.id); }
    });
    $$('input:not([type=hidden]), select, textarea').forEach(function (c) {
      if (c.getAttribute('aria-label') || c.getAttribute('aria-labelledby') || (c.id && document.querySelector('label[for="' + c.id + '"]')) || c.closest('label')) return;
      var name = c.getAttribute('placeholder') || c.getAttribute('title') || c.name;
      if (name) c.setAttribute('aria-label', name);
    });
    $$('button, a.btn').forEach(function (b) {
      if (b.getAttribute('aria-label') || (b.textContent || '').trim()) return;
      var name = b.getAttribute('title') || (b.hasAttribute('data-close') ? (document.documentElement.lang === 'bn' ? 'বন্ধ করুন' : 'Close') : '');
      if (name) b.setAttribute('aria-label', name);
    });
    $$('pre').forEach(function (pr) { if (!pr.hasAttribute('tabindex')) { pr.setAttribute('tabindex', '0'); pr.setAttribute('role', 'region'); pr.setAttribute('aria-label', 'Code example'); } });
    $$('[data-qr]').forEach(function (q) { q.setAttribute('role', 'img'); if (!q.getAttribute('aria-label')) q.setAttribute('aria-label', 'QR code: ' + q.getAttribute('data-qr')); });
  })();

  // Copy buttons
  $$('[data-copy]').forEach(function (b) {
    b.addEventListener('click', function () {
      var text = b.getAttribute('data-copy');
      var done = function () { var o = b.innerHTML; b.innerHTML = b.getAttribute('data-copied') || '✓'; setTimeout(function () { b.innerHTML = o; }, 1500); };
      if (navigator.clipboard) navigator.clipboard.writeText(text).then(done, function () { fallback(text); done(); });
      else { fallback(text); done(); }
    });
  });
  function fallback(text) { var t = document.createElement('textarea'); t.value = text; document.body.appendChild(t); t.select(); try { document.execCommand('copy'); } catch (e) {} t.remove(); }

  // Confirm forms
  $$('form[data-confirm]').forEach(function (f) {
    f.addEventListener('submit', function (e) { if (!window.confirm(f.getAttribute('data-confirm'))) e.preventDefault(); });
  });

  // Dialogs: <button data-dialog="id" data-fill='{"field":"value"}' data-action="/url">
  $$('[data-dialog]').forEach(function (b) {
    b.addEventListener('click', function () {
      var d = document.getElementById(b.getAttribute('data-dialog'));
      if (!d) return;
      var form = $('form', d);
      if (form && b.getAttribute('data-action')) form.setAttribute('action', b.getAttribute('data-action'));
      var fill = b.getAttribute('data-fill');
      if (fill && form) {
        try {
          var data = JSON.parse(fill);
          Object.keys(data).forEach(function (k) {
            var el = form.elements[k];
            if (el) el.value = data[k];
            $$('[data-text="' + k + '"]', d).forEach(function (t) { t.textContent = data[k]; });
          });
        } catch (e) {}
      }
      if (typeof d.showModal === 'function') d.showModal(); else d.setAttribute('open', '');
      var first = form && $('input:not([type=hidden]), textarea', form); if (first) setTimeout(function () { first.focus(); first.select && first.select(); }, 50);
    });
  });
  $$('[data-close]').forEach(function (b) {
    b.addEventListener('click', function () { var d = b.closest('dialog'); if (d) d.close(); });
  });
  $$('dialog').forEach(function (d) {
    d.addEventListener('click', function (e) { if (e.target === d) d.close(); });
  });

  // Live salary total on employee form
  var sf = $('[data-salary-form]');
  if (sf) {
    var calc = function () {
      var v = function (n) { var el = sf.elements[n]; var x = el ? parseFloat(String(el.value).replace(/,/g, '')) : 0; return isNaN(x) ? 0 : x; };
      var earn = v('basic') + v('house_rent') + v('medical') + v('conveyance') + v('other_allowance');
      var ded = v('tax') + v('pf') + v('other_deduction');
      var sym = sf.getAttribute('data-sym') || '', loc = sf.getAttribute('data-locale') || 'en-US';
      var fmt = function (n) { return sym + n.toLocaleString(loc, { minimumFractionDigits: 2, maximumFractionDigits: 2 }); };
      var g = $('[data-out=gross]'), d = $('[data-out=ded]'), n = $('[data-out=net]');
      if (g) g.textContent = fmt(earn); if (d) d.textContent = fmt(ded); if (n) n.textContent = fmt(earn - ded);
    };
    sf.addEventListener('input', calc); calc();
    // Quick split helper: fill structure from a gross amount
    var split = $('[data-split]');
    if (split) split.addEventListener('click', function () {
      var gross = parseFloat(window.prompt(split.getAttribute('data-split'), '30000'));
      if (!gross || gross <= 0) return;
      var basic = Math.round(gross * 0.6), hr = Math.round(gross * 0.3), med = Math.round(gross * 0.05);
      sf.elements.basic.value = basic; sf.elements.house_rent.value = hr; sf.elements.medical.value = med;
      sf.elements.conveyance.value = gross - basic - hr - med; sf.elements.other_allowance.value = 0; calc();
    });
  }

  // QR codes: <div data-qr="text"></div>
  if (window.qrcode) {
    $$('[data-qr]').forEach(function (el) {
      try {
        var q = window.qrcode(0, 'M');
        q.addData(el.getAttribute('data-qr'));
        q.make();
        el.innerHTML = q.createSvgTag({ cellSize: 4, margin: 0, scalable: true });
      } catch (e) {}
    });
  }

  // Auto-submit selects and select-on-click inputs (no inline handlers: strict CSP)
  $$('[data-autosubmit]').forEach(function (el) { el.addEventListener('change', function () { el.form && el.form.submit(); }); });
  $$('[data-select]').forEach(function (el) { el.addEventListener('click', function () { el.select(); }); });

  // Light / dark theme toggle (cookie so the server renders the right theme with no flash)
  $$('[data-theme-toggle]').forEach(function (b) {
    b.addEventListener('click', function () {
      var root = document.documentElement;
      var cur = root.getAttribute('data-theme');
      if (cur === 'auto') cur = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
      var next = cur === 'dark' ? 'light' : 'dark';
      root.setAttribute('data-theme', next);
      document.cookie = 'pst_theme=' + next + ';path=/;max-age=31536000;SameSite=Lax' + (location.protocol === 'https:' ? ';Secure' : '');
    });
  });


  // Fill an input from an example chip; Enter submits textareas marked data-enter-submit
  $$('[data-fill-input]').forEach(function (b) {
    b.addEventListener('click', function () { var f = b.closest('form'); var t = f && $('textarea, input[name=q]', f); if (t) { t.value = b.getAttribute('data-fill-input'); t.focus(); } });
  });
  $$('textarea[data-enter-submit]').forEach(function (t) {
    t.addEventListener('keydown', function (e) { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); if (t.value.trim()) t.form.submit(); } });
  });
  var bottom = document.getElementById('bottom'); if (bottom && location.hash === '#bottom') { var log = bottom.closest('.chat-log'); if (log) log.scrollTop = log.scrollHeight; }

  // ===== Live chat widget =====
  var cw = $('[data-chat]');
  if (cw) {
    var panel = $('.cw-panel', cw), body = $('[data-chat-body]', cw), chips = $('[data-chat-chips]', cw), form = $('[data-chat-form]', cw), dot = $('.cw-dot', cw);
    var token = (document.querySelector('meta[name=csrf-token]') || {}).content || '';
    var lastId = 0, loaded = false, status = 'new', timer = null, open = false;
    var esc = function (s) { var d = document.createElement('div'); d.textContent = s; return d.innerHTML; };
    var add = function (m) {
      if (m.id && m.id <= lastId) return; if (m.id) lastId = m.id;
      var who = m.from === 'visitor' ? 'me' : 'bot';
      var el = document.createElement('div'); el.className = 'msg ' + who;
      el.innerHTML = '<div class="bubble">' + esc(m.text) + (m.link ? '<br><a href="' + esc(m.link) + '">→ ' + (document.documentElement.lang === 'bn' ? 'দেখুন' : 'Open') + '</a>' : '') + (m.from === 'agent' ? '' : '') + '</div>';
      body.appendChild(el); body.scrollTop = body.scrollHeight;
    };
    var setChips = function (list) {
      chips.innerHTML = '';
      (list || []).forEach(function (q) { var b = document.createElement('button'); b.type = 'button'; b.className = 'chip-btn'; b.textContent = q; b.addEventListener('click', function () { send(q); }); chips.appendChild(b); });
    };
    var handle = function (d) {
      if (!d || !d.ok) return;
      if (d.greeting && !lastId && !body.children.length) add({ from: 'bot', text: d.greeting });
      (d.messages || []).forEach(add);
      if (d.suggestions && !lastId) setChips(d.suggestions); else if (lastId) setChips([]);
      status = d.status || status;
      if (!open && (d.messages || []).some(function (m) { return m.from === 'agent'; })) dot.hidden = false;
      schedule();
    };
    var poll = function () {
      fetch(cw.getAttribute('data-poll') + '?after=' + lastId, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } }).then(function (r) { return r.json(); }).then(handle).catch(function () {});
    };
    var schedule = function () {
      clearTimeout(timer);
      if (status === 'human') timer = setTimeout(poll, open ? 5000 : 20000);
    };
    var send = function (text) {
      text = (text || '').trim(); if (!text) return;
      add({ from: 'visitor', text: text }); setChips([]);
      var typing = document.createElement('div'); typing.className = 'cw-typing'; typing.textContent = '…'; body.appendChild(typing);
      var fd = new FormData(); fd.append('message', text); fd.append('after', String(lastId)); fd.append('_csrf', token);
      fetch(cw.getAttribute('data-send'), { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'X-CSRF-Token': token, 'Accept': 'application/json' } })
        .then(function (r) { return r.json(); })
        .then(function (d) { typing.remove(); if (d && d.ok) { lastId = Math.max(lastId, 0); (d.messages || []).forEach(function (m) { if (m.from !== 'visitor') add(m); else if (m.id > lastId) lastId = m.id; }); status = d.status; schedule(); } else if (d && d.error) { add({ from: 'bot', text: d.error }); } })
        .catch(function () { typing.remove(); add({ from: 'bot', text: 'Connection problem. Please try again.' }); });
    };
    $$('[data-chat-toggle]', cw).forEach(function (b) {
      b.addEventListener('click', function () {
        open = panel.hidden; panel.hidden = !open; dot.hidden = true;
        cw.classList.toggle('is-open', open); document.body.classList.toggle('cw-open', open);
        if (open) { if (!loaded) { loaded = true; poll(); } setTimeout(function () { var i = $('input', form); if (i) i.focus(); }, 50); }
        schedule();
      });
    });
    form.addEventListener('submit', function (e) { e.preventDefault(); var i = $('input', form); send(i.value); i.value = ''; });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && open) { var c = $('.cw-head [data-chat-toggle]', cw) || $('[data-chat-toggle]', cw); if (c) c.click(); } });
  }


  // Confirm on buttons; live character counters
  $$('button[data-confirm]').forEach(function (b) { b.addEventListener('click', function (e) { if (!window.confirm(b.getAttribute('data-confirm'))) e.preventDefault(); }); });
  $$('[data-count]').forEach(function (el) {
    var out = document.querySelector('[data-count-for="' + el.name + '"]'); if (!out) return;
    var upd = function () { out.textContent = el.value.length + ' / ' + (el.name === 'meta_title' ? 65 : 160); };
    el.addEventListener('input', upd); upd();
  });

  // Print
  $$('[data-print]').forEach(function (b) { b.addEventListener('click', function () { window.print(); }); });

  // Native share (mobile)
  $$('[data-share]').forEach(function (b) {
    if (!navigator.share) { b.classList.add('hide'); return; }
    b.addEventListener('click', function () { navigator.share({ title: document.title, url: b.getAttribute('data-share') }).catch(function () {}); });
  });

  // Installable app (PWA): register the service worker and offer an Install button where the page has one
  (function () {
    var sw = document.querySelector('meta[name=sw]');
    if (sw && 'serviceWorker' in navigator && (location.protocol === 'https:' || location.hostname === 'localhost' || location.hostname === '127.0.0.1')) {
      window.addEventListener('load', function () { navigator.serviceWorker.register(sw.getAttribute('content')).catch(function () {}); });
    }
    var deferred = null;
    var btns = $$('[data-install]');
    var standalone = window.matchMedia && window.matchMedia('(display-mode: standalone)').matches;
    if (standalone) return;
    window.addEventListener('beforeinstallprompt', function (e) {
      e.preventDefault(); deferred = e;
      btns.forEach(function (b) { b.hidden = false; });
    });
    btns.forEach(function (b) {
      b.addEventListener('click', function () {
        if (!deferred) { var h = document.querySelector('[data-install-help]'); if (h) h.hidden = false; return; }
        deferred.prompt(); deferred.userChoice.finally(function () { deferred = null; btns.forEach(function (x) { x.hidden = true; }); });
      });
    });
    if (/iphone|ipad|ipod/i.test(navigator.userAgent)) { var h = document.querySelector('[data-install-help]'); if (h) h.hidden = false; }
  })();

  // Cookie consent: analytics load only after "Accept all" (the server adds them on the next page load)
  (function () {
    var box = document.querySelector('[data-consent]');
    var set = function (v) {
      document.cookie = 'pst_consent=' + v + ';path=/;max-age=31536000;SameSite=Lax' + (location.protocol === 'https:' ? ';Secure' : '');
      location.reload();
    };
    $$('[data-consent-choice]').forEach(function (b) { b.addEventListener('click', function () { set(b.getAttribute('data-consent-choice')); }); });
    $$('[data-consent-open]').forEach(function (b) {
      b.addEventListener('click', function () { if (box) { box.hidden = false; var f = box.querySelector('button'); if (f) f.focus(); } });
    });
  })();
  // v1.9: menu groups remember open/closed; on tall screens everything starts open
  (function () {
    var store = function (k, v) { try { if (v === undefined) return localStorage.getItem(k); localStorage.setItem(k, v); } catch (e) { return null; } };
    var groups = $$('.nav-group');
    groups.forEach(function (g) {
      var k = 'pst_nav_' + g.getAttribute('data-group'), saved = store(k);
      if (!g.hasAttribute('data-current')) {
        if (saved === '1') g.open = true; else if (saved === '0') g.open = false; else if (window.innerHeight >= 940) g.open = true;
      }
      g.addEventListener('toggle', function () { store(k, g.open ? '1' : '0'); });
    });
    var cur = $('.nav a.on');
    if (cur && cur.scrollIntoView) { var sb = $('.sidebar'); if (sb && cur.offsetTop > sb.clientHeight - 120) cur.scrollIntoView({ block: 'center' }); }
  })();

  // v1.9: when the page's own buttons scroll away, show them in the top bar
  (function () {
    var head = $('.content > .page-head'), slot = $('[data-appbar-actions]');
    if (!head || !slot || !('IntersectionObserver' in window)) return;
    var row = $('.row', head) || head.lastElementChild;
    if (!row || row === head.firstElementChild) return;
    var items = $$('a.btn, button.btn', row).filter(function (b) { return b.offsetParent !== null && !b.disabled; }).slice(-3);
    if (!items.length) return;
    items.forEach(function (orig) {
      var p = document.createElement(orig.tagName === 'A' ? 'a' : 'button');
      if (orig.tagName === 'A') { p.href = orig.href; if (orig.target) p.target = orig.target; } else { p.type = 'button'; p.addEventListener('click', function () { orig.click(); }); }
      p.className = orig.className.replace(/\bbtn-block\b/, '') + ' btn-sm';
      p.tabIndex = -1;
      var ic = orig.querySelector('svg'); if (ic) p.appendChild(ic.cloneNode(true));
      var lbl = document.createElement('span'); lbl.className = 'lbl'; lbl.textContent = ' ' + orig.textContent.trim(); p.appendChild(lbl);
      slot.appendChild(p);
    });
    new IntersectionObserver(function (es) {
      var hidden = !es[0].isIntersecting;
      slot.classList.toggle('show', hidden);
      slot.setAttribute('aria-hidden', hidden ? 'false' : 'true');
      $$('a,button', slot).forEach(function (b) { b.tabIndex = hidden ? 0 : -1; });
    }, { rootMargin: '-64px 0px 0px 0px' }).observe(row);
  })();

  // v1.9: back to top
  (function () {
    var b = $('[data-to-top]');
    if (!b) return;
    if ($('.cw')) document.body.classList.add('has-chat');
    b.hidden = false;
    var on = false, tick = function () { var s = window.scrollY > 600; if (s !== on) { on = s; b.classList.toggle('show', s); b.tabIndex = s ? 0 : -1; } };
    window.addEventListener('scroll', tick, { passive: true }); tick();
    b.addEventListener('click', function () {
      var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
      window.scrollTo({ top: 0, behavior: reduce ? 'auto' : 'smooth' });
      var m = $('#main'); if (m) m.focus({ preventScroll: true });
    });
  })();
  // v2.0: first-party analytics. No cookies or storage; nothing is sent when the browser asks not to be tracked.
  (function () {
    var meta = document.querySelector('meta[name="pst-an"]');
    if (!meta || !window.fetch) return;
    if (navigator.globalPrivacyControl || navigator.doNotTrack === '1' || window.doNotTrack === '1') return;
    var base = meta.getAttribute('content'), id = null, visible = 0, last = Date.now(), maxScroll = 0;
    var post = function (url, data, beacon) {
      var body = JSON.stringify(data);
      if (beacon && navigator.sendBeacon) { try { return navigator.sendBeacon(url, new Blob([body], { type: 'application/json' })); } catch (e) {} }
      return fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: body, keepalive: true, credentials: 'same-origin' });
    };
    var q = []; location.search.replace(/^\?/, '').split('&').forEach(function (kv) { if (/^(utm_[a-z]+|ref)=/.test(kv)) q.push(kv); });
    post(base + '/collect', { p: location.pathname, u: q.length ? '?' + q.join('&') : '', r: document.referrer, t: document.title, w: screen.width, l: navigator.language })
      .then(function (r) { return r.json(); }).then(function (d) { id = d && d.id; }).catch(function () {});
    var tickTime = function () { var n = Date.now(); if (document.visibilityState === 'visible') visible += (n - last) / 1000; last = n; };
    window.addEventListener('scroll', function () {
      var h = document.documentElement, max = h.scrollHeight - h.clientHeight;
      var p = max > 0 ? Math.round(h.scrollTop / max * 100) : 100; if (p > maxScroll) maxScroll = p;
    }, { passive: true });
    var ping = function (beacon) { tickTime(); if (id) post(base + '/ping', { id: id, s: Math.round(visible), sc: maxScroll }, beacon); };
    setInterval(function () { if (document.visibilityState === 'visible') ping(false); }, 15000);
    document.addEventListener('visibilitychange', function () { if (document.visibilityState === 'hidden') ping(true); else last = Date.now(); });
    window.addEventListener('pagehide', function () { ping(true); });
    window.pstTrack = function (name, m) { post(base + '/collect', { e: name, m: m || '', p: location.pathname }, true); };
    document.addEventListener('click', function (e) {
      var t = e.target.closest && e.target.closest('[data-track], .cw-fab');
      if (!t) return;
      if (t.classList.contains('cw-fab')) { if ($('.cw-panel') && $('.cw-panel').hidden) window.pstTrack('chat_open'); return; }
      window.pstTrack(t.getAttribute('data-track'), t.getAttribute('data-track-meta') || '');
    }, true);
  })();

  // v2.0: admin live analytics refresh
  (function () {
    var box = $('[data-live-url]');
    if (!box) return;
    var url = box.getAttribute('data-live-url');
    var el = function (tag, cls, text) { var n = document.createElement(tag); if (cls) n.className = cls; if (text != null) n.textContent = text; return n; };
    var dev = { mobile: 'Mobile', desktop: 'Desktop', tablet: 'Tablet' };
    var draw = function (d) {
      $('[data-live-count]', box).textContent = d.count;
      $('[data-live-at]', box).textContent = d.at;
      var rows = $('[data-live-rows]', box); rows.textContent = '';
      if (!d.visitors.length) { var tr = el('tr'); var td = el('td', 'muted', 'Nobody is browsing right now.'); td.colSpan = 5; tr.appendChild(td); rows.appendChild(tr); }
      d.visitors.forEach(function (v) {
        var tr = el('tr'), who = el('td');
        who.appendChild(el('span', 'flag', v.flag)); who.appendChild(document.createTextNode(' '));
        if (v.user) { who.appendChild(el('b', '', v.user.name)); who.appendChild(document.createTextNode(' ')); who.appendChild(el('span', 'badge plain', v.user.role)); }
        else who.appendChild(document.createTextNode(v.place || 'Unknown'));
        if (v.is_new) { who.appendChild(document.createTextNode(' ')); who.appendChild(el('span', 'badge badge-brand plain', 'new')); }
        tr.appendChild(who);
        tr.appendChild(el('td', 'mono small', v.path));
        tr.appendChild(el('td', '', v.source));
        tr.appendChild(el('td', 'small', (dev[v.device] || v.device || '') + ' · ' + (v.browser || '')));
        tr.appendChild(el('td', 'num small', v.in_visit + ' · ' + v.pages + ' pages'));
        rows.appendChild(tr);
      });
      var mins = $('[data-live-mins]', box), mx = 1;
      d.minutes.forEach(function (m) { if (m.n > mx) mx = m.n; });
      $$('i', mins).forEach(function (i, k) { var m = d.minutes[k]; if (m) { i.style.height = Math.max(4, Math.round(m.n / mx * 100)) + '%'; i.title = m.m + ' · ' + m.n; } });
      var ev = $('[data-live-events]', box); ev.textContent = '';
      if (!d.events.length) ev.appendChild(el('li', 'muted small', 'Sign-ups, demo visits, checkouts and button clicks appear here.'));
      d.events.forEach(function (x) {
        var li = el('li'); li.appendChild(el('span', 'flag', x.flag));
        var sp = el('span'); sp.appendChild(el('b', '', x.event.replace(/_/g, ' ')));
        sp.appendChild(document.createTextNode((x.meta ? ' · ' + x.meta : '') + (x.user ? ' · ' + x.user : '')));
        sp.appendChild(el('em', '', x.ago)); li.appendChild(sp); ev.appendChild(li);
      });
    };
    setInterval(function () {
      if (document.visibilityState !== 'visible') return;
      fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' } }).then(function (r) { return r.ok ? r.json() : null; }).then(function (d) { if (d) draw(d); }).catch(function () {});
    }, 10000);
  })();
})();
