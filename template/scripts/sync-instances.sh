#!/usr/bin/env bash
#
# Copy template app/, tests/, and public/assets/ into the live instances.
# Never copies .env, writable/, instance docs/, or demo-media/ (faculty photo pack).
#
# Usage (from the template folder or this scripts/ directory):
#   ./scripts/sync-instances.sh
#
set -euo pipefail

TEMPLATE="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
REPO="$(cd "$TEMPLATE/.." && pwd)"

if [ ! -d "$TEMPLATE/app" ] || [ ! -d "$TEMPLATE/tests" ] || [ ! -d "$TEMPLATE/public/assets" ]; then
  echo "Erreur : ce script doit vivre dans template/scripts/." >&2
  exit 1
fi

copied=0
for dest_name in fseg fsi superadmin med fabi flsh; do
  dest="$REPO/$dest_name"
  if [ ! -d "$dest" ]; then
    echo "Ignore $dest_name (dossier absent)."
    continue
  fi

  echo "Synchronise $dest_name …"
  rsync -a --delete "$TEMPLATE/app/" "$dest/app/"
  rsync -a --delete "$TEMPLATE/tests/" "$dest/tests/"
  # Shared chrome only (vendor, CSS/JS, logo placeholders). Faculty photos live in
  # demo-media/ + public/uploads/sites/{slug}/ — never cloned across instances.
  rsync -a --delete \
    --exclude 'images/faculties/' \
    "$TEMPLATE/public/assets/" "$dest/public/assets/"
  rm -rf "$dest/public/assets/images/faculties"
  copied=$((copied + 1))
done

if [ "$copied" -eq 0 ]; then
  echo "Aucun dossier d'instance trouvé à côté de template/." >&2
  exit 1
fi

echo "Terminé ($copied instance(s)). .env, writable/, docs/ d'instance et demo-media/ n'ont pas été touchés."
