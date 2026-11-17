/* World Parks: a live map of everything on file. Zoomed out, one bubble per country and per US state with its true count;
   zoomed in past SPLIT, every place from the plugin's index through /places, as on the atlas. */
(function () {
  'use strict';
  var RPc = window.RP || {}, el = document.getElementById('worldLive');
  if (!el || typeof maplibregl === 'undefined') return;
  var bubbles = []; try { bubbles = JSON.parse(el.getAttribute('data-bubbles') || '[]'); } catch (e) {}
  var SPLIT = 5, STATES = 2.6, BB = ['bb-c', 'bb-u', 'bb-s'], atlasLink = document.getElementById('worldAtlas');

  var map = new maplibregl.Map({
    container: el,
    style: 'https://tiles.openfreemap.org/styles/liberty',
    center: [-40, 30],
    zoom: 1.4,
    attributionControl: false,
    cooperativeGestures: true   /* the page scrolls past the map; Ctrl + wheel or two fingers move the map itself */
  });
  map.addControl(new maplibregl.NavigationControl({ showCompass: false }), 'top-left');
  map.addControl(new maplibregl.AttributionControl({ compact: true }), 'bottom-right');

  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }
  function short(n) { return n >= 10000 ? Math.round(n / 1000) + 'k' : n >= 1000 ? (n / 1000).toFixed(1) + 'k' : String(n); }
  var popup = new maplibregl.Popup({ closeButton: false, closeOnClick: false, offset: 12 });

  var lastReq = 0;
  function load() {
    if (map.getZoom() < SPLIT || !map.getSource('parks')) return;
    var b = map.getBounds(), id = ++lastReq;
    var box = [b.getWest(), b.getSouth(), b.getEast(), b.getNorth()].map(function (v) { return v.toFixed(4); }).join(',');
    fetch(RPc.rest + 'places?bbox=' + box + '&limit=3000').then(function (r) { return r.json(); }).then(function (d) {
      if (id !== lastReq || !d.places || !map.getSource('parks')) return;
      map.getSource('parks').setData({ type: 'FeatureCollection', features: d.places.map(function (p) { return { type: 'Feature', geometry: { type: 'Point', coordinates: [p.lng, p.lat] }, properties: { t: p.t, city: p.city, st: p.st, ac: p.ac === null ? '' : p.ac, url: p.url } }; }) });
    }).catch(function () {});
  }
  function link() {
    if (!atlasLink) return;
    var c = map.getCenter();
    atlasLink.href = RPc.home + 'atlas/?lat=' + c.lat.toFixed(5) + '&lng=' + c.lng.toFixed(5) + '&zoom=' + Math.max(2, map.getZoom()).toFixed(1);
  }

  map.on('load', function () {
    map.addSource('bubbles', { type: 'geojson', data: { type: 'FeatureCollection', features: bubbles.map(function (b) { return { type: 'Feature', geometry: { type: 'Point', coordinates: [b.lng, b.lat] }, properties: { k: b.k, n: b.n, c: b.c, a: b.a, label: short(b.c) } }; }) } });
    /* countries until the places take over; the United States as one bubble until STATES, then one per state */
    var radius = ['+', 9, ['*', 0.3, ['sqrt', ['get', 'c']]]];
    [['c', 0, SPLIT], ['u', 0, STATES], ['s', STATES, SPLIT]].forEach(function (g) {
      var f = ['==', ['get', 'k'], g[0]], z = { source: 'bubbles', minzoom: g[1], maxzoom: g[2], filter: f };
      map.addLayer(Object.assign({ id: 'bb-glow-' + g[0], type: 'circle', paint: { 'circle-color': '#88B500', 'circle-opacity': 0.2, 'circle-radius': ['+', 7, radius] } }, z));
      map.addLayer(Object.assign({ id: 'bb-' + g[0], type: 'circle', paint: { 'circle-color': g[0] === 'c' ? '#88B500' : '#E85305', 'circle-radius': radius, 'circle-stroke-color': '#fff', 'circle-stroke-width': 2 } }, z));
      map.addLayer(Object.assign({ id: 'bb-n-' + g[0], type: 'symbol', layout: { 'text-field': ['get', 'label'], 'text-size': 12, 'text-font': ['Noto Sans Bold'], 'text-allow-overlap': true }, paint: { 'text-color': g[0] === 'c' ? '#14211A' : '#fff' } }, z));
    });

    map.addSource('parks', { type: 'geojson', data: { type: 'FeatureCollection', features: [] }, cluster: true, clusterRadius: 48, clusterMaxZoom: 13 });
    map.addLayer({ id: 'cl-glow', type: 'circle', source: 'parks', minzoom: SPLIT, filter: ['has', 'point_count'], paint: { 'circle-color': '#88B500', 'circle-opacity': 0.18, 'circle-radius': ['+', 22, ['*', 3, ['sqrt', ['get', 'point_count']]]] } });
    map.addLayer({ id: 'cl', type: 'circle', source: 'parks', minzoom: SPLIT, filter: ['has', 'point_count'], paint: { 'circle-color': '#88B500', 'circle-radius': ['+', 14, ['*', 3, ['sqrt', ['get', 'point_count']]]], 'circle-stroke-color': '#fff', 'circle-stroke-width': 2 } });
    map.addLayer({ id: 'cl-n', type: 'symbol', source: 'parks', minzoom: SPLIT, filter: ['has', 'point_count'], layout: { 'text-field': ['get', 'point_count_abbreviated'], 'text-size': 13, 'text-font': ['Noto Sans Bold'] }, paint: { 'text-color': '#14211A' } });
    map.addLayer({ id: 'pt', type: 'circle', source: 'parks', minzoom: SPLIT, filter: ['!', ['has', 'point_count']], paint: { 'circle-color': '#E85305', 'circle-radius': 6, 'circle-stroke-color': '#fff', 'circle-stroke-width': 1.5 } });

    /* a bubble opens into its places; a cluster opens into its dots; a dot opens its park */
    BB.forEach(function (id) {
      map.on('click', id, function (e) { popup.remove(); map.easeTo({ center: e.features[0].geometry.coordinates, zoom: id === 'bb-u' ? STATES + 0.6 : SPLIT + 1 }); });
      map.on('mousemove', id, function (e) {
        var p = e.features[0].properties;
        popup.setLngLat(e.features[0].geometry.coordinates).setHTML('<b>' + esc(p.n) + '</b><br><span>' + Number(p.c).toLocaleString('en-US') + ' places · ' + esc(p.a) + ' acres</span><br><span>Tap to zoom in</span>').addTo(map);
      });
    });
    map.on('click', 'cl', function (e) { var f = e.features[0]; map.getSource('parks').getClusterExpansionZoom(f.properties.cluster_id).then(function (z) { map.easeTo({ center: f.geometry.coordinates, zoom: z }); }); });
    map.on('click', 'pt', function (e) { window.location.href = e.features[0].properties.url; });
    BB.concat(['cl', 'pt']).forEach(function (id) {
      map.on('mouseenter', id, function () { map.getCanvas().style.cursor = 'pointer'; });
      map.on('mouseleave', id, function () { map.getCanvas().style.cursor = ''; popup.remove(); });
    });
    map.on('mousemove', 'pt', function (e) {
      var p = e.features[0].properties;
      popup.setLngLat(e.features[0].geometry.coordinates).setHTML('<b>' + esc(p.t) + '</b><br><span>' + esc(p.city || p.st) + ' · ' + (p.ac === '' ? 'acres not recorded' : Number(p.ac).toLocaleString('en-US') + ' acres') + '</span>').addTo(map);
    });
    map.on('moveend', function () { load(); link(); });
    load(); link();
  });
})();
