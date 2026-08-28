import { API_ERROR_CODES } from '../types/api'
import type { ApiErrorCode, FieldErrors } from '../types/api'

const apiBaseUrl = (import.meta.env.VITE_API_URL ?? 'http://localhost:8000/api').replace(/\/$/, '')

/**
 * A failure the API answered with, carrying its machine readable detail.
 *
 * `message` keeps the raw body for logs and debugging; it is written in
 * English and is never rendered. Callers branch on `code` and read `fields`,
 * and the copy catalogue turns that into the Spanish the user reads.
 *
 * Both are nullable on purpose: a 502 from a proxy or a crashed server does
 * not answer the envelope, and the client must survive that instead of
 * assuming every failure is well formed.
 */
export class ApiError extends Error {
  readonly status: number

  /** The API error code, or `null` when the body was not the error envelope. */
  readonly code: ApiErrorCode | null

  /** Per field messages, present only on validation failures. */
  readonly fields: FieldErrors | null

  constructor(
    message: string,
    status: number,
    code: ApiErrorCode | null = null,
    fields: FieldErrors | null = null,
  ) {
    super(message)
    this.name = 'ApiError'
    this.status = status
    this.code = code
    this.fields = fields
  }
}

/**
 * The request never reached the API: DNS, a refused connection, CORS, an
 * offline browser.
 *
 * Kept apart from `ApiError` because there is no status and no body to reason
 * about, and because the two deserve different words: "the server is
 * unreachable" is actionable, "unexpected error" is not.
 */
export class NetworkError extends Error {
  constructor(cause?: unknown) {
    super('The API could not be reached.', { cause })
    this.name = 'NetworkError'
  }
}

/** Options a caller may pass through to `fetch`. */
export type RequestOptions = {
  signal?: AbortSignal
}

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null && !Array.isArray(value)
}

function readCode(value: unknown): ApiErrorCode | null {
  // Narrowed against the known catalogue rather than trusted as-is: a code
  // this build does not know has no Spanish text either, so treating it as
  // unrecognised is what the screen would do with it anyway.
  return typeof value === 'string' && (API_ERROR_CODES as readonly string[]).includes(value)
    ? (value as ApiErrorCode)
    : null
}

/**
 * Reads `fields` only when every entry is a list of strings.
 *
 * A partially malformed map is rejected whole: half a map would let a caller
 * believe a field passed validation when the truth is that the body could not
 * be understood.
 */
function readFields(value: unknown): FieldErrors | null {
  if (!isRecord(value)) {
    return null
  }

  const fields: FieldErrors = {}

  for (const [field, messages] of Object.entries(value)) {
    if (!Array.isArray(messages) || !messages.every((message) => typeof message === 'string')) {
      return null
    }

    fields[field] = messages as string[]
  }

  return fields
}

/**
 * Turns a failed response into an `ApiError`, parsing the envelope when the
 * body carries one.
 *
 * Every step degrades instead of throwing: a body that is empty, is not JSON,
 * or is JSON of another shape still produces an `ApiError` with the status,
 * which is the one thing always known.
 */
async function toApiError(response: Response): Promise<ApiError> {
  let body = ''

  try {
    body = await response.text()
  } catch {
    // A truncated or aborted body must not mask the status we already have.
  }

  let parsed: unknown

  try {
    parsed = body === '' ? null : JSON.parse(body)
  } catch {
    parsed = null
  }

  const error = isRecord(parsed) && isRecord(parsed.error) ? parsed.error : null

  if (error === null) {
    return new ApiError(body || 'The API request failed.', response.status)
  }

  const message = typeof error.message === 'string' ? error.message : body

  return new ApiError(
    message || 'The API request failed.',
    response.status,
    readCode(error.code),
    // Absent on anything but a validation failure, so a missing key is the
    // normal case and not a parsing problem.
    'fields' in error ? readFields(error.fields) : null,
  )
}

async function request<T>(path: string, options?: RequestInit): Promise<T> {
  let response: Response

  try {
    response = await fetch(`${apiBaseUrl}/${path.replace(/^\//, '')}`, {
      ...options,
      // After the spread, not before: a caller passing `headers` would
      // otherwise replace the defaults wholesale instead of adding to them,
      // and a request would silently go out without its Accept header.
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        ...options?.headers,
      },
    })
  } catch (cause) {
    // A caller that aborted on purpose gets its own rejection back untouched:
    // dressing a cancellation up as a connection failure would make a stale
    // request look like an outage.
    if (options?.signal?.aborted) {
      throw cause
    }

    throw new NetworkError(cause)
  }

  if (!response.ok) {
    throw await toApiError(response)
  }

  if (response.status === 204) {
    return undefined as T
  }

  return response.json() as Promise<T>
}

export const api = {
  get<T>(path: string, options?: RequestOptions): Promise<T> {
    return request<T>(path, options)
  },

  post<T>(path: string, body: unknown, options?: RequestOptions): Promise<T> {
    return request<T>(path, {
      ...options,
      method: 'POST',
      body: JSON.stringify(body),
    })
  },

  patch<T>(path: string, body: unknown, options?: RequestOptions): Promise<T> {
    return request<T>(path, {
      ...options,
      method: 'PATCH',
      body: JSON.stringify(body),
    })
  },

  delete<T>(path: string, options?: RequestOptions): Promise<T> {
    return request<T>(path, { ...options, method: 'DELETE' })
  },
}
