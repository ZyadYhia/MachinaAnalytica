<?php

use App\Mcp\Servers\CompressorAirBlower;
use Laravel\Mcp\Facades\Mcp;

// Mcp::web('/mcp/demo', \App\Mcp\Servers\PublicServer::class);
Mcp::web('/mcp/compressor-air-blower', CompressorAirBlower::class);
Mcp::local('compressor-air-blower', CompressorAirBlower::class);
