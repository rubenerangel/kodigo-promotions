/**
 * Renders an API date, exactly as the day it names.
 *
 * The API sends dates as `YYYY-MM-DD`, with no time and no zone, because the
 * column is DATE: `2026-09-01` is a day on a calendar, not an instant.
 *
 * `new Date('2026-09-01')` does NOT preserve that, and this is the trap this
 * module exists to avoid. ECMAScript parses the date-only form as UTC
 * midnight, so any local formatting of it in a negative offset — this project
 * runs at UTC-5 — renders the day before: 31/08/2026. The shift is silent, and
 * it lands precisely on the boundary dates, which are the ones where
 * `validToday` changes value. A row would then read "hasta el 31/08" while the
 * summary counts the promotion as running today, and the screen would be
 * contradicting itself on the only dates anybody checks carefully.
 *
 * So the string is never turned into an instant. It arrives in the form it has
 * to be shown in, and the only work left is reordering its parts. Nothing here
 * should be "simplified" into `new Date(value).toLocaleDateString()`: that is
 * the bug, not the shorter version of this.
 */

/** `YYYY-MM-DD`, captured as the three civil parts the display reorders. */
const ISO_DATE = /^(\d{4})-(\d{2})-(\d{2})$/

/**
 * `2026-09-01` becomes `01/09/2026`.
 *
 * A string that is not a plain date is returned untouched rather than being
 * reshaped into something that looks like a date: showing the raw value makes
 * a contract change visible instead of hiding it behind plausible digits.
 */
export function formatPromotionDate(date: string): string {
  const parts = ISO_DATE.exec(date)

  if (parts === null) {
    return date
  }

  const [, year, month, day] = parts

  return `${day}/${month}/${year}`
}
