// Shared styled confirmation modal — used anywhere a consequential action
// (deactivate, delete, impersonate, regenerate a code, discard unsaved
// changes...) needs a yes/no gate, instead of the browser's own native
// window.confirm(). `dialog` is null when closed; set it to
// { title?, message, confirmLabel?, variant?: 'primary', onConfirm } to open.
import { useEffect } from 'react';

export default function ConfirmDialog({ busy, dialog, onCancel, onConfirm }) {
  // Escape cancels (unless an action is already running).
  useEffect(() => {
    if (!dialog) return undefined;
    const onKey = (e) => { if (e.key === 'Escape' && !busy) onCancel?.(); };
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, [dialog, busy, onCancel]);

  if (!dialog) return null;

  return (
    <div className="confirm-overlay" onClick={busy ? undefined : onCancel}>
      <section
        aria-labelledby="confirm-dialog-title"
        aria-modal="true"
        className="confirm-dialog"
        onClick={(event) => event.stopPropagation()}
        role="dialog"
      >
        <div className="confirm-dialog-icon" aria-hidden="true">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.4" strokeLinecap="round" strokeLinejoin="round">
            <path d="M12 9v4" />
            <path d="M12 17h.01" />
            <path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z" />
          </svg>
        </div>
        <div className="confirm-dialog-copy">
          <p className="eyebrow">Confirmation</p>
          <h3 id="confirm-dialog-title">{dialog.title ?? 'Confirm Action'}</h3>
          <p>{dialog.message}</p>
        </div>
        <div className="confirm-dialog-actions">
          <button className="ghost-button" disabled={busy} onClick={onCancel} type="button">
            Cancel
          </button>
          <button
            className={dialog.variant === 'primary' ? 'primary-button' : 'danger-button'}
            disabled={busy}
            onClick={onConfirm}
            type="button"
          >
            {busy ? 'Working...' : dialog.confirmLabel ?? 'Continue'}
          </button>
        </div>
      </section>
    </div>
  );
}
