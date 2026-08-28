import { useCallback, useRef, useState } from 'react'
import { copy } from '../copy/es'
import { resolveErrorMessage } from '../copy/resolveErrorMessage'
import type { Notice } from '../domain/notice'
import { promotionStatusAfter } from '../domain/promotionActions'
import type { PromotionTransitionAction } from '../domain/promotionActions'
import type { PromotionFormRequest } from '../domain/promotionForm'
import { hasIssues, promotionFormIssuesFrom } from '../domain/promotionFormIssues'
import type { PromotionSubmitOutcome } from '../domain/promotionFormIssues'
import { classifyPromotionWriteFailure, fieldErrorsOf } from '../domain/promotionWriteFailure'
import {
  createPromotion,
  deletePromotion,
  transitionPromotionStatus,
  updatePromotion,
} from '../services/promotions'
import type { Promotion, PromotionPayload } from '../types/api'

/** The row actions that write. `edit` only opens a form, so it is not one. */
export type PromotionRowWrite = PromotionTransitionAction | 'delete'

/**
 * The operation in flight, named precisely enough to point at the control that
 * started it.
 *
 * Every action button is disabled while any write runs, but only the one that
 * was pressed says so. A screen where six buttons all announce "Enviando…"
 * would be lying about five of them.
 */
export type PendingWrite =
  | { kind: 'form' }
  | { kind: 'row'; action: PromotionRowWrite; promotionId: number }

export type PromotionWrites = {
  /**
   * Bumped after every write that changed something on the server. Handed to
   * the readers so they read again.
   */
  revision: number
  pending: PendingWrite | null
  notice: Notice | null
  dismissNotice: () => void
  /** Creates or updates, depending on what the form was opened for. */
  submitForm: (
    request: PromotionFormRequest,
    payload: PromotionPayload,
  ) => Promise<PromotionSubmitOutcome>
  runRowWrite: (action: PromotionRowWrite, promotion: Promotion) => Promise<void>
}

/**
 * Every write the screen can perform, and everything that has to be true
 * around one.
 *
 * WHY THE REFRESH IS A COUNTER AND NOT A SHARED CACHE.
 *
 * The listing and the counters read independently, each with its own
 * `useApiResource`, and after a write both are stale at once. Three ways to fix
 * that were on the table:
 *
 * - Lift both reads into the shell and pass the payloads down. It works, and it
 *   makes the shell the owner of two payloads it never reads, so every future
 *   change to either shape goes through a component that has no interest in it.
 * - Add a client side cache. It is the general answer, and it is a dependency
 *   and a mental model for one number.
 * - Keep one counter here and hand it to both readers, which is what this does.
 *
 * The counter wins because the readers stay owners of their own data and the
 * shell only has to know that something changed, not what. Both are re-read
 * rather than patched in place: the counters are computed by the database, and
 * a status change moves more than the row it touched — recomputing them from
 * the promotion the API just answered would be a second implementation of the
 * summary, living in the browser, and drifting from the first.
 *
 * THE COUNTERS AND THE LISTING MUST NOT DISAGREE. Both are bumped by the same
 * value in the same render, so they refetch together. A fresh listing under
 * counters that still describe the previous state reads as a system that cannot
 * count, and that is worse than not refreshing at all.
 */
export function usePromotionWrites(): PromotionWrites {
  const [revision, setRevision] = useState(0)
  const [pending, setPending] = useState<PendingWrite | null>(null)
  const [notice, setNotice] = useState<Notice | null>(null)

  /**
   * The authority on whether a write is already running.
   *
   * `pending` drives the interface but cannot be trusted to guard against a
   * double press: two clicks dispatched before React has re-rendered would both
   * read the state as `null` and both send. The disabled attribute makes that
   * unlikely and this makes it impossible, which is the right split for an
   * operation that would otherwise create two promotions out of one intent.
   */
  const isWriting = useRef(false)

  const dismissNotice = useCallback(() => setNotice(null), [])

  const refresh = useCallback(() => setRevision((previous) => previous + 1), [])

  const submitForm = useCallback(
    async (
      request: PromotionFormRequest,
      payload: PromotionPayload,
    ): Promise<PromotionSubmitOutcome> => {
      if (isWriting.current) {
        return { kind: 'handled' }
      }

      isWriting.current = true
      setPending({ kind: 'form' })

      try {
        const saved =
          request.mode === 'create'
            ? await createPromotion(payload)
            : await updatePromotion(request.promotion.id, payload)

        refresh()
        setNotice({
          tone: 'success',
          // The name the server stored, not the characters that were typed:
          // Laravel trims on the way in, so the two can differ.
          message:
            request.mode === 'create'
              ? copy.notices.created(saved.name)
              : copy.notices.updated(saved.name),
          // The dialog is about to close, and closing a `<dialog>` hands focus
          // back to the button that opened it. Taking it here would be a fight
          // with the platform over a control that is still on screen.
          takeFocus: false,
        })

        return { kind: 'handled' }
      } catch (error) {
        const failure = classifyPromotionWriteFailure(error)

        if (failure === 'invalid') {
          const issues = promotionFormIssuesFrom(fieldErrorsOf(error))

          // A 422 naming only fields the form draws no control for would
          // otherwise be shown beside nothing, which looks like a submit that
          // did nothing at all.
          return hasIssues(issues)
            ? { kind: 'invalid', issues }
            : { kind: 'failed', message: resolveErrorMessage(error) }
        }

        if (failure === 'stale') {
          // The promotion is not what this form was opened on. Keeping the
          // dialog open would invite the same request again, and it would fail
          // the same way; the screen reads the data again and says why.
          refresh()
          setNotice({ tone: 'error', message: resolveErrorMessage(error), takeFocus: false })

          return { kind: 'handled' }
        }

        // Nothing was saved, the data typed is still wanted, and pressing
        // submit again is a reasonable next move. The dialog stays open.
        return { kind: 'failed', message: resolveErrorMessage(error) }
      } finally {
        isWriting.current = false
        setPending(null)
      }
    },
    [refresh],
  )

  const runRowWrite = useCallback(
    async (action: PromotionRowWrite, promotion: Promotion): Promise<void> => {
      if (isWriting.current) {
        return
      }

      isWriting.current = true
      setPending({ kind: 'row', action, promotionId: promotion.id })

      try {
        if (action === 'delete') {
          await deletePromotion(promotion.id)
        } else {
          await transitionPromotionStatus(promotion.id, promotionStatusAfter(action))
        }

        refresh()
        setNotice({
          tone: 'success',
          message: SUCCESS_NOTICE[action](promotion.name),
          // The control that started this is gone: a deleted row takes its
          // buttons with it, and a transition replaces them with the ones the
          // new status offers. Without this the focus would fall to the body
          // and a keyboard user would restart from the top of the page.
          takeFocus: true,
        })
      } catch (error) {
        if (classifyPromotionWriteFailure(error) === 'stale') {
          refresh()
        }

        setNotice({ tone: 'error', message: resolveErrorMessage(error), takeFocus: true })
      } finally {
        isWriting.current = false
        setPending(null)
      }
    },
    [refresh],
  )

  return { revision, pending, notice, dismissNotice, submitForm, runRowWrite }
}

/**
 * What each row write says when it worked.
 *
 * A table rather than a chain of conditionals, so a row action added to
 * `PromotionRowWrite` fails to compile until it has been given something to
 * say, instead of silently succeeding in silence.
 */
const SUCCESS_NOTICE: Record<PromotionRowWrite, (promotionName: string) => string> = {
  activate: copy.notices.activated,
  finish: copy.notices.finished,
  delete: copy.notices.deleted,
}
