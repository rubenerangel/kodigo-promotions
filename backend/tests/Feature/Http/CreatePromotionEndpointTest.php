<?php

namespace Tests\Feature\Http;

use App\Enums\ApiErrorCode;
use App\Enums\DiscountType;
use App\Enums\PromotionStatus;
use App\Models\Category;
use App\Models\Product;
use App\Models\Promotion;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * Creation seen from the outside: what the caller receives and what the
 * database keeps afterwards.
 *
 * The rules themselves are already covered against the validator in
 * StorePromotionRequestTest, and the use case in CreatePromotionTest. What
 * neither of them can answer is whether a rejected request leaves the table
 * as it was, whether each complaint travels under the field key a client will
 * read it from, and whether the boundaries behave the same once the whole
 * stack is in the way. That is what this file asserts, and nothing else.
 */
class CreatePromotionEndpointTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Product the default payload targets, built once per test.
     */
    private ?Product $product = null;

    /**
     * A rejected creation must be a no-op, not a partial write.
     *
     * The table is counted before and after instead of looking the payload up
     * by name: a row written with different data than the one sent would slip
     * straight past assertDatabaseMissing, which is precisely the failure this
     * test exists to catch. The surviving ids are compared as well, so
     * deleting one row while writing another could not pass either.
     *
     * @param  array<string, mixed>  $overrides
     */
    #[DataProvider('rejectedPayloads')]
    public function test_a_rejected_creation_leaves_the_promotions_table_untouched(array $overrides): void
    {
        $existing = Promotion::factory()->count(2)->create();

        $before = Promotion::count();

        $response = $this->postJson('/api/promotions', $this->payload($overrides));

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
        $response->assertJsonPath('error.code', ApiErrorCode::VALIDATION_FAILED->value);

        $this->assertSame(2, $before);
        $this->assertSame($before, Promotion::count());
        $this->assertSame($existing->pluck('id')->all(), Promotion::orderBy('id')->pluck('id')->all());
    }

    /**
     * Three unrelated reasons to reject, so the guarantee is not tied to the
     * one rule that happens to run first.
     *
     * @return array<string, array{array<string, mixed>}>
     */
    public static function rejectedPayloads(): array
    {
        return [
            'no target at all' => [['product_id' => null]],
            'the range ends before it starts' => [['start_date' => '2026-09-30', 'end_date' => '2026-09-01']],
            'a percentage above the range' => [['discount_value' => '101']],
        ];
    }

    /**
     * The key a message arrives under is part of the contract: the form binds
     * each one to an input, so an error filed under the wrong field would be
     * invisible to the user even though the request was answered correctly.
     *
     * The whole key set is compared, not just the presence of the expected
     * one, so a rule leaking a complaint about an untouched field fails here.
     *
     * @param  array<string, mixed>  $overrides
     * @param  list<string>  $expectedFields
     */
    #[DataProvider('rejectionsByField')]
    public function test_each_rejection_is_reported_under_its_own_field(array $overrides, array $expectedFields): void
    {
        $response = $this->postJson('/api/promotions', $this->payload($overrides));

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertSame($expectedFields, $this->reportedFields($response->json('error.fields')));
    }

    /**
     * @return array<string, array{array<string, mixed>, list<string>}>
     */
    public static function rejectionsByField(): array
    {
        return [
            'an empty name' => [['name' => ''], ['name']],
            // Neither target is present, so both sides of required_without
            // complain and the client may highlight either input.
            'no target at all' => [['product_id' => null], ['category_id', 'product_id']],
            'an unknown discount type' => [['discount_type' => 'HALF_OFF'], ['discount_type']],
            'a discount value that is not positive' => [['discount_value' => '0'], ['discount_value']],
            // The end date is dragged in because `after:start_date` cannot
            // compare against a date it failed to read.
            'a start date that is not a date' => [['start_date' => 'ayer'], ['end_date', 'start_date']],
            'an end date equal to the start date' => [['end_date' => '2026-09-01'], ['end_date']],
        ];
    }

    /**
     * A payload with nothing in it must describe everything that is missing.
     *
     * Answering only the first missing field would force the caller through
     * one round trip per field, which is a worse contract than the same 422
     * carrying the whole list.
     */
    public function test_an_empty_payload_reports_every_required_field_at_once(): void
    {
        $response = $this->postJson('/api/promotions', []);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertSame(
            ['category_id', 'discount_type', 'discount_value', 'end_date', 'name', 'product_id', 'start_date'],
            $this->reportedFields($response->json('error.fields')),
        );
    }

    /**
     * Two targets is a complaint about the targets, not about anything else.
     */
    public function test_sending_both_targets_is_reported_under_the_target_fields(): void
    {
        $category = Category::factory()->create();

        $response = $this->postJson('/api/promotions', $this->payload(['category_id' => $category->id]));

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertSame(['category_id', 'product_id'], $this->reportedFields($response->json('error.fields')));
    }

    /**
     * The percentage band over HTTP, both ends included and both sides out.
     *
     * The fixed amount case is the one that proves the band belongs to the
     * percentage alone: 2500.75 is far outside 1 to 100 and still accepted.
     */
    #[DataProvider('discountBoundaries')]
    public function test_the_discount_boundaries_answer_over_http(
        DiscountType $discountType,
        string $discountValue,
        int $expectedStatus,
    ): void {
        $response = $this->postJson('/api/promotions', $this->payload([
            'discount_type' => $discountType->value,
            'discount_value' => $discountValue,
        ]));

        $response->assertStatus($expectedStatus);

        $expectedStatus === Response::HTTP_CREATED
            ? $this->assertDatabaseCount('promotions', 1)
            : $this->assertDatabaseCount('promotions', 0);
    }

    /**
     * @return array<string, array{DiscountType, string, int}>
     */
    public static function discountBoundaries(): array
    {
        return [
            'a percentage of 1 is the lower bound' => [DiscountType::PERCENTAGE, '1', Response::HTTP_CREATED],
            'a percentage of 100 is the upper bound' => [DiscountType::PERCENTAGE, '100', Response::HTTP_CREATED],
            'a percentage below 1 is rejected' => [DiscountType::PERCENTAGE, '0.5', Response::HTTP_UNPROCESSABLE_ENTITY],
            'a percentage above 100 is rejected' => [DiscountType::PERCENTAGE, '101', Response::HTTP_UNPROCESSABLE_ENTITY],
            'a fixed amount ignores the percentage band' => [DiscountType::FIXED_AMOUNT, '2500.75', Response::HTTP_CREATED],
        ];
    }

    /**
     * The range is exclusive at its lower end: a promotion lasting no days is
     * not a promotion, while the very next day already is one.
     */
    #[DataProvider('dateBoundaries')]
    public function test_the_date_boundaries_answer_over_http(string $endDate, int $expectedStatus): void
    {
        $response = $this->postJson('/api/promotions', $this->payload([
            'start_date' => '2026-09-01',
            'end_date' => $endDate,
        ]));

        $response->assertStatus($expectedStatus);
    }

    /**
     * @return array<string, array{string, int}>
     */
    public static function dateBoundaries(): array
    {
        return [
            'the same day is rejected' => ['2026-09-01', Response::HTTP_UNPROCESSABLE_ENTITY],
            'one day later is accepted' => ['2026-09-02', Response::HTTP_CREATED],
        ];
    }

    /**
     * An unknown target is a payload the caller can fix, so it must read as a
     * 422 naming the field. Letting the id reach the column instead would
     * answer a 500 raised by the foreign key, which tells the caller nothing
     * and looks like an outage.
     */
    public function test_an_unknown_product_is_a_validation_error_and_not_a_foreign_key_failure(): void
    {
        $this->assertNull(Product::find(999999));

        $response = $this->postJson('/api/promotions', $this->payload(['product_id' => 999999]));

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
        $response->assertJsonPath('error.code', ApiErrorCode::VALIDATION_FAILED->value);
        $this->assertSame(['product_id'], $this->reportedFields($response->json('error.fields')));
        $this->assertDatabaseCount('promotions', 0);
    }

    public function test_a_product_promotion_is_returned_and_persisted_as_scheduled(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-15'));

        $product = Product::factory()->create();

        $response = $this->postJson('/api/promotions', [
            'name' => 'Promoción de temporada',
            'product_id' => $product->id,
            'discount_type' => DiscountType::PERCENTAGE->value,
            'discount_value' => '15.50',
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-30',
        ]);

        $response->assertCreated();
        // sole() doubles as the proof that the request wrote exactly one row.
        $promotion = Promotion::sole();

        $response->assertJsonPath('data', [
            'id' => $promotion->id,
            'name' => 'Promoción de temporada',
            'target' => ['type' => 'product', 'id' => $product->id, 'name' => $product->name],
            'discountType' => 'PERCENTAGE',
            'discountValue' => '15.50',
            'startDate' => '2026-09-01',
            'endDate' => '2026-09-30',
            'status' => 'SCHEDULED',
            'validToday' => true,
        ]);

        $this->assertDatabaseHas('promotions', [
            'id' => $promotion->id,
            'name' => 'Promoción de temporada',
            'product_id' => $product->id,
            'category_id' => null,
            'discount_type' => DiscountType::PERCENTAGE->value,
            'discount_value' => '15.50',
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-30',
            // The lifecycle always begins here; the payload has no say in it.
            'status' => PromotionStatus::SCHEDULED->value,
        ]);
    }

    public function test_a_category_promotion_is_persisted_with_no_product(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-15'));

        $category = Category::factory()->create();

        $response = $this->postJson('/api/promotions', [
            'name' => 'Bebidas de verano',
            'category_id' => $category->id,
            'discount_type' => DiscountType::FIXED_AMOUNT->value,
            'discount_value' => '1200.50',
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-30',
        ]);

        $response->assertCreated();
        $promotion = Promotion::sole();

        $response->assertJsonPath('data', [
            'id' => $promotion->id,
            'name' => 'Bebidas de verano',
            'target' => ['type' => 'category', 'id' => $category->id, 'name' => $category->name],
            'discountType' => 'FIXED_AMOUNT',
            'discountValue' => '1200.50',
            'startDate' => '2026-09-01',
            'endDate' => '2026-09-30',
            'status' => 'SCHEDULED',
            'validToday' => true,
        ]);

        $this->assertDatabaseHas('promotions', [
            'id' => $promotion->id,
            'name' => 'Bebidas de verano',
            'category_id' => $category->id,
            'discount_type' => DiscountType::FIXED_AMOUNT->value,
            'discount_value' => '1200.50',
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-30',
            'status' => PromotionStatus::SCHEDULED->value,
        ]);
        $this->assertNull($promotion->product_id);
    }

    /**
     * Guards the fixtures the rest of this file is built on.
     *
     * The target columns are exclusive at the database level, so a target
     * state that set its own column without clearing the other one would fail
     * the constraint the moment it were chained after the opposite state.
     * Asserting the cleared column, rather than that create() did not throw,
     * is what makes that visible here instead of somewhere unrelated.
     */
    public function test_the_promotion_factory_targets_one_column_at_a_time(): void
    {
        $byDefault = Promotion::factory()->create();
        $forCategory = Promotion::factory()->forCategory()->create();
        $forProduct = Promotion::factory()->forCategory()->forProduct()->create();

        $this->assertNotNull($byDefault->product_id);
        $this->assertNull($byDefault->category_id);

        $this->assertNotNull($forCategory->category_id);
        $this->assertNull($forCategory->product_id);

        $this->assertNotNull($forProduct->product_id);
        $this->assertNull($forProduct->category_id);
    }

    /**
     * A payload that would be accepted untouched, so each case above differs
     * from a valid creation only in the field it is about.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Promoción de temporada',
            'product_id' => $this->product()->id,
            'discount_type' => DiscountType::PERCENTAGE->value,
            'discount_value' => '15',
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-30',
        ], $overrides);
    }

    /**
     * The target of the default payload, created on first use.
     */
    private function product(): Product
    {
        return $this->product ??= Product::factory()->create();
    }

    /**
     * Field keys of an error body, sorted so the assertions do not depend on
     * the order the rules happen to be declared in.
     *
     * @param  array<string, list<string>>|null  $fields
     * @return list<string>
     */
    private function reportedFields(?array $fields): array
    {
        $this->assertIsArray($fields, 'The error body carries no field detail.');

        $keys = array_keys($fields);
        sort($keys);

        return $keys;
    }
}
