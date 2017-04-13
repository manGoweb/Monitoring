#!/usr/bin/env bash
set -euo pipefail
IFS=$'\n\t'

KEY="/tmp/git-crypt-key"

git status

curl -o "$KEY" "$MGW_CRYPT_URI"
git-crypt unlock "$KEY"
