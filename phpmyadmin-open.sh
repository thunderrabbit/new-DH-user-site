#!/bin/bash
# Open this site's database in DBeaver, connected.
# (It used to sign in to DreamHost's phpMyAdmin, which is retired; the file
# keeps its name so nobody has to learn a new one.)
#
#   phpmyadmin-open.sh            open DBeaver, connected
#   phpmyadmin-open.sh --mysql    open the mysql client instead
#   phpmyadmin-open.sh --close    close the ssh tunnel
#
# Committed on purpose: this file holds no secret. The credentials are read
# at run time from the server's site_config.php over ssh (or, on a server not
# yet converted, its old classes/Config/Config.php), using the same DEST and
# DEST_PATH that deploy_to_dh.sh beside it has filled in, so a site needs no
# further setup for this to work. The template repo itself keeps
# DEST="example" committed and refuses to run, like deploy_to_dh.sh.
#
# The password is never on a command line: DBeaver and mysql each read it
# from a mode-600 file in $XDG_RUNTIME_DIR (memory, not disk) that is removed
# again. The connection goes through an ssh tunnel to the DreamHost server:
# the database only accepts hosts named in the panel's "Allowable Hosts", and
# a laptop's public address changes, but the server is already allowed.
set -e
umask 077

cd "$(dirname "$0")"

MODE="${1:-}"
RUNTIME="${XDG_RUNTIME_DIR:-/run/user/$(id -u)}"
# How long DBeaver gets to start and read the password file.
VARS_SECONDS=90

# Read, don't source: deploy_to_dh.sh pushes and pulls when run.
DEST=$(sed -n 's/^DEST="\([^"]*\)".*/\1/p' deploy_to_dh.sh | head -1)
DEST_PATH=$(sed -n 's/^DEST_PATH="\([^"]*\)".*/\1/p' deploy_to_dh.sh | head -1)

if [ -z "$DEST" ] || [ "$DEST" = "example" ] || [ -z "$DEST_PATH" ]; then
    echo "Fill in DEST and DEST_PATH in deploy_to_dh.sh first." >&2
    exit 1
fi

# DEST_PATH is the project root on the server, relative to the user's home.
SITE=$(basename "$DEST_PATH")

# Each site gets its own local port, worked out from its name, so two sites
# can be open at once and a tunnel is only ever reused for its own site.
LOCAL_PORT=$((13306 + $(printf '%s' "$DEST/$DEST_PATH" | cksum | cut -d' ' -f1) % 1000))
TUNNEL="127.0.0.1:$LOCAL_PORT"

if [ "$MODE" = "--close" ]; then
    pkill -f -- "-L $TUNNEL:" && echo "Tunnel closed." || echo "No tunnel was open."
    exit 0
fi

if [ -n "$MODE" ] && [ "$MODE" != "--mysql" ]; then
    echo "Usage: $0 [--mysql | --close]" >&2
    exit 1
fi

if [ -z "$MODE" ]; then
    DBEAVER=$(command -v dbeaver-ce || command -v dbeaver || true)
    if [ -z "$DBEAVER" ]; then
        echo "DBeaver is not installed (no dbeaver-ce or dbeaver on PATH)." >&2
        exit 1
    fi
fi

echo "Fetching credentials from $DEST..." >&2

# PHP on the server reads the config, so quoting and escapes in it are PHP's
# to understand, not sed's. site_config.php returns an array; the old file is
# a class with a property per value. Each value comes back base64 on its own
# line, then the name of the file it came from. The ssh Host already carries
# the username and key.
mapfile -t ANSWER < <(ssh "$DEST" "php -- '$DEST_PATH'" << 'PHP'
<?php
$root = $argv[1];
if (is_file("$root/site_config.php")) {
    $from = 'site_config.php';
    $config = require "$root/$from";
} elseif (is_file("$root/classes/Config/Config.php")) {
    $from = 'classes/Config/Config.php';
    require "$root/$from";
    $config = get_object_vars(new \Config\Config());
} else {
    // Not STDERR: PHP does not define it for a script read from stdin.
    file_put_contents('php://stderr', "No site_config.php and no classes/Config/Config.php in $root\n");
    exit(1);
}
foreach (['dbHost', 'dbUser', 'dbPass', 'dbName'] as $key) {
    echo base64_encode((string) ($config[$key] ?? '')), "\n";
}
echo $from, "\n";
PHP
)

DB_HOST=$(printf '%s' "${ANSWER[0]}" | base64 -d)
DB_USER=$(printf '%s' "${ANSWER[1]}" | base64 -d)
DB_PASS=$(printf '%s' "${ANSWER[2]}" | base64 -d)
DB_NAME=$(printf '%s' "${ANSWER[3]}" | base64 -d)
FROM="${ANSWER[4]}"
unset ANSWER

if [ -z "$DB_HOST" ] || [ -z "$DB_USER" ] || [ -z "$DB_PASS" ] || [ -z "$DB_NAME" ]; then
    echo "Error: could not read dbHost, dbUser, dbPass and dbName from $DEST:$DEST_PATH/${FROM:-site_config.php}" >&2
    exit 1
fi

# The tunnel: this machine's port leads to the database, as seen from the
# DreamHost server. One left open by an earlier run is used again.
if pgrep -f -- "-L $TUNNEL:$DB_HOST:3306" > /dev/null; then
    echo "Using the tunnel already open on $TUNNEL." >&2
else
    ssh -f -N -o ExitOnForwardFailure=yes -L "$TUNNEL:$DB_HOST:3306" "$DEST"
    echo "Tunnel open on $TUNNEL (close it with: $0 --close)." >&2
fi

# Both file formats take a backslash as an escape, so double any in the password.
ESCAPED_PASS=${DB_PASS//\\/\\\\}

if [ "$MODE" = "--mysql" ]; then
    SECRETS=$(mktemp "$RUNTIME/db-open-XXXXXX.cnf")
    trap 'rm -f "$SECRETS"' EXIT
    printf '[client]\nuser="%s"\npassword="%s"\n' "$DB_USER" "${ESCAPED_PASS//\"/\\\"}" > "$SECRETS"
    mysql --defaults-extra-file="$SECRETS" -A -h 127.0.0.1 -P "$LOCAL_PORT" "$DB_NAME"
    exit 0
fi

# DBeaver replaces ${db_open_password} in -con with the value in the file
# that -vars names, so `ps` shows the file's path and not the password.
SECRETS=$(mktemp "$RUNTIME/db-open-XXXXXX.properties")
printf 'db_open_password=%s\n' "$ESCAPED_PASS" > "$SECRETS"

# save=false: a temporary connection, which DBeaver forgets when it closes.
CONNECTION="driver=mysql|host=127.0.0.1|port=$LOCAL_PORT|database=$DB_NAME|user=$DB_USER"
CONNECTION="$CONNECTION|password=\${db_open_password}|name=$SITE|save=false|connect=true"

setsid "$DBEAVER" -vars "$SECRETS" -con "$CONNECTION" > /dev/null 2>&1 &

# Remove the password file once DBeaver has had time to read it.
( sleep "$VARS_SECONDS"; rm -f "$SECRETS" ) > /dev/null 2>&1 &
disown -a

echo "DBeaver is opening $DB_NAME (credentials from $FROM). The password file is removed in $VARS_SECONDS seconds."
