import { api } from './api'
import type { RequestOptions } from './api'
import type {
  ApiEnvelope,
  Promotion,
  PromotionPayload,
  PromotionStatus,
  PromotionSummary,
} from '../types/api'

/**
 * Reads every promotion, ordered by start date, most recent first.
 *
 * The order is the API's and is kept as it arrives: re-sorting here would put
 * a second opinion about the listing in a module whose job is to fetch it.
 *
 * Declared at module level for the same reason as the summary reader: a stable
 * identity is what lets `useApiResource` depend on it without the caller
 * memoising anything.
 */
export function fetchPromotions(options?: RequestOptions): Promise<Promotion[]> {
  return api.get<ApiEnvelope<Promotion[]>>('/promotions', options).then((response) => response.data)
}

/**
 * Reads the status counters plus how many promotions run today.
 *
 * The `data` envelope is unwrapped here, at the edge, so nothing above this
 * module has to know that successful responses are wrapped at all.
 *
 * Declared at module level so its identity never changes between renders,
 * which is what lets `useApiResource` take it as a dependency without the
 * caller having to memoise anything.
 */
export function fetchPromotionSummary(options?: RequestOptions): Promise<PromotionSummary> {
  return api
    .get<ApiEnvelope<PromotionSummary>>('/promotions/summary', options)
    .then((response) => response.data)
}

/**
 * Creates a promotion and answers the one the API stored.
 *
 * The answer is returned rather than discarded because it is the only thing
 * that knows the identifier and the status the backend assigned. The screen
 * reads its `name` to say what it created, which is the server's name for it —
 * trimmed by Laravel's middleware — and not the characters that were typed.
 */
export function createPromotion(
  payload: PromotionPayload,
  options?: RequestOptions,
): Promise<Promotion> {
  return api
    .post<ApiEnvelope<Promotion>>('/promotions', payload, options)
    .then((response) => response.data)
}

/**
 * Replaces the editable data of a promotion.
 *
 * `PromotionPayload` has no `status` key and none may be added:
 * `UpdatePromotionRequest` declares it `prohibited` and answers 422 to a
 * payload carrying it, because the lifecycle moves through its own endpoint
 * below. The type is what keeps that from being sent by accident.
 */
export function updatePromotion(
  id: number,
  payload: PromotionPayload,
  options?: RequestOptions,
): Promise<Promotion> {
  return api
    .patch<ApiEnvelope<Promotion>>(`/promotions/${id}`, payload, options)
    .then((response) => response.data)
}

/**
 * Advances the lifecycle of a promotion.
 *
 * Takes the status to move to, which is what the endpoint accepts, and answers
 * the promotion as it now stands. A step the state machine does not allow comes
 * back as a 409 with `INVALID_STATUS_TRANSITION`; deciding which step to ask
 * for belongs to `domain/promotionActions.ts`.
 */
export function transitionPromotionStatus(
  id: number,
  status: PromotionStatus,
  options?: RequestOptions,
): Promise<Promotion> {
  return api
    .patch<ApiEnvelope<Promotion>>(`/promotions/${id}/status`, { status }, options)
    .then((response) => response.data)
}

/**
 * Removes a promotion.
 *
 * Answers nothing, because the endpoint answers 204 with no body. There is no
 * envelope to unwrap and nothing to hand back: the caller already knows which
 * promotion it asked to delete, and that name is what the screen reports.
 */
export function deletePromotion(id: number, options?: RequestOptions): Promise<void> {
  return api.delete<void>(`/promotions/${id}`, options)
}
