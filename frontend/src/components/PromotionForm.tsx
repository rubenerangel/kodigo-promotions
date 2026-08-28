import { useState } from 'react'
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
import type { PromotionFormRequest } from '../domain/promotionForm'
import { useApiResource } from '../hooks/useApiResource'
import { fetchPromotionTargets } from '../services/promotionTargets'
import { DISCOUNT_TYPES, PROMOTION_TARGET_TYPES } from '../types/api'
import type { PromotionPayload, PromotionTargetOption } from '../types/api'
import './PromotionForm.css'

type PromotionFormProps = {
  request: PromotionFormRequest
  onCancel: () => void
  /**
   * Handed the body the API would receive. Sending it is task 5.5; this form
   * builds it and stops there.
   */
  onSubmit: (payload: PromotionPayload) => void
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
 */
export function PromotionForm({ request, onCancel, onSubmit }: PromotionFormProps) {
  const [state, setState] = useState(() => initialPromotionForm(request))
  const catalog = useApiResource(fetchPromotionTargets)

  const limits = discountValueLimits(state.discountType)
  const options: PromotionTargetOption[] =
    state.target.type === 'product'
      ? (catalog.data?.products ?? [])
      : (catalog.data?.categories ?? [])

  const hasNoCatalogAtAll =
    catalog.data !== null && catalog.data.products.length === 0 && catalog.data.categories.length === 0

  function handleSubmit(event: React.FormEvent<HTMLFormElement>): void {
    event.preventDefault()

    const payload = buildPromotionPayload(state)

    // Null only when nothing is chosen, which the required select already
    // prevents. Guarding keeps the impossible case from becoming a payload
    // with an invented identifier.
    if (payload !== null) {
      onSubmit(payload)
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

  return (
    <form className="promotion-form" onSubmit={handleSubmit} noValidate={false}>
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
          value={state.name}
          onChange={(event) => setState({ ...state, name: event.target.value })}
        />
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
                onChange={() => setState(withTargetType(state, type))}
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
            value={state.target.optionId}
            onChange={(event) => setState(withTargetOption(state, event.target.value))}
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
                onChange={() => setState({ ...state, discountType })}
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
          aria-describedby="promotion-discount-value-hint"
          value={state.discountValue}
          // Read as characters and stored as characters: the exact decimal is
          // what the API expects, and a trip through a number is where it
          // would be lost.
          onChange={(event) => setState({ ...state, discountValue: event.target.value })}
        />
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
            value={state.startDate}
            onChange={(event) => setState({ ...state, startDate: event.target.value })}
          />
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
            value={state.endDate}
            onChange={(event) => setState({ ...state, endDate: event.target.value })}
          />
        </div>
      </div>

      <div className="promotion-form__actions">
        <button className="promotion-form__button" type="button" onClick={onCancel}>
          {copy.form.cancel}
        </button>
        <button className="promotion-form__button promotion-form__button--primary" type="submit">
          {request.mode === 'create' ? copy.form.createSubmit : copy.form.editSubmit}
        </button>
      </div>
    </form>
  )
}
