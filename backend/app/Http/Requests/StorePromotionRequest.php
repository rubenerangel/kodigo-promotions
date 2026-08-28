<?php

namespace App\Http\Requests;

use App\DataTransferObjects\CreatePromotionData;
use App\Domain\Targets\CategoryTarget;
use App\Domain\Targets\ProductTarget;
use App\Domain\Targets\PromotionTarget;
use App\Enums\ApiErrorCode;
use App\Enums\DiscountType;
use App\Http\Responses\ApiErrorResponse;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Fluent;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

/**
 * Input accepted when creating a promotion.
 *
 * The rules deliberately restate the CHECK constraints of the promotions
 * table. That is not accidental duplication: validation exists to answer a
 * readable 422, while the constraints remain the last line of defence for any
 * write that never travels through HTTP.
 */
class StorePromotionRequest extends FormRequest
{
    /**
     * The project exposes no authentication, so every caller may create.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            // required_without and prohibits are declared on both sides on
            // purpose: together they reject a payload carrying no target and a
            // payload carrying both.
            'product_id' => [
                'nullable',
                'integer',
                'required_without:category_id',
                'prohibits:category_id',
                // Resolving the id here turns an unknown target into a 422
                // instead of a PostgreSQL foreign key violation.
                'exists:products,id',
            ],
            'category_id' => [
                'nullable',
                'integer',
                'required_without:product_id',
                'prohibits:product_id',
                'exists:categories,id',
            ],
            'discount_type' => ['required', Rule::enum(DiscountType::class)],
            'discount_value' => [
                'required',
                'numeric',
                'gt:0',
                // The 1 to 100 range only makes sense for a percentage; a fixed
                // amount is free to be any positive figure.
                Rule::when(
                    fn (Fluent $input): bool => $input->get('discount_type') === DiscountType::PERCENTAGE->value,
                    ['min:1', 'max:100'],
                ),
            ],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after:start_date'],
        ];
    }

    /**
     * Translate the validated payload into the domain input of the use case.
     */
    public function toData(): CreatePromotionData
    {
        $validated = $this->validated();

        return new CreatePromotionData(
            name: $validated['name'],
            target: $this->target($validated),
            discountType: DiscountType::from($validated['discount_type']),
            // The DTO holds the decimal as text so no float rounding happens
            // between the request and the column.
            discountValue: (string) $validated['discount_value'],
            startDate: CarbonImmutable::parse($validated['start_date']),
            endDate: CarbonImmutable::parse($validated['end_date']),
        );
    }

    /**
     * Answer the API wide error body instead of the framework default.
     */
    protected function failedValidation(Validator $validator): void
    {
        $errors = $this->container->make(ApiErrorResponse::class);

        throw new HttpResponseException($errors->build(
            ApiErrorCode::VALIDATION_FAILED,
            'The given data was invalid.',
            Response::HTTP_UNPROCESSABLE_ENTITY,
            $validator->errors()->toArray(),
        ));
    }

    /**
     * Pick the target variant the payload describes.
     *
     * This is the one place a conditional on the variant belongs: turning an
     * HTTP payload into the right domain object is precisely the job of this
     * layer, and it happens once, at the edge. Past this point every consumer
     * sees a PromotionTarget and never asks which one it holds.
     *
     * @param  array<string, mixed>  $validated
     */
    private function target(array $validated): PromotionTarget
    {
        $productId = $validated['product_id'] ?? null;

        return $productId !== null
            ? new ProductTarget((int) $productId)
            : new CategoryTarget((int) $validated['category_id']);
    }
}
