import { useCallback, useEffect, useState } from 'react'
import type { RequestOptions } from '../services/api'

/** Reads a resource, given a way to cancel the read that is already running. */
export type ResourceLoader<T> = (options: RequestOptions) => Promise<T>

export type ApiResource<T> = {
  /** The payload of the last settled read, or `null` if it has not settled. */
  data: T | null
  isLoading: boolean
  /** The failure of the last settled read, or `null` if it succeeded. */
  error: unknown
  /** Reads the resource again. Safe to call from an event handler. */
  reload: () => void
}

/** What a finished read left behind, tagged with the attempt that produced it. */
type Settled<T> = {
  attempt: number
  data: T | null
  error: unknown
}

/**
 * Loads a resource on mount and on demand.
 *
 * Two hazards shape this hook, and both are about a result arriving for a
 * request nobody is waiting for any more:
 *
 * - StrictMode mounts every component twice in development. The effect runs,
 *   is cleaned up, and runs again, so two reads are in flight at once.
 * - A reload started while another read is still running can resolve second
 *   and overwrite the fresher answer with the staler one.
 *
 * Both are answered the same way, twice over. Each run owns an
 * `AbortController`; the cleanup aborts it, so a superseded run cancels its
 * request and writes nothing even if it resolves anyway. And every result is
 * stamped with the attempt that asked for it, so a stale one that did slip
 * through still could not be mistaken for the current answer.
 *
 * `isLoading` is derived rather than stored, by asking whether the settled
 * result belongs to the attempt now in flight. Storing it would mean flipping
 * a flag from inside the effect on every run, which is a second render for
 * something already knowable from what the hook holds.
 *
 * `load` is read as a dependency, so it must be stable: define it at module
 * level or wrap it in `useCallback`. An inline arrow would make the effect run
 * on every render and never stop loading.
 */
export function useApiResource<T>(load: ResourceLoader<T>): ApiResource<T> {
  const [attempt, setAttempt] = useState(0)
  const [settled, setSettled] = useState<Settled<T> | null>(null)

  useEffect(() => {
    const controller = new AbortController()

    load({ signal: controller.signal })
      .then((data) => {
        if (!controller.signal.aborted) {
          setSettled({ attempt, data, error: null })
        }
      })
      .catch((error: unknown) => {
        if (!controller.signal.aborted) {
          // The payload is dropped along with the failed read: keeping the
          // previous one would leave counts on screen that nothing has
          // confirmed still hold.
          setSettled({ attempt, data: null, error })
        }
      })

    return () => controller.abort()
  }, [load, attempt])

  const isSettled = settled !== null && settled.attempt === attempt

  const reload = useCallback(() => setAttempt((previous) => previous + 1), [])

  return {
    data: settled?.data ?? null,
    // A retry clears the previous failure by itself: until the new attempt
    // settles, there is no error to report.
    error: isSettled ? settled.error : null,
    isLoading: !isSettled,
    reload,
  }
}
