"""Pull one real city and its parks out of the parsed export, for use in the design prototype."""
import collections
import json
import pickle
import re
import sys

rows = pickle.load(open(sys.argv[1], 'rb'))
state, city = sys.argv[2], sys.argv[3]
seen = set()
parks = []
for r in rows:
    if r['nid'] in seen or r['status'] != 1:
        continue
    seen.add(r['nid'])
    if (r['province'] or '') == state and (r['city'] or '').lower() == city.lower():
        parks.append(r)


def clean(html):
    if not html:
        return ''
    t = re.sub(r'<[^>]+>', ' ', html)
    t = re.sub(r'&nbsp;', ' ', t)
    t = re.sub(r'&#39;', "'", t)
    t = re.sub(r'&quot;', '"', t)
    t = re.sub(r'&amp;', '&', t)
    return re.sub(r'\s+', ' ', t).strip()


out = []
for r in sorted(parks, key=lambda r: -(r['acreage'] or 0)):
    out.append({
        'nid': r['nid'], 'title': r['title'], 'acres': r['acreage'], 'owner': r['ownership'],
        'activities': [a.strip() for a in (r['activities'] or '').split('|') if a.strip()],
        'tags': [t.strip() for t in (r['park_tags'] or '').split('|') if t.strip()],
        'street': r['street'], 'postal': r['postal_code'], 'lat': r['latitude'], 'lon': r['longitude'],
        'url': r['url_path'], 'website': r['website'], 'image': r['first_image'],
        'created': r['created'], 'changed': r['changed'], 'body': clean(r['body_start']),
    })
total = sum(p['acres'] or 0 for p in out)
acts = collections.Counter(a for p in out for a in p['activities'])
print(f'{city}, {state}: {len(out)} parks, {total:,.2f} acres')
print('activities:', acts.most_common())
print()
for p in out:
    print(f"{p['acres']!s:>10}  {p['title']:<45} {', '.join(p['activities'])}")
    print(f"            {p['street']}, {p['postal']}  {p['lat']},{p['lon']}  /{p['url']}  img={bool(p['image'])} web={bool(p['website'])}")
    print(f"            {p['body'][:260]}")
json.dump(out, open(f'migration/samples/city-{state}-{city.lower()}.json', 'w'), indent=1)

# state roll-up
st = [r for r in rows if r['status'] == 1 and (r['province'] or '') == state]
st_uniq = {r['nid']: r for r in st}.values()
print(f'\n{state}: {len(st_uniq)} parks, {sum(r["acreage"] or 0 for r in st_uniq):,.2f} acres')
fish = sum(1 for r in st_uniq if 'Fishing' in (r['activities'] or ''))
fish_ac = sum(r['acreage'] or 0 for r in st_uniq if 'Fishing' in (r['activities'] or ''))
print(f'{state} parks with Fishing: {fish}, {fish_ac:,.2f} acres')
cities = collections.Counter((r['city'] or '') for r in st_uniq)
print('top cities:', cities.most_common(12))
us = {r['nid']: r for r in rows if r['status'] == 1 and r['country'] == 'us'}.values()
print(f'US: {len(us)} parks, {sum(r["acreage"] or 0 for r in us):,.2f} acres')
