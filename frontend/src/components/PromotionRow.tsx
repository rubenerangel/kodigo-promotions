import { copy } from '../copy/es'
import { promotionActionsFor } from '../domain/promotionActions'
import type { PromotionAction } from '../domain/promotionActions'
import { formatPromotionDate } from '../format/promotionDate'
import { formatPromotionDiscount } from '../format/promotionDiscount'
import type { Promotion } from '../types/api'
import './PromotionRow.css'

/**
 * Which actions get a treatment of their own. Everything else stays a neutral
 * button: only the move that advances the lifecycle and the one that destroys
 * data are worth pulling out of the row's uniform grey.
 */
const ACTION_TONES: Partial<Record<PromotionAction, 'primary' | 'danger'>> = {
  activate: 'primary',
  finish: 'primary',
  delete: 'danger',
}

/** The button's classes, without a dangling modifier for a neutral action. */
function actionClassName(action: PromotionAction): string {
  const tone = ACTION_TONES[action]

  return tone === undefined ? 'promotion-action' : `promotion-action promotion-action--${tone}`
}

type PromotionRowProps = {
  promotion: Promotion
  /**
   * Runs when an action button is pressed. The row names what was pressed and
   * on which promotion; whether that asks a question first, and what it sends,
   * belongs to the screen above.
   */
  onAction?: (action: PromotionAction, promotion: Promotion) => void
  /**
   * A write is in flight somewhere on the screen, so no button on this row
   * accepts a press.
   *
   * ALL OF THEM, NOT ONLY THE ONE THAT WAS PRESSED. A second write started
   * while the first is running would race the refresh that follows it, and the
   * listing would settle on whichever answer happened to arrive last. The
   * operations here are short and are not the kind anyone performs in parallel,
   * so waiting costs nothing next to a screen that ends up describing a state
   * the server was never in.
   */
  disabled?: boolean
  /** The action running on this row, if the one in flight is this row's. */
  runningAction?: PromotionAction | null
}

/**
 * One promotion, as a row of the listing.
 *
 * Three of its cells carry a distinction that is easy to collapse and
 * expensive to get wrong:
 *
 * - The target prints its kind before its name. "Café molido 500g" alone never
 *   says whether the discount touches one article or a whole family, and those
 *   are very different amounts of money.
 * - The status is a pill in the colours of its state, and always with the
 *   state's name in it. Read in greyscale it still says which state it is.
 * - Validity is deliberately not a pill and not in the status palette. A
 *   promotion can be ACTIVE with its dates already past, or FINISHED with
 *   today inside them, so if the two columns looked alike the screen would be
 *   claiming they are the same fact.
 */
export function PromotionRow({
  promotion,
  onAction,
  disabled = false,
  runningAction = null,
}: PromotionRowProps) {
  const actions = promotionActionsFor(promotion.status)

  return (
    <tr className="promotion-row">
      {/* A row header, not a cell: it names the row, which is what lets a
          screen reader say which promotion a given cell belongs to. */}
      <th className="promotion-row__name" scope="row">
        {promotion.name}
      </th>

      <td>
        <span className="promotion-row__target">
          <span className="promotion-row__target-kind">{copy.targetTypes[promotion.target.type]}</span>
          <span className="promotion-row__target-name">{promotion.target.name}</span>
        </span>
      </td>

      <td>
        <span className="promotion-row__discount">
          <span className="promotion-row__discount-value">
            {formatPromotionDiscount(promotion.discountType, promotion.discountValue)}
          </span>
          <span className="promotion-row__discount-kind">
            {copy.discountTypes[promotion.discountType]}
          </span>
        </span>
      </td>

      <td>
        {/* `dateTime` carries the machine readable value the display format
            deliberately reorders, so the cell is unambiguous to anything
            parsing it while the human sees a Spanish date. */}
        <span className="promotion-row__dates">
          <time dateTime={promotion.startDate}>{formatPromotionDate(promotion.startDate)}</time>
          <span aria-hidden="true">{copy.promotions.dateRangeMark}</span>
          <span className="visually-hidden">{copy.promotions.dateRangeSeparator}</span>
          <time dateTime={promotion.endDate}>{formatPromotionDate(promotion.endDate)}</time>
        </span>
      </td>

      <td>
        <span className={`promotion-status promotion-status--${promotion.status.toLowerCase()}`}>
          {copy.statuses[promotion.status]}
        </span>
      </td>

      <td>
        <span
          className={`promotion-validity ${promotion.validToday ? 'promotion-validity--valid' : 'promotion-validity--not-valid'}`}
        >
          {promotion.validToday ? copy.validity.valid : copy.validity.notValid}
        </span>
      </td>

      <td className="promotion-row__actions-cell">
        {actions.length === 0 ? (
          // Not an empty cell: a finished promotion has to say that it is
          // closed, or the missing buttons read as something that failed to
          // render.
          <p className="promotion-row__no-actions">{copy.promotions.noActions}</p>
        ) : (
          <div className="promotion-row__actions">
            {actions.map((action) => (
              <button
                key={action}
                type="button"
                className={actionClassName(action)}
                disabled={disabled}
                // Marks only the button whose request is actually running. Six
                // buttons all announcing that they are busy would be lying
                // about five of them.
                aria-busy={runningAction === action}
                // The visible label is the verb alone, because the row it sits
                // in supplies the rest. Heard on its own it would not, so the
                // accessible name names the promotion too.
                aria-label={copy.actions.forPromotion(copy.actions[action], promotion.name)}
                onClick={() => onAction?.(action, promotion)}
              >
                {runningAction === action ? copy.actions.running : copy.actions[action]}
              </button>
            ))}
          </div>
        )}
      </td>
    </tr>
  )
}
