<?php

namespace Tests\Feature\Http;

use App\Enums\ApiErrorCode;
use App\Enums\DiscountType;
use App\Enums\PromotionStatus;
use App\Models\Category;
use App\Models\Product;
use App\Models\Promotion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * Editing seen from the outside: what the caller receives and what the row
 * looks like afterwards.
 *
 * The use case is already covered in UpdatePromotionTest and the rules
 * against the validator in UpdatePromotionRequestTest. What only a real
 * request can answer is whether an edit reaches a promotion that is already
 * running, whether a rejected one is truly a no-op, whether the status a
 * caller sneaks into the body is refused out loud, and whether moving the
 * target between its two columns clears the one it left. That is what this
 * file asserts.
 *
 * It is kept apart from PromotionLifecycleTest for the same reason
 * CreatePromotionEndpointTest is its own file: this is about what a promotion
 * says, that one is about where it is in its life.
 */
class UpdatePromotionEndpointTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Target of the default payload, built once per test.
     */
    private ?Product $product = null;

    /**
     * A running promotion is still editable, and stays running.
     *
     * Both halves belong to the same request: the endpoint replaces the
     * editable fields and never touches the lifecycle, which advances through
     * its own endpoint. Asserting the status in the answer and in the row is
     * what separates "left alone" from "written back with the same value it
     * happened to have".
     */
    public function test_an_active_promotion_is_editable_and_keeps_its_status(): void
    {
        $promotion = Promotion::factory()->active()->create();
        $product = Product::factory()->create();

        $response = $this->patchJson("/api/promotions/{$promotion->id}", [
            'name' => 'Nombre nuevo',
            'product_id' => $product->id,
            'discount_type' => DiscountType::FIXED_AMOUNT->value,
            'discount_value' => '2500.75',
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-31',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.id', $promotion->id);
        $response->assertJsonPath('data.name', 'Nombre nuevo');
        $response->assertJsonPath('data.target', [
            'type' => 'product',
            'id' => $product->id,
            'name' => $product->name,
        ]);
        $response->assertJsonPath('data.discountType', DiscountType::FIXED_AMOUNT->value);
        $response->assertJsonPath('data.discountValue', '2500.75');
        $response->assertJsonPath('data.startDate', '2026-10-01');
        $response->assertJsonPath('data.endDate', '2026-10-31');
        $response->assertJsonPath('data.status', PromotionStatus::ACTIVE->value);

        $this->assertDatabaseHas('promotions', [
            'id' => $promotion->id,
            'name' => 'Nombre nuevo',
            'product_id' => $product->id,
            'category_id' => null,
            'discount_type' => DiscountType::FIXED_AMOUNT->value,
            'discount_value' => '2500.75',
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-31',
            // The edit endpoint has no say in the lifecycle.
            'status' => PromotionStatus::ACTIVE->value,
        ]);
    }

    /**
     * A status in the edit body is a 422 filed under `status`.
     *
     * The rule is `prohibited` rather than a silently dropped field so a
     * caller is never left believing the state moved. The field key matters
     * as much as the code: it is what lets a client point at the offending
     * input instead of showing a generic failure. The whole key set is
     * compared so an untouched field complaining alongside it would fail too.
     */
    public function test_a_status_in_the_edit_payload_is_rejected_under_its_own_field(): void
    {
        $promotion = Promotion::factory()->scheduled()->create();

        $response = $this->patchJson("/api/promotions/{$promotion->id}", $this->payload([
            'status' => PromotionStatus::ACTIVE->value,
        ]));

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
        $response->assertJsonPath('error.code', ApiErrorCode::VALIDATION_FAILED->value);
        $this->assertSame(['status'], $this->reportedFields($response->json('error.fields')));

        // The point of refusing instead of ignoring: nothing moved.
        $this->assertSame(PromotionStatus::SCHEDULED, Promotion::findOrFail($promotion->id)->status);
    }

    /**
     * The target moves between its two columns, and the one it leaves is
     * cleared.
     *
     * Both directions travel on the same promotion because the exclusive
     * target is a database constraint: a write that set the new column
     * without clearing the old one would not merely report a stale target, it
     * would fail outright on the way back. Asserting the response and the
     * cleared column at each hop is what tells those two failures apart.
     */
    public function test_an_edit_moves_the_target_between_columns_and_clears_the_one_it_left(): void
    {
        $product = Product::factory()->create();
        $category = Category::factory()->create();
        $promotion = Promotion::factory()->forProduct($product)->create();

        $toCategory = $this->patchJson("/api/promotions/{$promotion->id}", $this->payload([
            'product_id' => null,
            'category_id' => $category->id,
        ]));

        $toCategory->assertOk();
        $toCategory->assertJsonPath('data.target', [
            'type' => 'category',
            'id' => $category->id,
            'name' => $category->name,
        ]);
        $this->assertDatabaseHas('promotions', [
            'id' => $promotion->id,
            'category_id' => $category->id,
            'product_id' => null,
        ]);

        $backToProduct = $this->patchJson("/api/promotions/{$promotion->id}", $this->payload([
            'product_id' => $product->id,
        ]));

        $backToProduct->assertOk();
        $backToProduct->assertJsonPath('data.target', [
            'type' => 'product',
            'id' => $product->id,
            'name' => $product->name,
        ]);
        $this->assertDatabaseHas('promotions', [
            'id' => $promotion->id,
            'product_id' => $product->id,
            'category_id' => null,
        ]);
    }

    /**
     * A rejected edit is a no-op, not a partial write.
     *
     * The row is compared whole instead of by the field the payload was about:
     * a rule that ran after some columns were already written would leave the
     * others changed, and no single field assertion would notice. The table is
     * counted as well, so an edit that wrote a second row rather than
     * modifying the first could not pass either.
     *
     * @param  array<string, mixed>  $overrides
     */
    #[DataProvider('rejectedPayloads')]
    public function test_a_rejected_edit_leaves_the_row_and_the_table_untouched(array $overrides): void
    {
        Promotion::factory()->count(2)->create();
        $promotion = Promotion::factory()->scheduled()->create();

        $before = Promotion::findOrFail($promotion->id)->getAttributes();
        $rows = Promotion::count();

        $response = $this->patchJson("/api/promotions/{$promotion->id}", $this->payload($overrides));

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
        $response->assertJsonPath('error.code', ApiErrorCode::VALIDATION_FAILED->value);

        $this->assertSame(3, $rows);
        $this->assertSame($rows, Promotion::count());
        $this->assertSame($before, Promotion::findOrFail($promotion->id)->getAttributes());
    }

    /**
     * Four unrelated reasons to reject, so the guarantee is not tied to the
     * one rule that happens to run first.
     *
     * @return array<string, array{array<string, mixed>}>
     */
    public static function rejectedPayloads(): array
    {
        return [
            'an empty name' => [['name' => '']],
            'no target at all' => [['product_id' => null]],
            'the range ends before it starts' => [['start_date' => '2026-10-31', 'end_date' => '2026-10-01']],
            'a percentage above the range' => [['discount_value' => '101']],
        ];
    }

    /**
     * A payload that would be accepted untouched, so each case above differs
     * from a valid edit only in the field it is about.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Nombre nuevo',
            'product_id' => $this->product()->id,
            'discount_type' => DiscountType::PERCENTAGE->value,
            'discount_value' => '20',
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-31',
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
