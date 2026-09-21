"""Compact map dataset for one state, for the prototype atlas.
Row: [nid, title, lat, lon, acres, year, activityBits, stewardLevel, city, hasImage]"""
import collections
import json
import pickle
import re
import sys

rows = pickle.load(open(sys.argv[1], 'rb'))
state = sys.argv[2]
ACTS = ['Playground', 'Picnicking', 'Basketball', 'Restrooms', 'Baseball', 'Hiking', 'Softball', 'Soccer', 'Tennis',
        'Fishing', 'Walking', 'Biking', 'Swimming', 'Kayaking', 'Canoeing', 'Volleyball', 'Camping', 'Boating',
        'Sprayground', 'Hunting', 'Horse Back Riding', 'Dog-Park', 'Horseshoes', 'Cross-Country Skiing', 'Skate Park',
        'Disc-Golf', 'Golf', 'Water-Skiing', 'Handball', 'Ice-Skating', 'Roller-hockey', 'Snowmobiling', 'Sailboarding',
        'Sailing', 'Backpacking', 'Bocce', 'Climbing', 'Archery Range', 'Surfing', 'Scuba', 'Skiing', 'Rafting',
        'Caving', 'Hang-Gliding']
AI = {a: i for i, a in enumerate(ACTS)}


def steward(o):
    o = (o or '').strip()
    if not o:
        return 4
    if re.match(r'^(City|Town|Village) of', o):
        return 0
    if 'County' in o:
        return 1
    if o.startswith('State of'):
        return 2
    if 'United States' in o or 'Federal' in o or 'U.S.' in o or 'National' in o:
        return 3
    return 4


seen = set()
out = []
for r in rows:
    if r['nid'] in seen or r['status'] != 1 or (r['province'] or '') != state or not r['latitude']:
        continue
    seen.add(r['nid'])
    bits = 0
    for a in (r['activities'] or '').split('|'):
        a = a.strip()
        if a in AI:
            bits |= 1 << AI[a]
    out.append([r['nid'], r['title'], round(r['latitude'], 5), round(r['longitude'], 5),
                round(r['acreage'], 2) if r['acreage'] is not None else None,
                int((r['created'] or '2010')[:4]), bits, steward(r['ownership']), r['city'] or '',
                1 if r['first_image'] else 0])

# city centroids for labels, biggest first
cities = collections.defaultdict(list)
for p in out:
    if p[8]:
        cities[p[8]].append(p)
labels = []
for c, ps in sorted(cities.items(), key=lambda kv: -len(kv[1]))[:40]:
    labels.append([c, round(sum(p[2] for p in ps) / len(ps), 4), round(sum(p[3] for p in ps) / len(ps), 4), len(ps)])

json.dump({'state': state, 'acts': ACTS, 'parks': out, 'cities': labels}, open(f'migration/samples/map-{state}.json', 'w'), separators=(',', ':'))
print(len(out), 'parks;', len(labels), 'city labels; bytes:', len(json.dumps({'parks': out}, separators=(',', ':'))))
print('years:', sorted(collections.Counter(p[5] for p in out).items()))
print('stewards:', collections.Counter(p[7] for p in out))
