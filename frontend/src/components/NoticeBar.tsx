import { useEffect, useRef } from 'react'
import { copy } from '../copy/es'
import type { Notice } from '../domain/notice'
import './NoticeBar.css'

type NoticeBarProps = {
  /** The line to show, or `null` when nothing has happened yet. */
  notice: Notice | null
  onDismiss: () => void
}

/**
 * What the screen says about the write that just ran.
 *
 * The element is rendered only when there is something to say, so nothing about
 * an operation is on screen before one has run. Announcement does not rest on
 * that alone — a live region inserted together with its text is handled
 * unevenly across screen readers — which is the second reason the focus moves
 * for the writes started from a row: the notice is then read because it is
 * where the focus landed, not only because it appeared.
 *
 * The role depends on the tone, and the difference is not cosmetic. `alert` is
 * assertive: it interrupts whatever is being read, which is right for a failure
 * and rude for a confirmation. `status` is polite and waits its turn, which is
 * what a success deserves.
 *
 * It is dismissed by hand and never on a timer. A message that removes itself
 * after a few seconds is a message that whoever reads slowly, or looked away,
 * never received at all.
 */
export function NoticeBar({ notice, onDismiss }: NoticeBarProps) {
  const ref = useRef<HTMLDivElement>(null)

  useEffect(() => {
    if (notice?.takeFocus === true) {
      ref.current?.focus()
    }
  }, [notice])

  if (notice === null) {
    return null
  }

  return (
    <div
      className={`notice notice--${notice.tone}`}
      ref={ref}
      role={notice.tone === 'error' ? 'alert' : 'status'}
      // Focusable only as a destination, never in the tab order: it is where
      // the focus is put when the button that was pressed no longer exists, and
      // a bar that also had to be tabbed through would be in the way of
      // everyone else.
      tabIndex={-1}
    >
      <p className="notice__message">{notice.message}</p>
      <button className="notice__dismiss" type="button" onClick={onDismiss}>
        {copy.notices.dismiss}
      </button>
    </div>
  )
}
