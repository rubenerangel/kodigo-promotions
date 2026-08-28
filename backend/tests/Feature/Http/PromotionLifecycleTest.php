<?php

namespace Tests\Feature\Http;

use App\Enums\ApiErrorCode;
use App\Enums\DiscountType;
use App\Enums\PromotionStatus;
use App\Models\Promotion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * The lifecycle of a promotion as a client experiences it, and what it forbids.
 *
 * The transition table itself is already proven against the enum in
 * PromotionStatusTest, and each refusal against its use case in the Actions
 * suite. What no test answers yet is whether a promotion can actually be
 * walked from one end of its life to the other through the API, and whether
 * the door really closes behind it: three different operations refusing the
 * same finished promotion, each under a code of its own. That, and the
 * promise every guard makes -- a refusal writes nothing -- is what this file
 * asserts.
 */
class PromotionLifecycleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The whole journey in one narrative: SCHEDULED, ACTIVE, FINISHED.
     *
     * Each hop is checked twice, in the answer and in the row, because a
     * response built from the in-memory model would look identical to a
     * persisted change while leaving the database behind.
     */
    public function test_a_promotion_travels_from_scheduled_to_finished_over_http(): void
    {
        $promotion = Promotion::factory()->scheduled()->create();

        $this->assertSame(PromotionStatus::SCHEDULED, $this->stored($promotion->id)->status);

        $this->assertTransitionApplied($promotion->id, PromotionStatus::ACTIVE);
        $this->assertTransitionApplied($promotion->id, PromotionStatus::FINISHED);
    }

    /**
     * Once the journey ends, all three write endpoints refuse the promotion.
     *
     * The promotion is walked there through the API rather than seeded as
     * FINISHED, so the lock is proven to be the consequence of the trip and
     * not of a fixture. The three codes are gathered and compared as one map:
     * asserting them one by one would still pass if the mapping collapsed and
     * answered a single code for every refusal, which is exactly the failure
     * that would leave a client unable to explain what happened.
     */
    public function test_a_promotion_that_reached_the_end_is_locked_for_every_operation(): void
    {
        $promotion = Promotion::factory()->scheduled()->create();

        $this->assertTransitionApplied($promotion->id, PromotionStatus::ACTIVE);
        $this->assertTransitionApplied($promotion->id, PromotionStatus::FINISHED);

        $codes = [
            'edit' => $this->refusedCode(
                $this->patchJson("/api/promotions/{$promotion->id}", $this->editPayload($promotion)),
            ),
            'delete' => $this->refusedCode(
                $this->deleteJson("/api/promotions/{$promotion->id}"),
            ),
            'transition' => $this->refusedCode(
                $this->patchJson("/api/promotions/{$promotion->id}/status", [
                    'status' => PromotionStatus::ACTIVE->value,
                ]),
            ),
        ];

        $this->assertSame([
            'edit' => ApiErrorCode::PROMOTION_FINISHED->value,
            'delete' => ApiErrorCode::PROMOTION_NOT_SCHEDULED->value,
            'transition' => ApiErrorCode::INVALID_STATUS_TRANSITION->value,
        ], $codes);

        // Stated separately from the map above so the reason is on the record:
        // three refusals sharing one code would tell a client nothing about
        // which of the three doors it just found closed.
        $this->assertSame($codes, array_unique($codes), 'Two refusals answer the same code.');

        // The three refusals were refusals, not partial work.
        $this->assertSame(PromotionStatus::FINISHED, $this->stored($promotion->id)->status);
    }

    /**
     * A step the lifecycle forbids answers 409 and writes nothing.
     *
     * The row is compared whole, `updated_at` included: no exclusion is
     * needed because the use case checks the transition before touching the
     * model, so a refusal must not even bump the timestamp. Excluding it
     * would hide precisely the kind of half-applied write this test exists to
     * catch.
     *
     * The two cases are the ones HTTP had never seen: standing still and
     * going backwards. The forward jump that skips a step already travels
     * this path in ApiErrorMappingTest, and the complete table belongs to the
     * enum test.
     */
    #[DataProvider('forbiddenTransitions')]
    public function test_a_forbidden_transition_answers_a_conflict_and_leaves_the_row_untouched(
        PromotionStatus $from,
        PromotionStatus $to,
    ): void {
        $promotion = $this->promotionIn($from);
        $before = $this->row($promotion->id);

        $response = $this->patchJson("/api/promotions/{$promotion->id}/status", ['status' => $to->value]);

        $response->assertStatus(Response::HTTP_CONFLICT);
        $response->assertJsonPath('error.code', ApiErrorCode::INVALID_STATUS_TRANSITION->value);

        $this->assertArrayHasKey('updated_at', $before, 'The comparison must cover the timestamp too.');
        $this->assertSame($before, $this->row($promotion->id));
    }

    /**
     * @return array<string, array{PromotionStatus, PromotionStatus}>
     */
    public static function forbiddenTransitions(): array
    {
        return [
            'standing still is not a step' => [PromotionStatus::SCHEDULED, PromotionStatus::SCHEDULED],
            'the lifecycle never runs backwards' => [PromotionStatus::ACTIVE, PromotionStatus::SCHEDULED],
        ];
    }

    /**
     * Deletion is reserved for promotions that never ran.
     *
     * The ACTIVE case is already covered in ApiErrorMappingTest; the terminal
     * state is the one left, and it is the one a client is most likely to try
     * to tidy away.
     */
    public function test_deleting_a_finished_promotion_answers_a_conflict_and_keeps_the_row(): void
    {
        $promotion = Promotion::factory()->finished()->create();
        $before = $this->row($promotion->id);

        $response = $this->deleteJson("/api/promotions/{$promotion->id}");

        $response->assertStatus(Response::HTTP_CONFLICT);
        $response->assertJsonPath('error.code', ApiErrorCode::PROMOTION_NOT_SCHEDULED->value);

        // Read back rather than inferred from the status code: an endpoint
        // that deleted the row and then failed to answer would look the same
        // from the outside.
        $stored = Promotion::find($promotion->id);

        $this->assertNotNull($stored, 'A refused deletion removed the promotion anyway.');
        $this->assertSame(PromotionStatus::FINISHED, $stored->status);
        $this->assertSame($before, $stored->getAttributes());
    }

    /**
     * A deletion that succeeds is visible everywhere the promotion was.
     *
     * The listing and the counter are read from the same API a client uses,
     * so a row removed from the table but still served from somewhere else
     * would be caught here. The summary is only asked for the counter this
     * deletion moves; the rest of it belongs to its own test.
     */
    public function test_a_deleted_promotion_leaves_the_listing_and_lowers_its_counter(): void
    {
        $promotions = Promotion::factory()->count(3)->scheduled()->create();
        $removed = $promotions->first();

        $this->assertSame(3, $this->scheduledCounter());

        $this->deleteJson("/api/promotions/{$removed->id}")->assertNoContent();

        $listed = array_column($this->getJson('/api/promotions')->assertOk()->json('data'), 'id');

        $this->assertNotContains($removed->id, $listed);
        $this->assertCount(2, $listed);
        $this->assertSame(2, $this->scheduledCounter());
    }

    /**
     * The identifier of a deleted promotion names nothing, which is a 404.
     *
     * A client retrying a request it never saw the answer to must not be told
     * the server broke. The body is inspected as well: the second attempt
     * must fail at the model binding, before any statement is sent, so
     * nothing about the database can show up in the answer.
     */
    public function test_deleting_the_same_promotion_twice_answers_not_found(): void
    {
        $promotion = Promotion::factory()->scheduled()->create();

        $this->deleteJson("/api/promotions/{$promotion->id}")->assertNoContent();

        $response = $this->deleteJson("/api/promotions/{$promotion->id}");

        $response->assertStatus(Response::HTTP_NOT_FOUND);
        $response->assertJsonPath('error.code', ApiErrorCode::PROMOTION_NOT_FOUND->value);
        $this->assertStringNotContainsString('SQLSTATE', $response->getContent());
    }

    /**
     * Ask for a transition and check it landed, in the answer and in the row.
     */
    private function assertTransitionApplied(int $id, PromotionStatus $target): void
    {
        $response = $this->patchJson("/api/promotions/{$id}/status", ['status' => $target->value]);

        $response->assertOk();
        $response->assertJsonPath('data.id', $id);
        $response->assertJsonPath('data.status', $target->value);

        $this->assertSame($target, $this->stored($id)->status);
    }

    /**
     * The error code of an answer that must be a 409.
     */
    private function refusedCode(TestResponse $response): string
    {
        $response->assertStatus(Response::HTTP_CONFLICT);

        return $response->json('error.code');
    }

    /**
     * A promotion seeded in the given status through the factory states.
     */
    private function promotionIn(PromotionStatus $status): Promotion
    {
        $factory = match ($status) {
            PromotionStatus::SCHEDULED => Promotion::factory()->scheduled(),
            PromotionStatus::ACTIVE => Promotion::factory()->active(),
            PromotionStatus::FINISHED => Promotion::factory()->finished(),
        };

        return $factory->create();
    }

    /**
     * The promotion as the database holds it right now.
     */
    private function stored(int $id): Promotion
    {
        return Promotion::findOrFail($id);
    }

    /**
     * Every persisted column of a promotion, raw, for a before and after
     * comparison that no single field assertion could stand in for.
     *
     * @return array<string, mixed>
     */
    private function row(int $id): array
    {
        return $this->stored($id)->getAttributes();
    }

    /**
     * How many promotions the summary counts as scheduled.
     */
    private function scheduledCounter(): int
    {
        return $this->getJson('/api/promotions/summary')->assertOk()->json('data.scheduled');
    }

    /**
     * An edit payload that would be accepted if the promotion allowed edits,
     * so a refusal can only come from the state and never from validation.
     *
     * @return array<string, mixed>
     */
    private function editPayload(Promotion $promotion): array
    {
        return [
            'name' => 'Nombre nuevo',
            'product_id' => $promotion->product_id,
            'discount_type' => DiscountType::FIXED_AMOUNT->value,
            'discount_value' => '2500.75',
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-31',
        ];
    }
}
