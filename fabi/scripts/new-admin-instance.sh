#!/usr/bin/env bash
#
# Create the central "admin" folder: a copy of this codebase configured to
# serve ONLY the superadmin dashboard (no faculty public pages, ever — see
# app/Filters/CentralAdminOnlyFilter.php).
#
# Usage:
#   ./scripts/new-admin-instance.sh <destination-dir>
#
# Example:
#   ./scripts/new-admin-instance.sh ../admin
#
# See docs/MULTI_FOLDER_DEPLOYMENT.md for the full picture.
set -euo pipefail
CALLER_CWD="$(pwd -P)"
cd "$(dirname "${BASH_SOURCE[0]}")"
source ./_lib.sh

reject_link_vendor "$@"

DEST="${1:-}"

if [ -z "$DEST" ]; then
  echo "Usage: $0 <destination-dir>" >&2
  exit 1
fi

if [ "${DEST#/}" = "$DEST" ]; then
  DEST="$CALLER_CWD/$DEST"
fi

echo "Creating central admin instance at $DEST ..."
copy_instance "$DEST"

set_env_value "$DEST/.env" "app.centralAdminMode" "true"
set_env_value "$DEST/.env" "app.baseURL" "'https://admin.example.edu/'"
set_env_value "$DEST/.env" "session.cookieName" "ci_session_central"
set_env_value "$DEST/.env" "session.rememberCookieName" "remember_central"

if ! grep -q '^# app.uploadMirrors' "$DEST/.env" && ! grep -q '^app.uploadMirrors' "$DEST/.env"; then
  printf '\n# Copie des téléversements vers chaque faculté (chemins à adapter).\n# app.uploadMirrors = fseg:/absolute/path/fseg,fsi:/absolute/path/fsi\n' >> "$DEST/.env"
fi

cat <<EOF

Done. Still to do by hand in $DEST/.env before this goes live:

  1. app.baseURL          -> the real URL where superadmins will log in.
  2. database.default.*   -> SAME values as every faculty folder (shared DB).
  3. app.uploadMirrors    -> map slug:/path for each faculty public root
                             (uncomment the stub in .env).
  4. Create the first superadmin account (only needs doing once, from any
     instance, since they share the database):
       php spark admin:create-superadmin --email you@example.edu --username you

Then run:
  cd $DEST && composer install --no-dev

Reminder: this folder should never be reachable at a faculty's public URL —
put it on its own subdomain/path (e.g. admin.example.edu) and keep it out of
each faculty's document root.

EOF
