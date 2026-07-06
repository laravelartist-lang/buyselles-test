#!/bin/bash

###############################################################################
# BuySelles Horizon Starter Script
#
# For production, use Supervisor (see supervisor-worker.conf).
# This script is for local development only.
###############################################################################

echo "=========================================="
echo "  BuySelles Horizon Starter"
echo "=========================================="
echo ""

cd /home/awais-koder/WorkStation/projects/buyselles_bundle/buyselles

echo "Checking Redis connection..."
if redis-cli ping > /dev/null 2>&1; then
    echo "✓ Redis is running"
else
    echo "✗ Redis is NOT running!"
    echo "  Starting Redis..."
    sudo systemctl start redis
    if redis-cli ping > /dev/null 2>&1; then
        echo "✓ Redis started successfully"
    else
        echo "✗ Failed to start Redis. Please check Redis installation."
        exit 1
    fi
fi

echo ""
echo "Starting Horizon..."
echo "Dashboard: \${APP_URL:-http://localhost}/horizon (admin login required)"
echo "Press Ctrl+C to stop"
echo "=========================================="
echo ""

php artisan horizon
