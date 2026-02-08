#!/usr/bin/env bash

set -e

INTERVAL=60

echo "Starting WP cron worker (every ${INTERVAL}s)..."

while true; do
  echo "Running WP-Cron at $(date)"
  wp-env run cli wp cron event run --due-now
  sleep $INTERVAL
done
