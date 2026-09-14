#!/usr/bin/env bash
#
# Create a new faculty website folder by copying this codebase and pointing
# it at one faculty's data in the shared database.
#
# Usage:
#   ./scripts/new-faculty-instance.sh <slug> "<Full Name>" <destination-dir>
#
# Example:
#   ./scripts/new-faculty-instance.sh fsi "Faculté des Sciences et Ingénierie" ../fsi
#
# This only copies files and edits .env. It does NOT create the database
# row for the faculty — run `php spark site:create` in the new folder
# (see docs/CREER_UN_SITE.md). The two just need to end up using the same `slug`.
set -euo pipefail
CALLER_CWD="$(pwd -P)"
cd "$(dirname "${BASH_SOURCE[0]}")"
source ./_lib.sh

reject_link_vendor "$@"

SLUG="${1:-}"
NAME="${2:-}"
DEST="${3:-}"

if [ -z "$SLUG" ] || [ -z "$NAME" ] || [ -z "$DEST" ]; then
  echo "Usage: $0 <slug> \"<Full Name>\" <destination-dir>" >&2
  exit 1
fi

if [ "${DEST#/}" = "$DEST" ]; then
  DEST="$CALLER_CWD/$DEST"
fi

echo "Creating faculty instance '$SLUG' at $DEST ..."
copy_instance "$DEST"

set_env_value "$DEST/.env" "app.siteSlug" "$SLUG"
set_env_value "$DEST/.env" "app.centralAdminMode" "false"
set_env_value "$DEST/.env" "app.baseURL" "'https://$SLUG.example.edu/'"
set_env_value "$DEST/.env" "session.cookieName" "ci_session_$SLUG"
set_env_value "$DEST/.env" "session.rememberCookieName" "remember_$SLUG"

cat <<EOF

Done. Still to do by hand in $DEST/.env before this goes live:

  1. app.baseURL          -> the real public URL for '$NAME'.
  2. database.default.*   -> SAME values as every other faculty folder and
                              the admin folder (they share one database).
  3. If you haven't already, create the database row for this faculty:
       php spark site:create --identifier "$SLUG" --slug "$SLUG" --name "$NAME"
     (run from any instance — they all share the database)

Then run:
  cd $DEST && composer install --no-dev

EOF
