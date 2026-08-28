import { copy } from '../copy/es'
import type { DiscountType } from '../types/api'

/**
 * Renders a discount for a Spanish reader.
 *
 * The API sends the amount as text, because the column is `decimal(10, 2)` and
 * text is what keeps the exact decimal intact through JSON. This module honours
 * that: the value is never parsed into a `number`. Everything here is string
 * surgery on the digits that arrived — group the thousands, swap the decimal
 * point for a comma — so nothing can be rounded away, and a value with more
 * precision than a float can hold would still come out whole.
 *
 * The separators live in the copy catalogue with the rest of the Spanish, so
 * this module formats and does not decide how Spanish writes numbers.
 */

/** A plain non-negative decimal, which is all the contract can produce. */
const DECIMAL_VALUE = /^\d+(\.\d+)?$/

/**
 * Inserts the thousands separator between the digit groups of an integer.
 *
 * The lookahead matches every position that has a multiple of three digits
 * after it and a digit before it, which is exactly where a separator goes.
 */
function groupThousands(integerPart: string): string {
  return integerPart.replace(/\B(?=(\d{3})+(?!\d))/g, copy.numbers.groupSeparator)
}

/**
 * `PERCENTAGE` with `15.00` becomes `15 %`; `FIXED_AMOUNT` with `2500.75`
 * becomes `2.500,75`.
 *
 * The two variants round differently on purpose. A percentage drops the zeros
 * the column pads it with, because `15,00 %` reads as false precision. An
 * amount keeps its two decimals, because that is how money is written and
 * `2.500,7` would look like a truncation.
 *
 * No currency symbol is added: the contract carries an amount and never says
 * which currency it is in, and inventing one would state something the data
 * does not. The column prints the discount type beside the value instead, so a
 * bare number is still read as an amount and not as a second percentage.
 *
 * A value that is not a plain decimal is returned untouched, for the same
 * reason a malformed date is: a contract change should be visible, not
 * reformatted into something that looks correct.
 */
export function formatPromotionDiscount(discountType: DiscountType, value: string): string {
  if (!DECIMAL_VALUE.test(value)) {
    return value
  }

  const [integerPart, fractionPart = ''] = value.split('.')
  const digits = groupThousands(integerPart)

  if (discountType === 'PERCENTAGE') {
    const fraction = fractionPart.replace(/0+$/, '')
    const amount =
      fraction === '' ? digits : `${digits}${copy.numbers.decimalSeparator}${fraction}`

    return `${amount}${copy.numbers.percentSuffix}`
  }

  return fractionPart === '' ? digits : `${digits}${copy.numbers.decimalSeparator}${fractionPart}`
}
