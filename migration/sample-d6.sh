#!/usr/bin/env bash
# Pull a small, self-consistent sample of the RecPlanet Drupal 6 database.
#
# Produces a folder (and a .tar.gz of it) containing:
#   00-table-sizes.tsv        every table with MB on disk and row estimate  -> shows what makes it 10 GB
#   01-node-types.tsv         how many nodes of each type (park, blog, photo ...)
#   02-schema.sql             CREATE TABLE for every table, no data
#   03-config.sql             full rows of the small config tables (field definitions, vocabularies, roles ...)
#   04-sample.sql             N newest nodes of every type, plus every row that hangs off them
#   05-users.tsv              50 users with password hashes left out
#   06-location-sample.tsv    plain-text look at the location table
#
# Usage on the server (over SSH):
#   chmod +x sample-d6.sh
#   ./sample-d6.sh DBNAME DBUSER [DBHOST]
#   (you are asked for the password once)
#
# Optional: SAMPLE_N=100 ./sample-d6.sh ...   (rows per node type, default 200)
#
# Nothing here writes to the database. Everything is SELECT / mysqldump.

set -euo pipefail

DB="${1:?usage: $0 DBNAME DBUSER [DBHOST]}"
DBUSER="${2:?usage: $0 DBNAME DBUSER [DBHOST]}"
DBHOST="${3:-localhost}"
N="${SAMPLE_N:-200}"

read -r -s -p "MySQL password for $DBUSER: " MYSQL_PWD; echo
export MYSQL_PWD

OUT="recplanet-sample-$(date +%Y%m%d-%H%M)"
mkdir -p "$OUT"

Q()  { mysql -h "$DBHOST" -u "$DBUSER" -N -B "$DB" -e "$1"; }          # bare query, tab separated, no header
QH() { mysql -h "$DBHOST" -u "$DBUSER" -B "$DB" -e "$1"; }             # with header row
DUMP() { mysqldump -h "$DBHOST" -u "$DBUSER" --single-transaction --skip-lock-tables --quick --no-create-info --skip-triggers "$DB" "$@"; }

echo "== 00 table sizes"
QH "SELECT table_name,
           ROUND((data_length+index_length)/1024/1024,1) AS mb,
           table_rows AS approx_rows,
           engine
    FROM information_schema.tables
    WHERE table_schema='$DB'
    ORDER BY (data_length+index_length) DESC" > "$OUT/00-table-sizes.tsv"

echo "== 01 node types"
QH "SELECT type, COUNT(*) AS nodes, SUM(status=1) AS published, MIN(FROM_UNIXTIME(created)) AS first, MAX(FROM_UNIXTIME(changed)) AS last_changed
    FROM node GROUP BY type ORDER BY nodes DESC" > "$OUT/01-node-types.tsv"

echo "== 02 schema (no data)"
mysqldump -h "$DBHOST" -u "$DBUSER" --no-data --skip-triggers --skip-add-drop-table "$DB" > "$OUT/02-schema.sql"

echo "== 03 config tables, complete"
# Small tables that describe the structure. Missing ones are skipped silently (module may not be installed).
CONFIG_TABLES="node_type content_node_field content_node_field_instance content_group content_group_fields
vocabulary vocabulary_node_types term_data term_hierarchy term_synonym term_relation
role permission filter_formats filters blocks menu_custom imagecache_preset imagecache_action
location_instance_types location_countries fivestar_widget votingapi_tag"
: > "$OUT/03-config.sql"
for t in $CONFIG_TABLES; do
  if Q "SHOW TABLES LIKE '$t'" | grep -qx "$t"; then
    DUMP "$t" >> "$OUT/03-config.sql"
  fi
done
# Modules that are enabled, and the site variables (module settings), which are small.
QH "SELECT name, type, status, schema_version FROM system WHERE type='module' AND status=1 ORDER BY name" > "$OUT/03a-enabled-modules.tsv"
DUMP variable >> "$OUT/03-config.sql" || true

echo "== 04 linked sample: $N newest nodes per type"
NIDS=$(Q "SELECT type FROM node GROUP BY type" | while read -r t; do
         Q "SELECT nid FROM node WHERE type='$t' ORDER BY nid DESC LIMIT $N"
       done | paste -sd, -)
NID_WHERE="nid IN ($NIDS)"

: > "$OUT/04-sample.sql"
DUMP --where="$NID_WHERE" node node_revisions >> "$OUT/04-sample.sql"

# Every CCK table: per-type tables (content_type_*) and shared multi-value tables (content_field_*).
for t in $(Q "SHOW TABLES LIKE 'content\\_type\\_%'; SHOW TABLES LIKE 'content\\_field\\_%'"); do
  DUMP --where="$NID_WHERE" "$t" >> "$OUT/04-sample.sql"
done

# Things attached to a node by nid.
for t in term_node comments upload node_comment_statistics node_access book forum image_attach; do
  if Q "SHOW TABLES LIKE '$t'" | grep -qx "$t"; then
    DUMP --where="$NID_WHERE" "$t" >> "$OUT/04-sample.sql"
  fi
done

# Path aliases: src is 'node/123'.
DUMP --where="src IN (SELECT CONCAT('node/',nid) FROM node WHERE $NID_WHERE)" url_alias >> "$OUT/04-sample.sql"

# Location module: instance links nid -> lid, then the location rows themselves.
if Q "SHOW TABLES LIKE 'location_instance'" | grep -qx location_instance; then
  DUMP --where="$NID_WHERE" location_instance >> "$OUT/04-sample.sql"
  DUMP --where="lid IN (SELECT lid FROM location_instance WHERE $NID_WHERE)" location >> "$OUT/04-sample.sql"
fi

# Files referenced by these nodes (upload table and any CCK filefield/imagefield column named fid).
if Q "SHOW TABLES LIKE 'files'" | grep -qx files; then
  DUMP --where="fid IN (SELECT fid FROM upload WHERE $NID_WHERE)" files >> "$OUT/04-sample.sql" || true
fi

# Votes (fivestar / votingapi) and their cached averages for these nodes.
for t in votingapi_vote votingapi_cache; do
  if Q "SHOW TABLES LIKE '$t'" | grep -qx "$t"; then
    DUMP --where="content_type='node' AND content_id IN ($NIDS)" "$t" >> "$OUT/04-sample.sql"
  fi
done

echo "== 05 users (no password hashes)"
QH "SELECT uid, name, mail, FROM_UNIXTIME(created) AS created, FROM_UNIXTIME(access) AS last_access, status, timezone
    FROM users WHERE uid > 0 ORDER BY uid LIMIT 50" > "$OUT/05-users.tsv"
QH "SELECT r.name AS role, COUNT(*) AS users FROM users_roles ur JOIN role r ON r.rid=ur.rid GROUP BY r.name" > "$OUT/05a-users-per-role.tsv"
QH "SELECT COUNT(*) AS total_users, SUM(status=1) AS active, SUM(access > UNIX_TIMESTAMP()-365*86400) AS seen_last_year FROM users WHERE uid>0" > "$OUT/05b-user-counts.tsv"

echo "== 06 a readable look at locations"
if Q "SHOW TABLES LIKE 'location'" | grep -qx location; then
  QH "SELECT * FROM location ORDER BY lid DESC LIMIT 30" > "$OUT/06-location-sample.tsv"
  QH "SELECT country, COUNT(*) AS n FROM location GROUP BY country ORDER BY n DESC LIMIT 60" > "$OUT/06a-locations-per-country.tsv"
  QH "SELECT province, COUNT(*) AS n FROM location WHERE country='us' GROUP BY province ORDER BY n DESC" > "$OUT/06b-us-locations-per-state.tsv"
fi

echo "== 07 the acreage figure, straight from the database"
# Column names guessed from the rendered pages; the schema dump will show the real ones if these fail.
for t in $(Q "SHOW TABLES LIKE 'content\\_type\\_%'; SHOW TABLES LIKE 'content\\_field\\_%'"); do
  col=$(Q "SELECT column_name FROM information_schema.columns WHERE table_schema='$DB' AND table_name='$t' AND column_name LIKE '%acreage%' LIMIT 1")
  if [ -n "$col" ]; then
    QH "SELECT '$t.$col' AS source, COUNT(*) AS rows_with_value,
               SUM(CAST(REPLACE(\`$col\`,',','') AS DECIMAL(20,2))) AS total_acres
        FROM \`$t\` WHERE \`$col\` IS NOT NULL AND \`$col\` <> ''" >> "$OUT/07-acreage-total.tsv" || true
  fi
done

tar czf "$OUT.tar.gz" "$OUT"
echo
echo "Done. Sample folder: $OUT   Archive: $OUT.tar.gz"
du -sh "$OUT" "$OUT.tar.gz"
