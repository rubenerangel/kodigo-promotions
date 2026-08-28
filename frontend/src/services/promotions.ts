import { api } from './api'
import type { RequestOptions } from './api'
import type { ApiEnvelope, Promotion, PromotionSummary } from '../types/api'

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
