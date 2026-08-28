import { useEffect, useRef } from 'react'
import { PromotionForm } from './PromotionForm'
import { copy } from '../copy/es'
import type { PromotionFormRequest } from '../domain/promotionForm'
import type { PromotionSubmitOutcome } from '../domain/promotionFormIssues'
import type { PromotionPayload } from '../types/api'
import './PromotionFormDialog.css'

const TITLE_ID = 'promotion-form-title'

type PromotionFormDialogProps = {
  /** The form to show, or `null` while the dialog is closed. */
  request: PromotionFormRequest | null
  onClose: () => void
  /** Passed straight through to the form, which acts on what it answers. */
  onSubmit: (payload: PromotionPayload) => Promise<PromotionSubmitOutcome>
}

/**
 * The promotion form, shown as a modal dialog.
 *
 * Built on the browser's own `<dialog>` and opened with `showModal()`, so the
 * focus trap, the Escape key, the inert background and the return of focus to
 * whatever opened it come from the platform. Every one of those is a thing
 * hand rolled modals get wrong, and none of them is worth reimplementing.
 *
 * The form inside is keyed by the request, so opening the dialog again mounts
 * a fresh one. That is what guarantees nothing typed in a previous, abandoned
 * attempt survives into the next: there is no state to reset because there is
 * no state left.
 */
export function PromotionFormDialog({ request, onClose, onSubmit }: PromotionFormDialogProps) {
  const dialogRef = useRef<HTMLDialogElement>(null)

  useEffect(() => {
    const dialog = dialogRef.current

    if (dialog === null) {
      return
    }

    // Driven from the request rather than from a call at the click site, so
    // the element's open state cannot drift away from the state that decides
    // whether it should be open.
    if (request !== null && !dialog.open) {
      dialog.showModal()
    } else if (request === null && dialog.open) {
      dialog.close()
    }
  }, [request])

  const isEditing = request?.mode === 'edit'

  return (
    <dialog
      className="promotion-dialog"
      ref={dialogRef}
      aria-labelledby={TITLE_ID}
      // Fires for Escape as well as for close(), so both routes out of the
      // dialog end in the same place and the state cannot be left thinking it
      // is still open.
      onClose={onClose}
    >
      {request !== null && (
        <div className="promotion-dialog__panel">
          <div className="promotion-dialog__header">
            <h2 className="promotion-dialog__title" id={TITLE_ID}>
              {isEditing ? copy.form.editTitle : copy.form.createTitle}
            </h2>
            <button
              className="promotion-dialog__close"
              type="button"
              aria-label={copy.form.close}
              onClick={onClose}
            >
              {/* Decorative: the accessible name is on the button itself. */}
              <span aria-hidden="true">{copy.form.closeMark}</span>
            </button>
          </div>

          <PromotionForm
            key={isEditing ? `edit-${request.promotion.id}` : 'create'}
            request={request}
            onCancel={onClose}
            onSubmit={onSubmit}
          />
        </div>
      )}
    </dialog>
  )
}
