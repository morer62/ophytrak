#!/bin/bash
set -e
cd "$(dirname "$0")"
mkdir -p .logs
php bin/command.php marketplace-sync >> .logs/marketplace-sync.log 2>&1
