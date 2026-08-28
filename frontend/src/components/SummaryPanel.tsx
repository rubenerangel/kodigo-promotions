import { ErrorNotice } from './ErrorNotice'
import { SummaryCounter } from './SummaryCounter'
import { copy } from '../copy/es'
import { resolveErrorMessage } from '../copy/resolveErrorMessage'
import { useApiResource } from '../hooks/useApiResource'
import { fetchPromotionSummary } from '../services/promotions'
import './SummaryPanel.css'

const VALIDITY_HINT_ID = 'summary-validity-hint'

/**
 * The counters at the top of the screen.
 *
 * Split into two labelled groups on purpose. The three status counters
 * partition the collection and add up to the total; "vigentes hoy" counts a
 * property that cuts across all three, so a promotion can be counted there and
 * in one of the others at once. Shown as four identical tiles, the four
 * numbers would invite a sum that is wrong. The separation, the different
 * card treatment and the sentence under each group all say the same thing, so
 * the distinction survives whichever of the three a given reader notices.
 *
 * There is no empty state. Four zeros are a true and useful answer, not an
 * absence of one, and replacing them with "no hay nada todavía" would hide the
 * fact that the screen did read the data successfully.
 */
export function SummaryPanel() {
  const { data, error, isLoading, reload } = useApiResource(fetchPromotionSummary)

  return (
    <section className="summary" aria-labelledby="summary-title" aria-busy={isLoading}>
      <h2 className="summary__title" id="summary-title">
        {copy.summary.title}
      </h2>

      {/* Rendered on every pass, empty or not: a live region has to be in the
          document before its text changes for the change to be announced. */}
      <p className="visually-hidden" role="status">
        {isLoading ? copy.summary.loading : ''}
      </p>

      {error === null ? (
        <div className="summary__groups">
          <section className="summary__group" aria-labelledby="summary-status-title">
            <h3 className="summary__group-title" id="summary-status-title">
              {copy.summary.byStatusTitle}
            </h3>
            <ul className="summary__grid">
              <SummaryCounter label={copy.counters.scheduled} tone="scheduled" value={data?.scheduled ?? null} />
              <SummaryCounter label={copy.counters.active} tone="active" value={data?.active ?? null} />
              <SummaryCounter label={copy.counters.finished} tone="finished" value={data?.finished ?? null} />
            </ul>
            <p className="summary__hint">{copy.summary.byStatusHint}</p>
          </section>

          <section
            className="summary__group summary__group--validity"
            aria-labelledby="summary-validity-title"
          >
            <h3 className="summary__group-title" id="summary-validity-title">
              {copy.summary.validityTitle}
            </h3>
            <ul className="summary__grid summary__grid--single">
              <SummaryCounter
                describedBy={VALIDITY_HINT_ID}
                label={copy.counters.validToday}
                tone="validity"
                value={data?.validToday ?? null}
              />
            </ul>
            <p className="summary__hint" id={VALIDITY_HINT_ID}>
              {copy.summary.validityHint}
            </p>
          </section>
        </div>
      ) : (
        <ErrorNotice message={resolveErrorMessage(error)} onRetry={reload} />
      )}
    </section>
  )
}
