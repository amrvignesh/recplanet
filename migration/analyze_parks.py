"""Parse the full park export (phpMyAdmin SQL dump of query 3) and print the
statistics that the combined query 2 was meant to return, without touching the server.

Usage:  python migration/analyze_parks.py migration/samples/03-parks-full.sql
"""
import collections
import pickle
import re
import sys

COLS = ['nid', 'title', 'park_name', 'status', 'created', 'changed', 'author', 'url_path',
        'acreage', 'ownership', 'website', 'activities', 'park_tags', 'first_image', 'loc_name',
        'street', 'city', 'province', 'postal_code', 'country', 'latitude', 'longitude',
        'body_len', 'description_len', 'body_start', 'description_start']

BACKSLASH = chr(92)
QUOTE = chr(39)


def parse_values(s):
    out = []
    i = 0
    n = len(s)
    while i < n:
        c = s[i]
        if c in ' ,':
            i += 1
            continue
        if c == QUOTE:
            i += 1
            buf = []
            while i < n:
                ch = s[i]
                if ch == BACKSLASH:
                    buf.append(s[i + 1])
                    i += 2
                    continue
                if ch == QUOTE:
                    if i + 1 < n and s[i + 1] == QUOTE:
                        buf.append(QUOTE)
                        i += 2
                        continue
                    i += 1
                    break
                buf.append(ch)
                i += 1
            out.append(''.join(buf))
            continue
        m = re.match(r'NULL|-?\d+(\.\d+)?', s[i:])
        if not m:
            raise ValueError(s[i:i + 40])
        tok = m.group(0)
        i += len(tok)
        out.append(None if tok == 'NULL' else (float(tok) if '.' in tok else int(tok)))
    return out


def load(path):
    rows = []
    bad = 0
    with open(path, encoding='utf-8', errors='replace') as f:
        for line in f:
            if not line.startswith('INSERT INTO'):
                continue
            j = line.index('VALUES(') + 7
            body = line[j:].rstrip()
            if not body.endswith(');'):
                bad += 1
                continue
            try:
                v = parse_values(body[:-2])
            except Exception:
                bad += 1
                continue
            if len(v) != len(COLS):
                bad += 1
                continue
            rows.append(dict(zip(COLS, v)))
    return rows, bad


def top(counter, k=25):
    for key, n in counter.most_common(k):
        print(f'  {n:>7}  {key}')


def main():
    path = sys.argv[1]
    rows, bad = load(path)
    print(f'rows parsed: {len(rows)}   unparseable: {bad}')
    pickle.dump(rows, open(path + '.pkl', 'wb'))

    pub = [r for r in rows if r['status'] == 1]
    print(f'published: {len(pub)}')

    # --- acreage ---------------------------------------------------------
    acres = [r['acreage'] for r in pub if r['acreage'] is not None]
    print('\n== ACREAGE')
    print(f'  parks with acreage: {len(acres)}   without: {len(pub) - len(acres)}')
    print(f'  SUM = {sum(acres):,.2f}')
    print(f'  MAX = {max(acres):,.2f}')
    zero = sum(1 for a in acres if a == 0)
    print(f'  zero-acre records: {zero}')
    print('  largest 15:')
    for r in sorted(pub, key=lambda r: r['acreage'] or 0, reverse=True)[:15]:
        print(f"    {r['acreage']:>16,.2f}  {r['title']}  ({r['province']}, {r['country']})  /{r['url_path']}")

    # --- activities ------------------------------------------------------
    act = collections.Counter()
    per_park = []
    for r in pub:
        if r['activities']:
            items = [a.strip() for a in r['activities'].split('|') if a.strip()]
            per_park.append(len(items))
            act.update(set(items))
        else:
            per_park.append(0)
    print('\n== ACTIVITIES')
    print(f'  distinct values: {len(act)}   parks with none: {per_park.count(0)}   avg per park: {sum(per_park)/len(per_park):.2f}')
    top(act, 200)

    # --- ownership -------------------------------------------------------
    own = collections.Counter((r['ownership'] or '').strip() for r in pub)
    print('\n== OWNERSHIP (top 60)')
    print(f'  distinct values: {len(own)}   blank: {own[""]}')
    top(own, 60)

    # --- text fields -----------------------------------------------------
    print('\n== TEXT')
    body = sum(1 for r in pub if (r['body_len'] or 0) > 0)
    desc = sum(1 for r in pub if (r['description_len'] or 0) > 0)
    both = sum(1 for r in pub if (r['body_len'] or 0) > 0 and (r['description_len'] or 0) > 0)
    neither = sum(1 for r in pub if not (r['body_len'] or 0) and not (r['description_len'] or 0))
    print(f'  with body: {body}   with description field: {desc}   both: {both}   neither: {neither}')
    name_diff = sum(1 for r in pub if r['park_name'] and r['park_name'] != r['title'])
    name_set = sum(1 for r in pub if r['park_name'])
    print(f'  park_name set: {name_set}   differs from title: {name_diff}')
    web = sum(1 for r in pub if r['website'])
    print(f'  with website: {web}')
    img = sum(1 for r in pub if r['first_image'])
    print(f'  with at least one image: {img}')
    styled = sum(1 for r in pub if r['body_start'] and 'style=' in r['body_start'])
    print(f'  body starts with inline-styled HTML: {styled}')

    # --- location --------------------------------------------------------
    print('\n== LOCATION')
    noloc = sum(1 for r in pub if r['latitude'] is None)
    print(f'  published parks with no location: {noloc}')
    zeroll = sum(1 for r in pub if r['latitude'] == 0 and r['longitude'] == 0)
    print(f'  lat/lon both 0: {zeroll}')
    nocity = sum(1 for r in pub if r['latitude'] is not None and not r['city'])
    nostate = sum(1 for r in pub if r['country'] == 'us' and not r['province'])
    print(f'  no city: {nocity}   US with no state: {nostate}')
    ctry = collections.Counter(r['country'] for r in pub if r['country'] is not None)
    print(f'  countries: {len(ctry)}')
    top(ctry, 12)

    # --- url shapes ------------------------------------------------------
    print('\n== URL PATHS')
    shape = collections.Counter()
    for r in pub:
        p = r['url_path']
        if not p:
            shape['(no alias)'] += 1
            continue
        segs = p.split('/')
        if '[' in p:
            shape['contains [token]'] += 1
        elif len(segs) == 3 and r['country'] == 'us':
            shape['us: state/city/slug'] += 1
        elif len(segs) == 3:
            shape['non-us: 3 segments'] += 1
        elif len(segs) == 2:
            shape['2 segments'] += 1
        elif len(segs) == 1:
            shape['1 segment'] += 1
        else:
            shape[f'{len(segs)} segments'] += 1
    top(shape)
    mismatch = 0
    for r in pub:
        p = r['url_path']
        if p and r['country'] == 'us' and r['province'] and len(p.split('/')) == 3:
            if p.split('/')[0].lower() != r['province'].lower():
                mismatch += 1
    print(f'  US aliases whose state segment differs from the location state: {mismatch}')
    print('  samples of 2-segment / 1-segment / token aliases:')
    shown = 0
    for r in pub:
        p = r['url_path'] or ''
        if p and (len(p.split('/')) < 3 or '[' in p):
            print(f"    /{p}   {r['title']}  ({r['city']}, {r['province']}, {r['country']})")
            shown += 1
            if shown >= 15:
                break

    # --- duplicates ------------------------------------------------------
    print('\n== DUPLICATES')
    titles = collections.Counter(r['title'] for r in pub)
    print(f'  distinct titles: {len(titles)}   duplicate-title rows: {len(pub) - len(titles)}')
    tc = collections.Counter((r['title'], r['city'], r['province']) for r in pub)
    dup_tc = sum(n - 1 for n in tc.values() if n > 1)
    print(f'  same title + city + state: {dup_tc} extra rows')
    ll = collections.Counter((r['latitude'], r['longitude']) for r in pub if r['latitude'] is not None)
    dup_ll = sum(n - 1 for n in ll.values() if n > 1)
    print(f'  same coordinates: {dup_ll} extra rows')
    print('  sample title+city+state duplicates:')
    for (t, c, s), n in [x for x in tc.most_common(8) if x[1] > 1]:
        print(f'    {n} x  {t}  ({c}, {s})')

    # --- park tags -------------------------------------------------------
    print('\n== PARK TAGS')
    tags = collections.Counter()
    facility = collections.Counter()
    ntag = []
    for r in pub:
        if r['park_tags']:
            items = [t.strip() for t in r['park_tags'].split('|') if t.strip()]
            ntag.append(len(items))
            tags.update(items)
            # facility = the tag with the city/state prefix stripped, when the tag starts with the city name
            for t in items:
                if r['city'] and t.lower().startswith(r['city'].lower()):
                    rest = t[len(r['city']):].strip()
                    facility[rest] += 1
        else:
            ntag.append(0)
    print(f'  distinct tags on published parks: {len(tags)}   parks with none: {ntag.count(0)}   avg per park: {sum(ntag)/len(ntag):.2f}')
    print('  facility words after stripping the city name (top 60):')
    top(facility, 60)

    # --- authors / dates -------------------------------------------------
    print('\n== AUTHORS')
    top(collections.Counter(r['author'] for r in pub), 10)
    years = collections.Counter((r['created'] or '')[:4] for r in pub)
    print('\n== PARKS CREATED PER YEAR')
    for y in sorted(years):
        print(f'  {y}: {years[y]}')


if __name__ == '__main__':
    main()
