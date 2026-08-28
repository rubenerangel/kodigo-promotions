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

/** Discount variants. Mirrors `App\Enums\DiscountType`. */
export type DiscountType = 'PERCENTAGE' | 'FIXED_AMOUNT'

/** What a promotion applies to: exactly one product or one category. */
export type PromotionTarget = {
  type: 'product' | 'category'
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
