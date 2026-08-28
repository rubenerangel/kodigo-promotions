import type { PromotionStatus } from '../types/api'

/**
 * Which actions a promotion offers in each state.
 *
 * ONE PLACE, ON PURPOSE. Every component that has to decide whether a button
 * belongs on the screen asks here, so the rule cannot drift into three
 * slightly different rules spread across the listing, the row and the form.
 *
 * THE BACKEND REMAINS THE AUTHORITY. This table decides what is *shown*; it
 * authorises nothing. The API re-checks every one of these rules on its own
 * and answers 409 with a code when they do not hold, which is what protects
 * the data — a screen that had been open long enough for the promotion to have
 * changed underneath it, or a request sent by anything that is not this
 * screen, is caught there and only there. So the duplication below is
 * deliberate and accepted: without it the interface could only offer every
 * action always and let the user discover by failure which ones were real.
 * It is written down here so nobody later reads it as the rule and edits it
 * believing they are changing the system's behaviour.
 *
 * The table is derived from the backend, rule by rule:
 *
 * - `PromotionStatus::canTransitionTo` moves the lifecycle forward one step at
 *   a time — SCHEDULED to ACTIVE, ACTIVE to FINISHED — with no skips, no way
 *   back and no self transitions. Hence "activar" only on SCHEDULED,
 *   "finalizar" only on ACTIVE, and neither on FINISHED.
 * - Updating a FINISHED promotion is rejected (PROMOTION_FINISHED), so "editar"
 *   exists in the other two states only.
 * - Deleting is limited to SCHEDULED (PROMOTION_NOT_SCHEDULED), so "eliminar"
 *   appears nowhere else.
 *
 * If the backend's state machine ever changes, this table has to change with
 * it. Nothing fails automatically to say so, which is the cost of the
 * duplication and the reason the mapping is small, isolated and named.
 */
export type PromotionAction = 'activate' | 'finish' | 'edit' | 'delete'

/**
 * Order matters: it is the order the buttons appear in, and it puts the
 * lifecycle move first and the destructive action last, the same way in every
 * row, so a "eliminar" never lands where "editar" was in the row above.
 */
const ACTIONS_BY_STATUS: Record<PromotionStatus, readonly PromotionAction[]> = {
  SCHEDULED: ['activate', 'edit', 'delete'],
  ACTIVE: ['finish', 'edit'],
  // Terminal state: nothing may be done to it any more. The empty list is the
  // rule, not a gap left to fill in later.
  FINISHED: [],
}

/** The actions offered for a status, in the order they are shown. */
export function promotionActionsFor(status: PromotionStatus): readonly PromotionAction[] {
  return ACTIONS_BY_STATUS[status]
}
