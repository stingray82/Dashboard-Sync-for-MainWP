#!/usr/bin/env bash
# suite_release.sh — create/upload a suite release from built zips
# - Always git add/commit/push uupd/ + zips (or add -A if PUSH_ALL=true)
# - Rebuild missing zips (optional)
# - Create GH release (gh or curl fallback)
# NOTE: Save with LF line endings.

set -Eeuo pipefail

CFG=""
DEBUG=false
NO_PAUSE=false
PLUGINS_OVERRIDE=""
CFG_DIR=""
REBUILD=false
DRY_RUN=false
NO_GH=false

usage() {
  cat <<'EOF'
Usage: bash ./suite_release.sh --cfg=path/to/suite.cfg [options]

Options:
  --plugins=slug1,slug2     Only include these slugs (override cfg list)
  --cfg-dir=./plugin-cfgs   Where per-plugin cfgs live (used with --rebuild)
  --rebuild                 If a zip is missing, rebuild it via plugin_build.sh --cfg
  --dry-run                 Print what would happen; skip git/GitHub calls
  --no-gh                   Skip gh even if installed (use curl if $GITHUB_TOKEN)
  --debug                   Verbose logs
  --no-pause                Don’t pause on exit (Windows convenience)
  --help, -h                Show this help
EOF
}

# ---------- arg parse ----------
for raw in "$@"; do
  [[ -z "${raw//[[:space:]]/}" ]] && continue
  arg="${raw%$'\r'}"
  case "$arg" in
    --cfg=*)      CFG="${arg#*=}" ;;
    --plugins=*)  PLUGINS_OVERRIDE="${arg#*=}" ;;
    --cfg-dir=*)  CFG_DIR="${arg#*=}" ;;
    --rebuild)    REBUILD=true ;;
    --dry-run)    DRY_RUN=true ;;
    --no-gh)      NO_GH=true ;;
    --debug)      DEBUG=true ;;
    --no-pause)   NO_PAUSE=true ;;
    --help|-h)    usage; exit 0 ;;
    *) echo "Unknown option: $arg"; usage; exit 2 ;;
  esac
done

$DEBUG && set -x

pause_if_needed() {
  # only pause if interactive and not in CI and --no-pause wasn't set
  [[ "${CI:-}" != "1" && "${NO_PAUSE:-false}" = false && -t 1 ]] && read -p $'\nPress Enter to exit...'
}

on_exit() {
  code=$?
  if [[ $code -eq 0 ]]; then
    pause_if_needed
  fi
  exit $code
}

trap on_exit EXIT


[[ -n "$CFG" && -f "$CFG" ]] || { echo "[ERR] --cfg is required"; exit 1; }

have_cmd(){ command -v "$1" >/dev/null 2>&1; }
json_escape(){ sed -e 's/\\/\\\\/g' -e 's/"/\\"/g' -e ':a;N;$!ba;s/\r//g'; }
read_file_or_empty(){ local f="$1"; [[ -n "$f" && -f "$f" ]] && cat "$f" || echo ""; }

# ---------- locate repo root (works when run from cfg/ or root) ----------
script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
if [[ "$(basename "$script_dir")" == "cfg" ]]; then
  repo_root="$(cd "$script_dir/.." && pwd)"
else
  repo_root="$script_dir"
fi
cd "$repo_root"

# ---------- cfg loader (strips inline comments) ----------
_trim(){ local s="$1"; s="${s#"${s%%[![:space:]]*}"}"; printf '%s' "${s%"${s##*[![:space:]]}"}"; }
_strip_inline_comments(){
  local v="$1"
  v="${v%%#*}"   # drop after first '#'
  v="${v%%;*}"   # drop after first ';'
  _trim "$v"
}

unset GITHUB_OWNER GITHUB_REPO SUITE_TAG SUITE_NOTES_FILE RAW_BRANCH UUPD_DIR PLUGINS \
      PUSH_ALL PUSH_REMOTE PUSH_BRANCH
declare -a PLUGINS=()

while IFS= read -r line || [[ -n "$line" ]]; do
  line="${line//$'\r'/}"
  [[ -z "$line" || "${line:0:1}" == "#" || "${line:0:1}" == ";" ]] && continue

  if [[ "$line" =~ ^PLUGINS=\( ]]; then
    block="$line"
    while [[ "$block" != *")" ]]; do
      read -r next || true; next="${next//$'\r'/}"; block+=$'\n'"$next"
    done
    eval "$block"   # trusted local cfg
    continue
  fi

  if [[ "$line" =~ ^([A-Za-z_][A-Za-z0-9_]*)=(.*)$ ]]; then
    key="${BASH_REMATCH[1]}"; val="${BASH_REMATCH[2]}"
    # strip one layer of quotes then inline comments
    if [[ "$val" == \"*\" && "$val" == *\" ]]; then val="${val%\"}"; val="${val#\"}"; fi
    if [[ "$val" == \'*\' && "$val" == *\' ]]; then val="${val%\'}"; val="${val#\'}"; fi
    val="$(_strip_inline_comments "$val")"
    printf -v "$key" '%s' "$val"
  fi
done < "$CFG"

: "${GITHUB_OWNER:?GITHUB_OWNER required}"
: "${GITHUB_REPO:?GITHUB_REPO required}"
UUPD_DIR="${UUPD_DIR:-uupd}"
RAW_BRANCH="${RAW_BRANCH:-main}"

PUSH_ALL="${PUSH_ALL:-false}"             # true => git add -A
PUSH_REMOTE="${PUSH_REMOTE:-origin}"
PUSH_BRANCH="${PUSH_BRANCH:-$RAW_BRANCH}" # fallback to current branch if empty/auto

# Expand date expression in SUITE_TAG (supports v$(date +%Y.%m.%d-%H%M))
if [[ -z "${SUITE_TAG:-}" ]]; then
  SUITE_TAG="v$(date +%Y.%m.%d-%H%M)"
elif [[ "$SUITE_TAG" == *'$('*')'* ]]; then
  SUITE_TAG="$(eval "echo $SUITE_TAG")"
fi

# Override plugin list via flag
if [[ -n "$PLUGINS_OVERRIDE" ]]; then
  IFS=',' read -r -a PLUGINS <<< "$PLUGINS_OVERRIDE"
fi
[[ ${#PLUGINS[@]} -gt 0 ]] || { echo "[ERR] No plugins listed"; exit 1; }

# Suite notes (path relative to repo root)
SUITE_NOTES_CONTENT="$(read_file_or_empty "${SUITE_NOTES_FILE:+$repo_root/$SUITE_NOTES_FILE}")"
SUITE_NOTES_JSON=""
[[ -n "$SUITE_NOTES_CONTENT" ]] && SUITE_NOTES_JSON="\"$(printf '%s' "$SUITE_NOTES_CONTENT" | json_escape)\""

# ---------- auth headers for curl fallback ----------
declare -a AUTH_HEADER=()
[[ -n "${GITHUB_TOKEN:-}" ]] && AUTH_HEADER=(-H "Authorization: token $GITHUB_TOKEN")

create_or_get_release() {
  local tag="$1"
  $DRY_RUN && { echo "[dry-run] would create/get release: $tag"; return 0; }

  if ! $NO_GH && have_cmd gh; then
    if gh release view "$tag" --repo "$GITHUB_OWNER/$GITHUB_REPO" >/dev/null 2>&1; then return 0; fi
    if [[ -n "${SUITE_NOTES_FILE:-}" && -f "$repo_root/${SUITE_NOTES_FILE:-}" ]]; then
      gh release create "$tag" --repo "$GITHUB_OWNER/$GITHUB_REPO" --draft=false --notes-file "$repo_root/${SUITE_NOTES_FILE}" && return 0 || echo "[warn] gh failed; will try curl"
    else
      gh release create "$tag" --repo "$GITHUB_OWNER/$GITHUB_REPO" --draft=false --notes "Suite release $tag" && return 0 || echo "[warn] gh failed; will try curl"
    fi
  fi

  # curl path
  set +e
  get_resp=$(curl -fsS "${AUTH_HEADER[@]}" "https://api.github.com/repos/$GITHUB_OWNER/$GITHUB_REPO/releases/tags/$tag" 2>/dev/null)
  code=$?; set -e
  if [[ $code -ne 0 || -z "$get_resp" || "$(echo "$get_resp" | grep -c '"id":')" -eq 0 ]]; then
    local body="{\"tag_name\":\"$tag\",\"name\":\"$tag\",\"body\":${SUITE_NOTES_JSON:-\"Suite release $tag\"}}"
    curl -fsS -X POST "${AUTH_HEADER[@]}" -H "Content-Type: application/json" -d "$body" \
      "https://api.github.com/repos/$GITHUB_OWNER/$GITHUB_REPO/releases" >/dev/null
  fi
}

upload_asset() {
  local tag="$1" file="$2" fname; fname="$(basename "$file")"
  $DRY_RUN && { echo "[dry-run] would upload: $fname → $GITHUB_OWNER/$GITHUB_REPO@$tag"; return 0; }

  if ! $NO_GH && have_cmd gh; then
    gh release upload "$tag" "$file" --repo "$GITHUB_OWNER/$GITHUB_REPO" --clobber >/dev/null
    return 0
  fi

  # curl path
  local release upload_url rid assets aid
  release=$(curl -fsS "${AUTH_HEADER[@]}" "https://api.github.com/repos/$GITHUB_OWNER/$GITHUB_REPO/releases/tags/$tag")
  upload_url=$(echo "$release" | sed -n 's/.*"upload_url": "\(.*\){.*/\1/p')
  rid=$(echo "$release" | awk -F: '/"id":/{gsub(/[ ,]/,"",$2);print $2; exit}')
  assets=$(curl -fsS "${AUTH_HEADER[@]}" "https://api.github.com/repos/$GITHUB_OWNER/$GITHUB_REPO/releases/$rid/assets")
  aid=$(echo "$assets" | awk -v n="$fname" -F'[,:]' '$0~/"name":"'"$fname"'"/{getline; if($0~/"id"/){gsub(/[^0-9]/,"",$2); print $2; exit}}' || true)
  [[ -n "$aid" ]] && curl -fsS -X DELETE "${AUTH_HEADER[@]}" "https://api.github.com/repos/$GITHUB_OWNER/$GITHUB_REPO/releases/assets/$aid" >/dev/null || true
  curl -fsS -X POST "${AUTH_HEADER[@]}" -H "Content-Type: application/zip" --data-binary @"$file" "${upload_url}?name=${fname}" >/dev/null
}

# ---------- gather zips (and optionally rebuild) ----------
declare -a ZIPS=()
for slug in "${PLUGINS[@]}"; do
  zip_path="$repo_root/$slug.zip"
  if [[ ! -f "$zip_path" && -n "$CFG_DIR" && $REBUILD = true ]]; then
    cfg_file="$CFG_DIR/$slug.cfg"
    [[ -f "$cfg_file" ]] || { echo "[ERR] Missing cfg for $slug at $cfg_file"; exit 1; }
    if $DRY_RUN; then
      echo "[dry-run] would rebuild $slug via: plugin_build.sh --cfg='$cfg_file' --no-pause"
    else
      bash "$script_dir/plugin_build.sh" --cfg="$cfg_file" --no-pause
    fi
  fi
  [[ -f "$zip_path" ]] || { echo "[ERR] ZIP missing for $slug: $zip_path"; exit 1; }
  ZIPS+=( "$zip_path" )
done

# ---------- git add/commit/push BEFORE release ----------
git_stage_and_push(){
  have_cmd git || { echo "[ERR] git not found"; return 1; }
  git rev-parse --is-inside-work-tree >/dev/null 2>&1 || { echo "[ERR] not a git repo"; return 1; }

  local branch="$PUSH_BRANCH"
  if [[ -z "$branch" || "$branch" == "auto" ]]; then
    branch="$(git rev-parse --abbrev-ref HEAD)"
  fi
  local remote="$PUSH_REMOTE"

  if [[ "${PUSH_ALL,,}" == "true" ]]; then
    $DRY_RUN && echo "[dry-run] git add -A" || git add -A
  else
    if $DRY_RUN; then
      echo "[dry-run] git add $UUPD_DIR/"
      for z in "${ZIPS[@]}"; do echo "[dry-run] git add '$z'"; done
    else
      [[ -d "$UUPD_DIR" ]] && git add "$UUPD_DIR/" 2>/dev/null || true
      for z in "${ZIPS[@]}"; do git add "$z" 2>/dev/null || true; done
    fi
  fi

  if $DRY_RUN; then
    echo "[dry-run] would commit & push to $remote $branch with message: Suite release $SUITE_TAG"
    return 0
  fi

  if git diff --cached --quiet; then
    echo "No changes to commit (uupd/zips already up to date)."
  else
    if [[ -n "$SUITE_NOTES_CONTENT" ]]; then
      git commit -m "Suite release $SUITE_TAG" -m "$SUITE_NOTES_CONTENT"
    else
      git commit -m "Suite release $SUITE_TAG"
    fi
  fi

  git push "$remote" "$branch"
}

echo "Suite tag: $SUITE_TAG"
git_stage_and_push
create_or_get_release "$SUITE_TAG"
echo "Release ready."

for zip_path in "${ZIPS[@]}"; do
  echo "Uploading $(basename "$zip_path")…"
  upload_asset "$SUITE_TAG" "$zip_path"
done

echo "Done. Uploaded ${#ZIPS[@]} asset(s) to $GITHUB_OWNER/$GITHUB_REPO @ $SUITE_TAG."
