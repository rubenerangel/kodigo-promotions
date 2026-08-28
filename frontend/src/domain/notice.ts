/**
 * The one line the screen says about the operation that just ran.
 *
 * Every mutation ends here, successful or not, because a write that finishes
 * in silence leaves the user guessing: the dialog closed, the row moved, and
 * nothing said whether the server agreed. It is a resolved sentence rather
 * than a code, unlike the field issues, because a notice is an event and not
 * the state of a resource — there is nothing to re-derive on the next render,
 * and the text was taken from the catalogue the moment the event happened.
 */
export type NoticeTone = 'success' | 'error'

export type Notice = {
  tone: NoticeTone
  /** Already in Spanish, from the copy catalogue. */
  message: string
  /**
   * Asks the notice to take the focus.
   *
   * Set when the control that started the operation is gone from the screen by
   * the time the notice appears — a deleted row, or a button set replaced by
   * the one the new status offers. Without it the focus would fall back to the
   * document body and a keyboard user would restart from the top of the page.
   * A submission from the dialog does not set it: closing a `<dialog>` hands
   * focus back to whatever opened it, and that button is still there.
   */
  takeFocus: boolean
}
