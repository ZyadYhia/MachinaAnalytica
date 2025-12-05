#!/bin/bash
# MCP Compressor Air Blower Server Wrapper
# This ensures the correct PHP binary is used
cd "$(dirname "$0")"
/opt/homebrew/bin/php artisan mcp:start compressor-air-blower
