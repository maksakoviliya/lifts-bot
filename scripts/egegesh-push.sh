#!/usr/bin/env bash
# Fetches egegesh pages from a machine that can reach the site and pushes them to the bot,
# because the prod hosting is blocked by egegesh. Needs only bash and curl.
#
# Run by hand:
#   EGEGESH_PUSH_URL=https://<bot-host>/egegesh/push EGEGESH_PUSH_TOKEN=<token> ./scripts/egegesh-push.sh
# Or from cron every 15 minutes (put the two variables at the top of the crontab):
#   */15 * * * * /path/to/egegesh-push.sh >> /var/log/egegesh-push.log 2>&1
#
# Prod decides what to fetch next (see EgegeshPushController), so all parsing stays in the bot.
set -euo pipefail

: "${EGEGESH_PUSH_URL:?set EGEGESH_PUSH_URL, e.g. https://<bot-host>/egegesh/push}"
: "${EGEGESH_PUSH_TOKEN:?set EGEGESH_PUSH_TOKEN, the same value as in the bot .env}"

UA='Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0 Safari/537.36'
tmp=$(mktemp -d)
trap 'rm -rf "$tmp"' EXIT

fetch() { curl -fsS -m 20 -A "$UA" -o "$2" "$1"; }
push() { curl -fsS -m 60 -H "Authorization: Bearer $EGEGESH_PUSH_TOKEN" -H 'Accept: application/json' "$@" "$EGEGESH_PUSH_URL"; }

fetch https://egegesh.ru/info "$tmp/info.html"
fetch https://egegesh.ru/screens "$tmp/screens.html"
# lifts + camera list; the bot answers with "alias page_url" per camera
push -F "info=@$tmp/info.html" -F "screens=@$tmp/screens.html" > "$tmp/cams"

cams=0 images=0 failed=0
while read -r alias page; do
    # alias goes into a -F value: anything unusual could turn it into a file upload
    [[ $alias =~ ^[a-z0-9-]+$ ]] || continue

    # the bot answers with the screenshot url only when it doesn't have that picture yet
    if ! fetch "$page" "$tmp/page.html" || ! image=$(push -F "alias=$alias" -F "page=@$tmp/page.html"); then
        echo "cam $alias: page failed" >&2
        failed=$((failed + 1))
        continue
    fi
    cams=$((cams + 1))

    [[ -n $image ]] || continue
    if fetch "$image" "$tmp/image.jpg" && push -F "alias=$alias" -F "image=@$tmp/image.jpg" > /dev/null; then
        images=$((images + 1))
    else
        echo "cam $alias: image failed" >&2
        failed=$((failed + 1))
    fi
done < "$tmp/cams"

echo "$(date '+%F %T') lifts ok, cams $cams, new images $images, failed $failed"
[[ $failed -eq 0 ]]
