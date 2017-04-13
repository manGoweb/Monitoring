#!/usr/bin/env bash
set -euo pipefail
IFS=$'\n\t'
DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
ROOT_DIR="$(dirname "$DIR")"

cd "$ROOT_DIR"

rm -rf build.zip
zip -r build.zip . \
	-x '*.DS_Store*' \
	-x '.git/*' \
	-x '.github/*' \
	-x '.idea/*' \
	-x 'node_modules/*' \
	-x 'log/*' \
	-x 'temp/*' \
	-x 'vendor/*/doc/*' \
	-x 'vendor/*/docs/*' \
	-x 'vendor/*/test/*' \
	-x 'vendor/*/tests/*'

# add empty dirs
mkdir temp/cache || true
zip build.zip log/
zip build.zip temp/{,cache}

set -x

# add prod config
LOCAL="app/config/config.local.neon"
mv "$LOCAL.bak" "$LOCAL" || true
mv "$LOCAL" "$LOCAL.bak" || true
cp config/prod.neon "$LOCAL"
zip build.zip "$LOCAL"
mv "$LOCAL.bak" "$LOCAL" || true


HASH="$(openssl dgst -md5 build.zip | awk '{print $2}')"
S3_URI="s3://build.mangoweb.org/monitoring/$HASH.zip"

aws s3 cp build.zip "$S3_URI"


function kube-patch() {
	kubectl --namespace "monitoring" patch "$@"
}

kube-patch configmap "config" -p \
	'{"data": {"src.uri": "'"$S3_URI"'"}}'

kube-patch deployment "app-deployment" -p \
  '{"spec":{"template":{"metadata":{"labels":{"date":"'"$(date +'%s')"'"}}}}}'
