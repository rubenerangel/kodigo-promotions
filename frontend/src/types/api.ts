/**
 * Wire contract of the promotions API.
 *
 * Every type here mirrors a backend shape one to one. Nothing in this module
 * is presentation: labels, wording and formatting belong to the copy
 * catalogue, so the contract can change without dragging the interface with
 * it, and the interface can be reworded without touching the contract.
 */

/** Lifecycle state of a promotion. Mirrors `App\Enums\PromotionStatus`. */
export const PROMOTION_STATUSES = ['SCHEDULED', 'ACTIVE', 'FINISHED'] as const

export type PromotionStatus = (typeof PROMOTION_STATUSES)[number]

/**
 * Discount variants. Mirrors `App\Enums\DiscountType`.
 *
 * Written as a list rather than as a bare union, like the statuses above, so
 * the form can offer one control per variant by walking it instead of
 * repeating the two names in a second place that nothing keeps in step.
 */
export const DISCOUNT_TYPES = ['PERCENTAGE', 'FIXED_AMOUNT'] as const

export type DiscountType = (typeof DISCOUNT_TYPES)[number]

/**
 * The two things a promotion can be aimed at. Mirrors the two implementations
 * of `App\Domain\Targets\PromotionTarget`.
 */
export const PROMOTION_TARGET_TYPES = ['product', 'category'] as const

export type PromotionTargetType = (typeof PROMOTION_TARGET_TYPES)[number]

/** What a promotion applies to: exactly one product or one category. */
export type PromotionTarget = {
  type: PromotionTargetType
  id: number
  name: string
}

/**
 * A single promotion as the API serialises it.
 *
 * `discountValue` is a string because the column is `decimal(10, 2)` and the
 * backend emits it as text so the exact decimal survives JSON encoding. It is
 * kept as text here too: turning it into a `number` on arrival would undo that
 * on the very first parse.
 */
export type Promotion = {
  id: number
  name: string
  target: PromotionTarget
  discountType: DiscountType
  discountValue: string
  /** `YYYY-MM-DD`. Date only: the column is DATE and carries no time. */
  startDate: string
  /** `YYYY-MM-DD`. */
  endDate: string
  status: PromotionStatus
  validToday: boolean
}

/**
 * Counters answered by `GET /api/promotions/summary`.
 *
 * The three status counters partition the collection and add up to the total.
 * `validToday` does not: it counts promotions whose date range covers today,
 * which may sit in any status, so it overlaps the other three.
 */
export type PromotionSummary = {
  scheduled: number
  active: number
  finished: number
  validToday: number
}

/** Successful responses carry their payload under a `data` key. */
export type ApiEnvelope<T> = {
  data: T
}

/**
 * Machine readable failure codes. Mirrors `App\Enums\ApiErrorCode`.
 *
 * The interface branches on these instead of matching message text, which is
 * what lets the API answer in English while the screen speaks Spanish.
 */
export const API_ERROR_CODES = [
  'VALIDATION_FAILED',
  'PROMOTION_NOT_FOUND',
  'PROMOTION_FINISHED',
  'INVALID_STATUS_TRANSITION',
  'PROMOTION_NOT_SCHEDULED',
  'PROMOTION_STATE_CONFLICT',
] as const

export type ApiErrorCode = (typeof API_ERROR_CODES)[number]

/** Per field validation messages, keyed by the field that failed. */
export type FieldErrors = Record<string, string[]>

/**
 * One selectable target: the identifier the API expects and the label the user
 * reads. Answered for products and for categories alike, since the form treats
 * both the same way once it knows which kind it is choosing from.
 */
export type PromotionTargetOption = {
  id: number
  name: string
}

/** Answer of `GET /api/promotion-targets`, already sorted by the API. */
export type PromotionTargetCatalog = {
  products: PromotionTargetOption[]
  categories: PromotionTargetOption[]
}

/**
 * The target half of a write payload: `product_id` or `category_id`, never
 * both and never neither.
 *
 * The optional `never` on the opposite key is what makes that a compile time
 * guarantee and not a convention. `{ product_id: 3, category_id: 1 }` matches
 * neither member of the union, so it cannot be built, in the same way the
 * backend's `prohibits:` rules reject it at runtime. This is the wire level
 * echo of the domain rule: `ProductTarget` and `CategoryTarget` are two
 * classes, each carrying one identifier, and an ambiguous target is not
 * something the system rejects, it is something it cannot express.
 */
export type PromotionPayloadTarget =
  | { product_id: number; category_id?: never }
  | { category_id: number; product_id?: never }

/**
 * The body sent to `POST /api/promotions` and to
 * `PATCH /api/promotions/{id}`.
 *
 * Keys are snake_case because these are the field names the Form Requests
 * validate, not names this interface chooses. The rest of the client speaks
 * camelCase, and the crossing happens here, at the wire.
 *
 * THERE IS NO `status` KEY, AND NONE MAY BE ADDED. `UpdatePromotionRequest`
 * declares `status` as `prohibited`, so sending it answers 422 and the whole
 * edit is lost. That is deliberate on the backend's part: the lifecycle moves
 * through `PATCH /api/promotions/{id}/status`, which is the only place the
 * allowed transitions are enforced. A `status` added here would look like a
 * missing field being filled in and would break every edit.
 *
 * `discount_value` is text for the same reason the API answers it as text: the
 * column is `decimal(10, 2)`, and a round trip through a JavaScript number is
 * exactly where the exact decimal would be lost.
 *
 * The dates are `YYYY-MM-DD`, the value an `<input type="date">` already
 * produces, carried through untouched. See `format/promotionDate.ts` for what
 * happens when such a string is turned into a `Date` on the way.
 */
export type PromotionPayload = PromotionPayloadTarget & {
  name: string
  discount_type: DiscountType
  discount_value: string
  start_date: string
  end_date: string
}
