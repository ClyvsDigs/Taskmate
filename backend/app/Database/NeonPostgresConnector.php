<?php

namespace App\Database;

use Illuminate\Database\Connectors\PostgresConnector;
use PDO;

/**
 * Adds Neon's endpoint ID to the PDO DSN.
 *
 * Older libpq builds (including the one bundled with PHP on Windows) do not
 * send SNI, so Neon requires the endpoint ID to be supplied through the
 * connection's "options" startup parameter. Laravel's default server options
 * are emitted as "-c name=value", which Neon explicitly rejects for this
 * parameter, so the raw "endpoint=<id>" form is appended here instead.
 */
class NeonPostgresConnector extends PostgresConnector
{
    /**
     * Get the PDO options for the connection.
     *
     * Neon aborts a transaction when DDL is issued through a server-side
     * prepared statement, which breaks migrations. Emulated prepares avoid
     * that problem and are limited to Neon connections here.
     *
     * @param  array  $config
     * @return array
     */
    public function getOptions(array $config)
    {
        $options = parent::getOptions($config);

        if ($this->resolveEndpoint($config) !== null) {
            $options[PDO::ATTR_EMULATE_PREPARES] = true;
        }

        return $options;
    }

    /**
     * Add the Neon endpoint option to the DSN.
     *
     * @param  string  $dsn
     * @param  array  $config
     * @return string
     */
    protected function addServerOptions($dsn, array $config)
    {
        $endpoint = $this->resolveEndpoint($config);

        if ($endpoint !== null) {
            $endpoint = str_replace(['\\', "'"], ['\\\\', "\\'"], $endpoint);

            $dsn .= ";options='endpoint={$endpoint}'";
        }

        return parent::addServerOptions($dsn, $config);
    }

    /**
     * Resolve the Neon endpoint ID from the config or the connection host.
     *
     * @param  array  $config
     * @return string|null
     */
    protected function resolveEndpoint(array $config)
    {
        if (! empty($config['neon_endpoint'])) {
            return (string) $config['neon_endpoint'];
        }

        $host = $config['host'] ?? null;

        if (is_string($host) && preg_match('/^(ep-[a-z0-9-]+)\./', $host, $matches)) {
            return $matches[1];
        }

        return null;
    }
}
