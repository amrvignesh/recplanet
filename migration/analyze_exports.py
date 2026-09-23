"""Read the phpMyAdmin SQL exports of queries A, B and C and print what they tell us."""
import collections
import re
import sys
from analyze_parks import parse_values

COLS = ['section', 'a', 'b', 'c', 'd', 'e', 'f', 'g', 'h']


def load(path):
    rows = []
    text = open(path, encoding='utf-8', errors='replace').read()
    for m in re.finditer(r'INSERT INTO\s+\(`section`[^)]*\)\s+VALUES\s*(.*?);\s*$', text, re.S | re.M):
        body = m.group(1)
        # split "(...),(...)" at top level
        depth = 0
        start = None
        in_str = False
        i = 0
        while i < len(body):
            ch = body[i]
            if in_str:
                if ch == chr(92):
                    i += 2
                    continue
                if ch == chr(39):
                    in_str = False
            else:
                if ch == chr(39):
                    in_str = True
                elif ch == '(':
                    if depth == 0:
                        start = i + 1
                    depth += 1
                elif ch == ')':
                    depth -= 1
                    if depth == 0 and start is not None:
                        vals = parse_values(body[start:i])
                        if len(vals) == len(COLS):
                            rows.append(dict(zip(COLS, vals)))
                        start = None
            i += 1
    return rows


def by(rows, sec):
    return [r for r in rows if r['section'] == sec]


base = sys.argv[1] if len(sys.argv) > 1 else 'migration/query_exports'
A = load(f'{base}/query_a.sql')
B = load(f'{base}/query_b.sql')
C = load(f'{base}/query_c.sql')
for name, rows in [('A', A), ('B', B), ('C', C)]:
    print(name, collections.Counter(r['section'] for r in rows))

print('\n== ACTIVITY ALLOWED VALUES (from the field definition)')
raw = ''.join(r['b'] for r in sorted(by(A, 'activity_allowed'), key=lambda r: int(r['a'])))
m = re.search(r'allowed_values";s:\d+:"(.*?)";', raw, re.S)
if m:
    vals = [v for v in m.group(1).replace('\\n', '\n').split('\n') if v.strip()]
    print(f'  {len(vals)} values in stored order:')
    print('  ' + ' | '.join(vals))
else:
    print('  (allowed_values not found; raw start:)', raw[:300])

print('\n== CONTEST PHOTOS')
cr = by(A, 'contest_row')
print(f'  {len(cr)} photos; with file: {sum(1 for r in cr if r["e"])}; with star rating: {sum(1 for r in cr if r["f"])}; with winning_voter: {sum(1 for r in cr if r["g"])}')
years = collections.Counter(r['d'][:4] for r in cr)
print('  by year:', sorted(years.items()))
authors = collections.Counter(r['c'] for r in cr)
print('  top photographers:', authors.most_common(8))
flickr = sum(1 for r in cr if re.search(r'/\d{8,}_[0-9a-f]{10}_[a-z]\.jpg$', r['e'] or ''))
print(f'  Flickr-pattern filenames: {flickr}')
print('  sample:', [(r['b'], r['c'], r['e'].split('/')[-1]) for r in cr[:3]])
cc = by(A, 'contest_coding')
print(f'\n== CONTEST CODING field: {len(cc)} photos have text. Samples:')
for r in cc[:3]:
    print('   ', r['a'], '|', r['b'][:160].replace('\n', ' '))

print('\n== VOTES')
vt = by(A, 'vote_total')
print(f'  {len(vt)} photos voted on; total votes {sum(int(r["b"]) for r in vt):,}; top 8:')
titles = {r['a']: r['b'] for r in cr}
for r in sorted(vt, key=lambda r: -int(r['b']))[:8]:
    print(f"    {int(r['b']):>6} votes  avg {r['c']:>5}  voters {r['d']:>3}  {r['e'][:10]}..{r['f'][:10]}  {titles.get(r['a'], '(nid ' + r['a'] + ')')}")
vs = by(A, 'vote_sample')
print('  sample values:', [(r['d'], r['e'], r['f'], r['g']) for r in vs[:5]])
print('  votes by year:', sorted(collections.Counter(r['e'][:4] for r in vt).items()))

ct = by(A, 'contest_tag')
print(f'\n== CONTEST TAGS: {len(ct)} tags; top 15:', [(r['b'], r['c']) for r in ct[:15]])

up = by(A, 'upload_pic')
print(f'\n== UPLOAD PARK PICTURE: {len(up)} rows; with park: {sum(1 for r in up if r["d"])}; with file: {sum(1 for r in up if r["f"])}')
print('  sample:', [(r['b'], r['e'], r['f'].split('/')[-1]) for r in up[:4]])

print('\n== BROKEN ALIASES')
ab = by(B, 'alias_broken')
print(f'  {len(ab)} aliases contain "["; sample:')
for r in ab[:6]:
    print(f"    /{r['c']}  <- {r['b']}  {r['d']}")
print('  patterns:', collections.Counter(re.sub(r'/[^/]+$', '/…', r['c']) for r in ab).most_common(5))

ac = by(B, 'alias_category')
print(f'\n== CATEGORY ALIASES sample ({len(ac)}):')
for r in ac[:6]:
    print(f"    /{r['b']}  =  {r['c']}")

nw = by(B, 'nodewords')
print('\n== NODEWORDS (meta tags):', [(r['a'], r['b'], r['c']) for r in nw])

print('\n== BLOG')
bl = by(C, 'blog_row')
print(f'  {len(bl)} posts; with image: {sum(1 for r in bl if r["g"])}; with tags: {sum(1 for r in bl if r["f"])}; authors: {collections.Counter(r["c"] for r in bl).most_common(6)}')
print('  url shapes:', collections.Counter('depth ' + str(r['e'].count('/') + 1) if r['e'] else 'none' for r in bl))
print('  sample:', [(r['b'][:40], r['e'], r['f'][:50]) for r in bl[:4]])

print('\n== FORUM')
for r in by(C, 'forum_row'):
    print(f"  [{r['a']}] {r['b']}  by {r['c']} {r['d'][:10]}")
    if r['b'].lower().startswith(('photo rules', 'park')):
        print('     ', re.sub(r'<[^>]+>', ' ', r['e'])[:700])

print('\n== PAGES')
for r in by(C, 'page_row'):
    body = re.sub(r'<[^>]+>', ' ', (r['d'] or '') + (r['e'] or '') + (r['f'] or ''))
    body = re.sub(r'\s+', ' ', body).strip()
    print(f"  [{r['a']}] {r['b']}  /{r['c']}  ({r['g']} chars)")
    if r['b'] in ('About Us', 'Congrats'):
        print('     ', body[:1500])
