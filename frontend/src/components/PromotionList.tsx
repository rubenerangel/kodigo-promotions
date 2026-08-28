import type { ReactNode } from 'react'
import type { PromotionAction } from '../domain/promotionActions'
import type { Promotion } from '../types/api'
import { ErrorNotice } from './ErrorNotice'
import { PromotionRow } from './PromotionRow'
import { copy } from '../copy/es'
import { resolveErrorMessage } from '../copy/resolveErrorMessage'
import { useApiResource } from '../hooks/useApiResource'
import type { PendingWrite } from '../hooks/usePromotionWrites'
import { fetchPromotions } from '../services/promotions'
import './PromotionList.css'

const CAPTION_ID = 'promotions-caption'

type PromotionListProps = {
  /**
   * Told which action was asked for, and on which promotion. The listing does
   * not act: it names what was pressed and lets the screen above decide, which
   * is what keeps the dialog out of the table.
   */
  onAction?: (action: PromotionAction, promotion: Promotion) => void
  /**
   * Bumped by the screen after every write. The listing owns its own read and
   * keeps owning it; this only says that what it is showing has been overtaken.
   */
  revision?: number
  /** The write in flight, so the row that started it can say so. */
  pending?: PendingWrite | null
}

/**
 * The promotion listing.
 *
 * A real `<table>`, not a grid of divs: this is tabular data, and the element
 * is what gives a screen reader row and column navigation, the header of the
 * column it is reading and the name of the row it is in. None of that can be
 * recovered from styling afterwards.
 *
 * The table keeps a minimum width and scrolls inside its own container, so at
 * 375px the columns stay legible and it is the listing that scrolls sideways,
 * never the page. The container is focusable on purpose: a region that scrolls
 * has to be reachable by keyboard for anyone who does not use a pointer.
 *
 * Unlike the summary above, this one does have an empty state. Zero counters
 * are an answer; an empty table is not, and a header row over nothing looks
 * like a listing that failed rather than one with nothing to list yet.
 */
export function PromotionList({ onAction, revision = 0, pending = null }: PromotionListProps) {
  const { data, error, isLoading, reload } = useApiResource(fetchPromotions, revision)

  let body: ReactNode

  if (error !== null) {
    body = <ErrorNotice message={resolveErrorMessage(error)} onRetry={reload} />
  } else if (isLoading) {
    // `aria-hidden` because the live region below announces this same text:
    // without it a screen reader would meet the sentence twice, once as an
    // announcement and once as a paragraph.
    body = (
      <p className="promotions__notice" aria-hidden="true">
        {copy.promotions.loading}
      </p>
    )
  } else if (data === null || data.length === 0) {
    body = (
      <div className="promotions__empty">
        <p className="promotions__empty-title">{copy.promotions.emptyTitle}</p>
        <p className="promotions__empty-hint">{copy.promotions.emptyHint}</p>
      </div>
    )
  } else {
    body = (
      <div
        className="promotions__scroller"
        role="region"
        aria-labelledby={CAPTION_ID}
        tabIndex={0}
      >
        <table className="promotions__table">
          {/* Hidden from the page, present for assistive technology: the
              heading above already carries the visible name. */}
          <caption className="visually-hidden" id={CAPTION_ID}>
            {copy.promotions.caption}
          </caption>
          <thead>
            <tr>
              <th scope="col">{copy.promotions.columns.name}</th>
              <th scope="col">{copy.promotions.columns.target}</th>
              <th scope="col">{copy.promotions.columns.discount}</th>
              <th scope="col">{copy.promotions.columns.dates}</th>
              <th scope="col">{copy.promotions.columns.status}</th>
              <th scope="col">{copy.promotions.columns.validity}</th>
              <th scope="col">{copy.promotions.columns.actions}</th>
            </tr>
          </thead>
          <tbody>
            {data.map((promotion) => (
              <PromotionRow
                key={promotion.id}
                promotion={promotion}
                onAction={onAction}
                disabled={pending !== null}
                runningAction={
                  pending !== null && pending.kind === 'row' && pending.promotionId === promotion.id
                    ? pending.action
                    : null
                }
              />
            ))}
          </tbody>
        </table>
      </div>
    )
  }

  return (
    <section className="promotions" aria-labelledby="promotions-title" aria-busy={isLoading}>
      <h2 className="promotions__title" id="promotions-title">
        {copy.promotions.title}
      </h2>

      {/* Rendered on every pass, empty or not: a live region has to already be
          in the document for a change to its text to be announced. */}
      <p className="visually-hidden" role="status">
        {isLoading ? copy.promotions.loading : ''}
      </p>

      {body}
    </section>
  )
}
