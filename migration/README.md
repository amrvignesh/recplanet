# Sampling the old Drupal 6 database

The live database is about 10 GB. Before downloading all of it, we pull a few megabytes that are enough to understand the structure: the complete schema, the field and vocabulary definitions, a size report per table, and a few hundred linked rows for every content type.

There are two ways to do it. Use whichever you have access to.

## Option A: phpMyAdmin (no shell needed)

Three steps. Save everything into `migration/samples/` here (git-ignored).

**1. Export the schema, no data.**
Select the database in the left column, then: Export tab → Export method: *Custom* → Format: *SQL* → under "Format-specific options" choose *Structure* (not "Structure and data") → Go. Save the file as `02-schema.sql`. It is small.

**2. Run query 1.**
Open `migration/phpmyadmin-queries.sql`. Paste the whole of QUERY 1 into the SQL tab and press Go. It returns one table with nine text columns; the first column, `section`, says what each row is (sizes, node types, fields, terms, users ...). Under the result click *Export*, choose *CSV*, make sure "Dump all rows" is selected, and save it as `01-overview.csv`.

**2b. Run queries 2 and 3.**
In the `cck_column` rows of query 1, find the park table and its acreage, ownership and activities columns. If they differ from `content_type_park` and `field_pacreage_value` etc., change the lines marked `<<` in queries 2 and 3, then run each and export as `02-parks.csv` and `03-acreage.csv`.

**3. Export a data sample of the park tables.**
Export tab → *Custom* → tick only these tables: `node`, `node_revisions`, `content_type_park` (or whatever 07 showed), `location`, `location_instance`, `url_alias`, `term_node` → Format: *SQL* → under "Rows" choose *Dump some row(s)*, Number of rows: `500`, Row to begin at: `0` → Go. Save as `04-sample.sql`.

Then tell me the files are in `migration/samples/`.

## Option B: shell script over SSH

```
scp migration/sample-d6.sh USER@recplanet.com:~/
ssh USER@recplanet.com
chmod +x sample-d6.sh
./sample-d6.sh DBNAME DBUSER
```

It asks for the database password once. Database name, user and host are in `sites/default/settings.php` on the server, in the `$db_url` line:

```
$db_url = 'mysqli://DBUSER:PASSWORD@localhost/DBNAME';
```

The script only reads. It runs `SELECT` and `mysqldump` and never writes to the database.

## What comes back

A folder named `recplanet-sample-<date>` and a `.tar.gz` of it. Copy the archive down with `scp` and put it in `migration/samples/` here (that folder is git-ignored).

| File | What it tells us |
|---|---|
| `00-table-sizes.tsv` | Which tables make up the 10 GB. Expect `cache_*`, `watchdog`, `search_index`, `accesslog` and `sessions` near the top, none of which migrate. |
| `01-node-types.tsv` | How many parks, blog posts, photos and so on, and when each type was last edited. |
| `02-schema.sql` | Every table's real column names. |
| `03-config.sql` | Field definitions (`content_node_field*`), vocabularies and terms, roles and permissions, module settings. |
| `03a-enabled-modules.tsv` | Which Drupal modules are switched on. |
| `04-sample.sql` | 200 newest nodes of each type with their field rows, terms, comments, aliases, locations, files and votes. |
| `05-users.tsv`, `05a`, `05b` | A look at members without password hashes, plus counts by role and how many were active in the last year. |
| `06-location-sample.tsv`, `06a`, `06b` | Raw location rows, and how the places spread across countries and US states. |
| `07-acreage-total.tsv` | The acreage column summed straight from the database, to compare with the 4,744,111,591.10 shown on the site. |

## If something is missing

Tables the script expects but the site does not have are skipped. If `07-acreage-total.tsv` is empty, the acreage column is not named with "acreage"; the schema dump will show what it is called.

To take fewer or more rows per type:

```
SAMPLE_N=50 ./sample-d6.sh DBNAME DBUSER
```
