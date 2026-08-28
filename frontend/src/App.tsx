import { useState } from 'react'
import { ConfirmDialog } from './components/ConfirmDialog'
import { NoticeBar } from './components/NoticeBar'
import { PromotionFormDialog } from './components/PromotionFormDialog'
import { PromotionList } from './components/PromotionList'
import { SummaryPanel } from './components/SummaryPanel'
import { copy } from './copy/es'
import type { PromotionAction } from './domain/promotionActions'
import { needsConfirmation } from './domain/promotionConfirmation'
import type { PromotionConfirmRequest } from './domain/promotionConfirmation'
import type { PromotionFormRequest } from './domain/promotionForm'
import type { PromotionSubmitOutcome } from './domain/promotionFormIssues'
import { usePromotionWrites } from './hooks/usePromotionWrites'
import type { Promotion, PromotionPayload } from './types/api'
import './App.css'

/**
 * The application shell.
 *
 * One screen by design, so there is no router: a header naming the product, the
 * counters, the listing and, over all of it, the promotion form and the
 * confirmation as dialogs.
 *
 * What lives here is the arbitration between the pieces, and nothing else. The
 * listing reports which button was pressed; this decides whether that opens a
 * form, asks a question first, or is sent straight away. The writing itself,
 * along with the refresh and the notice, belongs to `usePromotionWrites`, so
 * this file stays a composition rather than a second place where the rules
 * about the API live.
 */
function App() {
  const [formRequest, setFormRequest] = useState<PromotionFormRequest | null>(null)
  const [confirmRequest, setConfirmRequest] = useState<PromotionConfirmRequest | null>(null)
  const writes = usePromotionWrites()

  function handleAction(action: PromotionAction, promotion: Promotion): void {
    if (action === 'edit') {
      setFormRequest({ mode: 'edit', promotion })

      return
    }

    // Which of the three one way doors is worth a question is decided in the
    // domain, beside the table that decides when each action is offered, and
    // not by a condition written here.
    if (needsConfirmation(action)) {
      setConfirmRequest({ action, promotion })

      return
    }

    void writes.runRowWrite(action, promotion)
  }

  function handleConfirm(): void {
    if (confirmRequest === null) {
      return
    }

    const { action, promotion } = confirmRequest

    // Closed before the request is sent, not after it answers. The question has
    // been answered, so leaving the dialog up while the write runs would ask it
    // a second time, and it would be covering the row whose buttons are the
    // ones saying that something is happening.
    setConfirmRequest(null)
    void writes.runRowWrite(action, promotion)
  }

  async function handleSubmit(payload: PromotionPayload): Promise<PromotionSubmitOutcome> {
    if (formRequest === null) {
      return { kind: 'handled' }
    }

    const outcome = await writes.submitForm(formRequest, payload)

    // `handled` covers a promotion that was saved and a failure the screen took
    // over by reading everything again. Either way there is nothing left for
    // the form to show, so the dialog closes; `invalid` and `failed` keep it
    // open with what was typed still in it.
    if (outcome.kind === 'handled') {
      setFormRequest(null)
    }

    return outcome
  }

  return (
    <div className="app">
      <header className="app__header">
        <div className="app__bar">
          <h1 className="app__title">{copy.app.name}</h1>

          <button
            className="app__create"
            type="button"
            disabled={writes.pending !== null}
            onClick={() => setFormRequest({ mode: 'create' })}
          >
            {copy.form.open}
          </button>
        </div>
      </header>

      <main className="app__main">
        <NoticeBar notice={writes.notice} onDismiss={writes.dismissNotice} />

        <SummaryPanel revision={writes.revision} />
        <PromotionList
          onAction={handleAction}
          revision={writes.revision}
          pending={writes.pending}
        />
      </main>

      <PromotionFormDialog
        request={formRequest}
        onClose={() => setFormRequest(null)}
        onSubmit={handleSubmit}
      />

      <ConfirmDialog
        request={confirmRequest}
        onCancel={() => setConfirmRequest(null)}
        onConfirm={handleConfirm}
      />
    </div>
  )
}

export default App
