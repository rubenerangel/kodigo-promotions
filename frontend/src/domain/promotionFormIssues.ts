import { NAME_MAX_LENGTH } from './promotionForm'
import type { PromotionFormState } from './promotionForm'
import type { FieldErrors } from '../types/api'

/**
 * What can be wrong with the promotion form, and where the message goes.
 *
 * Two sources feed the same slots. The form checks its own rules before
 * sending, so a mistake is answered without a round trip; and the API answers
 * 422 with a map of field names when a rule the form does not model fails.
 * Both end up as an issue code beside a control, which is what keeps the
 * screen from having two ways of saying "this field is wrong".
 *
 * The codes are codes, not sentences. The Spanish lives in the copy catalogue,
 * for the same reason the API's English `message` never reaches the screen:
 * this module states which rule broke, and one file decides how to say it.
 */

/**
 * The controls that can carry a message.
 *
 * The two kinds of choice — target kind and discount kind — are absent, and
 * that is not an omission: both are radio groups with one option always
 * checked, so neither has an invalid state to report.
 *
 * The order is the order the controls appear in, and it is load bearing: it is
 * what "the first field with a problem" means when the focus is moved.
 */
export const PROMOTION_FORM_FIELDS = [
  'name',
  'target',
  'discountValue',
  'startDate',
  'endDate',
] as const

export type PromotionFormField = (typeof PROMOTION_FORM_FIELDS)[number]

/**
 * One rule per code, plus `rejected`.
 *
 * `rejected` is the answer to a question this module cannot answer: the API
 * said a field is invalid for a rule the form does not check, and the only
 * thing that arrives with it is an English sentence meant for a log. The
 * realistic case is `exists:products,id` — an option that was in the catalogue
 * when the form opened and is not any more. Inventing a specific reason would
 * be a guess, and repeating the API's wording would break the one rule the
 * copy catalogue exists to hold, so the text says exactly what is known: the
 * server did not accept this value.
 */
export type PromotionFormIssue =
  | 'nameRequired'
  | 'nameTooLong'
  | 'targetRequired'
  | 'discountValueRequired'
  | 'discountValueNotNumeric'
  | 'discountValueNotPositive'
  | 'discountValueOutOfPercentageRange'
  | 'startDateRequired'
  | 'endDateRequired'
  | 'endDateNotAfterStart'
  | 'rejected'

/**
 * The message each field is showing, if any.
 *
 * A field maps to `undefined` rather than being absent so a message can be
 * cleared by writing over it, without rebuilding the record.
 */
export type PromotionFormIssues = Partial<Record<PromotionFormField, PromotionFormIssue>>

/** Whether anything at all is wrong. */
export function hasIssues(issues: PromotionFormIssues): boolean {
  return PROMOTION_FORM_FIELDS.some((field) => issues[field] !== undefined)
}

/** How many controls are showing a message, for the announcement. */
export function countIssues(issues: PromotionFormIssues): number {
  return PROMOTION_FORM_FIELDS.filter((field) => issues[field] !== undefined).length
}

/**
 * The first field with a problem, in the order the controls are read.
 *
 * This is what the focus follows. Announcing that there are errors and leaving
 * the caret where it was tells someone who cannot see the whole form that a
 * problem exists and not where, which is the half of the message that is of no
 * use.
 */
export function firstIssueField(issues: PromotionFormIssues): PromotionFormField | null {
  return PROMOTION_FORM_FIELDS.find((field) => issues[field] !== undefined) ?? null
}

/**
 * Removes the message a field was showing, leaving the rest alone.
 *
 * Called as the field is edited: a message that outlives the value it was
 * about is a message about nothing.
 */
export function withoutIssues(
  issues: PromotionFormIssues,
  ...fields: readonly PromotionFormField[]
): PromotionFormIssues {
  const cleared: PromotionFormIssues = { ...issues }

  for (const field of fields) {
    cleared[field] = undefined
  }

  return cleared
}

/** The name in `YYYY-MM-DD` order compares as text, so no `Date` is built. */
function isBefore(earlier: string, later: string): boolean {
  return earlier < later
}

/**
 * The amount, checked against the rule its kind carries.
 *
 * Mirrors `discount_value` in both Form Requests: `gt:0` always, and
 * `min:1 max:100` when the kind is a percentage. `Number` is used only to
 * compare; the value that travels stays the text that was typed.
 */
function discountValueIssue(state: PromotionFormState): PromotionFormIssue | undefined {
  if (state.discountValue.trim() === '') {
    return 'discountValueRequired'
  }

  const amount = Number(state.discountValue)

  if (!Number.isFinite(amount)) {
    return 'discountValueNotNumeric'
  }

  if (amount <= 0) {
    return 'discountValueNotPositive'
  }

  return state.discountType === 'PERCENTAGE' && (amount < 1 || amount > 100)
    ? 'discountValueOutOfPercentageRange'
    : undefined
}

/**
 * The dates, which are one rule and not two.
 *
 * `after:start_date` is strict on the backend and the table carries the same
 * CHECK, so a promotion that starts and ends on the same day is rejected. The
 * comparison is only made once both dates are present: complaining that the
 * end is not after a start that has not been chosen yet points at the wrong
 * control.
 */
function endDateIssue(state: PromotionFormState): PromotionFormIssue | undefined {
  if (state.endDate === '') {
    return 'endDateRequired'
  }

  if (state.startDate === '') {
    return undefined
  }

  return isBefore(state.startDate, state.endDate) ? undefined : 'endDateNotAfterStart'
}

/**
 * Everything the form can tell is wrong before sending anything.
 *
 * THE CLIENT IS NOT THE AUTHORITY, and this function does not pretend to be:
 * the rules restated here are the ones the Form Requests already enforce, and
 * the API re-checks every one of them. What the restatement buys is an answer
 * without a round trip and a message beside the control that caused it, which
 * is the same trade `domain/promotionActions.ts` records for the actions table.
 *
 * Names are checked trimmed, because Laravel's `TrimStrings` middleware trims
 * before `required` and `max:150` are applied. Checking the raw value would
 * accept a name of spaces that the backend then refuses.
 */
export function validatePromotionForm(state: PromotionFormState): PromotionFormIssues {
  const name = state.name.trim()

  return {
    name: name === '' ? 'nameRequired' : name.length > NAME_MAX_LENGTH ? 'nameTooLong' : undefined,
    target: state.target.optionId === '' ? 'targetRequired' : undefined,
    discountValue: discountValueIssue(state),
    startDate: state.startDate === '' ? 'startDateRequired' : undefined,
    endDate: endDateIssue(state),
  }
}

/**
 * WHY BOTH TARGET KEYS LAND ON ONE CONTROL.
 *
 * The wire has `product_id` and `category_id`; the form has a single select,
 * because the state holds one kind and one identifier and cannot express a
 * target that names both. So both keys map to `target`, and that is safe
 * rather than lossy: the payload carries exactly one of the two keys, so at
 * most one of them can come back with a message, and whichever it is, it is
 * about the option the user chose in that one select. Splitting them would
 * mean drawing a second, empty slot for a control the form does not have.
 *
 * `required_without` and `prohibits` fire on these same keys, and a message
 * from either still belongs on the select: those rules are about which target
 * was named, which is the question that select asks.
 */
const FIELD_BY_WIRE_KEY: Record<string, PromotionFormField> = {
  name: 'name',
  product_id: 'target',
  category_id: 'target',
  discount_value: 'discountValue',
  start_date: 'startDate',
  end_date: 'endDate',
}

/**
 * The 422 field map, turned into messages beside the controls.
 *
 * Only the keys the form draws a control for are kept. A key it does not know
 * — `discount_type` or `status`, neither of which the form lets anyone get
 * wrong — is dropped here, and the caller notices the result is empty and
 * states the failure at form level instead of hiding it beside no control.
 */
export function promotionFormIssuesFrom(fields: FieldErrors | null): PromotionFormIssues {
  if (fields === null) {
    return {}
  }

  const issues: PromotionFormIssues = {}

  for (const key of Object.keys(fields)) {
    const field = FIELD_BY_WIRE_KEY[key]

    if (field !== undefined) {
      issues[field] = 'rejected'
    }
  }

  return issues
}

/**
 * What the screen made of a submission, answered back to the form.
 *
 * The form owns its own messages, so it has to be told what to show rather
 * than handed a prop the screen keeps in step. `handled` covers both a save
 * and a failure the screen took over by closing the dialog: in either case
 * there is nothing left for the form to draw.
 */
export type PromotionSubmitOutcome =
  | { kind: 'handled' }
  | { kind: 'invalid'; issues: PromotionFormIssues }
  /** Nothing was saved, and pressing submit again is a reasonable next move. */
  | { kind: 'failed'; message: string }
