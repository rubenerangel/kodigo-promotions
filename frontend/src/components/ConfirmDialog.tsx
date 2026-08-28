import { useEffect, useRef } from 'react'
import { copy } from '../copy/es'
import type { PromotionConfirmRequest } from '../domain/promotionConfirmation'
import './ConfirmDialog.css'

const TITLE_ID = 'confirm-title'
const QUESTION_ID = 'confirm-question'

type ConfirmDialogProps = {
  /** What to ask about, or `null` while the dialog is closed. */
  request: PromotionConfirmRequest | null
  onCancel: () => void
  onConfirm: () => void
}

/**
 * The question asked before an action that cannot be undone.
 *
 * A `<dialog>` and not `window.confirm`, for reasons that are not about looks.
 * The native prompt cannot be worded beyond one line, cannot be styled, blocks
 * the main thread, is suppressed outright by some browsers in an iframe, and on
 * mobile is rendered by the operating system with the page's origin printed
 * above it, which reads like a security warning rather than a question about a
 * promotion. Its text also cannot be marked up, so the name of the promotion
 * could not be emphasised inside it.
 *
 * The verb, not "Aceptar", is what the confirming button says. A dialog answered
 * by a button labelled with the action it performs can be understood from the
 * button alone, which matters because that button is the last thing read before
 * a row is destroyed.
 *
 * `aria-describedby` points at the question, so the dialog is announced as its
 * title followed by what is actually being asked, rather than as a title over
 * two buttons.
 */
export function ConfirmDialog({ request, onCancel, onConfirm }: ConfirmDialogProps) {
  const dialogRef = useRef<HTMLDialogElement>(null)

  useEffect(() => {
    const dialog = dialogRef.current

    if (dialog === null) {
      return
    }

    // Driven from the request, exactly as the form dialog is, so the element's
    // open state cannot drift from the state that decides whether it is open.
    if (request !== null && !dialog.open) {
      dialog.showModal()
    } else if (request === null && dialog.open) {
      dialog.close()
    }
  }, [request])

  /**
   * The `close` event, once it is certain it is about the question on screen.
   *
   * Escape closes a modal dialog without asking anything, and that has to mean
   * the same as pressing "Cancelar", so both ways out of the dialog end here
   * and the state cannot be left believing the question is still open.
   *
   * WHY THE EVENT IS NOT TAKEN AT FACE VALUE.
   *
   * It does not arrive with the `close()` that caused it. The element queues
   * it and it is dispatched in a later task, and by then the screen may have
   * opened the question again, for the same row or another one. That event is
   * the echo of a dialog that no longer exists, and answering it would cancel
   * the question that is on screen a few milliseconds after it appeared, with
   * nobody having asked for that. It was measured happening: `showModal` and,
   * six milliseconds later, a `close()` no click accounts for.
   *
   * A dialog that is open right now is one nobody has closed, which is what
   * makes `open` the thing to read: false means this event is the current
   * question being dismissed, true means it is an echo and there is nothing to
   * answer.
   */
  function handleClose(): void {
    if (dialogRef.current?.open === true) {
      return
    }

    onCancel()
  }

  const words = request === null ? null : copy.confirm[request.action]

  return (
    <dialog
      className="confirm"
      ref={dialogRef}
      aria-labelledby={TITLE_ID}
      aria-describedby={QUESTION_ID}
      onClose={handleClose}
    >
      {request !== null && words !== null && (
        <div className="confirm__panel">
          <h2 className="confirm__title" id={TITLE_ID}>
            {words.title}
          </h2>

          <p className="confirm__question" id={QUESTION_ID}>
            {words.question(request.promotion.name)}
          </p>

          <div className="confirm__actions">
            {/* Holds the focus when the dialog opens, and is first in the
                source order. The dialog exists to interrupt an action that
                cannot be undone, so the key already under the finger — Enter,
                pressed once more out of momentum — has to answer "no". Focusing
                the destructive button would turn this dialog into one extra
                keypress on the way to the same deletion, which is the failure
                mode a confirmation is supposed to prevent. */}
            <button className="confirm__button" type="button" autoFocus onClick={onCancel}>
              {copy.confirm.cancel}
            </button>
            <button
              className={`confirm__button confirm__button--${request.action}`}
              type="button"
              onClick={onConfirm}
            >
              {words.confirm}
            </button>
          </div>
        </div>
      )}
    </dialog>
  )
}
