<?php

namespace Tests\Feature\Http;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * RefreshDatabase is deliberately absent: the endpoint touches no table, and
 * the failure case purges the connection, which does not mix well with the
 * transaction that trait opens.
 */
class HealthCheckTest extends TestCase
{
    public function test_it_answers_ok_when_the_database_responds(): void
    {
        $this->getJson('/health')
            ->assertOk()
            ->assertExactJson([
                'status' => 'ok',
                'database' => 'ok',
            ]);
    }

    public function test_it_really_queries_the_database(): void
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->getJson('/health')->assertOk();

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        // A health check that never touches the database would answer 200 just
        // as happily, so the query itself is part of the contract.
        $this->assertNotEmpty($queries);
        $this->assertSame('select 1', $queries[0]['query']);
    }

    public function test_it_answers_service_unavailable_when_the_database_is_unreachable(): void
    {
        // Point the connection at a host that cannot resolve and drop the
        // cached one, so the driver fails for real instead of a double
        // reporting failure on its behalf.
        config(['database.connections.pgsql.host' => 'no-such-host.invalid']);
        DB::purge('pgsql');

        $this->getJson('/health')
            ->assertStatus(503)
            ->assertExactJson([
                'status' => 'error',
                'database' => 'unavailable',
            ]);
    }
}
