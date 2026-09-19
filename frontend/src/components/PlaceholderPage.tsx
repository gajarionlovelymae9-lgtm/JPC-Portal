interface PlaceholderPageProps {
  title: string
  description: string
}

/**
 * "Coming soon" state used by every Phase 1 placeholder module page.
 * Full implementations arrive in later phases.
 */
export function PlaceholderPage({ title, description }: PlaceholderPageProps) {
  return (
    <section className="placeholder" aria-label="Coming soon">
      <div className="placeholder__icon" aria-hidden="true">
        +
      </div>
      <h2 className="placeholder__title">{title}</h2>
      <p className="placeholder__text">{description}</p>
      <span className="placeholder__badge">Coming soon</span>
    </section>
  )
}