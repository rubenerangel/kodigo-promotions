import { copy } from './es'
import { ApiError, NetworkError } from '../services/api'

/**
 * Picks the Spanish text for a failure.
 *
 * This is the single crossing between the API's vocabulary and the user's.
 * It reads `code` and nothing else: the API's `message` is English, meant for
 * a log, and never reaches the screen. A code this build does not recognise
 * arrives here as `null` and falls back to the unexpected text, which is the
 * honest answer when there is no wording for what happened.
 */
export function resolveErrorMessage(error: unknown): string {
  if (error instanceof NetworkError) {
    return copy.errors.network
  }

  if (error instanceof ApiError && error.code !== null) {
    return copy.errors[error.code]
  }

  return copy.errors.unexpected
}
