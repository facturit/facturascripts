<?php
namespace FacturaScripts\Plugins\McpApi;

use FacturaScripts\Core\Kernel;

class Init
{
    public function init(): void
    {
        Kernel::addRoute('/.well-known/mcp.json', '\\FacturaScripts\\Dinamic\\Controller\\McpWellKnown', 0, 'mcp-well-known');
        Kernel::addRoute('/mcp/oauth/authorize', '\\FacturaScripts\\Dinamic\\Controller\\McpOAuthAuthorize', 0, 'mcp-oauth-authorize');
        Kernel::addRoute('/mcp/oauth/token', '\\FacturaScripts\\Dinamic\\Controller\\McpOAuthToken', 0, 'mcp-oauth-token');
    }

    public function update(): void
    {
        // nothing to update programmatically
    }

    public function uninstall(): void
    {
        // nothing to clean up
    }
}
