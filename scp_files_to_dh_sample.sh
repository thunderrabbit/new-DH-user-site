#!/bin/bash
#
# Watch this working copy and copy each SAVED file up to DreamHost.
#
# THIS IS NOT A DEPLOY SCRIPT. It copies individual files as you save them,
# one at a time, so you can edit locally and refresh the browser. It does not
# sync the tree, does not delete anything, and does not know about git.
#
# Copy this file to scp_files_to_<HOST>.sh, where <HOST> is the ssh Host you
# set as DEST below. Name them the same so a tired glance at the filename tells
# you which server the script points at. Those copies are gitignored.
#
#   cp scp_files_to_dh_sample.sh scp_files_to_bc.sh   # DEST="bc"
#   ./scp_files_to_bc.sh
#
# Run it in a terminal you leave open while you work.
#
# ---------------------------------------------------------------------------
# THREE THINGS THAT WILL BITE YOU
#
# 1. Files saved by rename are MISSED.
#    inotifywait fires on close_write. Editors and tools that write to a temp
#    file and mv it into place never trigger it -- the file changes on disk and
#    this script says nothing. `touch` the file to make it notice:
#
#        touch wwwroot/index.php
#
#    (Verified: a normal write fires, a `touch` fires, an `mv` does not.)
#
# 2. Parent directories must already exist on the server.
#    scp will not create them. Create a new directory over ssh before saving
#    the first file into it.
#
# 3. The first copy of a new site is not this script's job.
#    Bulk-copy the tree once (scp -r, excluding .git), then start the watcher
#    for the edit loop that follows.
# ---------------------------------------------------------------------------

set -euo pipefail

# An ssh alias from ~/.ssh/config -- keep the username and key there, not here.
# Name this file scp_files_to_${DEST}.sh to match.
DEST="example"

# Project root on the server, with a trailing slash. NOT the web root:
# this must be the directory that contains wwwroot/, classes/, prepend.php.
DEST_PATH="/home/example_user/example.com/"

if ! command -v inotifywait >/dev/null 2>&1; then
    echo "inotifywait not found. On Debian/Ubuntu: sudo apt install inotify-tools" >&2
    exit 1
fi

echo "Watching $(pwd)"
echo "  -> ${DEST}:${DEST_PATH}"
echo "Ctrl-C to stop. Remember: files saved by mv need a touch."

# --format '%w%f' emits a clean relative path like ./wwwroot/index.php,
# which scp appends to DEST_PATH to land in the matching place on the server.
inotifywait --quiet --monitor --recursive \
            --event close_write \
            --exclude '/\.git/' \
            --format '%w%f' \
            . \
| while IFS= read -r file; do
    printf '%s -> %s\n' "$file" "${DEST_PATH}${file}"
    scp -q "$file" "${DEST}:${DEST_PATH}${file}" \
        || echo "  FAILED (does the parent directory exist on the server?)" >&2
done
