<?php

namespace Tests\Feature\Http\Requests;

use App\DataTransferObjects\CreatePromotionData;
use App\Domain\Targets\CategoryTarget;
use App\Domain\Targets\ProductTarget;
use App\Enums\ApiErrorCode;
use App\Enums\DiscountType;
use App\Http\Requests\StorePromotionRequest;
use App\Http\Responses\ApiErrorResponse;
use App\Models\Category;
use App\Models\Product;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class StorePromotionRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_accepts_a_product_as_the_only_target(): void
    {
        $product = Product::create(['name' => 'Café molido 500g']);

        $this->assertPasses(['product_id' => $product->id]);
    }

    public function test_it_accepts_a_category_as_the_only_target(): void
    {
        $category = Category::create(['name' => 'Bebidas']);

        $this->assertPasses(['category_id' => $category->id]);
    }

    public function test_it_rejects_a_payload_without_any_target(): void
    {
        $this->assertFailsFor(['product_id', 'category_id'], []);
    }

    public function test_it_rejects_a_payload_carrying_both_targets(): void
    {
        $product = Product::create(['name' => 'Café molido 500g']);
        $category = Category::create(['name' => 'Bebidas']);

        $this->assertFailsFor(
            ['product_id', 'category_id'],
            ['product_id' => $product->id, 'category_id' => $category->id],
        );
    }

    public function test_it_rejects_a_product_that_does_not_exist(): void
    {
        $this->assertFailsFor(['product_id'], ['product_id' => 9999]);
    }

    public function test_it_rejects_a_category_that_does_not_exist(): void
    {
        $this->assertFailsFor(['category_id'], ['category_id' => 9999]);
    }

    /**
     * The percentage range is inclusive on both ends.
     */
    public function test_it_accepts_the_percentage_boundaries(): void
    {
        $product = Product::create(['name' => 'Café molido 500g']);

        foreach (['1', '100'] as $value) {
            $this->assertPasses([
                'product_id' => $product->id,
                'discount_type' => DiscountType::PERCENTAGE->value,
                'discount_value' => $value,
            ]);
        }
    }

    public function test_it_rejects_a_percentage_outside_the_range(): void
    {
        $product = Product::create(['name' => 'Café molido 500g']);

        foreach (['0.5', '101'] as $value) {
            $this->assertFailsFor(['discount_value'], [
                'product_id' => $product->id,
                'discount_type' => DiscountType::PERCENTAGE->value,
                'discount_value' => $value,
            ]);
        }
    }

    /**
     * The range belongs to percentages only, so a fixed amount well past 100
     * must go through untouched.
     */
    public function test_it_accepts_a_fixed_amount_above_the_percentage_range(): void
    {
        $product = Product::create(['name' => 'Café molido 500g']);

        $this->assertPasses([
            'product_id' => $product->id,
            'discount_type' => DiscountType::FIXED_AMOUNT->value,
            'discount_value' => '2500.75',
        ]);
    }

    public function test_it_rejects_a_discount_value_that_is_not_positive(): void
    {
        $product = Product::create(['name' => 'Café molido 500g']);

        foreach (DiscountType::cases() as $type) {
            foreach (['0', '-10'] as $value) {
                $this->assertFailsFor(['discount_value'], [
                    'product_id' => $product->id,
                    'discount_type' => $type->value,
                    'discount_value' => $value,
                ]);
            }
        }
    }

    public function test_it_rejects_an_unknown_discount_type(): void
    {
        $product = Product::create(['name' => 'Café molido 500g']);

        $this->assertFailsFor(['discount_type'], [
            'product_id' => $product->id,
            'discount_type' => 'BUY_ONE_GET_ONE',
        ]);
    }

    public function test_it_accepts_an_end_date_after_the_start_date(): void
    {
        $product = Product::create(['name' => 'Café molido 500g']);

        $this->assertPasses([
            'product_id' => $product->id,
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-02',
        ]);
    }

    public function test_it_rejects_an_end_date_equal_to_the_start_date(): void
    {
        $product = Product::create(['name' => 'Café molido 500g']);

        $this->assertFailsFor(['end_date'], [
            'product_id' => $product->id,
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-01',
        ]);
    }

    public function test_it_rejects_an_end_date_before_the_start_date(): void
    {
        $product = Product::create(['name' => 'Café molido 500g']);

        $this->assertFailsFor(['end_date'], [
            'product_id' => $product->id,
            'start_date' => '2026-09-30',
            'end_date' => '2026-09-01',
        ]);
    }

    public function test_it_rejects_a_payload_missing_every_required_field(): void
    {
        $validator = Validator::make([], $this->request()->rules());

        $this->assertTrue($validator->fails());

        $errors = $validator->errors()->toArray();

        foreach (['name', 'discount_type', 'discount_value', 'start_date', 'end_date'] as $field) {
            $this->assertArrayHasKey($field, $errors);
        }
    }

    public function test_it_maps_a_product_payload_to_a_product_target(): void
    {
        $product = Product::create(['name' => 'Café molido 500g']);

        $data = $this->dataFrom(['product_id' => $product->id]);

        $this->assertInstanceOf(ProductTarget::class, $data->target);
        $this->assertSame(
            ['product_id' => $product->id, 'category_id' => null],
            $data->target->columns(),
        );
    }

    public function test_it_maps_a_category_payload_to_a_category_target(): void
    {
        $category = Category::create(['name' => 'Bebidas']);

        $data = $this->dataFrom(['category_id' => $category->id]);

        $this->assertInstanceOf(CategoryTarget::class, $data->target);
        $this->assertSame(
            ['product_id' => null, 'category_id' => $category->id],
            $data->target->columns(),
        );
    }

    public function test_it_maps_the_remaining_fields_to_their_domain_types(): void
    {
        $product = Product::create(['name' => 'Café molido 500g']);

        $data = $this->dataFrom([
            'product_id' => $product->id,
            'name' => 'Promoción de temporada',
            'discount_type' => DiscountType::FIXED_AMOUNT->value,
            'discount_value' => '2500.75',
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-30',
        ]);

        $this->assertInstanceOf(CreatePromotionData::class, $data);
        $this->assertSame('Promoción de temporada', $data->name);
        $this->assertSame(DiscountType::FIXED_AMOUNT, $data->discountType);
        // Text, not float: the decimal has to survive untouched.
        $this->assertSame('2500.75', $data->discountValue);
        $this->assertInstanceOf(CarbonImmutable::class, $data->startDate);
        $this->assertInstanceOf(CarbonImmutable::class, $data->endDate);
        $this->assertSame('2026-09-01', $data->startDate->toDateString());
        $this->assertSame('2026-09-30', $data->endDate->toDateString());
    }

    public function test_it_answers_the_api_error_body_when_validation_fails(): void
    {
        $request = $this->request([]);

        try {
            $request->validateResolved();
            $this->fail('Validation was expected to fail.');
        } catch (HttpResponseException $exception) {
            $response = $exception->getResponse();
            $body = json_decode((string) $response->getContent(), true);

            $this->assertSame(422, $response->getStatusCode());
            $this->assertSame(ApiErrorCode::VALIDATION_FAILED->value, $body['error']['code']);
            $this->assertSame('The given data was invalid.', $body['error']['message']);
            $this->assertArrayHasKey('name', $body['error']['fields']);
        }
    }

    public function test_the_error_body_carries_code_message_and_fields(): void
    {
        $response = $this->errorResponse()->build(
            ApiErrorCode::VALIDATION_FAILED,
            'The given data was invalid.',
            422,
            ['discount_value' => ['El valor debe estar entre 1 y 100.']],
        );

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame([
            'error' => [
                'code' => 'VALIDATION_FAILED',
                'message' => 'The given data was invalid.',
                'fields' => [
                    'discount_value' => ['El valor debe estar entre 1 y 100.'],
                ],
            ],
        ], json_decode((string) $response->getContent(), true));
    }

    public function test_the_error_body_omits_the_fields_key_when_there_are_no_field_errors(): void
    {
        $response = $this->errorResponse()->build(
            ApiErrorCode::VALIDATION_FAILED,
            'The given data was invalid.',
            422,
        );

        $body = json_decode((string) $response->getContent(), true);

        $this->assertArrayNotHasKey('fields', $body['error']);
        $this->assertSame(['code', 'message'], array_keys($body['error']));
    }

    /**
     * Resolve from the container to prove the builder needs no manual binding.
     */
    private function errorResponse(): ApiErrorResponse
    {
        return $this->app->make(ApiErrorResponse::class);
    }

    /**
     * A request bound to the container, so rules and validation can run
     * without a routed HTTP call, which only arrives with the controllers.
     *
     * @param  array<string, mixed>  $payload
     */
    private function request(array $payload = []): StorePromotionRequest
    {
        $request = StorePromotionRequest::create('/', 'POST', $payload);
        $request->setContainer($this->app);

        return $request;
    }

    /**
     * Valid input with the target left out, so each test states its own.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Promoción de temporada',
            'discount_type' => DiscountType::PERCENTAGE->value,
            'discount_value' => '15',
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-30',
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function assertPasses(array $overrides): void
    {
        $validator = Validator::make($this->payload($overrides), $this->request()->rules());

        $this->assertFalse(
            $validator->fails(),
            'Expected the payload to pass, got: '.json_encode($validator->errors()->toArray()),
        );
    }

    /**
     * @param  list<string>  $fields
     * @param  array<string, mixed>  $overrides
     */
    private function assertFailsFor(array $fields, array $overrides): void
    {
        $validator = Validator::make($this->payload($overrides), $this->request()->rules());

        $this->assertTrue($validator->fails(), 'Expected the payload to fail.');

        $errors = $validator->errors()->toArray();

        foreach ($fields as $field) {
            $this->assertArrayHasKey($field, $errors);
        }
    }

    /**
     * Run the whole request lifecycle and hand back the domain input.
     *
     * @param  array<string, mixed>  $overrides
     */
    private function dataFrom(array $overrides): CreatePromotionData
    {
        $request = $this->request($this->payload($overrides));
        $request->validateResolved();

        return $request->toData();
    }
}
