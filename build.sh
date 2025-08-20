#!/usr/bin/env bash
# build.sh — pick cfg(s) and run plugin_build.sh or suite_release.sh from cfg/
set -euo pipefail

err() { echo "[ERR] $*" >&2; }
script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
repo_root="$script_dir"
cfg_dir="$repo_root/cfg"

# sanity checks
[[ -d "$cfg_dir" ]] || { err "cfg folder not found: $cfg_dir"; exit 1; }
[[ -f "$cfg_dir/plugin_build.sh" ]] || { err "plugin_build.sh not found in $cfg_dir"; exit 1; }
[[ -f "$cfg_dir/suite_release.sh" ]] || { err "suite_release.sh not found in $cfg_dir"; exit 1; }

# collect cfg files
shopt -s nullglob
cfg_files=( "$cfg_dir"/*.cfg )
shopt -u nullglob
((${#cfg_files[@]})) || { err "No .cfg files in $cfg_dir"; exit 1; }

echo "========================================="
echo "Select cfg(s) to run:"
for ((i=0; i<${#cfg_files[@]}; i++)); do
  base="$(basename "${cfg_files[$i]}")"
  echo "  $((i+1))) ${base%.*}"
done
echo "  A) All"
echo "  Q) Quit"
echo "========================================="
read -rp "Enter number(s) (e.g. 1,3) or A: " choice || true

[[ "${choice^^}" == "Q" ]] && exit 0
choice="${choice//[[:space:]]/}"

# parse selection
declare -a idxs=()
if [[ "${choice^^}" == "A" ]]; then
  for ((i=0; i<${#cfg_files[@]}; i++)); do idxs+=("$i"); done
else
  IFS=',' read -r -a picks <<< "$choice"
  for p in "${picks[@]}"; do
    [[ "$p" =~ ^[0-9]+$ ]] || { err "Invalid token: $p"; exit 1; }
    n=$((p-1))
    (( n>=0 && n<${#cfg_files[@]} )) || { err "Out of range: $p"; exit 1; }
    idxs+=("$n")
  done
fi

# flags (split into arrays)
read -rp "Extra flags for plugin_build.sh [--no-pause]: " plugin_flags_line || true
plugin_flags_line="${plugin_flags_line:---no-pause}"
read -rp "Extra flags for suite_release.sh [-]: " suite_flags_line || true
read -r -a plugin_flags <<< "$plugin_flags_line"
read -r -a suite_flags  <<< "${suite_flags_line:-}"

echo
echo "Repo root     : $repo_root"
echo "CFG dir       : $cfg_dir"
echo

for i in "${idxs[@]}"; do
  cfg="${cfg_files[$i]}"
  base="$(basename "$cfg" .cfg)"
  mode="PLUGIN"
  if [[ "${base,,}" == "suite" || "${base,,}" == *"-suite" ]]; then
    mode="SUITE"
  fi
  echo "=== Running: $(basename "$cfg") [$mode] ==="
  if [[ "$mode" == "SUITE" ]]; then
    bash "$cfg_dir/suite_release.sh" --cfg="$cfg" "${suite_flags[@]}"
  else
    bash "$cfg_dir/plugin_build.sh" --cfg="$cfg" "${plugin_flags[@]}"
  fi
  echo
done

echo "Done."

