#!/usr/bin/env bash
# Build ONE plugin from its cfg:
# - Update headers (external PHP script or built-in patcher)
# - Stage clean files, exclude junk
# - ZIP with 7-Zip (top-level folder = PLUGIN_SLUG)
# - Generate uupd/<slug>/index.json from MD/HTML/TXT
# - Validate zip structure
# plugin_build.sh — build ONE plugin from its cfg
set -euo pipefail

CFG=""
DEBUG=false
NO_PAUSE=false

usage() {
  cat <<'EOF'
Usage: bash ./plugin_build.sh --cfg=path/to/plugin.cfg [--debug] [--no-pause]
EOF
}

# ---- args ----
for raw in "$@"; do
  [[ -z "${raw//[[:space:]]/}" ]] && continue
  arg="${raw%$'\r'}"
  case "$arg" in
    --cfg=*)    CFG="${arg#*=}" ;;
    --debug)    DEBUG=true ;;
    --no-pause) NO_PAUSE=true ;;
    --help|-h)  usage; exit 0 ;;
    *) echo "Unknown option: $arg"; usage; exit 2 ;;
  esac
done
$DEBUG && set -x

pause_if_needed(){ [[ "${CI:-}" != "1" && $NO_PAUSE = false && -t 1 ]] && read -p $'\nPress Enter to exit...'; }
on_error(){ code=$?; echo -e "\n\033[31mError:\033[0m $code"; pause_if_needed; exit $code; }
trap on_error ERR
trap '[[ "$?" -eq 0 ]] && pause_if_needed' EXIT

[[ -n "$CFG" && -f "$CFG" ]] || { echo "[ERR] --cfg is required"; exit 1; }

# ---- env & helpers ----
script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# prefer REPO_ROOT if provided; else if script is under cfg/, use its parent as repo root
if [[ -n "${REPO_ROOT:-}" ]]; then
  repo_root="$(cd "$REPO_ROOT" && pwd)"
elif [[ "$(basename "$script_dir")" == "cfg" ]]; then
  repo_root="$(cd "$script_dir/.." && pwd)"
else
  repo_root="$(cd "$script_dir" && pwd)"
fi

have_cmd(){ command -v "$1" >/dev/null 2>&1; }


SEVENZIP_BIN="${SEVENZIP:-7z}"     # can be overridden in cfg
RAW_BRANCH_DEFAULT="main"

json_escape(){ sed -e 's/\\/\\\\/g' -e 's/"/\\"/g' -e ':a;N;$!ba;s/\r//g'; }
read_file_or_empty(){ local f="$1"; [[ -n "$f" && -f "$f" ]] && cat "$f" || echo ""; }
find_first(){ local base="$1" name="$2"; for ext in md html txt; do local p="$base/$name.$ext"; [[ -f "$p" ]] && { echo "$p"; return 0; }; done; return 1; }
read_content_file(){ local f="$1"; [[ -f "$f" ]] || { echo ""; return; }; case "$f" in *.html) sed 's/\r//g' "$f";; *) sed ':a;N;$!ba;s/\r//g;s/\n/<br\/>/g' "$f";; esac; }

find_main_file(){
  local plugin_dir="$1"
  local entry_hint="${2-}"
  local candidate="$plugin_dir/$entry_hint"
  if [[ -n "$entry_hint" && -f "$candidate" ]]; then echo "$candidate"; return 0; fi
  local hit
  while IFS= read -r -d '' f; do
    if head -n 160 "$f" | awk 'BEGIN{IGNORECASE=1}/Plugin[[:space:]]+Name[[:space:]]*:|Version[[:space:]]*:/ {exit 0} END{exit 1}'; then
      hit="$f"; break
    fi
  done < <(find "$plugin_dir" -maxdepth 1 -type f -name "*.php" -print0)
  [[ -n "${hit:-}" ]] && { echo "$hit"; return 0; }
  hit="$(find "$plugin_dir" -maxdepth 1 -type f -name "*.php" | sort | head -n1 || true)"
  [[ -n "$hit" ]] && echo "$hit" || return 1
}

read_header_field(){
  local file="$1" key="$2"
  awk -v k="$key" 'BEGIN{IGNORECASE=1}
    $0 ~ "^[[:space:]]*(\\*|//)?[[:space:]]*"k"[[:space:]]*:" {
      sub("^[[:space:]]*(\\*|//)?[[:space:]]*"k"[[:space:]]*:[[:space:]]*","",$0);
      gsub(/[[:space:]]+$/,"",$0); print $0; exit
    }' "$file"
}

write_header_field(){
  local file="$1" key="$2" val="$3"
  if grep -qiE "^[[:space:]]*(\*|//)?[[:space:]]*$key[[:space:]]*:" "$file"; then
    perl -0777 -pe 'BEGIN{$k=$ARGV[0];$v=$ARGV[1]} s/^(\s*(?:\*|\/\/)?\s*$k\s*:).*$/$1 $v/gmi' "$key" "$val" -i -- "$file"
  else
    perl -0777 -pe 'BEGIN{$k=$ARGV[0];$v=$ARGV[1]}
      if(s/(\*\/|\?>)/ * '"$key"': '"$val"'\n$1/s){1}else{$_="/* '"$key"': $v */\n".$_}' "$key" "$val" -i -- "$file"
  fi
}

# -------------------- load cfg (safe) --------------------
while IFS= read -r line || [[ -n "$line" ]]; do
  line="${line//$'\r'/}"
  [[ -z "$line" || "${line:0:1}" == "#" || "${line:0:1}" == ";" ]] && continue
  if [[ "$line" =~ ^([A-Za-z_][A-Za-z0-9_]*)=(.*)$ ]]; then
    key="${BASH_REMATCH[1]}"; val="${BASH_REMATCH[2]}"
    [[ "$val" == \"*\" ]] && val="${val%\"}" && val="${val#\"}"
    printf -v "$key" '%s' "$val"
  fi
done < "$CFG"

# -------------------- required + defaults --------------------
: "${PLUGIN_SLUG:?PLUGIN_SLUG required in cfg}"
: "${PLUGIN_NAME:?PLUGIN_NAME required in cfg}"
: "${GITHUB_OWNER:?GITHUB_OWNER required in cfg}"
: "${GITHUB_REPO:?GITHUB_REPO required in cfg}"

UUPD_DIR="${UUPD_DIR:-uupd}"
RAW_BRANCH="${RAW_BRANCH:-$RAW_BRANCH_DEFAULT}"
ZIP_NAME="${ZIP_NAME:-$PLUGIN_SLUG.zip}"
AUTHOR_NAME="${AUTHOR_NAME:-}"
AUTHOR_URL="${AUTHOR_URL:-}"
JSON_SLUG_PREFIX="${JSON_SLUG_PREFIX:-}"
ENTRY_HINT="${ENTRY_HINT:-$PLUGIN_SLUG.php}"
SEVENZIP_BIN="${SEVENZIP:-$SEVENZIP_BIN}"

# default header script path (yours), can be overridden in cfg
HEADER_SCRIPT="${HEADER_SCRIPT:-C:/Ignore By Avast/0. PATHED Items/Plugins/deployscripts/myplugin_headers.php}"

# -------------------- paths --------------------
plugin_dir="$repo_root/$PLUGIN_SLUG"
[[ -d "$plugin_dir" ]] || { echo "[ERR] plugin folder missing: $plugin_dir"; exit 1; }
main_file="$(find_main_file "$plugin_dir" "$ENTRY_HINT")" || { echo "[ERR] cannot find main plugin file"; exit 1; }


# -------------------- update headers --------------------
if [[ -n "$HEADER_SCRIPT" && -f "$HEADER_SCRIPT" ]]; then
  php "$HEADER_SCRIPT" "$main_file"
else
  [[ -n "${HEADER_VERSION:-}"           ]] && write_header_field "$main_file" "Version"             "$HEADER_VERSION"
  [[ -n "${HEADER_REQUIRES_AT_LEAST:-}" ]] && write_header_field "$main_file" "Requires at least"   "$HEADER_REQUIRES_AT_LEAST"
  [[ -n "${HEADER_TESTED_UP_TO:-}"      ]] && write_header_field "$main_file" "Tested up to"        "$HEADER_TESTED_UP_TO"
  [[ -n "${HEADER_REQUIRES_PHP:-}"      ]] && write_header_field "$main_file" "Requires PHP"        "$HEADER_REQUIRES_PHP"
fi

version="$(read_header_field "$main_file" "Version")"
req="$(read_header_field "$main_file" "Requires at least")"
tested="$(read_header_field "$main_file" "Tested up to")"
rphp="$(read_header_field "$main_file" "Requires PHP")"
[[ -n "$version" ]] || { echo "[ERR] Version not found in header"; exit 1; }

REQ_JSON="${REQ_JSON:-$req}"
TESTED_JSON="${TESTED_JSON:-$tested}"
RPHP_JSON="${RPHP_JSON:-$rphp}"

# -------------------- stage, clean, zip --------------------
# -------------------- stage, clean, zip --------------------
have_cmd "$SEVENZIP_BIN" || { echo "[ERR] 7z not found (set SEVENZIP in cfg if needed)"; exit 1; }
build_root="$repo_root/.build"
ts="$(date +%Y%m%d%H%M%S)"
stage="$build_root/$PLUGIN_SLUG-$ts"
mkdir -p "$stage/$PLUGIN_SLUG"

# Ensure staging gets removed even if we error out later
cleanup_staging() {
  if [[ -n "${stage:-}" ]] && [[ -d "$stage" ]] && [[ "${DEBUG:-false}" != "true" ]]; then
    rm -rf "$stage" >/dev/null 2>&1 || true
    # remove .build if now empty
    if [[ -d "$build_root" ]] && [[ -z "$(find "$build_root" -mindepth 1 -print -quit 2>/dev/null)" ]]; then
      rmdir "$build_root" 2>/dev/null || true
    fi
  fi
}

on_exit() {
  ec=$?
  cleanup_staging
  if [[ $ec -eq 0 ]]; then
    pause_if_needed
  fi
  exit $ec
}

# Replace any prior EXIT trap with this unified one:
trap on_exit EXIT



# Copy plugin files into staging (preserve perms/times); Git Bash cp -a works fine
cp -a "$plugin_dir"/. "$stage/$PLUGIN_SLUG/"

# Default excludes (remove if present in staged copy)
rm -rf \
  "$stage/$PLUGIN_SLUG/.git" \
  "$stage/$PLUGIN_SLUG/.github" \
  "$stage/$PLUGIN_SLUG/node_modules" \
  "$stage/$PLUGIN_SLUG/vendor/bin" \
  "$stage/$PLUGIN_SLUG/tests" \
  "$stage/$PLUGIN_SLUG/test" \
  "$stage/$PLUGIN_SLUG/.build" \
  "$stage/$PLUGIN_SLUG/uupd" \
  "$stage/$PLUGIN_SLUG/cfg" \
  "$stage/$PLUGIN_SLUG/build" \
  "$stage/$PLUGIN_SLUG/.DS_Store" 2>/dev/null || true

# Optional: .pluginignore (globs relative to the plugin root inside staging)
if [[ -f "$plugin_dir/.pluginignore" ]]; then
  while IFS= read -r pat || [[ -n "$pat" ]]; do
    pat="${pat//$'\r'/}"
    [[ -z "$pat" || "${pat:0:1}" == "#" || "${pat:0:1}" == ";" ]] && continue
    # expand and remove
    ( shopt -s nullglob dotglob
      for p in "$stage/$PLUGIN_SLUG"/$pat; do rm -rf "$p"; done
    )
  done < "$plugin_dir/.pluginignore"
fi

zip_path="$repo_root/$ZIP_NAME"
rm -f "$zip_path"
(
  cd "$stage"
  # zip the staged folder so the ZIP root is exactly PLUGIN_SLUG/
  "$SEVENZIP_BIN" a -tzip -mx=9 "$zip_path" "$PLUGIN_SLUG" >/dev/null
)

# -------------------- validate zip --------------------
validate_zip(){
  local z="$1" slug="$2" entry_hint_rel="$3"
  [[ -f "$z" ]] || { echo "[ERR] zip not found: $z"; return 1; }

  # Use 7-Zip's structured output; collect only entry paths (skip archive path with drive colon)
  local paths
  paths="$("$SEVENZIP_BIN" l -slt "$z" \
          | sed 's/\r$//' \
          | awk -F' = ' '/^Path = /{ if (index($2,":")==0) print $2 }')"

  [[ -n "$paths" ]] || { echo "[ERR] zip listing empty"; return 1; }

  # Require top-level folder to be exactly <slug>/
  if ! grep -q "^${slug}/" <<<"$paths"; then
    echo "[ERR] zip does not have top-level folder '${slug}/'"; return 1
  fi

  # Ensure main file exists directly under <slug>/ (or any top-level *.php with headers)
  local mf="${slug}/${entry_hint_rel}"
  if ! grep -qx "$mf" <<<"$paths"; then
    # fallback: any php at top-level
    local top_php
    top_php="$(awk -v s="$slug" -F/ '$1==s && NF==2 && $0 ~ /\.php$/ {print $0}' <<<"$paths" | head -n1)"
    if [[ -z "$top_php" ]]; then
      echo "[ERR] no plugin main PHP found directly under '${slug}/'"; return 1
    fi
  fi
  return 0
}
# -------------------- write index.json --------------------
raw_base="https://raw.githubusercontent.com/$GITHUB_OWNER/$GITHUB_REPO/$RAW_BRANCH"
dl_url="https://github.com/$GITHUB_OWNER/$GITHUB_REPO/releases/latest/download/$ZIP_NAME"

out_dir="$repo_root/$UUPD_DIR/$PLUGIN_SLUG"
mkdir -p "$out_dir"

desc_file="$(find_first "$out_dir" description || true)"
inst_file="$(find_first "$out_dir" installation || true)"
faq_file="$(find_first "$out_dir" faq || true)"
chlog_file="$(find_first "$out_dir" changelog || true)"

desc="$( [[ -n "$desc_file" ]] && read_content_file "$desc_file" || echo "" )"
inst="$( [[ -n "$inst_file" ]] && read_content_file "$inst_file" || echo "" )"
faq="$(  [[ -n "$faq_file"  ]] && read_content_file "$faq_file"  || echo "" )"
chlog="$( [[ -n "$chlog_file" ]] && read_content_file "$chlog_file" || echo "" )"

now="$(date '+%Y-%m-%d %H:%M:%S')"
icon1="$raw_base/$UUPD_DIR/$PLUGIN_SLUG/icon-128.png"
icon2="$raw_base/$UUPD_DIR/$PLUGIN_SLUG/icon-256.png"
banLow="$raw_base/$UUPD_DIR/$PLUGIN_SLUG/banner-772x250.png"
banHigh="$raw_base/$UUPD_DIR/$PLUGIN_SLUG/banner-1544x500.png"

json_path="$out_dir/index.json"
if have_cmd jq; then
  jq -n \
    --arg slug "${JSON_SLUG_PREFIX}$PLUGIN_SLUG" \
    --arg name "$PLUGIN_NAME" \
    --arg version "$version" \
    --arg author "$AUTHOR_NAME" \
    --arg author_homepage "$AUTHOR_URL" \
    --arg requires_php "$RPHP_JSON" \
    --arg requires "$REQ_JSON" \
    --arg tested "$TESTED_JSON" \
    --arg desc "$desc" \
    --arg inst "$inst" \
    --arg faq  "$faq" \
    --arg chlog "$chlog" \
    --arg last_updated "$now" \
    --arg dl "$dl_url" \
    --arg icon1 "$icon1" \
    --arg icon2 "$icon2" \
    --arg banLow "$banLow" \
    --arg banHigh "$banHigh" \
    '{
      slug:$slug, name:$name, version:$version,
      author:$author, author_homepage:$author_homepage,
      requires_php:$requires_php, requires:$requires, tested:$tested,
      sections:{ description:$desc, installation:$inst, frequently_asked_questions:$faq, changelog:$chlog },
      last_updated:$last_updated, download_url:$dl,
      banners:{low:$banLow, high:$banHigh},
      icons:{"1x":$icon1,"2x":$icon2}
    }' > "$json_path"
else
  esc(){ printf '%s' "$1" | json_escape; }
  cat > "$json_path" <<EOF
{
  "slug": "$(esc "${JSON_SLUG_PREFIX}$PLUGIN_SLUG")",
  "name": "$(esc "$PLUGIN_NAME")",
  "version": "$(esc "$version")",
  "author": "$(esc "$AUTHOR_NAME")",
  "author_homepage": "$(esc "$AUTHOR_URL")",
  "requires_php": "$(esc "$RPHP_JSON")",
  "requires": "$(esc "$REQ_JSON")",
  "tested": "$(esc "$TESTED_JSON")",
  "sections": {
    "description": "$(esc "$desc")",
    "installation": "$(esc "$inst")",
    "frequently_asked_questions": "$(esc "$faq")",
    "changelog": "$(esc "$chlog")"
  },
  "last_updated": "$(esc "$now")",
  "download_url": "$(esc "$dl_url")",
  "banners": { "low": "$(esc "$banLow")", "high": "$(esc "$banHigh")" },
  "icons": { "1x": "$(esc "$icon1")", "2x": "$(esc "$icon2")" }
}
EOF
fi

echo "[OK] Built: $(basename "$zip_path")"
echo "[OK] JSON: $json_path"
