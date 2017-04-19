#!/usr/bin/env bash
set -euo pipefail
IFS=$'\n\t'
DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"

cd "$(dirname "$DIR")"

function loop {
	while true; do
		php www/index.php "$@" || true
	done &
}

function loop-every {
	while true; do
		php www/index.php "${@:2}" || true
        sleep "$1"
	done &
}

php www/index.php migrations:continue
php www/index.php rabbitmq:setup-fabric

# assuming 70 http checks, period of 350
# averages to one monitoring request per 5 seconds
loop-every  350 pd:monitoring:check:publish:alive-checks
loop-every 1800 pd:monitoring:check:publish:certificate-checks
loop-every  600 pd:monitoring:check:publish:dns-a-checks
loop-every  600 pd:monitoring:check:publish:dns-cname-checks
loop-every  600 pd:monitoring:check:publish:feed-checks
loop-every   60 pd:monitoring:check:publish:rabbit-consumer-checks
loop-every   30 pd:monitoring:check:slack-check-statuses

loop rabbitmq:consume aliveCheck
loop rabbitmq:consume dnsACheck
loop rabbitmq:consume dnsCnameCheck
loop rabbitmq:consume certificateCheck
loop rabbitmq:consume feedCheck
loop rabbitmq:consume rabbitConsumerCheck

# All loops run as separate processes in parallel.
# Wait until all sub-processes end, which should be never.
wait
