import { useState } from 'react';

// Shown once after an account is created or its credentials are re-sent.
// The temporary password is displayed so it can be handed over in person when
// the email does not arrive; the server never stores it in plain text.
export default function CredentialsDialog({ result, onClose }) {
  const [copied, setCopied] = useState(false);
  if (!result) return null;
  const copy = async () => {
    try { await navigator.clipboard.writeText(result.temporary_password); setCopied(true); } catch { setCopied(false); }
  };
  return (
    <div className="confirm-overlay" onClick={onClose}>
      <section aria-labelledby="credentials-title" aria-modal="true" className="confirm-dialog" onClick={(event) => event.stopPropagation()} role="dialog">
        <div className="confirm-dialog-copy">
          <p className="eyebrow">Account ready</p>
          <h3 id="credentials-title">{result.name ? `${result.name}'s temporary password` : 'Temporary password'}</h3>
          {result.email_sent
            ? <p>The sign-in details were emailed to <strong>{result.email}</strong>.</p>
            : <p className="notice error" role="alert">The email to {result.email} could not be sent. Give the password below to the user in person.</p>}
          <p>
            <code style={{ fontSize: '1.1rem', fontWeight: 700, padding: '6px 12px', background: 'var(--surface-2, #f1f5f9)', borderRadius: 6, userSelect: 'all' }}>{result.temporary_password}</code>
          </p>
          <p className="muted">Shown only once. It expires in 72 hours, and the user must choose their own password at first sign-in.</p>
        </div>
        <div className="confirm-dialog-actions">
          <button className="ghost-button" onClick={copy} type="button">{copied ? 'Copied' : 'Copy password'}</button>
          <button className="primary-button" onClick={onClose} type="button">Done</button>
        </div>
      </section>
    </div>
  );
}
