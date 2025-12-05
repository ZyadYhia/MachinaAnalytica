<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\CompressorAirBlowerReadings;
use Laravel\Mcp\Server;

class CompressorAirBlower extends Server
{
    /**
     * The MCP server's name.
     */
    protected string $name = 'Compressor Air Blower';

    /**
     * The MCP server's version.
     */
    protected string $version = '0.0.1';

    /**
     * The MCP server's instructions for the LLM.
     */
    protected string $instructions = <<<'MARKDOWN'
        This server provides tools to monitor and analyze compressor air blower sensor data.
        You can retrieve readings including flow, temperature, pressure, vibration, and operational status.
        Use the available tools to query historical data, analyze trends, and monitor system health.
    MARKDOWN;

    /**
     * The tools registered with this MCP server.
     *
     * @var array<int, class-string<\Laravel\Mcp\Server\Tool>>
     */
    protected array $tools = [
        CompressorAirBlowerReadings::class,
    ];

    /**
     * The resources registered with this MCP server.
     *
     * @var array<int, class-string<\Laravel\Mcp\Server\Resource>>
     */
    protected array $resources = [
        //
    ];

    /**
     * The prompts registered with this MCP server.
     *
     * @var array<int, class-string<\Laravel\Mcp\Server\Prompt>>
     */
    protected array $prompts = [
        //
    ];
}
