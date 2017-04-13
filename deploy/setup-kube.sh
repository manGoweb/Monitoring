#!/usr/bin/env bash
set -euo pipefail
IFS=$'\n\t'

mkdir "/config/.kube"
curl -o "/config/.kube/config" "$K8S_CREDENTIALS"
