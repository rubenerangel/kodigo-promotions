import { api } from './api'
import type { RequestOptions } from './api'
import type { ApiEnvelope, PromotionTargetCatalog } from '../types/api'

/**
 * Reads the products and the categories a promotion may be aimed at.
 *
 * Both lists travel under one URI, `promotion-targets`, and not behind a
 * `/products` and a `/categories` of their own: this project exposes no
 * product API, and the form needs both lists at once to fill a single select.
 *
 * The API already sorts each list alphabetically in Spanish, accents included,
 * and the order is kept exactly as it arrives. Re-sorting here would put a
 * second opinion about the catalogue inside the module whose job is to fetch
 * it, and `Array.prototype.sort` without a collator would place "Té verde"
 * after "Zumo".
 *
 * Declared at module level, like the other readers, so its identity never
 * changes between renders and `useApiResource` can depend on it without the
 * caller memoising anything.
 */
export function fetchPromotionTargets(options?: RequestOptions): Promise<PromotionTargetCatalog> {
  return api
    .get<ApiEnvelope<PromotionTargetCatalog>>('/promotion-targets', options)
    .then((response) => response.data)
}
