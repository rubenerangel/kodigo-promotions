<?php

namespace App\Domain;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Reports whether the database actually answers.
 *
 * The connection arrives by constructor instead of through the DB facade, so
 * the check depends on the abstraction the container already provides.
 */
final readonly class DatabaseHealth
{
    public function __construct(private ConnectionInterface $connection) {}

    /**
     * Run a real query against the database.
     *
     * A trivial statement is enough: what matters is that the driver opens the
     * connection and the server answers, which a configuration check alone
     * would never prove. Any failure means unavailable, so the catch is
     * deliberately broad.
     */
    public function isAvailable(): bool
    {
        try {
            $this->connection->select('select 1');

            return true;
        } catch (Throwable $exception) {
            // Recording the reason is what turns a red health check into a
            // diagnosis. Swallowing it silently once cost an hour of guessing
            // why a container that migrated fine could not serve a request.
            Log::warning('Database health check failed: '.$exception->getMessage());

            return false;
        }
    }
}
