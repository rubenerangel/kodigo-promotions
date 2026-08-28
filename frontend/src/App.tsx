import { useState } from 'react'
import { PromotionFormDialog } from './components/PromotionFormDialog'
import { PromotionList } from './components/PromotionList'
import { SummaryPanel } from './components/SummaryPanel'
import { copy } from './copy/es'
import type { PromotionAction } from './domain/promotionActions'
import type { PromotionFormRequest } from './domain/promotionForm'
import type { Promotion, PromotionPayload } from './types/api'
import './App.css'

/**
 * The application shell.
 *
 * One screen by design, so there is no router: a header naming the product,
 * the counters, the listing and, over all of it, the promotion form as a
 * dialog.
 *
 * The request to open that form lives here rather than in the listing. The
 * listing reports which button was pressed and on which promotion; deciding
 * what a press means is the screen's business, which is what keeps the table
 * from having to know a dialog exists.
 */
function App() {
  const [formRequest, setFormRequest] = useState<PromotionFormRequest | null>(null)

  function handleAction(action: PromotionAction, promotion: Promotion): void {
    // Only editing opens the form. Activating, finishing and deleting are
    // wired in task 5.5, and are deliberately left alone rather than given a
    // placeholder that would pretend to work.
    if (action === 'edit') {
      setFormRequest({ mode: 'edit', promotion })
    }
  }

  function handleSubmit(_payload: PromotionPayload): void {
    // Task 5.5 sends this to the API and refreshes the listing and the
    // counters. Closing is all this screen can honestly do today: pretending
    // to save would be worse than not saving.
    setFormRequest(null)
  }

  return (
    <div className="app">
      <header className="app__header">
        <div className="app__bar">
          <h1 className="app__title">{copy.app.name}</h1>

          <button
            className="app__create"
            type="button"
            onClick={() => setFormRequest({ mode: 'create' })}
          >
            {copy.form.open}
          </button>
        </div>
      </header>

      <main className="app__main">
        <SummaryPanel />
        <PromotionList onAction={handleAction} />
      </main>

      <PromotionFormDialog
        request={formRequest}
        onClose={() => setFormRequest(null)}
        onSubmit={handleSubmit}
      />
    </div>
  )
}

export default App
