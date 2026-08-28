<?php

namespace App\Http\Requests;

use App\Enums\ApiErrorCode;
use App\Enums\PromotionStatus;
use App\Http\Responses\ApiErrorResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

/**
 * Input accepted when advancing the status of a promotion.
 *
 * Only the shape of the payload is checked here. Whether the lifecycle allows
 * the requested step is a domain rule and stays in the use case, so this
 * request never grows knowledge of which transitions exist.
 */
class TransitionPromotionStatusRequest extends FormRequest
{
    /**
     * The project exposes no authentication, so every caller may transition.
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
            'status' => ['required', Rule::enum(PromotionStatus::class)],
        ];
    }

    /**
     * The requested status, already turned into its domain type.
     *
     * Converting here keeps the controller free of any knowledge about how the
     * status travels over HTTP.
     */
    public function status(): PromotionStatus
    {
        return PromotionStatus::from($this->validated()['status']);
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
}
