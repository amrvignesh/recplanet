"""Second pass over the parsed export: dedupe by nid, recompute the counter, and derive
the facility vocabulary hidden in the Park Tags."""
import collections
import pickle
import sys

rows = pickle.load(open(sys.argv[1], 'rb'))
by_nid = collections.OrderedDict()
multi_loc = 0
for r in rows:
    if r['nid'] in by_nid:
        multi_loc += 1
        continue
    by_nid[r['nid']] = r
parks = [r for r in by_nid.values() if r['status'] == 1]
print(f'unique nodes: {len(by_nid)}   published: {len(parks)}   rows that were second locations of the same park: {multi_loc}')

acres = [r['acreage'] for r in parks if r['acreage'] is not None]
total = sum(acres)
ant = sum(r['acreage'] or 0 for r in parks if r['country'] == 'aq')
us = sum(r['acreage'] or 0 for r in parks if r['country'] == 'us')
print('\n== ACRE COUNTER, deduped')
print(f'  total            {total:>20,.2f}   (site shows 4,744,111,591.10)')
print(f'  Antarctica       {ant:>20,.2f}   ({ant/total*100:.1f}% of the total)')
print(f'  United States    {us:>20,.2f}')
print(f'  rest of world    {total-ant-us:>20,.2f}')
blm = sum(r['acreage'] or 0 for r in parks if 'BLM' in (r['title'] or ''))
res = sum(r['acreage'] or 0 for r in parks if 'Reservation' in (r['title'] or ''))
print(f'  of US: BLM land rows {blm:,.0f}; "Reservations" rows {res:,.0f}')
big = [r for r in parks if (r['acreage'] or 0) >= 1_000_000]
print(f'  records of 1,000,000 acres or more: {len(big)}, holding {sum(r["acreage"] for r in big):,.0f} acres')
small = [r for r in parks if r['acreage'] is not None and r['acreage'] < 100]
print(f'  records under 100 acres: {len(small)}, holding {sum(r["acreage"] for r in small):,.0f} acres')

print('\n== DUPLICATES, deduped by nid')
tc = collections.Counter((r['title'], r['city'], r['province']) for r in parks)
dups = [(k, n) for k, n in tc.items() if n > 1]
print(f'  same title + city + state: {sum(n-1 for _, n in dups)} extra rows across {len(dups)} groups')
for (t, c, s), n in sorted(dups, key=lambda x: -x[1])[:10]:
    print(f'    {n} x  {t}  ({c}, {s})')
ll = collections.Counter((r['latitude'], r['longitude']) for r in parks if r['latitude'])
print(f'  same coordinates: {sum(n-1 for n in ll.values() if n > 1)} extra rows')

# Facility vocabulary: strip "<City> <State name>" from the front of each tag.
STATES = {
 'AL':'Alabama','AK':'Alaska','AZ':'Arizona','AR':'Arkansas','CA':'California','CO':'Colorado','CT':'Connecticut',
 'DE':'Delaware','FL':'Florida','GA':'Georgia','HI':'Hawaii','ID':'Idaho','IL':'Illinois','IN':'Indiana','IA':'Iowa',
 'KS':'Kansas','KY':'Kentucky','LA':'Louisiana','ME':'Maine','MD':'Maryland','MA':'Massachusetts','MI':'Michigan',
 'MN':'Minnesota','MS':'Mississippi','MO':'Missouri','MT':'Montana','NE':'Nebraska','NV':'Nevada','NH':'New Hampshire',
 'NJ':'New Jersey','NM':'New Mexico','NY':'New York','NC':'North Carolina','ND':'North Dakota','OH':'Ohio','OK':'Oklahoma',
 'OR':'Oregon','PA':'Pennsylvania','RI':'Rhode Island','SC':'South Carolina','SD':'South Dakota','TN':'Tennessee',
 'TX':'Texas','UT':'Utah','VT':'Vermont','VA':'Virginia','WA':'Washington','WV':'West Virginia','WI':'Wisconsin',
 'WY':'Wyoming','DC':'DC','PR':'Puerto Rico'}
fac = collections.Counter()
unparsed = collections.Counter()
for r in parks:
    if not r['park_tags']:
        continue
    state = STATES.get(r['province'] or '', '')
    for t in [x.strip() for x in r['park_tags'].split('|') if x.strip()]:
        tl = t.lower()
        rest = None
        if r['city'] and tl.startswith(r['city'].lower()):
            rest = t[len(r['city']):].strip()
            if state and rest.lower().startswith(state.lower()):
                rest = rest[len(state):].strip()
        elif state and tl.startswith(state.lower()):
            rest = t[len(state):].strip()
        if rest:
            fac[rest.lower()] += 1
        else:
            unparsed[t] += 1
print(f'\n== FACILITY TYPES derived from Park Tags: {len(fac)} distinct, top 80')
for k, n in fac.most_common(80):
    print(f'  {n:>6}  {k}')
print(f'\n  tags that did not start with the city or state ({sum(unparsed.values())} uses), top 20:')
for k, n in unparsed.most_common(20):
    print(f'  {n:>6}  {k}')

print('\n== OWNERSHIP shape')
own_kind = collections.Counter()
for r in parks:
    o = (r['ownership'] or '').strip()
    if not o: own_kind['blank'] += 1
    elif o.startswith('City of') or o.startswith('Town of') or o.startswith('Village of'): own_kind['City/Town/Village of ...'] += 1
    elif o.startswith('State of'): own_kind['State of ...'] += 1
    elif o.endswith('County') or ' County' in o: own_kind['... County'] += 1
    elif 'United States' in o or 'Federal' in o or 'U.S.' in o: own_kind['Federal'] += 1
    else: own_kind['other'] += 1
for k, n in own_kind.most_common():
    print(f'  {n:>6}  {k}')
