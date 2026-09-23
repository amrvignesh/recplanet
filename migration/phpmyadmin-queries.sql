-- RecPlanet Drupal 6: understanding the data through phpMyAdmin
--
-- Query 1 (overview) and query 3 (all parks, wide) have been run.
-- What is left is split into THREE short queries, A, B and C. Run each on its own in
-- phpMyAdmin > SQL > Go, then Export the result as CSV ("Dump all rows") and save it under
-- migration/samples/ with the name shown. Each returns the same nine text columns
-- (section, a..h) as query 1. Every join here runs on an index; nothing scans a big table
-- against another big table, which is what hung the server before.
--
-- Everything only reads.


-- =====================================================================================
-- QUERY A: the contest.                                        -> 4a-contest.csv
--   activity_allowed  slice_no, text          (the stored allowed-values list, 1500-char slices)
--   contest_row       nid, title, author, created, filepath, star_rating, winning_voter, comment
--   contest_coding    nid, first 300 chars of "contest coding"
--   vote_total        nid, votes, avg_percent, voters, first_vote, last_vote
--   vote_sample       vote_id, nid, uid, value, value_type, tag, source, voted   (20 newest)
--   contest_tag       tid, tag, photos
--   upload_pic        nid, uploader, created, park_nid, park_title, filepath
-- =====================================================================================

SELECT * FROM (

  (SELECT 'activity_allowed' AS section, '1' AS a, CAST(SUBSTRING(global_settings, 1, 1500) AS CHAR) AS b,
          '' AS c, '' AS d, '' AS e, '' AS f, '' AS g, '' AS h
   FROM content_node_field WHERE field_name = 'field_pactivities')
  UNION ALL
  (SELECT 'activity_allowed', '2', CAST(SUBSTRING(global_settings, 1501, 1500) AS CHAR), '', '', '', '', '', ''
   FROM content_node_field WHERE field_name = 'field_pactivities')
  UNION ALL
  (SELECT 'activity_allowed', '3', CAST(SUBSTRING(global_settings, 3001, 1500) AS CHAR), '', '', '', '', '', ''
   FROM content_node_field WHERE field_name = 'field_pactivities')
  UNION ALL
  (SELECT 'activity_allowed', '4', CAST(SUBSTRING(global_settings, 4501, 1500) AS CHAR), '', '', '', '', '', ''
   FROM content_node_field WHERE field_name = 'field_pactivities')

  UNION ALL

  (SELECT 'contest_row', CAST(n.nid AS CHAR), CAST(n.title AS CHAR), CAST(IFNULL(u.name, '') AS CHAR),
          CAST(FROM_UNIXTIME(n.created) AS CHAR), CAST(IFNULL(f.filepath, '') AS CHAR),
          CAST(IFNULL(c.field_contest_star_rating_value, '') AS CHAR),
          CAST(IFNULL(c.field_winning_voter_value, '') AS CHAR),
          CAST(IFNULL(LEFT(c.field_contest_comment_value, 200), '') AS CHAR)
   FROM node n
   JOIN content_type_photo_contest c ON c.vid = n.vid
   LEFT JOIN files f ON f.fid = c.field_photo_contest_fid
   LEFT JOIN users u ON u.uid = n.uid
   WHERE n.type = 'photo_contest'
   ORDER BY n.nid DESC)

  UNION ALL

  (SELECT 'contest_coding', CAST(n.nid AS CHAR), CAST(LEFT(c.field_contest_codings_value, 300) AS CHAR),
          '', '', '', '', '', ''
   FROM node n
   JOIN content_type_photo_contest c ON c.vid = n.vid
   WHERE n.type = 'photo_contest'
     AND c.field_contest_codings_value IS NOT NULL AND c.field_contest_codings_value <> ''
   ORDER BY n.nid DESC)

  UNION ALL

  (SELECT 'vote_total', CAST(v.nid AS CHAR), CAST(v.votes AS CHAR), CAST(v.avg_percent AS CHAR),
          CAST(v.voters AS CHAR), CAST(FROM_UNIXTIME(v.first_ts) AS CHAR), CAST(FROM_UNIXTIME(v.last_ts) AS CHAR), '', ''
   FROM (SELECT content_id AS nid, COUNT(*) AS votes, ROUND(AVG(value), 1) AS avg_percent,
                COUNT(DISTINCT uid) AS voters, MIN(timestamp) AS first_ts, MAX(timestamp) AS last_ts
         FROM votingapi_vote
         WHERE content_type = 'node'
         GROUP BY content_id) v
   ORDER BY v.votes DESC)

  UNION ALL

  (SELECT 'vote_sample', CAST(vote_id AS CHAR), CAST(content_id AS CHAR), CAST(uid AS CHAR), CAST(value AS CHAR),
          CAST(value_type AS CHAR), CAST(tag AS CHAR), CAST(IFNULL(vote_source, '') AS CHAR),
          CAST(FROM_UNIXTIME(timestamp) AS CHAR)
   FROM votingapi_vote
   ORDER BY vote_id DESC LIMIT 20)

  UNION ALL

  (SELECT 'contest_tag', CAST(ct.tid AS CHAR), CAST(ct.name AS CHAR), CAST(ct.photos AS CHAR), '', '', '', '', ''
   FROM (SELECT t.tid, t.name, COUNT(tn.nid) AS photos
         FROM term_data t
         LEFT JOIN term_node tn ON tn.tid = t.tid
         WHERE t.vid = 6
         GROUP BY t.tid, t.name) ct
   ORDER BY ct.photos DESC)

  UNION ALL

  (SELECT 'upload_pic', CAST(n.nid AS CHAR), CAST(IFNULL(u.name, '') AS CHAR), CAST(FROM_UNIXTIME(n.created) AS CHAR),
          CAST(IFNULL(c.field_park_pic_node_nid, '') AS CHAR), CAST(IFNULL(pk.title, '') AS CHAR),
          CAST(IFNULL(f.filepath, '') AS CHAR), '', ''
   FROM node n
   JOIN content_type_upload_park_picture c ON c.vid = n.vid
   LEFT JOIN node pk ON pk.nid = c.field_park_pic_node_nid
   LEFT JOIN users u ON u.uid = n.uid
   LEFT JOIN content_field_park_picture pp ON pp.vid = n.vid
   LEFT JOIN files f ON f.fid = pp.field_park_picture_fid
   WHERE n.type = 'upload_park_picture'
   ORDER BY n.nid DESC)

) AS contest;


-- =====================================================================================
-- QUERY B: the old URLs.                                        -> 4b-aliases.csv
--   alias_broken     pid, src, dst, title            (the 75 aliases containing "[")
--   alias_category   src, dst, term                  (30 samples of the 50,309 term aliases)
--   nodewords        type, name, rows                (hand-written meta tags, counted)
-- The joins start from the small side and look up url_alias by its indexed src column.
-- =====================================================================================

SELECT * FROM (

  (SELECT 'alias_broken' AS section, CAST(a.pid AS CHAR) AS a, CAST(a.src AS CHAR) AS b, CAST(a.dst AS CHAR) AS c,
          CAST(IFNULL(n.title, '') AS CHAR) AS d, '' AS e, '' AS f, '' AS g, '' AS h
   FROM url_alias a
   LEFT JOIN node n ON n.nid = CAST(SUBSTRING(a.src, 6) AS UNSIGNED) AND a.src LIKE 'node/%'
   WHERE a.dst LIKE '%[%')

  UNION ALL

  (SELECT 'alias_category', CAST(a.src AS CHAR), CAST(a.dst AS CHAR), CAST(t.name AS CHAR), '', '', '', '', ''
   FROM (SELECT tid, name FROM term_data WHERE vid = 5 ORDER BY tid DESC LIMIT 30) t
   JOIN url_alias a ON a.src = CONCAT('taxonomy/term/', t.tid))

  UNION ALL

  (SELECT 'nodewords', CAST(nw.type AS CHAR), CAST(nw.name AS CHAR), CAST(nw.rows_ AS CHAR), '', '', '', '', ''
   FROM (SELECT type, name, COUNT(*) AS rows_ FROM nodewords GROUP BY type, name) nw)

) AS aliases;


-- =====================================================================================
-- QUERY C: blog, forum and pages.                               -> 4c-text.csv
--   blog_row    nid, title, author, created, url, tags, image, body_len     (all 80)
--   forum_row   nid, title, author, created, first 1500 chars               (all 11)
--   page_row    nid, title, url, body 1-1500, body 1501-3000, body 3001-4500, body_len   (all 11)
-- =====================================================================================

SELECT * FROM (

  (SELECT 'blog_row' AS section, CAST(n.nid AS CHAR) AS a, CAST(n.title AS CHAR) AS b, CAST(IFNULL(u.name, '') AS CHAR) AS c,
          CAST(FROM_UNIXTIME(n.created) AS CHAR) AS d, CAST(IFNULL(al.dst, '') AS CHAR) AS e,
          CAST(IFNULL((SELECT GROUP_CONCAT(t.name SEPARATOR ' | ')
                       FROM term_node tn JOIN term_data t ON t.tid = tn.tid
                       WHERE tn.vid = n.vid), '') AS CHAR) AS f,
          CAST(IFNULL(f.filepath, '') AS CHAR) AS g,
          CAST(LENGTH(r.body) AS CHAR) AS h
   FROM node n
   JOIN node_revisions r ON r.vid = n.vid
   LEFT JOIN users u ON u.uid = n.uid
   LEFT JOIN url_alias al ON al.src = CONCAT('node/', n.nid)
   LEFT JOIN content_field_blog_image bi ON bi.vid = n.vid AND bi.delta = 0
   LEFT JOIN files f ON f.fid = bi.field_blog_image_fid
   WHERE n.type = 'blogs'
   ORDER BY n.nid DESC)

  UNION ALL

  (SELECT 'forum_row', CAST(n.nid AS CHAR), CAST(n.title AS CHAR), CAST(IFNULL(u.name, '') AS CHAR),
          CAST(FROM_UNIXTIME(n.created) AS CHAR),
          CAST(REPLACE(REPLACE(LEFT(r.body, 1500), '\r', ''), '\n', ' ') AS CHAR), '', '', ''
   FROM node n
   JOIN node_revisions r ON r.vid = n.vid
   LEFT JOIN users u ON u.uid = n.uid
   WHERE n.type = 'forum'
   ORDER BY n.nid DESC)

  UNION ALL

  (SELECT 'page_row', CAST(n.nid AS CHAR), CAST(n.title AS CHAR), CAST(IFNULL(al.dst, '') AS CHAR),
          CAST(REPLACE(REPLACE(SUBSTRING(r.body, 1, 1500), '\r', ''), '\n', ' ') AS CHAR),
          CAST(REPLACE(REPLACE(SUBSTRING(r.body, 1501, 1500), '\r', ''), '\n', ' ') AS CHAR),
          CAST(REPLACE(REPLACE(SUBSTRING(r.body, 3001, 1500), '\r', ''), '\n', ' ') AS CHAR),
          CAST(LENGTH(r.body) AS CHAR), ''
   FROM node n
   JOIN node_revisions r ON r.vid = n.vid
   LEFT JOIN url_alias al ON al.src = CONCAT('node/', n.nid)
   WHERE n.type = 'page'
   ORDER BY n.nid DESC)

) AS textual;
