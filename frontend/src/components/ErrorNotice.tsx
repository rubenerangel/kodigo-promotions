import { copy } from '../copy/es'
import './ErrorNotice.css'

type ErrorNoticeProps = {
  /** Already resolved to Spanish by the copy catalogue. */
  message: string
  onRetry: () => void
}

/**
 * A failure the user can act on.
 *
 * `role="alert"` so the message is announced when it replaces the content it
 * failed to load, and the retry button is a real button so it is reachable by
 * keyboard and named by its own text.
 */
export function ErrorNotice({ message, onRetry }: ErrorNoticeProps) {
  return (
    <div className="error-notice" role="alert">
      <p className="error-notice__message">{message}</p>
      <button type="button" className="error-notice__retry" onClick={onRetry}>
        {copy.actions.retry}
      </button>
    </div>
  )
}
