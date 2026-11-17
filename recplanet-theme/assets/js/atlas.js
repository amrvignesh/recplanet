/* The atlas: MapLibre GL over OpenFreeMap tiles; every dot comes from the plugin's index through /places. */
(function () {
  'use strict';
  var RPc = window.RP || {}, root = document.getElementById('atlas');
  if (!root || typeof maplibregl === 'undefined') return;
  var ACTS = RPc.acts || [], STW = { city: '#88B500', county: '#0FB8E6', state: '#FFE66D', federal: '#E85305', tribal: '#C77DFF', other: '#9FB095' };
  var start = {}; try { start = JSON.parse(root.getAttribute('data-start') || '{}'); } catch (e) {}
  var state = { acts: (root.getAttribute('data-activity') || '').split(',').filter(Boolean), stw: [], minAc: 0, noPhoto: false, year: new Date().getFullYear(), me: null, travelKm: 1.25, travelMode: 'walk', sel: null, placing: false, selecting: false };
  try { state.stw = JSON.parse(root.getAttribute('data-steward') || '[]') || []; } catch (e) {}
  try { var saved = JSON.parse(localStorage.getItem('rp-me') || 'null'); if (saved) state.me = saved; } catch (e) {}

  var map = new maplibregl.Map({
    container: 'map',
    style: 'https://tiles.openfreemap.org/styles/positron',
    center: start.lng ? [start.lng, start.lat] : [-98.35, 39.5],
    zoom: start.zoom || 3.6,
    attributionControl: false
  });
  map.addControl(new maplibregl.NavigationControl({ showCompass: false }), 'bottom-right');
  map.addControl(new maplibregl.AttributionControl({ compact: true }), 'bottom-right');
  if (start.bbox) map.fitBounds([[start.bbox[0], start.bbox[1]], [start.bbox[2], start.bbox[3]]], { padding: 40, duration: 0 });

  function brand() {
    /* Tint the base map towards the brand: water in the sky blue, parks in the hill green, everything else quiet. */
    var layers = map.getStyle().layers || [];
    layers.forEach(function (l) {
      var id = l.id.toLowerCase();
      try {
        if (l.type === 'fill' && /water/.test(id)) map.setPaintProperty(l.id, 'fill-color', '#BDEBF7');
        if (l.type === 'fill' && /(park|wood|forest|grass|green|golf|cemetery)/.test(id)) map.setPaintProperty(l.id, 'fill-color', '#D9EBC4');
        if (l.type === 'fill' && /(landuse|residential|background|land)/.test(id) && !/water|park/.test(id)) map.setPaintProperty(l.id, 'fill-color', '#F3F7EE');
      } catch (e) {}
    });
  }

  var apiBase = RPc.rest + 'places';
  /* Managed by: chosen stewards are levels (city, federal ...) or steward term ids, any of which may match. */
  function stwKey(s) { return s.type === 'level' ? s.key : String(s.id); }
  function stwParam() { return state.stw.length ? '&steward=' + state.stw.map(stwKey).join(',') : ''; }
  /* Activity lens: any number of activities; a place shows when it has any of them. */
  function actParam() { return state.acts.length ? '&activity=' + encodeURIComponent(state.acts.join(',')) : ''; }
  function bbox(dp) { var b = map.getBounds(); return [b.getWest(), b.getSouth(), b.getEast(), b.getNorth()].map(function (v) { return v.toFixed(dp); }).join(','); }
  function params() {
    var p = 'bbox=' + bbox(4);
    p += actParam() + stwParam();
    if (state.minAc) p += '&min_acres=' + state.minAc;
    if (state.year < new Date().getFullYear()) p += '&year=' + state.year;
    if (state.noPhoto) p += '&no_photo=1';
    return p + '&limit=3000';
  }
  var lastReq = 0, places = [], inView = { count: 0, acres: 0 };
  function load() {
    var id = ++lastReq;
    fetch(apiBase + '?' + params()).then(function (r) { return r.json(); }).then(function (d) {
      if (id !== lastReq || !d.places || !map.getSource('parks')) return;
      places = d.places; inView = d.in_view;
      map.getSource('parks').setData({ type: 'FeatureCollection', features: places.map(function (p) { return { type: 'Feature', geometry: { type: 'Point', coordinates: [p.lng, p.lat] }, properties: { id: p.id, t: p.t, ac: p.ac === null ? 0 : p.ac, acn: p.ac === null ? 0 : 1, city: p.city, st: p.st, sw: p.sw, bits: p.bits, ph: p.ph, url: p.url, col: STW[p.sw] || STW.other } }; }) });
      panel();
    });
  }

  map.on('load', function () {
    brand();
    map.addSource('parks', { type: 'geojson', data: { type: 'FeatureCollection', features: [] }, cluster: true, clusterRadius: 48, clusterMaxZoom: 13 });
    map.addLayer({ id: 'cl-glow', type: 'circle', source: 'parks', filter: ['has', 'point_count'], paint: { 'circle-color': '#88B500', 'circle-opacity': 0.18, 'circle-radius': ['+', 22, ['*', 3, ['sqrt', ['get', 'point_count']]]] } });
    map.addLayer({ id: 'cl', type: 'circle', source: 'parks', filter: ['has', 'point_count'], paint: { 'circle-color': '#88B500', 'circle-radius': ['+', 14, ['*', 3, ['sqrt', ['get', 'point_count']]]], 'circle-stroke-color': '#fff', 'circle-stroke-width': 2 } });
    map.addLayer({ id: 'cl-n', type: 'symbol', source: 'parks', filter: ['has', 'point_count'], layout: { 'text-field': ['get', 'point_count_abbreviated'], 'text-size': 13, 'text-font': ['Noto Sans Bold'] }, paint: { 'text-color': '#14211A' } });
    map.addLayer({ id: 'pt', type: 'circle', source: 'parks', filter: ['!', ['has', 'point_count']], paint: { 'circle-color': '#88B500', 'circle-radius': 6, 'circle-stroke-color': '#14211A', 'circle-stroke-width': 1.5 } });
    map.addLayer({ id: 'me-ring', type: 'fill', source: { type: 'geojson', data: { type: 'FeatureCollection', features: [] } }, paint: { 'fill-color': '#E85305', 'fill-opacity': 0.08 } });
    map.addLayer({ id: 'me-ring-line', type: 'line', source: 'me-ring', paint: { 'line-color': '#E85305', 'line-width': 1.5, 'line-dasharray': [3, 3] } });
    map.addLayer({ id: 'sel', type: 'fill', source: { type: 'geojson', data: { type: 'FeatureCollection', features: [] } }, paint: { 'fill-color': '#FFE66D', 'fill-opacity': 0.12 } });
    map.addLayer({ id: 'sel-line', type: 'line', source: 'sel', paint: { 'line-color': '#FFE66D', 'line-width': 2, 'line-dasharray': [2, 2] } });
    applyMode(); load(); drawMe();
    map.on('moveend', load);
    map.on('click', 'cl', function (e) { var f = e.features[0]; map.getSource('parks').getClusterExpansionZoom(f.properties.cluster_id).then(function (z) { map.easeTo({ center: f.geometry.coordinates, zoom: z }); }); });
    map.on('click', 'pt', function (e) { if (!state.placing && !state.selecting) window.location.href = e.features[0].properties.url; });
    map.on('mouseenter', 'pt', function () { map.getCanvas().style.cursor = 'pointer'; });
    map.on('mouseleave', 'pt', function () { map.getCanvas().style.cursor = ''; popup.remove(); });
    var popup = new maplibregl.Popup({ closeButton: false, closeOnClick: false, offset: 10 });
    map.on('mousemove', 'pt', function (e) {
      var p = e.features[0].properties, acts = []; ACTS.forEach(function (n, i) { if (p.bits & (1 << i)) acts.push(n); });
      popup.setLngLat(e.features[0].geometry.coordinates).setHTML('<b>' + p.t + '</b><br><span>' + (p.city || p.st) + ' · ' + (p.acn ? Number(p.ac).toLocaleString('en-US') + ' acres' : 'acres not recorded') + '</span><br><span>' + (acts.slice(0, 4).join(', ') || 'no activities recorded') + '</span>' + (p.ph ? '' : '<br><span style="color:#E85305">No photo yet</span>')).addTo(map);
    });
    map.on('click', function (e) {
      if (state.placing) { state.me = { lat: e.lngLat.lat, lng: e.lngLat.lng }; try { localStorage.setItem('rp-me', JSON.stringify(state.me)); } catch (x) {} state.placing = false; document.getElementById('placeMe').classList.remove('on'); map.getCanvas().style.cursor = ''; drawMe(); panel(); }
    });
    /* select an area: drag a box */
    var selStart = null;
    map.on('mousedown', function (e) { if (!state.selecting) return; e.preventDefault(); selStart = e.lngLat; map.dragPan.disable(); });
    map.on('mousemove', function (e) { if (!selStart) return; state.sel = [selStart, e.lngLat]; drawSel(); });
    map.on('mouseup', function () { if (!selStart) return; selStart = null; map.dragPan.enable(); state.selecting = false; document.getElementById('selArea').classList.remove('on'); panel(); });
  });

  function circle(lat, lng, km) { var pts = []; for (var i = 0; i <= 64; i++) { var a = i / 64 * Math.PI * 2; pts.push([lng + km / (111 * Math.cos(lat * Math.PI / 180)) * Math.cos(a), lat + km / 111 * Math.sin(a)]); } return { type: 'Feature', geometry: { type: 'Polygon', coordinates: [pts] } }; }
  function drawMe() {
    if (!map.getSource('me-ring')) return;
    map.getSource('me-ring').setData({ type: 'FeatureCollection', features: state.me ? [circle(state.me.lat, state.me.lng, state.travelKm), circle(state.me.lat, state.me.lng, state.travelKm * 2)] : [] });
    if (meMarker) meMarker.remove();
    if (state.me) { var el = document.createElement('div'); el.style.cssText = 'width:18px;height:18px;border-radius:50%;background:#E85305;border:3px solid #fff;box-shadow:0 2px 8px rgba(0,0,0,.4)'; meMarker = new maplibregl.Marker({ element: el }).setLngLat([state.me.lng, state.me.lat]).addTo(map); }
  }
  var meMarker = null;
  function drawSel() {
    if (!state.sel) { map.getSource('sel').setData({ type: 'FeatureCollection', features: [] }); return; }
    var a = state.sel[0], b = state.sel[1];
    map.getSource('sel').setData({ type: 'FeatureCollection', features: [{ type: 'Feature', geometry: { type: 'Polygon', coordinates: [[[a.lng, a.lat], [b.lng, a.lat], [b.lng, b.lat], [a.lng, b.lat], [a.lng, a.lat]]] } }] });
  }
  function applyMode() {
    if (!map.getLayer('pt')) return;
    map.setPaintProperty('pt', 'circle-radius', 6); map.setPaintProperty('pt', 'circle-color', state.acts.length ? '#FFB300' : '#88B500');
    document.getElementById('legend').innerHTML = state.acts.length ? '<span><i style="background:#FFB300"></i>' + state.acts.join(' or ') + '</span>' : '<span><i style="background:#88B500"></i>Places · a number is how many are grouped there</span>';
  }
  function km(a, b) { var R = 6371, dl = (b.lat - a.lat) * Math.PI / 180, dn = (b.lng - a.lng) * Math.PI / 180, x = Math.sin(dl / 2) * Math.sin(dl / 2) + Math.cos(a.lat * Math.PI / 180) * Math.cos(b.lat * Math.PI / 180) * Math.sin(dn / 2) * Math.sin(dn / 2); return 2 * R * Math.asin(Math.sqrt(x)); }
  function mins(d) { var sp = state.travelMode === 'walk' ? 5 : state.travelMode === 'bike' ? 15 : 50; return Math.max(1, Math.round(d * 1.25 / sp * 60)); }
  function item(p, lead) {
    var acts = []; ACTS.forEach(function (n, i) { if (p.bits & (1 << i)) acts.push(n); });
    return '<a class="it" href="' + p.url + '"><span class="m"><span class="w">' + lead + '</span><span class="a">' + (p.ac === null ? 'acres not recorded' : Number(p.ac).toLocaleString('en-US') + ' ac') + '</span></span><b>' + p.t + '</b><small>' + (p.city || p.st || '') + ' · ' + (acts.slice(0, 3).join(', ') || 'no activities recorded') + '</small></a>';
  }
  function fmt(n) { return Math.round(n).toLocaleString('en-US'); }
  function panel() {
    var body = document.getElementById('rpBody'), title = document.getElementById('rpTitle'), note = document.getElementById('rpNote');
    if (state.sel) {
      var a = state.sel[0], b = state.sel[1], pts = places.filter(function (p) { return p.lng >= Math.min(a.lng, b.lng) && p.lng <= Math.max(a.lng, b.lng) && p.lat >= Math.min(a.lat, b.lat) && p.lat <= Math.max(a.lat, b.lat); });
      var n = pts.length, ac = pts.reduce(function (s, p) { return s + (p.ac || 0); }, 0), counts = {}, st = {};
      pts.forEach(function (p) { ACTS.forEach(function (nm, i) { if (p.bits & (1 << i)) counts[nm] = (counts[nm] || 0) + 1; }); st[p.sw] = (st[p.sw] || 0) + 1; });
      var top = Object.keys(counts).sort(function (x, y) { return counts[y] - counts[x]; }).slice(0, 6);
      title.textContent = 'Your selection'; note.textContent = 'A counter for any area you draw. Drag again to redraw.';
      document.getElementById('vN').textContent = fmt(n); document.getElementById('vA').textContent = fmt(ac);
      body.innerHTML = '<div class="sel"><h4>What you can do here</h4><div class="bars">' + top.map(function (k) { return '<a href="#"><span>' + k + '</span><span class="t"><span class="f" style="width:' + Math.round(counts[k] / (n || 1) * 100) + '%"></span></span><b>' + counts[k] + '</b></a>'; }).join('') + '</div><h4 style="margin-top:8px">Managed by</h4><div class="bars">' + Object.keys(st).map(function (k) { return '<a href="#"><span>' + k + '</span><span class="t"><span class="f" style="width:' + Math.round(st[k] / (n || 1) * 100) + '%;background:' + STW[k] + '"></span></span><b>' + st[k] + '</b></a>'; }).join('') + '</div><button type="button" class="btn btn-ghost" id="clearSel">Clear selection</button></div>' + pts.sort(function (x, y) { return (y.ac || 0) - (x.ac || 0); }).slice(0, 10).map(function (p) { return item(p, 'largest first'); }).join('');
      document.getElementById('clearSel').onclick = function () { state.sel = null; drawSel(); panel(); };
      return;
    }
    document.getElementById('vN').textContent = fmt(inView.count); document.getElementById('vA').textContent = fmt(inView.acres);
    if (state.me) {
      title.textContent = 'Within reach'; note.textContent = 'Ordered by ' + state.travelMode + 'ing time from your point.';
      fetch(RPc.rest + 'near?lat=' + state.me.lat + '&lng=' + state.me.lng + '&km=' + (state.travelKm * 2) + '&limit=20' + actParam() + stwParam()).then(function (r) { return r.json(); }).then(function (rows) {
        body.innerHTML = rows.length ? rows.map(function (p) { return item(p, mins(p.km) + ' min ' + state.travelMode); }).join('') : '<div class="empty">Nothing on file within reach. Widen the travel time or move the point.</div>';
      });
      return;
    }
    title.textContent = 'In view';
    if (map.getZoom() >= 9) { note.textContent = 'Everything on screen, largest first.'; body.innerHTML = places.slice(0, 20).map(function (p) { return item(p, p.city || p.st); }).join('') || '<div class="empty">Nothing here with these filters.</div>'; }
    else { note.textContent = 'Pan and zoom to change what is counted. Click a cluster to open it.'; body.innerHTML = '<div class="empty">Zoom in, or place yourself on the map, to list places.</div>'; }
  }

  /* controls */
  var lens = document.getElementById('lens'), lensMore = document.getElementById('lensMore');
  function ensureChip(name) {
    if (Array.prototype.some.call(lens.querySelectorAll('.chip'), function (x) { return x.getAttribute('data-a') === name; })) return;
    var b = document.createElement('button'); b.className = 'chip'; b.type = 'button'; b.setAttribute('data-a', name); b.textContent = name; lens.insertBefore(b, lensMore);
  }
  function paintLens() { Array.prototype.forEach.call(lens.querySelectorAll('.chip'), function (x) { var a = x.getAttribute('data-a') || ''; x.classList.toggle('on', a ? state.acts.indexOf(a) >= 0 : !state.acts.length); }); }
  function toggleAct(name) {
    if (!name) state.acts = [];
    else { var i = state.acts.indexOf(name); if (i >= 0) state.acts.splice(i, 1); else { state.acts.push(name); ensureChip(name); } }
    paintLens(); applyMode(); load();
  }
  lens.addEventListener('click', function (e) { var b = e.target.closest('button.chip'); if (b) toggleAct(b.getAttribute('data-a') || ''); });
  lensMore.addEventListener('change', function () { if (this.value) { toggleAct(this.value); this.value = ''; } });
  state.acts.forEach(ensureChip); paintLens();

  /* Managed by: a type-ahead over the thousands of stewards and the six levels. Each pick becomes a chip; chips are OR-ed. */
  var pick = document.getElementById('stwPick'), stwQ = document.getElementById('stwQ'), stwList = document.getElementById('stwList'), stwChips = document.getElementById('stwChips');
  var sugg = [], hi = -1, stwReq = 0, stwTimer = 0;
  function esc(s) { return String(s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }
  function stwColor(s) { return STW[s.type === 'level' ? s.key : s.level] || STW.other; }
  function stwHas(s) { return state.stw.some(function (x) { return stwKey(x) === stwKey(s); }); }
  function mark(label, q) { var i = q ? label.toLowerCase().indexOf(q.toLowerCase()) : -1; return i < 0 ? esc(label) : esc(label.slice(0, i)) + '<b>' + esc(label.slice(i, i + q.length)) + '</b>' + esc(label.slice(i + q.length)); }
  function chips() {
    stwChips.innerHTML = state.stw.map(function (s, i) { return '<span class="pchip"><i style="background:' + stwColor(s) + '"></i>' + esc(s.label) + (s.type === 'level' ? ' <small>all</small>' : '') + '<button type="button" data-i="' + i + '" aria-label="Remove ' + esc(s.label) + '">×</button></span>'; }).join('');
    stwQ.placeholder = state.stw.length ? 'Add another…' : 'Type a city, agency or level…';
  }
  function openList(on) { stwList.hidden = !on; stwQ.setAttribute('aria-expanded', on ? 'true' : 'false'); if (!on) { hi = -1; stwQ.removeAttribute('aria-activedescendant'); } }
  function suggest() {
    var q = stwQ.value.trim(), id = ++stwReq;
    fetch(RPc.rest + 'stewards?q=' + encodeURIComponent(q) + '&bbox=' + bbox(2)).then(function (r) { return r.json(); }).then(function (d) {
      if (id !== stwReq || document.activeElement !== stwQ) return;
      sugg = (d.items || []).filter(function (s) { return !stwHas(s); }); hi = -1;
      var html = '', grp = '';
      sugg.forEach(function (s, i) {
        var g = s.type === 'level' ? 'By level' : (s.here ? 'In this view' : 'Everywhere');
        if (g !== grp) { grp = g; html += '<li class="pick-grp" role="presentation">' + g + '</li>'; }
        var meta = s.type === 'level' ? 'every ' + s.label.toLowerCase() + ' steward' : (s.here ? s.here.toLocaleString('en-US') + ' in view · ' : '') + s.n.toLocaleString('en-US') + ' places';
        html += '<li role="option" id="stwOpt' + i + '" data-i="' + i + '" aria-selected="false"><i style="background:' + stwColor(s) + '"></i><span>' + mark(s.label, q) + '</span><small>' + meta + '</small></li>';
      });
      stwList.innerHTML = html || '<li class="pick-empty">' + (q ? 'No steward called “' + esc(q) + '”.' : 'Zoom in to see who manages places here.') + '</li>';
      openList(true);
    });
  }
  function move(d) {
    var opts = stwList.querySelectorAll('[role=option]'); if (!opts.length) return;
    hi = (hi + d + opts.length) % opts.length;
    Array.prototype.forEach.call(opts, function (o, i) { o.classList.toggle('hi', i === hi); o.setAttribute('aria-selected', i === hi ? 'true' : 'false'); });
    stwQ.setAttribute('aria-activedescendant', opts[hi].id); opts[hi].scrollIntoView({ block: 'nearest' });
  }
  function choose(i) { var s = sugg[i]; if (!s || stwHas(s)) return; state.stw.push(s); stwQ.value = ''; chips(); openList(false); load(); stwQ.focus(); }
  stwQ.addEventListener('input', function () { clearTimeout(stwTimer); stwTimer = setTimeout(suggest, 160); });
  stwQ.addEventListener('focus', function () { if (stwList.hidden) suggest(); });
  stwQ.addEventListener('keydown', function (e) {
    if (e.key === 'ArrowDown') { e.preventDefault(); if (stwList.hidden) suggest(); else move(1); }
    else if (e.key === 'ArrowUp') { e.preventDefault(); move(-1); }
    else if (e.key === 'Enter') { if (sugg.length) { e.preventDefault(); choose(hi >= 0 ? hi : 0); } }
    else if (e.key === 'Escape') { openList(false); }
    else if (e.key === 'Backspace' && !stwQ.value && state.stw.length) { state.stw.pop(); chips(); load(); }
  });
  stwList.addEventListener('mousedown', function (e) { e.preventDefault(); var li = e.target.closest('[role=option]'); if (li) choose(parseInt(li.getAttribute('data-i'), 10)); });
  stwChips.addEventListener('click', function (e) { var b = e.target.closest('button'); if (!b) return; state.stw.splice(parseInt(b.getAttribute('data-i'), 10), 1); chips(); load(); stwQ.focus(); });
  document.addEventListener('click', function (e) { if (!pick.contains(e.target)) openList(false); });
  chips();
  document.getElementById('minac').addEventListener('input', function () { var v = parseInt(this.value, 10); state.minAc = v === 0 ? 0 : Math.round(Math.pow(10, v / 25)); document.getElementById('minacLbl').textContent = state.minAc.toLocaleString('en-US') + ' acres and up'; load(); });
  document.getElementById('nophoto').addEventListener('change', function () { state.noPhoto = this.checked; load(); });
  document.getElementById('travel').addEventListener('click', function (e) { var b = e.target.closest('button'); if (!b) return; Array.prototype.forEach.call(this.querySelectorAll('button'), function (x) { x.classList.remove('on'); }); b.classList.add('on'); state.travelKm = parseFloat(b.getAttribute('data-km')); state.travelMode = b.getAttribute('data-mode'); drawMe(); panel(); });
  document.getElementById('placeMe').addEventListener('click', function () { state.placing = !state.placing; this.classList.toggle('on', state.placing); map.getCanvas().style.cursor = state.placing ? 'crosshair' : ''; if (!state.placing) { state.me = null; try { localStorage.removeItem('rp-me'); } catch (x) {} drawMe(); panel(); } });
  document.getElementById('locateMe').addEventListener('click', function () { var b = this; if (!navigator.geolocation) return; b.textContent = 'Locating…'; navigator.geolocation.getCurrentPosition(function (pos) { state.me = { lat: pos.coords.latitude, lng: pos.coords.longitude }; try { localStorage.setItem('rp-me', JSON.stringify(state.me)); } catch (x) {} b.textContent = '◎ Located'; map.easeTo({ center: [state.me.lng, state.me.lat], zoom: 13 }); drawMe(); panel(); }, function () { b.textContent = 'Location unavailable'; }); });
  document.getElementById('selArea').addEventListener('click', function () { state.selecting = !state.selecting; this.classList.toggle('on', state.selecting); if (!state.selecting) { state.sel = null; drawSel(); panel(); } });
  document.getElementById('shareView').addEventListener('click', function () { var c = map.getCenter(), u = RPc.home + 'atlas/?lat=' + c.lat.toFixed(5) + '&lng=' + c.lng.toFixed(5) + '&zoom=' + map.getZoom().toFixed(1) + actParam() + stwParam(); var b = this; (navigator.clipboard ? navigator.clipboard.writeText(u) : Promise.reject()).then(function () { b.textContent = '✓ Link copied'; }, function () { window.prompt('Copy this link', u); }); });
})();
