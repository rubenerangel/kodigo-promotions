import { useRef, useState } from 'react'
import type { FormEvent } from 'react'
import { ErrorNotice } from './ErrorNotice'
import { copy } from '../copy/es'
import { resolveErrorMessage } from '../copy/resolveErrorMessage'
import {
  DISCOUNT_VALUE_STEP,
  NAME_MAX_LENGTH,
  buildPromotionPayload,
  discountValueLimits,
  initialPromotionForm,
  withTargetOption,
  withTargetType,
} from '../domain/promotionForm'
import type { PromotionFormRequest, PromotionFormState } from '../domain/promotionForm'
import {
  countIssues,
  firstIssueField,
  hasIssues,
  validatePromotionForm,
  withoutIssues,
} from '../domain/promotionFormIssues'
import type {
  PromotionFormField,
  PromotionFormIssues,
  PromotionSubmitOutcome,
} from '../domain/promotionFormIssues'
import { useApiResource } from '../hooks/useApiResource'
import { fetchPromotionTargets } from '../services/promotionTargets'
import { DISCOUNT_TYPES, PROMOTION_TARGET_TYPES } from '../types/api'
import type { PromotionPayload, PromotionTargetOption } from '../types/api'
import './PromotionForm.css'

/** The id of the message belonging to a field, so the control can point at it. */
function issueId(field: PromotionFormField): string {
  return `promotion-${field}-issue`
}

/** Joins the ids a control is described by, dropping the ones that are absent. */
function describedBy(...ids: readonly (string | false)[]): string | undefined {
  const present = ids.filter((id): id is string => id !== false)

  return present.length === 0 ? undefined : present.join(' ')
}

type PromotionFormProps = {
  request: PromotionFormRequest
  onCancel: () => void
  /**
   * Sends the body to the API and answers what became of it.
   *
   * The form is told the outcome rather than watching a prop, because the
   * messages beside its controls are its own state. A 422 comes back as
   * `invalid` and lands in the same slots the form's own checks use; anything
   * the screen dealt with itself comes back as `handled`, and there is nothing
   * left to draw.
   */
  onSubmit: (payload: PromotionPayload) => Promise<PromotionSubmitOutcome>
}

/**
 * The fields of a promotion, for creating one and for editing one.
 *
 * Mounted only while the dialog is open, and remounted on every open. That is
 * what resets the fields between uses, and it is also why the catalogue is
 * read here rather than by the screen: the lists of products and categories
 * are of no use to anyone who never opens this form, and a session that opens
 * it twice is better served by a second read than by a list that was true when
 * the page loaded.
 *
 * WHY THE FORM VALIDATES ITSELF WITH THE NATIVE ATTRIBUTES STILL IN PLACE.
 *
 * `noValidate` turns off the browser's own reporting, and every `required`,
 * `min`, `max` and `step` stays exactly where it was. The two do different
 * jobs. The attributes shape the control and describe it: they are what makes a
 * phone show a numeric keypad, what puts real bounds on the spinner, and what
 * exposes the field as required to a screen reader. The browser's *reporting*
 * is what had to go, because it shows one bubble at a time, in the language of
 * the browser rather than of the page, positioned over the layout and dismissed
 * by the next scroll. Our messages are in Spanish, appear beside every field
 * that has one at once, stay on screen, and are read out as part of the field
 * they belong to through `aria-describedby`. Keeping both would mean the
 * browser silently winning the race and our messages never appearing at all.
 *
 * THE CLIENT IS NOT THE AUTHORITY. Every rule checked here is checked again by
 * the Form Requests, and a 422 lands in these same slots.
 */
export function PromotionForm({ request, onCancel, onSubmit }: PromotionFormProps) {
  const [state, setState] = useState(() => initialPromotionForm(request))
  const [issues, setIssues] = useState<PromotionFormIssues>({})
  /** A failure that belongs to no single field: the write did not happen. */
  const [failure, setFailure] = useState<string | null>(null)
  const [isSubmitting, setIsSubmitting] = useState(false)
  const catalog = useApiResource(fetchPromotionTargets)

  /**
   * The controls, so the focus can be moved to the first one with a problem.
   * Held as elements rather than looked up by id at submit time, which would
   * work and would put a `document.getElementById` inside a React component.
   */
  const controls = useRef<Partial<Record<PromotionFormField, HTMLElement | null>>>({})

  const limits = discountValueLimits(state.discountType)
  const options: PromotionTargetOption[] =
    state.target.type === 'product'
      ? (catalog.data?.products ?? [])
      : (catalog.data?.categories ?? [])

  const hasNoCatalogAtAll =
    catalog.data !== null &&
    catalog.data.products.length === 0 &&
    catalog.data.categories.length === 0

  /**
   * Records new issues and sends the caret to the first of them.
   *
   * Saying that there are errors and leaving the focus where it was tells
   * someone who cannot see the whole form that a problem exists but not where,
   * which is the half of the message that is of no use.
   */
  function reportIssues(found: PromotionFormIssues): void {
    setIssues(found)

    const field = firstIssueField(found)

    if (field !== null) {
      controls.current[field]?.focus()
    }
  }

  /** Applies an edit and clears whatever the edited fields were complaining of. */
  function edit(next: PromotionFormState, ...touched: readonly PromotionFormField[]): void {
    setState(next)
    setIssues((previous) => withoutIssues(previous, ...touched))
    // A failure about a payload that no longer exists is a failure about
    // nothing, so the first edit after one clears it.
    setFailure(null)
  }

  async function handleSubmit(event: FormEvent<HTMLFormElement>): Promise<void> {
    event.preventDefault()

    // The button is disabled while a write runs; this covers the submit that
    // does not come from the button, such as Enter pressed in a text field.
    if (isSubmitting) {
      return
    }

    const found = validatePromotionForm(state)

    if (hasIssues(found)) {
      setFailure(null)
      reportIssues(found)

      return
    }

    const payload = buildPromotionPayload(state)

    // Unreachable while `targetRequired` above covers the only case that
    // answers null. Guarding anyway keeps an impossible state from becoming a
    // payload with an invented identifier.
    if (payload === null) {
      reportIssues({ target: 'targetRequired' })

      return
    }

    setIssues({})
    setFailure(null)
    setIsSubmitting(true)

    const outcome = await onSubmit(payload)

    setIsSubmitting(false)

    if (outcome.kind === 'invalid') {
      reportIssues(outcome.issues)
    } else if (outcome.kind === 'failed') {
      setFailure(outcome.message)
    }
  }

  if (catalog.error !== null) {
    return (
      <div className="promotion-form__notice">
        <ErrorNotice message={resolveErrorMessage(catalog.error)} onRetry={catalog.reload} />
      </div>
    )
  }

  if (catalog.isLoading) {
    return (
      <p className="promotion-form__notice" role="status">
        {copy.form.catalog.loading}
      </p>
    )
  }

  if (hasNoCatalogAtAll) {
    return (
      <p className="promotion-form__notice promotion-form__notice--empty">
        {copy.form.catalog.empty}
      </p>
    )
  }

  const issueCount = countIssues(issues)

  return (
    <form className="promotion-form" onSubmit={handleSubmit} noValidate>
      {/* Says how many fields are wrong, for whoever submitted with half the
          form scrolled out of sight. The messages themselves stay beside their
          controls; this only says where to look. */}
      {issueCount > 0 && (
        <p className="promotion-form__alert" role="alert">
          {copy.form.issueSummary(issueCount)}
        </p>
      )}

      {failure !== null && (
        <p className="promotion-form__alert" role="alert">
          {failure}
        </p>
      )}

      <div className="promotion-form__field">
        <label className="promotion-form__label" htmlFor="promotion-name">
          {copy.form.fields.name}
        </label>
        <input
          className="promotion-form__control"
          id="promotion-name"
          name="name"
          type="text"
          maxLength={NAME_MAX_LENGTH}
          required
          autoFocus
          ref={(element) => {
            controls.current.name = element
          }}
          aria-invalid={issues.name !== undefined}
          aria-describedby={describedBy(issues.name !== undefined && issueId('name'))}
          value={state.name}
          onChange={(event) => edit({ ...state, name: event.target.value }, 'name')}
        />
        {issues.name !== undefined && (
          <p className="promotion-form__issue" id={issueId('name')}>
            {copy.form.issues[issues.name]}
          </p>
        )}
      </div>

      {/* A fieldset, so the pair of radios is announced as one question with
          its legend, instead of two unrelated controls. */}
      <fieldset className="promotion-form__group">
        <legend className="promotion-form__legend">{copy.form.fields.targetType}</legend>
        <div className="promotion-form__choices">
          {PROMOTION_TARGET_TYPES.map((type) => (
            <label className="promotion-form__choice" key={type}>
              <input
                type="radio"
                name="target_type"
                value={type}
                checked={state.target.type === type}
                // Switching clears the chosen option. Product 3 and category 3
                // are unrelated rows, so carrying the identifier across would
                // aim the promotion at the wrong thing rather than at nothing,
                // and the backend would accept it.
                onChange={() => edit(withTargetType(state, type), 'target')}
              />
              <span>{copy.targetTypes[type]}</span>
            </label>
          ))}
        </div>
      </fieldset>

      <div className="promotion-form__field">
        <label className="promotion-form__label" htmlFor="promotion-target">
          {copy.targetTypes[state.target.type]}
        </label>
        {options.length === 0 ? (
          <p className="promotion-form__hint promotion-form__hint--empty">
            {copy.form.targetEmpty[state.target.type]}
          </p>
        ) : (
          <select
            className="promotion-form__control"
            id="promotion-target"
            name="target"
            required
            ref={(element) => {
              controls.current.target = element
            }}
            aria-invalid={issues.target !== undefined}
            aria-describedby={describedBy(issues.target !== undefined && issueId('target'))}
            value={state.target.optionId}
            onChange={(event) => edit(withTargetOption(state, event.target.value), 'target')}
          >
            {/* Empty value, so the required select stays invalid until a real
                option is picked. */}
            <option value="">{copy.form.targetPlaceholder}</option>
            {options.map((option) => (
              <option key={option.id} value={String(option.id)}>
                {option.name}
              </option>
            ))}
          </select>
        )}
        {issues.target !== undefined && (
          <p className="promotion-form__issue" id={issueId('target')}>
            {copy.form.issues[issues.target]}
          </p>
        )}
      </div>

      <fieldset className="promotion-form__group">
        <legend className="promotion-form__legend">{copy.form.fields.discountType}</legend>
        <div className="promotion-form__choices">
          {DISCOUNT_TYPES.map((discountType) => (
            <label className="promotion-form__choice" key={discountType}>
              <input
                type="radio"
                name="discount_type"
                value={discountType}
                checked={state.discountType === discountType}
                // The amount is checked against the rule its kind carries, so
                // changing the kind can settle or unsettle the amount. The
                // message goes with it rather than outliving the rule it was
                // about.
                onChange={() => edit({ ...state, discountType }, 'discountValue')}
              />
              <span>{copy.discountTypes[discountType]}</span>
            </label>
          ))}
        </div>
      </fieldset>

      <div className="promotion-form__field">
        <label className="promotion-form__label" htmlFor="promotion-discount-value">
          {copy.form.fields.discountValue}
        </label>
        <input
          className="promotion-form__control"
          id="promotion-discount-value"
          name="discount_value"
          type="number"
          inputMode="decimal"
          step={DISCOUNT_VALUE_STEP}
          min={limits.min}
          max={limits.max}
          required
          ref={(element) => {
            controls.current.discountValue = element
          }}
          aria-invalid={issues.discountValue !== undefined}
          // The hint stays in the list when there is a message: the accepted
          // range is still worth hearing, and it is what says how to fix it.
          aria-describedby={describedBy(
            issues.discountValue !== undefined && issueId('discountValue'),
            'promotion-discount-value-hint',
          )}
          value={state.discountValue}
          // Read as characters and stored as characters: the exact decimal is
          // what the API expects, and a trip through a number is where it
          // would be lost.
          onChange={(event) => edit({ ...state, discountValue: event.target.value }, 'discountValue')}
        />
        {issues.discountValue !== undefined && (
          <p className="promotion-form__issue" id={issueId('discountValue')}>
            {copy.form.issues[issues.discountValue]}
          </p>
        )}
        <p className="promotion-form__hint" id="promotion-discount-value-hint">
          {copy.form.discountValueHints[state.discountType]}
        </p>
      </div>

      <div className="promotion-form__dates">
        <div className="promotion-form__field">
          <label className="promotion-form__label" htmlFor="promotion-start-date">
            {copy.form.fields.startDate}
          </label>
          {/* A date control writes YYYY-MM-DD, which is the API's own format,
              so no Date is built and no time zone takes part. */}
          <input
            className="promotion-form__control"
            id="promotion-start-date"
            name="start_date"
            type="date"
            required
            ref={(element) => {
              controls.current.startDate = element
            }}
            aria-invalid={issues.startDate !== undefined}
            aria-describedby={describedBy(issues.startDate !== undefined && issueId('startDate'))}
            value={state.startDate}
            // Moving the start can settle or unsettle the end, since the rule
            // is about the pair, so both messages are cleared together.
            onChange={(event) =>
              edit({ ...state, startDate: event.target.value }, 'startDate', 'endDate')
            }
          />
          {issues.startDate !== undefined && (
            <p className="promotion-form__issue" id={issueId('startDate')}>
              {copy.form.issues[issues.startDate]}
            </p>
          )}
        </div>

        <div className="promotion-form__field">
          <label className="promotion-form__label" htmlFor="promotion-end-date">
            {copy.form.fields.endDate}
          </label>
          <input
            className="promotion-form__control"
            id="promotion-end-date"
            name="end_date"
            type="date"
            required
            // The end has to fall after the start, never on it, matching the
            // date_range check the table carries.
            min={state.startDate === '' ? undefined : state.startDate}
            ref={(element) => {
              controls.current.endDate = element
            }}
            aria-invalid={issues.endDate !== undefined}
            aria-describedby={describedBy(issues.endDate !== undefined && issueId('endDate'))}
            value={state.endDate}
            onChange={(event) => edit({ ...state, endDate: event.target.value }, 'endDate')}
          />
          {issues.endDate !== undefined && (
            <p className="promotion-form__issue" id={issueId('endDate')}>
              {copy.form.issues[issues.endDate]}
            </p>
          )}
        </div>
      </div>

      <div className="promotion-form__actions">
        <button
          className="promotion-form__button"
          type="button"
          disabled={isSubmitting}
          onClick={onCancel}
        >
          {copy.form.cancel}
        </button>
        <button
          className="promotion-form__button promotion-form__button--primary"
          type="submit"
          disabled={isSubmitting}
        >
          {isSubmitting
            ? copy.form.submitting
            : request.mode === 'create'
              ? copy.form.createSubmit
              : copy.form.editSubmit}
        </button>
      </div>
    </form>
  )
}
