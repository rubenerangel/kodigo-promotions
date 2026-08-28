import { ApiError } from '../services/api'
import type { FieldErrors } from '../types/api'

/**
 * What kind of answer a failed write deserves.
 *
 * Three kinds, because the screen has exactly three sensible reactions and the
 * status code is enough to tell them apart:
 *
 * - `invalid` (422): the payload was refused field by field. The dialog stays
 *   open and the messages go beside the controls, because the data the user
 *   typed is still the data they want to send.
 * - `stale` (409 and 404): the promotion is not in the state the screen
 *   believed. Something changed it — another tab, another client, a screen left
 *   open — so the listing on screen is describing a past. Retrying the same
 *   request would fail the same way, and leaving the row as it is would keep
 *   offering actions the server has already refused. The right answer is to
 *   read the data again and say why.
 * - `retryable`: everything else. A connection that dropped, a proxy, a bug.
 *   Nothing was saved as far as the screen can tell, and pressing the same
 *   button again is a reasonable next move.
 *
 * The classification is by status and not by `code`, on purpose. The codes are
 * a catalogue that grows; the three status codes are the contract, and a code
 * this build has never heard of still arrives with a status that says how to
 * treat it. `resolveErrorMessage` remains the one place that turns a code into
 * words.
 */
export type PromotionWriteFailure = 'invalid' | 'stale' | 'retryable'

export function classifyPromotionWriteFailure(error: unknown): PromotionWriteFailure {
  if (!(error instanceof ApiError)) {
    return 'retryable'
  }

  if (error.status === 422) {
    return 'invalid'
  }

  return error.status === 409 || error.status === 404 ? 'stale' : 'retryable'
}

/**
 * The per field messages a failure carried, when it carried any.
 *
 * Kept beside the classification because both read the same object, and
 * because it spares every caller an `instanceof` against the transport.
 */
export function fieldErrorsOf(error: unknown): FieldErrors | null {
  return error instanceof ApiError ? error.fields : null
}
