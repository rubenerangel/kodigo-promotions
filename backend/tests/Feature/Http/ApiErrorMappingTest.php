<?php

namespace Tests\Feature\Http;

use App\Enums\ApiErrorCode;
use App\Enums\DiscountType;
use App\Enums\PromotionStatus;
use App\Exceptions\PromotionStateConflict;
use App\Http\Exceptions\DomainExceptionRenderer;
use App\Models\Product;
use App\Models\Promotion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use ReflectionClass;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * How domain and routing failures reach the client.
 *
 * Everything here travels through a real request: the mapping only earns its
 * keep if the status and the code survive the whole stack, from the use case
 * that raises the exception to the body the caller reads.
 */
class ApiErrorMappingTest extends TestCase
{
    use RefreshDatabase;

    public function test_editing_a_finished_promotion_answers_a_conflict(): void
    {
        $product = Product::create(['name' => 'Café molido 500g']);
        $promotion = $this->promotion(
            'Terminada',
            $product->id,
            startDate: '2026-07-01',
            endDate: '2026-07-31',
            status: PromotionStatus::FINISHED,
        );

        $response = $this->patchJson("/api/promotions/{$promotion->id}", [
            'name' => 'Nombre nuevo',
            'product_id' => $product->id,
            'discount_type' => DiscountType::FIXED_AMOUNT->value,
            'discount_value' => '2500.75',
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-31',
        ]);

        $response->assertStatus(Response::HTTP_CONFLICT);
        $response->assertJsonPath('error.code', ApiErrorCode::PROMOTION_FINISHED->value);
        // The text is the one the domain wrote, not a second wording kept in
        // the HTTP layer that could drift away from it.
        $response->assertJsonPath('error.message', 'A finished promotion cannot be modified.');
        $this->assertHasNoFieldDetail($response->json('error'));

        // A rejected edit must not leave half of the payload behind, so the
        // row is asserted whole rather than by its name alone.
        $this->assertDatabaseHas('promotions', [
            'id' => $promotion->id,
            'name' => 'Terminada',
            'discount_type' => DiscountType::PERCENTAGE->value,
            'discount_value' => '15.00',
            'start_date' => '2026-07-01',
            'end_date' => '2026-07-31',
            'status' => PromotionStatus::FINISHED->value,
        ]);
    }

    public function test_an_invalid_status_jump_answers_a_conflict(): void
    {
        $product = Product::create(['name' => 'Café molido 500g']);
        $promotion = $this->promotion('Programada', $product->id);

        $response = $this->patchJson("/api/promotions/{$promotion->id}/status", [
            'status' => PromotionStatus::FINISHED->value,
        ]);

        $response->assertStatus(Response::HTTP_CONFLICT);
        $response->assertJsonPath('error.code', ApiErrorCode::INVALID_STATUS_TRANSITION->value);
        // Naming both states is what lets a client explain the refusal.
        $response->assertJsonPath('error.message', 'A promotion cannot transition from SCHEDULED to FINISHED.');
        $this->assertHasNoFieldDetail($response->json('error'));

        $this->assertDatabaseHas('promotions', [
            'id' => $promotion->id,
            'status' => PromotionStatus::SCHEDULED->value,
        ]);
    }

    public function test_deleting_a_promotion_that_already_started_answers_a_conflict(): void
    {
        $product = Product::create(['name' => 'Café molido 500g']);
        $promotion = $this->promotion('Activa', $product->id, status: PromotionStatus::ACTIVE);

        $response = $this->deleteJson("/api/promotions/{$promotion->id}");

        $response->assertStatus(Response::HTTP_CONFLICT);
        $response->assertJsonPath('error.code', ApiErrorCode::PROMOTION_NOT_SCHEDULED->value);
        $response->assertJsonPath('error.message', 'Only a SCHEDULED promotion allows this operation, but it is ACTIVE.');
        $this->assertHasNoFieldDetail($response->json('error'));

        $this->assertDatabaseHas('promotions', ['id' => $promotion->id, 'name' => 'Activa']);
    }

    /**
     * Every endpoint carrying an identifier answers the same 404, so a client
     * never has to learn a second shape for the same situation.
     */
    public function test_an_unknown_identifier_answers_not_found_on_every_endpoint(): void
    {
        $missing = 999999;

        $requests = [
            ['PATCH', "/api/promotions/{$missing}"],
            ['PATCH', "/api/promotions/{$missing}/status"],
            ['DELETE', "/api/promotions/{$missing}"],
        ];

        foreach ($requests as [$method, $uri]) {
            $response = $this->json($method, $uri);

            $response->assertStatus(Response::HTTP_NOT_FOUND);
            $response->assertExactJson([
                'error' => [
                    'code' => ApiErrorCode::PROMOTION_NOT_FOUND->value,
                    'message' => 'The requested promotion does not exist.',
                ],
            ]);
        }
    }

    public function test_a_non_numeric_identifier_answers_not_found(): void
    {
        $response = $this->patchJson('/api/promotions/no-numerico');

        $response->assertStatus(Response::HTTP_NOT_FOUND);
        $response->assertJsonPath('error.code', ApiErrorCode::PROMOTION_NOT_FOUND->value);
        // The point of the route constraint: the segment never reached SQL,
        // so nothing about a bigint column can show up in the answer.
        $this->assertStringNotContainsString('SQLSTATE', $response->getContent());
    }

    /**
     * The case that motivated the numeric constraint on {promotion}.
     *
     * Without it this URI matched promotions/{promotion} for DELETE, the
     * binder handed the literal "summary" to a bigint column and PostgreSQL
     * answered with a 500. With the constraint the parameterised route no
     * longer matches, and the router answers 405 because this URI does exist,
     * only for GET. The database is never asked, which is what the body
     * assertions below pin down.
     */
    public function test_deleting_the_summary_uri_never_reaches_the_database(): void
    {
        $response = $this->deleteJson('/api/promotions/summary');

        $this->assertNotSame(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            $response->getStatusCode(),
            'A literal segment must not travel to a bigint column.',
        );
        $response->assertStatus(Response::HTTP_METHOD_NOT_ALLOWED);
        $this->assertStringNotContainsString('SQLSTATE', $response->getContent());
        $this->assertStringNotContainsString('bigint', $response->getContent());
    }

    /**
     * The completeness guard for the class to code table.
     *
     * That table lives in the HTTP layer so the domain never learns about
     * ApiErrorCode, and the price of the choice is that adding a rule and
     * forgetting its code still compiles, still answers 409 and only shows up
     * as a vague code once in production. This test is what makes the
     * omission loud: it enumerates the concrete subclasses on disk, so a rule
     * added tomorrow is covered without anyone remembering to list it here.
     */
    public function test_every_state_conflict_rule_has_a_code_of_its_own(): void
    {
        // Resolved through the container to prove the renderer is autowirable,
        // and not only usable as the instance bootstrap/app.php composes.
        $renderer = $this->app->make(DomainExceptionRenderer::class);
        $request = Request::create('/api/promotions/1', 'PATCH');

        $rules = $this->concreteStateConflicts();

        $this->assertNotEmpty($rules, 'No PromotionStateConflict subclass was found.');

        $codes = [];

        foreach ($rules as $rule) {
            // Built without its constructor: which arguments each rule needs
            // is beside the point, since the mapping is decided by the class.
            $exception = (new ReflectionClass($rule))->newInstanceWithoutConstructor();

            $response = $renderer($exception, $request);

            $this->assertNotNull($response, "{$rule} is not rendered by the HTTP layer.");
            $this->assertSame(Response::HTTP_CONFLICT, $response->getStatusCode(), "{$rule} does not answer 409.");

            $code = json_decode($response->getContent(), true)['error']['code'];

            $this->assertNotSame(
                ApiErrorCode::PROMOTION_STATE_CONFLICT->value,
                $code,
                "{$rule} has no entry in the conflict code table, so it falls back to the generic code.",
            );

            $codes[] = $code;
        }

        // Two rules sharing a code would leave the client unable to tell them
        // apart, which is the whole reason the codes exist.
        $this->assertSame($codes, array_unique($codes), 'Two state rules answer the same code.');
    }

    /**
     * The new handling is registered for the API alone.
     */
    public function test_the_health_check_keeps_its_own_answer(): void
    {
        $this->getJson('/health')
            ->assertOk()
            ->assertExactJson([
                'status' => 'ok',
                'database' => 'ok',
            ]);
    }

    /**
     * A state conflict carries no per field detail, so the key must be absent
     * rather than present and empty.
     *
     * @param  array<string, mixed>  $error
     */
    private function assertHasNoFieldDetail(array $error): void
    {
        $this->assertArrayNotHasKey('fields', $error);
        $this->assertSame(['code', 'message'], array_keys($error));
    }

    /**
     * Every instantiable state rule declared in app/Exceptions.
     *
     * @return list<class-string<PromotionStateConflict>>
     */
    private function concreteStateConflicts(): array
    {
        $rules = [];

        foreach (glob($this->app->path('Exceptions/*.php')) as $file) {
            $class = 'App\\Exceptions\\'.basename($file, '.php');

            if (! class_exists($class)) {
                continue;
            }

            $reflection = new ReflectionClass($class);

            if ($reflection->isAbstract() || ! $reflection->isSubclassOf(PromotionStateConflict::class)) {
                continue;
            }

            $rules[] = $class;
        }

        return $rules;
    }

    private function promotion(
        string $name,
        int $productId,
        string $startDate = '2026-09-01',
        string $endDate = '2026-09-30',
        PromotionStatus $status = PromotionStatus::SCHEDULED,
    ): Promotion {
        return Promotion::create([
            'name' => $name,
            'product_id' => $productId,
            'discount_type' => DiscountType::PERCENTAGE,
            'discount_value' => '15.00',
            'start_date' => $startDate,
            'end_date' => $endDate,
            'status' => $status,
        ]);
    }
}
