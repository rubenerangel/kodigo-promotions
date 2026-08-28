<?php

namespace App\Http\Requests;

use App\DataTransferObjects\UpdatePromotionData;
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
 * Input accepted when updating a promotion.
 *
 * The field rules match creation because an update replaces the same editable
 * data. The two requests are kept apart rather than chained by inheritance:
 * they build different DTOs, so one could not stand in for the other, and
 * sharing a parent would let a change meant for creation reach updates
 * silently.
 */
class UpdatePromotionRequest extends FormRequest
{
    /**
     * The project exposes no authentication, so every caller may update.
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
            'product_id' => [
                'nullable',
                'integer',
                'required_without:category_id',
                'prohibits:category_id',
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
                Rule::when(
                    fn (Fluent $input): bool => $input->get('discount_type') === DiscountType::PERCENTAGE->value,
                    ['min:1', 'max:100'],
                ),
            ],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after:start_date'],
            // Status advances through its own endpoint, which enforces the
            // allowed transitions. Rejecting it here is louder than dropping
            // it, so a caller never believes a state change was applied.
            'status' => ['prohibited'],
        ];
    }

    /**
     * Translate the validated payload into the domain input of the use case.
     */
    public function toData(): UpdatePromotionData
    {
        $validated = $this->validated();

        return new UpdatePromotionData(
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
     * The conditional belongs at this edge, once, so no use case downstream
     * has to ask which variant it received.
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
