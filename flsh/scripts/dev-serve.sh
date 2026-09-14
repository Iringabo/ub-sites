#!/usr/bin/env bash
# Démarre / arrête / affiche l'état de toutes les instances de la plateforme
# en développement local. Chaque instance écoute sur le port déclaré dans son
# propre .env (app.baseURL) ; à défaut, sur les ports par défaut ci-dessous.
#
# Usage (depuis n'importe quelle instance) :
#   scripts/dev-serve.sh           démarre fseg + fsi + superadmin
#   scripts/dev-serve.sh stop      arrête tout
#   scripts/dev-serve.sh status    vérifie ce qui répond
#
# Ports par défaut : fseg=8101, fsi=8102, superadmin=8103.
set -u

script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
instance_dir="$(dirname "$script_dir")"
platform_dir="$(dirname "$instance_dir")"

INSTANCES="${PLATFORM_INSTANCES:-fseg fsi superadmin}"
declare -A DEFAULT_PORTS=( [fseg]=8101 [fsi]=8102 [superadmin]=8103 )

port_of() {
  local dir="$1" name="$2" p=""
  if [[ -f "$dir/.env" ]]; then
    p=$(grep -E '^app\.baseURL' "$dir/.env" | grep -oE ':[0-9]{2,5}' | head -1 | tr -d ':')
  fi
  echo "${p:-${DEFAULT_PORTS[$name]:-8080}}"
}

pidfile_for() { printf '/tmp/platform-dev-%s.pid' "$1"; }

do_start() {
  for name in $INSTANCES; do
    dir="$platform_dir/$name"
    if [[ ! -d "$dir" ]]; then
      echo "↷ $name : dossier absent, ignoré"
      continue
    fi

    port=$(port_of "$dir" "$name")
    pidfile="$(pidfile_for "$name")"

    if fuser -s "$port/tcp" 2>/dev/null; then
      echo "• $name : déjà en écoute sur http://localhost:$port"
      continue
    fi

    (cd "$dir" && setsid nohup php spark serve --host localhost --port "$port" \
        > "/tmp/platform-dev-$name.log" 2>&1 < /dev/null &
     echo $! > "$pidfile")
    echo "▶ $name : http://localhost:$port"
  done
  echo "Logs : /tmp/platform-dev-<instance>.log"
}

do_stop() {
  for name in $INSTANCES; do
    pidfile="$(pidfile_for "$name")"
    [[ -f "$pidfile" ]] && kill "$(cat "$pidfile")" 2>/dev/null
    rm -f "$pidfile"
    dir="$platform_dir/$name"
    port=$(port_of "$dir" "$name")
    fuser -k "$port/tcp" 2>/dev/null
    echo "■ $name : arrêté (port $port)"
  done
}

do_status() {
  rc=0
  for name in $INSTANCES; do
    dir="$platform_dir/$name"
    [[ -d "$dir" ]] || continue
    port=$(port_of "$dir" "$name")
    code=$(curl -s -m 3 -o /dev/null -w '%{http_code}' "http://localhost:$port/healthz" || true)
    if [[ "$code" == "204" ]]; then
      echo "✓ $name : http://localhost:$port (healthz 204)"
    else
      echo "✗ $name : http://localhost:$port ne répond pas ($code)"
      rc=1
    fi
  done
  exit $rc
}

case "${1:-start}" in
  start)  do_start ;;
  stop)   do_stop ;;
  status) do_status ;;
  *) echo "Usage: $0 [start|stop|status]" >&2; exit 2 ;;
esac
