/* RecPlanet theme: parallax, the counter, time of day, the trail companion, near me, ratings, freshness, the draw notice. */
(function () {
  'use strict';
  var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var RPc = window.RP || {};
  function $(s, r) { return (r || document).querySelector(s); }
  function $$(s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); }
  function api(path, opts) {
    opts = opts || {};
    var h = { 'Content-Type': 'application/json' };
    if (RPc.nonce) h['X-WP-Nonce'] = RPc.nonce;
    return fetch(RPc.rest + path, { method: opts.method || 'GET', headers: h, credentials: 'same-origin', body: opts.body ? JSON.stringify(opts.body) : undefined }).then(function (r) { return r.json(); });
  }
  function fmt(n) { return Math.round(n).toLocaleString('en-US'); }
  function fmtAc(n) { return Number(n).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }

  /* ---- menu: phone toggle, dropdowns on touch, rotating search hint ---- */
  var menuBtn = $('#menuBtn'), menu = $('#menu');
  if (menuBtn && menu) {
    menuBtn.addEventListener('click', function () { var open = !menu.classList.contains('open'); menu.classList.toggle('open', open); document.body.classList.toggle('menu-open', open); menuBtn.setAttribute('aria-expanded', String(open)); });
    $$('.has-sub > a', menu).forEach(function (a) {
      a.addEventListener('click', function (e) {
        var sub = a.parentElement, touch = window.matchMedia('(hover: none)').matches || window.innerWidth <= 1100;
        if (touch && !sub.classList.contains('open')) { e.preventDefault(); $$('.has-sub.open', menu).forEach(function (o) { if (o !== sub) o.classList.remove('open'); }); sub.classList.add('open'); }
      });
    });
    document.addEventListener('click', function (e) { if (!menu.contains(e.target) && e.target !== menuBtn) $$('.has-sub.open', menu).forEach(function (o) { o.classList.remove('open'); }); });
  }
  $$('a[href$="/random/"]').forEach(function (a) { a.addEventListener('click', function () { a.href = a.href.split('?')[0] + '?r=' + Date.now(); }); });
  var q = $('#q');
  if (q && q.getAttribute('data-hints') && !q.value) {
    var hints = q.getAttribute('data-hints').split('|'), hi = Math.floor(Math.random() * hints.length);
    q.placeholder = hints[hi];
    if (!reduce) setInterval(function () { if (document.activeElement === q || q.value) return; hi = (hi + 1) % hints.length; q.placeholder = hints[hi]; }, 4000);
  }

  /* ---- parallax ---- */
  var layers = $$('[data-depth]'), ticking = false;
  function update() {
    var y = window.scrollY || 0;
    layers.forEach(function (el) {
      var parent = el.parentElement, d = parseFloat(el.getAttribute('data-depth'));
      var rect = parent.getBoundingClientRect();
      if (rect.bottom < -200 || rect.top > window.innerHeight + 200) return;
      var base = parent.offsetTop || 0;
      el.style.transform = 'translate3d(0,' + ((y - base) * d).toFixed(1) + 'px,0)';
    });
    ticking = false;
  }
  if (!reduce && layers.length) {
    window.addEventListener('scroll', function () { if (!ticking) { requestAnimationFrame(update); ticking = true; } }, { passive: true });
    window.addEventListener('resize', update);
    update();
  }

  /* ---- counter: counts up to the live figure ---- */
  var hero = $('#hero');
  if (hero) {
    var target = parseFloat(hero.getAttribute('data-acres') || '0'), intEl = $('#acresInt'), decEl = $('#acresDec');
    function render(v) { var w = Math.floor(v), d = Math.round((v - w) * 100); intEl.textContent = w.toLocaleString('en-US'); decEl.textContent = '.' + (d < 10 ? '0' + d : d); }
    if (!reduce && target > 0) {
      var start = Math.max(0, target - 1200), t0 = null, dur = 2200;
      requestAnimationFrame(function step(ts) { if (!t0) t0 = ts; var p = Math.min(1, (ts - t0) / dur), e = 1 - Math.pow(1 - p, 3); render(start + (target - start) * e); if (p < 1) requestAnimationFrame(step); else render(target); });
    }
    /* time of day and season */
    var now = new Date(), h = now.getHours(), m = now.getMonth(), cls = '';
    if (h < 6 || h >= 20) cls = 't-night'; else if (h < 8) cls = 't-dawn'; else if (h >= 17) cls = 't-dusk';
    if (m >= 9 && m <= 10 && cls !== 't-night') cls += ' s-autumn';
    if ((m === 11 || m <= 1) && cls !== 't-night') cls += ' s-winter';
    hero.className = 'hero ' + cls;
  }

  /* ---- growth chart ---- */
  var svg = $('#growth');
  if (svg) {
    var series = {}; try { series = JSON.parse(svg.getAttribute('data-series') || '{}'); } catch (e) {}
    var years = Object.keys(series).map(Number).sort(function (a, b) { return a - b; });
    if (years.length) {
      var y0 = Math.min(2010, years[0]), y1 = Math.max(years[years.length - 1], new Date().getFullYear());
      var xs = [], cum = [], s = 0;
      for (var y = y0; y <= y1; y++) { s += series[y] || 0; xs.push(y); cum.push(s); }
      var W = 520, H = 240, L = 50, R = 10, T = 14, B = 30, maxY = Math.max(1000, cum[cum.length - 1] * 1.05);
      var ns = 'http://www.w3.org/2000/svg';
      function el(n, a) { var e = document.createElementNS(ns, n); for (var k in a) e.setAttribute(k, a[k]); return e; }
      function X(i) { return L + i * (W - L - R) / Math.max(1, xs.length - 1); }
      function Y(v) { return T + (H - T - B) * (1 - v / maxY); }
      var step = Math.pow(10, Math.floor(Math.log10(maxY))) / 2;
      for (var g = 0; g <= maxY; g += step) { svg.appendChild(el('line', { x1: L, x2: W - R, y1: Y(g), y2: Y(g), stroke: 'currentColor', 'stroke-opacity': .15 })); var t = el('text', { x: L - 6, y: Y(g) + 4, 'text-anchor': 'end', 'font-size': 10, fill: 'currentColor', 'font-family': 'Bricolage Grotesque' }); t.textContent = g >= 1000 ? (g / 1000) + 'k' : g; svg.appendChild(t); }
      var d = 'M' + X(0) + ' ' + Y(cum[0]); cum.forEach(function (v, i) { if (i) d += ' L' + X(i) + ' ' + Y(v); });
      svg.appendChild(el('path', { d: d + ' L' + X(cum.length - 1) + ' ' + Y(0) + ' L' + X(0) + ' ' + Y(0) + ' Z', fill: '#88B500', opacity: .15 }));
      svg.appendChild(el('path', { d: d, fill: 'none', stroke: '#88B500', 'stroke-width': 3, 'stroke-linejoin': 'round' }));
      xs.forEach(function (yr, i) { if (i % 4 === 0 || i === xs.length - 1) { var t2 = el('text', { x: X(i), y: H - 8, 'text-anchor': 'middle', 'font-size': 11, fill: 'currentColor', 'font-family': 'Bricolage Grotesque' }); t2.textContent = yr; svg.appendChild(t2); } });
      var last = cum[cum.length - 1];
      svg.appendChild(el('circle', { cx: X(cum.length - 1), cy: Y(last), r: 5, fill: '#E85305', stroke: '#fff', 'stroke-width': 2 }));
      var lt = el('text', { x: X(cum.length - 1) - 8, y: Y(last) - 10, 'text-anchor': 'end', 'font-size': 12, fill: 'currentColor', 'font-family': 'Bricolage Grotesque', 'font-weight': 800 }); lt.textContent = last.toLocaleString('en-US'); svg.appendChild(lt);
    }
  }

  /* ---- trail companion on the home page ---- */
  var trip = $('#trip');
  if (trip && !reduce) {
    var road = $('#roadPath'), car = $('#car'), hiker = $('#hiker'), mid = $('#roadMid'), roadLen = road.getTotalLength();
    var stops = [['near', 'Near me'], ['ledger', 'The counter'], ['ribbon', 'Every acre'], ['coverage', 'Where we are'], ['contest-home', 'Contest'], ['journal', 'Blog']];
    var signEls = [];
    function el2(n, a) { var e = document.createElementNS('http://www.w3.org/2000/svg', n); for (var k in a) e.setAttribute(k, a[k]); return e; }
    function span() { return Math.max(1, document.documentElement.scrollHeight - window.innerHeight); }
    function placeSigns() {
      var g = $('#signs'); g.innerHTML = ''; signEls = [];
      stops.forEach(function (s, i) {
        var sec = document.getElementById(s[0]); if (!sec) return;
        var frac = Math.min(1, Math.max(0, (sec.offsetTop - 120) / span())), pt = road.getPointAtLength(frac * roadLen), left = i % 2 === 0;
        var grp = el2('g', { class: 'sign', transform: 'translate(' + pt.x + ' ' + pt.y + ')' }), w = s[1].length * 6.6 + 14, x = left ? -18 - w : 18;
        grp.appendChild(el2('line', { x1: left ? -14 : 14, y1: 0, x2: left ? -18 : 18, y2: 0 }));
        grp.appendChild(el2('rect', { x: x, y: -11, width: w, height: 22, rx: 5 }));
        var t = el2('text', { x: x + w / 2, y: 4, 'text-anchor': 'middle' }); t.textContent = s[1]; grp.appendChild(t);
        grp.addEventListener('click', function () { sec.scrollIntoView({ behavior: 'smooth' }); });
        g.appendChild(grp); signEls.push({ el: grp, frac: frac });
      });
      drive();
    }
    function drive() {
      var p = Math.min(1, Math.max(0, (window.scrollY || 0) / span())), L = p * roadLen;
      var a = road.getPointAtLength(Math.max(0, L - 2)), b = road.getPointAtLength(Math.min(roadLen, L + 2)), ang = Math.atan2(b.y - a.y, b.x - a.x) * 180 / Math.PI - 90, pt = road.getPointAtLength(L);
      car.setAttribute('transform', 'translate(' + pt.x + ' ' + pt.y + ') rotate(' + ang + ')');
      hiker.setAttribute('transform', 'translate(' + pt.x + ' ' + pt.y + ') rotate(' + (ang * 0.35) + ')');
      var ph = Math.floor(L / 9) % 2;
      $('#legA').setAttribute('d', ph ? 'M0 4 L-5 14 L-7 20' : 'M0 4 L-2 14 L-1 20');
      $('#legB').setAttribute('d', ph ? 'M0 4 L2 14 L1 20' : 'M0 4 L5 13 L8 19');
      $('#armB').setAttribute('d', ph ? 'M4 -5 L9 2' : 'M4 -5 L8 -10');
      mid.style.strokeDashoffset = (-L * 0.6).toFixed(1);
      signEls.forEach(function (s) { s.el.classList.toggle('passed', p >= s.frac - 0.005); });
    }
    window.addEventListener('scroll', function () { requestAnimationFrame(drive); }, { passive: true });
    window.addEventListener('resize', placeSigns);
    window.addEventListener('load', placeSigns);
    placeSigns();
  }

  /* ---- near me on the home page ---- */
  var nearCards = $('#nearCards'), nearChips = $('#nearChips');
  if (nearCards && nearChips) {
    var me = null, act = '';
    try { var saved = localStorage.getItem('rp-me'); if (saved) me = JSON.parse(saved); act = localStorage.getItem('rp-act') || ''; } catch (e) {}
    function card(p, lead) {
      var acts = []; (RPc.acts || []).forEach(function (n, i) { if (p.bits & (1 << i)) acts.push(n); });
      return '<a class="card" href="' + p.url + '"><div class="card-body"><div class="card-meta"><span class="walk">' + lead + '</span><span class="ac">' + (p.ac === null ? 'acres not recorded' : fmtAc(p.ac) + ' acres') + '</span></div><h3>' + p.t + '</h3><p>' + (p.city || p.st || '') + '</p><div class="tags">' + acts.slice(0, 3).map(function (a) { return '<span>' + a + '</span>'; }).join('') + (acts.length > 3 ? '<span>+' + (acts.length - 3) + '</span>' : '') + '</div></div></a>';
    }
    function load() {
      if (!me) return;
      api('near?lat=' + me.lat + '&lng=' + me.lng + '&km=2&limit=6&activity=' + encodeURIComponent(act)).then(function (rows) {
        if (!rows || !rows.length) { $('#nearNote').textContent = 'Nothing on file within a 15 minute walk' + (act ? ' for ' + act : '') + '. Try another activity or open the atlas.'; nearCards.innerHTML = ''; return; }
        $('#nearNote').textContent = 'The closest places to you' + (act ? ' for ' + act : '') + ', by walking time.';
        nearCards.innerHTML = rows.slice(0, 6).map(function (p) { return card(p, Math.max(1, Math.round(p.km * 1.25 / 5 * 60)) + ' min walk'); }).join('');
      });
    }
    nearChips.addEventListener('click', function (e) {
      var b = e.target.closest('button.chip'); if (!b) return;
      if (b.id === 'locate') {
        if (!navigator.geolocation) return;
        b.textContent = 'Locating…';
        navigator.geolocation.getCurrentPosition(function (pos) { me = { lat: pos.coords.latitude, lng: pos.coords.longitude }; try { localStorage.setItem('rp-me', JSON.stringify(me)); } catch (e) {} b.textContent = '◎ Located'; load(); }, function () { b.textContent = 'Location unavailable'; });
        return;
      }
      $$('.chip', nearChips).forEach(function (c) { if (c.id !== 'locate') c.classList.remove('on'); }); b.classList.add('on');
      act = b.getAttribute('data-a') || ''; try { localStorage.setItem('rp-act', act); } catch (e) {}
      if (me) load(); else $('#locate').click();
    });
    if (me) { $('#locate').textContent = '◎ Located'; $$('.chip', nearChips).forEach(function (c) { c.classList.toggle('on', (c.getAttribute('data-a') || '') === act && c.id !== 'locate'); }); load(); }
  }

  /* ---- freshness on a park ---- */
  var fresh = $('.fresh');
  if (fresh) {
    var park = fresh.getAttribute('data-park'), form = $('.fresh-form', fresh);
    fresh.addEventListener('click', function (e) {
      var b = e.target.closest('button[data-answer]'); if (!b) return;
      if (b.getAttribute('data-answer') === 'yes') {
        api('parks/' + park + '/freshness', { method: 'POST', body: { answer: 'yes' } }).then(function () { b.textContent = 'Thanks, noted today'; $$('button[data-answer]', fresh).forEach(function (x) { x.disabled = true; }); });
      } else { form.hidden = false; $('textarea', form).focus(); }
    });
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      api('parks/' + park + '/freshness', { method: 'POST', body: { answer: 'no', note: $('textarea', form).value } }).then(function () { form.innerHTML = '<p><b>Thank you.</b> An editor will look at it.</p>'; });
    });
    $$('.fresh-open').forEach(function (a) { a.addEventListener('click', function (e) { e.preventDefault(); form.hidden = false; fresh.scrollIntoView({ behavior: 'smooth' }); $('textarea', form).focus(); }); });
  }

  /* ---- star rating on a photo ---- */
  $$('.stars').forEach(function (box) {
    var id = box.getAttribute('data-photo'), btns = $$('.star', box), cnt = $('.cnt', box);
    function paint(score, mine) { btns.forEach(function (b, i) { b.classList.toggle('on', score >= i + 0.75); b.classList.toggle('mine', mine === i + 1); }); }
    api('photos/' + id + '/rating').then(function (s) { if (s && typeof s.score !== 'undefined') { paint(s.score, s.yours); cnt.innerHTML = '<b class="num">' + Number(s.score).toFixed(1) + '</b> from <b class="num">' + fmt(s.votes) + '</b> ratings' + (s.yours ? ' · yours: ' + s.yours : ''); } });
    btns.forEach(function (b, i) {
      b.addEventListener('mouseenter', function () { btns.forEach(function (x, j) { x.classList.toggle('hover', j <= i); }); });
      b.addEventListener('mouseleave', function () { btns.forEach(function (x) { x.classList.remove('hover'); }); });
      b.addEventListener('click', function () {
        api('photos/' + id + '/rating', { method: 'POST', body: { stars: i + 1 } }).then(function (s) {
          if (s.error) { cnt.textContent = s.error; return; }
          paint(s.score, s.yours); cnt.innerHTML = '<b class="num">' + Number(s.score).toFixed(1) + '</b> from <b class="num">' + fmt(s.votes) + '</b> ratings · yours: ' + s.yours;
        });
      });
    });
  });

  /* ---- park name suggestions on the upload form ---- */
  var pq = $('#park_q'), pl = $('#park_list'), pid = $('#park_id');
  if (pq && pl) {
    var t = null, opts = {};
    pq.addEventListener('input', function () {
      pid.value = opts[pq.value] || 0;
      clearTimeout(t); if (pq.value.length < 2) return;
      t = setTimeout(function () { api('parks/suggest?q=' + encodeURIComponent(pq.value)).then(function (rows) { opts = {}; pl.innerHTML = rows.map(function (r) { opts[r.label] = r.id; return '<option value="' + r.label.replace(/"/g, '&quot;') + '">'; }).join(''); pid.value = opts[pq.value] || 0; }); }, 200);
    });
  }

  /* ---- World Parks: the state choropleth and the world dots ---- */
  var usBox = $('#usmapBox');
  if (usBox) {
    var data = {}; try { data = JSON.parse(usBox.getAttribute('data-states') || '{}'); } catch (e) {}
    var max = parseInt(usBox.getAttribute('data-max') || '1', 10), tip = $('#usTip'), svgm = $('.usmap', usBox);
    var lmax = Math.log(max + 1), i = 0;
    $$('.st', usBox).forEach(function (p) {
      var code = p.getAttribute('data-st'), d = data[code];
      p.style.setProperty('--d', i++);
      if (!d || !d.count) return;
      var lvl = Math.max(1, Math.min(5, Math.ceil(Math.log(d.count + 1) / lmax * 5)));
      p.classList.add('l' + lvl);
      p.setAttribute('tabindex', '0'); p.setAttribute('role', 'link'); p.setAttribute('aria-label', d.name + ', ' + d.count + ' places');
      function show(e) {
        tip.innerHTML = '<b>' + d.name + '</b><span>' + d.count.toLocaleString('en-US') + ' places · ' + Math.round(d.acres).toLocaleString('en-US') + ' acres</span>';
        tip.hidden = false;
        var r = usBox.getBoundingClientRect(); tip.style.left = Math.min(r.width - 230, (e.clientX - r.left) + 14) + 'px'; tip.style.top = ((e.clientY - r.top) - 10) + 'px';
      }
      p.addEventListener('mousemove', show); p.addEventListener('mouseleave', function () { tip.hidden = true; });
      p.addEventListener('click', function () { window.location.href = d.url; });
      p.addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); window.location.href = d.url; } });
    });
    if (!reduce) requestAnimationFrame(function () { svgm.classList.add('lit'); });
  }
  var wm = $('#worldMap');
  if (wm) {
    var wtip = $('#worldTip');
    $$('.wdot', wm).forEach(function (a) {
      a.addEventListener('mousemove', function (e) {
        wtip.innerHTML = '<b>' + a.getAttribute('data-name') + '</b><span>' + Number(a.getAttribute('data-n')).toLocaleString('en-US') + ' places · ' + a.getAttribute('data-a') + ' acres</span>';
        wtip.hidden = false; var r = wm.getBoundingClientRect(); wtip.style.left = Math.min(r.width - 230, (e.clientX - r.left) + 14) + 'px'; wtip.style.top = ((e.clientY - r.top) - 10) + 'px';
      });
      a.addEventListener('mouseleave', function () { wtip.hidden = true; });
    });
  }

  /* ---- the voter prize notice ---- */
  var congrats = $('#congrats');
  if (congrats) {
    api('draw/me').then(function (d) {
      if (!d || !d.winner) return;
      congrats.innerHTML = '<div class="wrap"><b>Congratulations!</b> You are the winner among all voters' + (d.prize ? ' of ' + d.prize : '') + '. Your claim code is <code>' + d.code + '</code>. Use the contact page and quote it.</div>';
      congrats.hidden = false;
    }).catch(function () {});
  }
})();
