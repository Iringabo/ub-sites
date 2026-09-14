#!/usr/bin/env bash
# Shared helpers for scripts/new-faculty-instance.sh and
# scripts/new-admin-instance.sh. Not meant to be run directly.
set -euo pipefail

# Copies the current project into $1, excluding things that must never be
# duplicated (git history, local writable data, OS cruft).
# Each instance MUST have its own vendor/ (see AGENTS.md): a vendor symlink
# makes Composer autoload App\ from the template instead of the copy.
copy_instance() {
  local dest="$1"
  local source_dir
  source_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

  if [ -e "$dest" ]; then
    echo "Erreur : '$dest' existe déjà. Choisissez un autre dossier de destination." >&2
    exit 1
  fi

  mkdir -p "$dest"

  local exclude_args=(
    --exclude='.git'
    --exclude='.env'
    --exclude='writable/cache/*'
    --exclude='writable/logs/*'
    --exclude='writable/session/*'
    --exclude='writable/debugbar/*'
    --exclude='writable/uploads/*'
    --exclude='public/uploads/*'
    --exclude='.DS_Store'
  )

  rsync -a "${exclude_args[@]}" "$source_dir"/ "$dest"/

  # Keep the folder structure for anything we excluded the *contents* of.
  mkdir -p "$dest"/writable/cache "$dest"/writable/logs "$dest"/writable/session \
           "$dest"/writable/debugbar "$dest"/writable/uploads "$dest"/public/uploads
  touch "$dest"/writable/cache/.gitkeep "$dest"/writable/logs/.gitkeep \
        "$dest"/writable/session/.gitkeep "$dest"/writable/debugbar/.gitkeep \
        "$dest"/writable/uploads/.gitkeep "$dest"/public/uploads/.gitkeep

  cp "$source_dir/.env.example" "$dest/.env"
  chmod -R u+w "$dest/writable" "$dest/public/uploads" 2>/dev/null || true
}

# Rewrites a single "key = value" line in the destination .env, adding the
# key if it isn't present yet. Matches the CodeIgniter dotenv format used
# throughout this project (dotted keys, no quoting requirement).
set_env_value() {
  local env_file="$1"
  local key="$2"
  local value="$3"

  # Escape for sed replacement text.
  local escaped_value
  escaped_value=$(printf '%s' "$value" | sed -e 's/[\/&]/\\&/g')

  if grep -q "^${key}[[:space:]]*=" "$env_file"; then
    sed -i.bak "s/^${key}[[:space:]]*=.*/${key} = ${escaped_value}/" "$env_file"
    rm -f "$env_file.bak"
  else
    printf '\n%s = %s\n' "$key" "$value" >> "$env_file"
  fi
}

reject_link_vendor() {
  for arg in "$@"; do
    if [ "$arg" = "--link-vendor" ]; then
      echo "Erreur : --link-vendor est refusé. Un symlink de vendor/ casse l'autoload App\\ (le code exécuté n'est plus celui du dossier copié). Lancez composer install dans l'instance." >&2
      exit 1
    fi
  done
}
