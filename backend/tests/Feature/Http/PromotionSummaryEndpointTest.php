<?php

namespace Tests\Feature\Http;

use App\Enums\PromotionStatus;
use App\Models\Promotion;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Validity and counters as the API reports them.
 *
 * Health check traceability: `/health` is not exercised here on purpose. It is
 * already covered by HealthCheckTest, which asserts the 200, proves the query
 * really runs by reading the query log, and drives the 503 by pointing the
 * connection at a host that cannot resolve so the driver fails for real.
 * ApiErrorMappingTest additionally pins that the API error envelope never
 * reaches it. Repeating any of that here would raise the test count without
 * raising confidence.
 */
class PromotionSummaryEndpointTest extends TestCase
{
    use RefreshDatabase;

    private const TODAY = '2026-09-15';

    public function test_the_listing_reports_validity_inclusively_on_both_ends(): void
    {
        $this->travelTo(CarbonImmutable::parse(self::TODAY));
        $this->boundarySet();

        // All four in a single response: the contrast between them is the
        // point, and a shared request rules out the reading being an artefact
        // of one call.
        $validity = $this->validityByName();

        $this->assertSame(
            [
                'ended yesterday' => false,
                'ends today' => true,
                'spans today' => true,
                'starts today' => true,
                'starts tomorrow' => false,
            ],
            $validity,
        );
    }

    /**
     * @return list<array{0: string, 1: bool, 2: string}>
     */
    public static function clockPositions(): array
    {
        return [
            'one second before the last midnight' => ['2026-09-30 23:59:59', true, 'the closing day is still inside the range'],
            'one second into the first day' => ['2026-09-01 00:00:01', true, 'the opening day is already inside the range'],
            'one second before the first day' => ['2026-08-31 23:59:59', false, 'the eve is still outside the range'],
        ];
    }

    /**
     * The time of day must not decide validity.
     *
     * A comparison made on instants rather than on days would answer
     * differently at 23:59 than at noon, and the failure would only ever show
     * up at night, where it reads as a timezone problem instead of a bug.
     */
    #[DataProvider('clockPositions')]
    public function test_the_time_of_day_does_not_change_validity(string $now, bool $expected, string $because): void
    {
        $this->travelTo(CarbonImmutable::parse($now));

        Promotion::factory()->forProduct()->create([
            'name' => 'September',
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-30',
        ]);

        $this->assertSame([
            'September' => $expected,
        ], $this->validityByName(), $because);
    }

    /**
     * The listing and the summary must agree on how many promotions run today.
     *
     * They reach the answer by different roads: the listing serialises the
     * rule in PHP for every row, the summary counts it in SQL. A domain level
     * test already pins those two against each other, but only a request to
     * each endpoint proves the screen will not show a counter that contradicts
     * the list printed right under it.
     */
    public function test_the_listing_and_the_summary_agree_on_how_many_run_today(): void
    {
        $this->travelTo(CarbonImmutable::parse(self::TODAY));
        // Boundary dates on purpose: promotions comfortably inside the range
        // would agree even if one of the two roads had its ends wrong.
        $this->boundarySet();

        $countedInTheListing = Collection::make($this->validityByName())
            ->filter()
            ->count();

        $reportedBySummary = $this->getJson('/api/promotions/summary')
            ->assertOk()
            ->json('data.validToday');

        $this->assertSame($countedInTheListing, $reportedBySummary);
        $this->assertSame(3, $reportedBySummary);
    }

    public function test_the_summary_counts_every_status(): void
    {
        $this->travelTo(CarbonImmutable::parse(self::TODAY));

        // Deliberately different amounts, so two swapped counters are visible.
        Promotion::factory()->count(3)->scheduled()->runningOn(self::TODAY)->create();
        Promotion::factory()->count(2)->active()->runningOn(self::TODAY)->create();
        Promotion::factory()->endedBefore(self::TODAY)->finished()->create();

        $this->getJson('/api/promotions/summary')
            ->assertOk()
            ->assertExactJson([
                'data' => [
                    'scheduled' => 3,
                    'active' => 2,
                    'finished' => 1,
                    // Five run today; the finished one ended before it.
                    'validToday' => 5,
                ],
            ]);
    }

    public function test_validity_overlaps_the_status_counters_instead_of_splitting_them(): void
    {
        $this->travelTo(CarbonImmutable::parse(self::TODAY));

        foreach (PromotionStatus::cases() as $status) {
            Promotion::factory()
                ->runningOn(self::TODAY)
                ->state(['status' => $status])
                ->create();
        }

        $this->getJson('/api/promotions/summary')
            ->assertOk()
            ->assertExactJson([
                'data' => [
                    'scheduled' => 1,
                    'active' => 1,
                    'finished' => 1,
                    // Not a fourth slice of the same cake: every promotion is
                    // running today whatever status it was left in.
                    'validToday' => 3,
                ],
            ]);
    }

    public function test_an_empty_table_answers_four_zeros(): void
    {
        $this->getJson('/api/promotions/summary')
            ->assertOk()
            ->assertExactJson([
                'data' => [
                    'scheduled' => 0,
                    'active' => 0,
                    'finished' => 0,
                    'validToday' => 0,
                ],
            ]);
    }

    public function test_the_summary_carries_exactly_the_four_agreed_keys(): void
    {
        $data = $this->getJson('/api/promotions/summary')->assertOk()->json('data');

        // Asserting the key set rather than the presence of each one: a fifth
        // key would be a silent change to the contract the screen reads.
        $this->assertSame(
            ['active', 'finished', 'scheduled', 'validToday'],
            $this->sortedKeys($data),
        );
    }

    /**
     * Five promotions placed around the frozen day, touching both ends.
     */
    private function boundarySet(): void
    {
        $ranges = [
            'starts today' => ['2026-09-15', '2026-09-30'],
            'ends today' => ['2026-09-01', '2026-09-15'],
            'spans today' => ['2026-09-01', '2026-09-30'],
            'ended yesterday' => ['2026-09-01', '2026-09-14'],
            'starts tomorrow' => ['2026-09-16', '2026-09-30'],
        ];

        foreach ($ranges as $name => [$startDate, $endDate]) {
            Promotion::factory()->forProduct()->create([
                'name' => $name,
                'start_date' => $startDate,
                'end_date' => $endDate,
            ]);
        }
    }

    /**
     * Validity of every promotion in the listing, keyed by name and sorted so
     * the assertion does not depend on the order the endpoint returns.
     *
     * @return array<string, bool>
     */
    private function validityByName(): array
    {
        $promotions = $this->getJson('/api/promotions')->assertOk()->json('data');

        $validity = [];

        foreach ($promotions as $promotion) {
            $validity[$promotion['name']] = $promotion['validToday'];
        }

        ksort($validity);

        return $validity;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    private function sortedKeys(array $data): array
    {
        $keys = array_keys($data);
        sort($keys);

        return $keys;
    }
}
