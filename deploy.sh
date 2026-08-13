#!/usr/bin/env bash
#
# Nahrá obsah adresára public/ na FTP. Heslo číta z macOS Keychainu,
# takže sa nikde neukladá v texte ani v histórii shellu.
#
#   ./deploy.sh save-password        # jednorazovo uloží heslo do Keychainu
#   ./deploy.sh ls [cesta]           # vypíše obsah vzdialeného adresára
#   ./deploy.sh deploy               # nahrá public/ na server
#
set -euo pipefail

FTP_HOST="${FTP_HOST:-ftp.kseftar.sk}"
FTP_USER="${FTP_USER:-}"
REMOTE_DIR="${REMOTE_DIR:-/www_root_tobiaskarafa_sk}"
LOCAL_DIR="${LOCAL_DIR:-$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/public}"
USE_FTPS="${USE_FTPS:-1}"     # 1 = vyžadovať šifrovanie (FTPS), 0 = obyčajné FTP
ASSUME_YES=0

die()  { printf '\033[31mChyba:\033[0m %s\n' "$*" >&2; exit 1; }
info() { printf '\033[36m%s\033[0m\n' "$*"; }

[[ "$(uname)" == "Darwin" ]] || die "Skript číta macOS Keychain — spusti ho na Macu."
command -v curl >/dev/null || die "curl nie je nainštalovaný."

need_user() {
  [[ -n "$FTP_USER" ]] || die "Nastav FTP používateľa: FTP_USER=meno ./deploy.sh $1
Alebo si ho zapíš natrvalo do hlavičky tohto skriptu."
}

# Heslo hľadá najprv ako 'internet password' (tak ho ukladá väčšina FTP klientov),
# potom ako 'generic password'.
get_password() {
  security find-internet-password -s "$FTP_HOST" -a "$FTP_USER" -w 2>/dev/null && return 0
  security find-generic-password  -s "$FTP_HOST" -a "$FTP_USER" -w 2>/dev/null && return 0
  return 1
}

save_password() {
  need_user save-password
  printf 'Heslo pre %s@%s: ' "$FTP_USER" "$FTP_HOST" >&2
  local pw; read -rs pw; printf '\n' >&2
  [[ -n "$pw" ]] || die "Prázdne heslo."
  security add-internet-password -s "$FTP_HOST" -a "$FTP_USER" -r "ftp " \
    -w "$pw" -U -T /usr/bin/curl -T /usr/bin/security
  info "Heslo uložené do Keychainu (položka: $FTP_HOST, účet: $FTP_USER)."
}

# curl dostane prihlasovacie údaje cez stdin, nie cez argumenty —
# inak by bolo heslo viditeľné v zozname procesov.
curl_ftp() {
  local pw; pw="$(get_password)" || die "Heslo pre $FTP_USER@$FTP_HOST nie je v Keychaine.
Spusti najprv: FTP_USER=$FTP_USER ./deploy.sh save-password"
  local -a tls=()
  [[ "$USE_FTPS" == "1" ]] && tls=(--ssl-reqd)
  # curl config očakáva escapované úvodzovky a spätné lomítka
  local esc="${pw//\\/\\\\}"; esc="${esc//\"/\\\"}"
  printf 'user = "%s:%s"\n' "$FTP_USER" "$esc" \
    | curl --config - --connect-timeout 20 --max-time 300 "${tls[@]}" "$@"
}

# Hosting pomenúva korene webov ako www_root_<domena_s_podtrznikmi>,
# takže z cesty vieme zložiť výslednú URL.
public_url() {
  if [[ -n "${PUBLIC_URL:-}" ]]; then printf '%s' "${PUBLIC_URL%/}"; return; fi
  if [[ "$REMOTE_DIR" =~ www_root_([A-Za-z0-9_-]+) ]]; then
    local raw="${BASH_REMATCH[1]}" dom rest
    dom="${raw//_/.}"
    rest="${REMOTE_DIR#*"$raw"}"
    printf 'https://%s%s' "$dom" "${rest%/}"
  else
    printf 'https://%s%s' "${FTP_HOST#ftp.}" "${REMOTE_DIR%/}"
  fi
}

remote_ls() {
  need_user ls
  local path="${1:-/}"
  info "Obsah ftp://$FTP_HOST$path"
  curl_ftp --list-only "ftp://${FTP_HOST}${path%/}/" \
    || die "Výpis zlyhal. Skús USE_FTPS=0 (server nemusí podporovať FTPS)."
}

deploy() {
  need_user deploy
  [[ -d "$LOCAL_DIR" ]] || die "Adresár $LOCAL_DIR neexistuje."

  local -a files=()
  while IFS= read -r -d '' f; do files+=("$f"); done \
    < <(find "$LOCAL_DIR" -type f ! -name '.*' -print0)
  [[ ${#files[@]} -gt 0 ]] || die "V $LOCAL_DIR nie sú žiadne súbory."

  echo
  info "Nahrám na ftp://${FTP_HOST}${REMOTE_DIR}/  (šifrovanie: $([[ $USE_FTPS == 1 ]] && echo FTPS || echo žiadne))"
  local f rel
  for f in "${files[@]}"; do
    rel="${f#"$LOCAL_DIR"/}"
    printf '  %-28s %s\n' "$rel" "$(du -h "$f" | cut -f1)"
  done
  echo
  echo "Existujúce súbory s rovnakým názvom sa prepíšu. Nič sa nemaže."
  if [[ "$REMOTE_DIR" =~ ^/www_root_[^/]+/?$ ]]; then
    printf '\033[33mPozor:\033[0m toto je koreň webu — index.html prepíše titulnú stránku.\n'
  fi
  if [[ $ASSUME_YES -eq 0 ]]; then
    printf 'Pokračovať? [a/N] '
    local ans; read -r ans
    [[ "$ans" =~ ^[aAyY]$ ]] || { echo "Zrušené."; exit 0; }
  fi

  for f in "${files[@]}"; do
    rel="${f#"$LOCAL_DIR"/}"
    printf '→ %s ... ' "$rel"
    curl_ftp --ftp-create-dirs -T "$f" "ftp://${FTP_HOST}${REMOTE_DIR%/}/${rel}" \
      --silent --show-error || die "Nahrávanie $rel zlyhalo."
    printf 'hotovo\n'
  done

  echo
  info "Nahrané. Skús: $(public_url)/"
  echo "(Ak by adresa nesedela, over si cestu cez ./deploy.sh ls /)"
}

cmd="${1:-deploy}"; shift || true
while [[ $# -gt 0 ]]; do
  case "$1" in
    -y|--yes) ASSUME_YES=1 ;;
    *) break ;;
  esac
  shift
done

case "$cmd" in
  save-password) save_password ;;
  ls)            remote_ls "${1:-/}" ;;
  deploy)        deploy ;;
  *)             die "Neznámy príkaz: $cmd (použi save-password | ls | deploy)" ;;
esac
