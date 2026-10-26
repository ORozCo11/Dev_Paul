import { Component } from 'react';

// Function components can't catch render errors (React only calls
// getDerivedStateFromError/componentDidCatch on class components), so this
// one legitimately has to be a class — everything else in this codebase is
// hooks/functions.
//
// Wraps the app so a crash anywhere in the render tree (e.g. malformed
// boundary GeoJSON, or any other unexpected data shape) shows this friendly
// fallback instead of a white screen.
class ErrorBoundary extends Component {
  constructor(props) {
    super(props);
    this.state = { hasError: false };
    this.handleReload = this.handleReload.bind(this);
  }

  static getDerivedStateFromError() {
    return { hasError: true };
  }

  componentDidCatch(error, info) {
    console.error('ErrorBoundary caught a render error:', error, info?.componentStack);
  }

  handleReload() {
    window.location.reload();
  }

  render() {
    if (!this.state.hasError) {
      return this.props.children;
    }

    return (
      <main className="auth-page">
        <section className="auth-card">
          <p className="eyebrow">VMS</p>
          <h1>Something went wrong</h1>
          <p className="auth-subtitle">
            An unexpected error occurred and this page couldn&apos;t continue.
            Reloading usually fixes it.
          </p>
          <button
            type="button"
            className="primary-link"
            style={{ border: 'none', cursor: 'pointer', marginTop: 16 }}
            onClick={this.handleReload}
          >
            Reload page
          </button>
        </section>
      </main>
    );
  }
}

export default ErrorBoundary;
