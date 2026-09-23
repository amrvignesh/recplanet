#!/usr/bin/env bash
# Drive the park import on staging in foreground batches over SSH, because WordPress.com
# kills detached processes when the session ends. Idempotent: re-running is safe.
# Usage: bash migration/import-batches.sh [start_after_nid]
KEY=~/.ssh/recplanet_wpcom
HOST=recplanet.wordpress.com@ssh.wp.com
SSH="ssh -i $KEY -o IdentitiesOnly=yes -o BatchMode=yes -o ServerAliveInterval=60 $HOST"
LOG="${LOG:-$HOME/recplanet-import.log}"
LAST="${1:-0}"
BATCH="${BATCH:-1500}"
echo "start $(date) after=$LAST batch=$BATCH" >> "$LOG"
while true; do
  OUT=$($SSH "cd ~/htdocs && wp recplanet import parks --after=$LAST --limit=$BATCH --files=/home/150723239/d6-import/files 2>&1 | grep -E 'Success|Error|Fatal' | tail -2")
  echo "$(date +%H:%M) after=$LAST :: $OUT" >> "$LOG"
  N=$(echo "$OUT" | grep -oE '"last_nid":[0-9]+' | grep -oE '[0-9]+$')
  if [ -z "$N" ] || [ "$N" = "$LAST" ]; then echo "done at nid $LAST $(date)" >> "$LOG"; break; fi
  LAST=$N
done
$SSH "cd ~/htdocs && wp recplanet rebuild 2>&1 | tail -1 && wp recplanet report && wp edge-cache purge --domain=recplanet.wpcomstaging.com >/dev/null 2>&1" >> "$LOG" 2>&1
echo "ALL DONE $(date)" >> "$LOG"
