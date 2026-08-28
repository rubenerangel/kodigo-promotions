import type { PromotionAction } from './promotionActions'
import type { Promotion } from '../types/api'

/**
 * Which actions ask before they act.
 *
 * WHY NOT ALL OF THEM, GIVEN THAT EVERY STATE CHANGE IS IRREVERSIBLE.
 *
 * The lifecycle only moves forward — SCHEDULED to ACTIVE to FINISHED, with no
 * way back — so activating, finishing and deleting are all one way doors, and
 * the tempting rule is to confirm every one of them. That rule is worse than
 * this one, because a dialog that appears on every press stops being read: the
 * user learns the second click and applies it to the dialog that actually
 * mattered. A confirmation is only worth what the reader's attention is worth,
 * so it is spent where the loss is real.
 *
 * - `delete` destroys a row. Nothing brings it back, and the promotion stops
 *   existing rather than changing. It confirms.
 * - `finish` is terminal in the strongest sense the API has: a FINISHED
 *   promotion refuses updates (PROMOTION_FINISHED), refuses deletion
 *   (PROMOTION_NOT_SCHEDULED) and has no transition left. The promotion is
 *   closed for good, and closing it by accident cannot be undone by any means
 *   the interface offers. It confirms.
 * - `activate` moves one step forward and leaves the promotion fully editable
 *   and still finishable. What it costs is the ability to delete, which only
 *   SCHEDULED promotions allow — a real loss, but a small one next to the two
 *   above, and deleting a promotion that has already started is not a normal
 *   operation. It is also the move the user came to the row to make, its label
 *   says exactly what it does, and its effect is visible immediately in the
 *   status pill and in the counters. It does not confirm.
 * - `edit` opens a form, which is its own confirmation: nothing is written
 *   until the form is submitted.
 */
export type PromotionConfirmableAction = 'finish' | 'delete'

const CONFIRMABLE_ACTIONS: readonly PromotionAction[] = ['finish', 'delete']

/** Whether an action has to be confirmed before it is sent. */
export function needsConfirmation(action: PromotionAction): action is PromotionConfirmableAction {
  return CONFIRMABLE_ACTIONS.includes(action)
}

/**
 * What the confirmation is about.
 *
 * The promotion travels with the action, and not just its identifier, because
 * the question has to name it. "¿Seguro?" over a row the dialog has covered is
 * a question nobody can answer, so the text needs the name and the name has to
 * be here to be written.
 */
export type PromotionConfirmRequest = {
  action: PromotionConfirmableAction
  promotion: Promotion
}
