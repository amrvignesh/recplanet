"""Fill the prototype template with real Irving, TX data and the brand images."""
import base64
import json
import sys

tpl, out, city_json, logo, bg = sys.argv[1:6]
h = open(tpl, encoding='utf-8').read()
parks = json.load(open(city_json, encoding='utf-8'))

logo_uri = 'data:image/png;base64,' + base64.b64encode(open(logo, 'rb').read()).decode()
bg_uri = 'data:image/jpeg;base64,' + base64.b64encode(open(bg, 'rb').read()).decode()

# --- city list rows -------------------------------------------------------
rows = []
for p in parks:
    acres = f"{p['acres']:,.2f}" if p['acres'] is not None else '<span class="ph">not recorded</span>'
    acts = ''.join(f'<span>{a}</span>' for a in p['activities'][:4])
    more = f'<span class="more">+{len(p["activities"]) - 4}</span>' if len(p['activities']) > 4 else ''
    rows.append(
        f'<a class="prow" href="#/park" data-nid="{p["nid"]}">'
        f'<span class="pname">{p["title"]}</span>'
        f'<span class="pacts">{acts}{more}</span>'
        f'<span class="pac num">{acres}</span></a>')
h = h.replace('__IRVING_ROWS__', '\n'.join(rows))

# --- city map: project lat/lon into a 900 x 520 box ------------------------
lats = [p['lat'] for p in parks if p['lat']]
lons = [p['lon'] for p in parks if p['lon']]
la0, la1, lo0, lo1 = min(lats), max(lats), min(lons), max(lons)
W, H, PAD = 900, 520, 40
dots = []
for p in parks:
    if not p['lat']:
        continue
    x = PAD + (p['lon'] - lo0) / (lo1 - lo0) * (W - 2 * PAD)
    y = H - PAD - (p['lat'] - la0) / (la1 - la0) * (H - 2 * PAD)
    r = 5 + min(14, (p['acres'] or 1) ** 0.5 * 1.1)
    dots.append(f'<g class="dot"><circle cx="{x:.0f}" cy="{y:.0f}" r="{r:.0f}" fill="#88B500" stroke="#fff" stroke-width="2"/>'
                f'<title>{p["title"]} · {p["acres"] if p["acres"] is not None else "?"} acres</title></g>')
h = h.replace('__IRVING_DOTS__', '\n'.join(dots))

# --- near-me list: every Irving park by walking time from Fritz Park -------
import math
fritz = next(p for p in parks if p['title'] == 'Fritz Park')
def km(a, b):
    R = 6371.0
    la1, lo1, la2, lo2 = map(math.radians, [a['lat'], a['lon'], b['lat'], b['lon']])
    d = math.sin((la2 - la1) / 2) ** 2 + math.cos(la1) * math.cos(la2) * math.sin((lo2 - lo1) / 2) ** 2
    return 2 * R * math.asin(math.sqrt(d))
near = sorted([p for p in parks if p['lat'] and p['title'] != 'Fritz Park'], key=lambda p: km(fritz, p))[:8]
nrows = []
for p in near:
    mins = round(km(fritz, p) * 1.25 / 5 * 60)   # road factor 1.25, 5 km/h
    acres = f"{p['acres']:,.2f} ac" if p['acres'] is not None else 'acres not recorded'
    acts = ', '.join(p['activities'][:3]) or 'no activities recorded'
    nrows.append(f'<a href="#/park"><span class="m"><span class="w">{mins} min walk</span><span class="a">{acres}</span></span>'
                 f'<b>{p["title"]}</b><small>{p["owner"]} · {acts}</small></a>')
h = h.replace('__NEAR_ROWS__', '\n'.join(nrows))

def nice(d):
    from datetime import datetime
    return datetime.strptime(d[:10], '%Y-%m-%d').strftime('%-d %B %Y') if d else '[date]'
try:
    h = h.replace('__FRITZ_CREATED__', nice(fritz['created'])).replace('__FRITZ_CHANGED__', nice(fritz['changed']))
except ValueError:
    from datetime import datetime
    h = h.replace('__FRITZ_CREATED__', datetime.strptime(fritz['created'][:10], '%Y-%m-%d').strftime('%d %B %Y').lstrip('0'))
    h = h.replace('__FRITZ_CHANGED__', datetime.strptime(fritz['changed'][:10], '%Y-%m-%d').strftime('%d %B %Y').lstrip('0'))

mapjson = open('migration/samples/map-TX.json', encoding='utf-8').read().replace('</', '<' + chr(92) + '/')
h = h.replace('__MAP_DATA__', mapjson)
h = h.replace('__LOGO__', logo_uri).replace('__BGTOP__', bg_uri)
open(out, 'w', encoding='utf-8').write(h)
print('written', out, len(h.encode()), 'bytes')
