"""Rewrite a phpMyAdmin dump of the Drupal 6 tables so every table gets a prefix (default d6_)
and can be loaded into the WordPress database next to the wp_ tables.

Usage: python migration/prefix_dump.py migration/admin_test.sql migration/d6.sql [d6_]
"""
import re
import sys

src, dst = sys.argv[1], sys.argv[2]
prefix = sys.argv[3] if len(sys.argv) > 3 else 'd6_'

stmt = re.compile(r'^(CREATE TABLE(?: IF NOT EXISTS)?|INSERT INTO|ALTER TABLE|DROP TABLE(?: IF EXISTS)?|LOCK TABLES|TRUNCATE TABLE|REPLACE INTO)\s+`([^`]+)`')
comment = re.compile(r'^(-- (?:Table structure for table|Dumping data for table|Indexes for table|AUTO_INCREMENT for table|Indexes for dumped tables|AUTO_INCREMENT for dumped tables)\s*)`([^`]+)`')
n = 0
tables = set()
with open(src, encoding='utf-8', errors='surrogateescape') as fi, open(dst, 'w', encoding='utf-8', errors='surrogateescape', newline='\n') as fo:
    for line in fi:
        m = stmt.match(line)
        if m and not m.group(2).startswith(prefix):
            line = line[:m.start(2)] + prefix + line[m.start(2):]
            tables.add(prefix + m.group(2))
            n += 1
        else:
            c = comment.match(line)
            if c:
                line = line[:c.start(2)] + prefix + line[c.start(2):]
        # WordPress.com's database wants InnoDB; the Drupal tables are MyISAM.
        line = line.replace('ENGINE=MyISAM', 'ENGINE=InnoDB')
        # Drop any FULLTEXT keys, which InnoDB on older servers may reject and we never use.
        if re.match(r'^\s*FULLTEXT KEY', line):
            continue
        fo.write(line)
print(f'{n} statements prefixed; {len(tables)} tables:', ' '.join(sorted(tables)))
