import './SummaryCounter.css'

export type SummaryCounterTone = 'scheduled' | 'active' | 'finished' | 'validity'

type SummaryCounterProps = {
  label: string
  /** The count, or `null` while it is still being read. */
  value: number | null
  tone: SummaryCounterTone
  /** Id of the text that explains this counter, when one exists. */
  describedBy?: string
}

/**
 * One counter card.
 *
 * The label is always printed, so the card says which state it counts in
 * words; `tone` only chooses the colour that repeats that same fact. A card
 * therefore reads correctly with the colour ignored, which is the point.
 *
 * While `value` is null the card renders a placeholder that occupies exactly
 * the line box the number will occupy, so the arriving data replaces it
 * without moving anything on the page.
 */
export function SummaryCounter({ label, value, tone, describedBy }: SummaryCounterProps) {
  const isLoading = value === null

  return (
    <li className={`counter counter--${tone}`} aria-describedby={describedBy}>
      {isLoading ? (
        <span className="counter__value counter__value--placeholder" aria-hidden="true" />
      ) : (
        <span className="counter__value">{value}</span>
      )}
      <span className="counter__label">{label}</span>
    </li>
  )
}
