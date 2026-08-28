import type { DiscountType, Promotion, PromotionPayload, PromotionTargetType } from '../types/api'

/**
 * The state of the promotion form, and the payload it turns into.
 *
 * Kept apart from the components on purpose: what the form holds and what it
 * sends are rules, not markup, and they are the part of this feature that has
 * to be readable on its own, without a JSX file around it.
 *
 * The one rule everything here exists to protect is the exclusive target.
 */

/** How long a name the `name` column and both Form Requests accept. */
export const NAME_MAX_LENGTH = 150

/** Two decimals, the precision of the `decimal(10, 2)` column. */
export const DISCOUNT_VALUE_STEP = '0.01'

/**
 * WHY ONE KIND AND ONE IDENTIFIER, AND NOT TWO IDENTIFIER FIELDS.
 *
 * The obvious shape for this form is `{ productId, categoryId }`, and it is
 * the wrong one. It can hold a value in both fields at once, which is the very
 * state the whole feature exists to prevent: the backend rejects it with
 * `prohibits:`, and the domain models a target as two classes, `ProductTarget`
 * and `CategoryTarget`, each carrying a single identifier. With two fields the
 * interface would be the one place in the system where an ambiguous target is
 * representable, and the code would then need a rule, applied by hand before
 * every write, to keep it from being sent.
 *
 * So the form holds the kind and ONE identifier. There is no second field to
 * fall out of step with the first, no pair to reconcile before submitting, and
 * no check to forget: a target naming both a product and a category cannot be
 * built out of this type.
 *
 * The identifier stays a string because that is what a `<select>` reads and
 * writes, and `''` is the honest spelling of "nothing chosen yet" — narrower
 * than `0` or `NaN`, which are numbers that look like answers.
 */
export type PromotionFormTarget = {
  type: PromotionTargetType
  /** The chosen option's identifier, or `''` while none is chosen. */
  optionId: string
}

/** Everything the form holds while it is being filled in. */
export type PromotionFormState = {
  name: string
  target: PromotionFormTarget
  discountType: DiscountType
  /**
   * Text, straight from the control and untouched. Parsing it into a number to
   * print it back is the one step that can lose a decimal, and nothing here
   * needs its numeric value.
   */
  discountValue: string
  /** `YYYY-MM-DD`, exactly as `<input type="date">` writes it. */
  startDate: string
  /** `YYYY-MM-DD`. */
  endDate: string
}

/**
 * What the screen asked the form for.
 *
 * A union, and not a mode beside an optional promotion: "editing, but no
 * promotion" is not a state this feature has, and the union is what keeps it
 * from being written.
 */
export type PromotionFormRequest =
  | { mode: 'create' }
  | { mode: 'edit'; promotion: Promotion }

export type PromotionFormMode = PromotionFormRequest['mode']

/**
 * An empty form.
 *
 * A kind of target has to be preselected, since the choice is a pair of radios
 * and a form that starts with neither of them checked only adds a step. It
 * starts on "Producto", the narrower of the two, so that reaching a whole
 * category has to be deliberate.
 */
function emptyPromotionForm(): PromotionFormState {
  return {
    name: '',
    target: { type: 'product', optionId: '' },
    discountType: 'PERCENTAGE',
    discountValue: '',
    startDate: '',
    endDate: '',
  }
}

/**
 * The promotion, as the form holds it.
 *
 * Every value crosses over as text, with no conversion at any point. The dates
 * arrive as `YYYY-MM-DD`, which is what the date control reads, so there is
 * nothing to translate; the amount arrives as `15.00` and stays `15.00`.
 * `status` is not read at all: the form does not edit it, and the update
 * endpoint refuses to receive it.
 */
function promotionFormFrom(promotion: Promotion): PromotionFormState {
  return {
    name: promotion.name,
    target: {
      type: promotion.target.type,
      optionId: String(promotion.target.id),
    },
    discountType: promotion.discountType,
    discountValue: promotion.discountValue,
    startDate: promotion.startDate,
    endDate: promotion.endDate,
  }
}

/** The state a request opens the form with. */
export function initialPromotionForm(request: PromotionFormRequest): PromotionFormState {
  return request.mode === 'create' ? emptyPromotionForm() : promotionFormFrom(request.promotion)
}

/**
 * Switches the kind of target, discarding whatever was chosen.
 *
 * Clearing is the whole reason this exists as a function instead of an inline
 * update of `target.type`. The identifiers of products and of categories are
 * two independent sequences: product 3 and category 3 both exist and have
 * nothing to do with each other. Carrying the identifier across the switch
 * would leave the form holding a product's identifier labelled as a category,
 * a target that resolves to the wrong thing rather than to nothing. The
 * backend would accept it, because category 3 does exist, and would discount
 * the wrong catalogue: a silent wrong answer, not an error.
 */
export function withTargetType(
  state: PromotionFormState,
  type: PromotionTargetType,
): PromotionFormState {
  return { ...state, target: { type, optionId: '' } }
}

/** Records the chosen option, leaving the kind alone. */
export function withTargetOption(state: PromotionFormState, optionId: string): PromotionFormState {
  return { ...state, target: { ...state.target, optionId } }
}

/** The bounds of the amount, as the control declares them. */
export type DiscountValueLimits = {
  min: string
  /** Absent when the rule sets no ceiling. */
  max: string | undefined
}

/**
 * The numeric limits of the amount, which depend on the kind of discount.
 *
 * Mirrors the `discount_value` rules of both Form Requests: greater than zero
 * always, and between 1 and 100 when it is a percentage, because 150 % off is
 * not a discount. Restated here so the control can carry the real bounds. The
 * backend re-checks every one of them and remains the authority, exactly as
 * `domain/promotionActions.ts` sets out for the actions table.
 */
export function discountValueLimits(discountType: DiscountType): DiscountValueLimits {
  return discountType === 'PERCENTAGE'
    ? { min: '1', max: '100' }
    : // `gt:0` sets no ceiling. The floor is one step rather than `0`, which
      // the control would otherwise offer as a valid amount.
      { min: DISCOUNT_VALUE_STEP, max: undefined }
}

/**
 * The body the API would receive for this form.
 *
 * The single identifier becomes `product_id` or `category_id` here, at the one
 * crossing between the form's vocabulary and the wire's — the same place, and
 * the same shape of decision, as `StorePromotionRequest::target()` on the
 * backend. Because the state holds one identifier, there is nothing to choose
 * between and no way for both keys to reach the body.
 *
 * `status` is absent, and its absence is the point: `UpdatePromotionRequest`
 * declares it `prohibited` and answers 422 to any payload carrying it, because
 * the lifecycle only moves through `PATCH /api/promotions/{id}/status`.
 * Nothing is missing here.
 *
 * Answers `null` when no option is chosen. The control is `required`, so the
 * browser stops such a submit before this function is reached; the `null` is
 * what keeps the function total instead of inventing a `NaN` identifier for a
 * case that has no answer.
 */
export function buildPromotionPayload(state: PromotionFormState): PromotionPayload | null {
  if (state.target.optionId === '') {
    return null
  }

  const fields = {
    name: state.name,
    discount_type: state.discountType,
    // Sent as the characters that were typed. Neither `Number` nor `toFixed`
    // touches it on the way out.
    discount_value: state.discountValue,
    // Already `YYYY-MM-DD`: the date control produces the API's own format, so
    // no `Date` is constructed and no time zone takes part.
    start_date: state.startDate,
    end_date: state.endDate,
  }

  // Parsed because the wire says integer, and safe to parse: the only values
  // this field ever holds are the ones written into the options from the
  // catalogue, which are integers the API itself sent.
  const optionId = Number(state.target.optionId)

  return state.target.type === 'product'
    ? { ...fields, product_id: optionId }
    : { ...fields, category_id: optionId }
}
