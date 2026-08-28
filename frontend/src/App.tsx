import { SummaryPanel } from './components/SummaryPanel'
import { copy } from './copy/es'
import './App.css'

/**
 * The application shell.
 *
 * One screen by design, so there is no router: a header naming the product and
 * a single main column that the promotion listing and the promotion form will
 * join, in that order, as they are built.
 */
function App() {
  return (
    <div className="app">
      <header className="app__header">
        <div className="app__bar">
          <h1 className="app__title">{copy.app.name}</h1>
        </div>
      </header>

      <main className="app__main">
        <SummaryPanel />
        {/* The promotion listing (task 5.2) and the promotion form (task 5.3)
            mount here, as further children of this column. Nothing is drawn
            for them yet: a placeholder would announce a feature that does not
            exist. */}
      </main>
    </div>
  )
}

export default App
