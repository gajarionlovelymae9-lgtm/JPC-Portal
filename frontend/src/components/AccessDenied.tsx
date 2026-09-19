export function AccessDenied() {
  return (
    <div className="page-state page-state--denied" role="alert">
      <h1>Access denied</h1>
      <p>You do not have permission to view this page.</p>
    </div>
  )
}