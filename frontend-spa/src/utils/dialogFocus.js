// Keep keyboard interaction inside a modal and restore the opener on close.
export function manageDialogFocus(panel, { onClose, isBusy = () => false }) {
  const doc = panel.ownerDocument;
  const previous = doc.activeElement;
  const oldOverflow = doc.body.style.overflow;
  const focusable = () => [...panel.querySelectorAll('button:not(:disabled), a[href], input:not(:disabled), select:not(:disabled), textarea:not(:disabled), [tabindex="0"]')]
    .filter((element) => element.getClientRects().length && !element.closest('[hidden], [inert]'));
  const focusFirst = () => (focusable()[0] ?? panel).focus();
  doc.body.style.overflow = 'hidden';
  focusFirst();
  const keydown = (event) => {
    if (doc.querySelector('.confirm-overlay')) return;
    if (event.key === 'Escape') {
      event.preventDefault();
      event.stopPropagation();
      if (!isBusy()) onClose();
      return;
    }
    if (event.key !== 'Tab') return;
    const elements = focusable();
    const first = elements[0];
    const last = elements.at(-1);
    if (!first) {
      event.preventDefault();
      panel.focus();
    } else if (!panel.contains(doc.activeElement) || doc.activeElement === panel) {
      event.preventDefault();
      (event.shiftKey ? last : first).focus();
    } else if (event.shiftKey && doc.activeElement === first) {
      event.preventDefault();
      last.focus();
    } else if (!event.shiftKey && doc.activeElement === last) {
      event.preventDefault();
      first.focus();
    }
  };
  doc.addEventListener('keydown', keydown);
  return () => {
    doc.removeEventListener('keydown', keydown);
    doc.body.style.overflow = oldOverflow;
    if (previous?.isConnected) previous.focus();
  };
}
