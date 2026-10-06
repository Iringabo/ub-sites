#!/usr/bin/env bash
#
# Copy template app/, tests/, public/assets/ and the operating docs
# (README.md, AGENTS.md, CHANGELOG.md, docs/{ARCHITECTURE,GUIDE_ADMINISTRATEUR,
# DEPLOIEMENT,OPERATIONS_LOCALES,SECURITE_QUALITE}.md) into the live instances;
# other docs/*.md in an instance are removed. Site-creation guides
# (docs/CREER_UN_SITE.md, docs/AGENT_NOUVEAU_SITE.md), docs/archive/, .env,
# writable/ and demo-media/ (faculty photo pack) are never copied.
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

ROOT_DOCS=(README.md AGENTS.md CHANGELOG.md)
INSTANCE_DOCS=(ARCHITECTURE.md GUIDE_ADMINISTRATEUR.md DEPLOIEMENT.md OPERATIONS_LOCALES.md SECURITE_QUALITE.md)

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
  # robots.txt is generated per site (with its sitemap URL); a static file would shadow the route.
  rm -f "$dest/public/robots.txt"
  # Operating docs only: site-creation guides stay in the template.
  for doc in "${ROOT_DOCS[@]}"; do
    cp "$TEMPLATE/$doc" "$dest/$doc"
  done
  mkdir -p "$dest/docs"
  for doc in "${INSTANCE_DOCS[@]}"; do
    cp "$TEMPLATE/docs/$doc" "$dest/docs/$doc"
  done
  for existing in "$dest"/docs/*.md; do
    [ -e "$existing" ] || continue
    keep=0
    for doc in "${INSTANCE_DOCS[@]}"; do
      [ "$(basename "$existing")" = "$doc" ] && keep=1
    done
    [ "$keep" -eq 1 ] || rm -f "$existing"
  done
  rm -rf "$dest/docs/archive"
  rm -f "$dest/README-FIRST.md" "$dest/CONTRIBUTING.md"
  copied=$((copied + 1))
done

if [ "$copied" -eq 0 ]; then
  echo "Aucun dossier d'instance trouvé à côté de template/." >&2
  exit 1
fi

echo "Terminé ($copied instance(s)). Documentation d'exploitation recopiée ; .env, writable/, public/uploads/ et demo-media/ n'ont pas été touchés."
