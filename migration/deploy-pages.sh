#!/usr/bin/env bash
# Runs ON the staging server after the theme and plugin are unpacked: creates the pages that
# templates need, flushes rewrites and the edge cache, and prints checks.
set -e
cd ~/htdocs
mk() {
  if [ "$(wp post list --post_type=page --name="$1" --format=count)" = "0" ]; then
    ID=$(wp post create --post_type=page --post_status=publish --post_title="$2" --post_name="$1" --porcelain)
  else
    ID=$(wp post list --post_type=page --name="$1" --field=ID)
  fi
  [ -n "$3" ] && wp post meta update "$ID" _wp_page_template "$3" >/dev/null
  if [ -n "$4" ] && [ -f "$4" ]; then wp post update "$ID" --post_content="$(cat "$4")" >/dev/null; fi
  echo "page $1 -> $ID"
}
mk acre-counter "Acre Counter" page-acre-counter.php ""
mk blog-tags "Blog Tags" page-blog-tags.php ""
mk contact "Contact Us" "" ~/d6-import/text/contact-page.html
wp rewrite flush --hard >/dev/null 2>&1
wp edge-cache purge --domain=recplanet.wpcomstaging.com >/dev/null 2>&1
for u in "/acre-counter/" "/acre-counter/?country=us" "/acre-counter/?country=us&state=TX" "/blog-tags/" "/contact/"; do
  curl -sL -o /tmp/p.html "https://recplanet.wpcomstaging.com$u"
  echo "$u errs=$(grep -cE 'Fatal|Warning:' /tmp/p.html) h1=[$(grep -oE '<h1[^>]*>[^<]*' /tmp/p.html | head -1 | sed 's/<h1[^>]*>//')] form=$(grep -c 'wp-block-jetpack-contact-form' /tmp/p.html)"
done
for u in "/acreage" "/blogtags" "/map/node" "/contact-us"; do
  curl -s -o /dev/null -w "%{http_code} %{redirect_url} <- $u\n" "https://recplanet.wpcomstaging.com$u"
done
curl -sL -o /tmp/h.html "https://recplanet.wpcomstaging.com/?nocache=$RANDOM"
echo "home: surprise-in-hero=$(grep -c '>Surprise me<' /tmp/h.html) dice-in-menu=$(grep -c 'class="dice"' /tmp/h.html) tabbar-world=$(grep -c '>World</span>' /tmp/h.html)"
echo "parks: $(wp post list --post_type=rp_park --format=count)"
