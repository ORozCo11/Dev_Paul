import { useEffect, useState } from 'react';
import api from '../api/axios';

function managedLink(value) {
  try {
    const url = new URL(value, window.location.href);
    const origin = new URL(api.defaults.baseURL, window.location.href).origin;
    return url.origin === origin && /^\/api\/files\/[^/]+$/.test(url.pathname) && url.searchParams.has('signature');
  } catch { return false; }
}

export default function ManagedFileLinks() {
  const [error, setError] = useState('');
  useEffect(() => {
    let disposed = false;
    const freshLink = async (url) => {
      const response = await api.get('/files/refresh', { params: { url } });
      return response.data.url;
    };
    const click = async (event) => {
      const link = event.target instanceof Element ? event.target.closest('a[href]') : null;
      if (!link || !managedLink(link.href) || event.defaultPrevented || event.button !== 0) return;
      event.preventDefault();
      const separate = link.target === '_blank' || event.ctrlKey || event.metaKey || event.shiftKey;
      const popup = separate ? window.open('', '_blank') : null;
      if (popup) popup.opener = null;
      try {
        const url = await freshLink(link.href);
        if (popup) popup.location.replace(url); else window.location.assign(url);
      } catch {
        popup?.close();
        if (!disposed) setError('This file could not be opened. Refresh the page and try again.');
      }
    };
    const imageError = async (event) => {
      const img = event.target;
      if (!(img instanceof HTMLImageElement) || !managedLink(img.src) || img.dataset.fileRetried) return;
      img.dataset.fileRetried = 'true';
      try { const url = await freshLink(img.src); if (!disposed && img.isConnected) img.src = url; } catch { /* the image's alt text remains available */ }
    };
    document.addEventListener('click', click, true);
    document.addEventListener('error', imageError, true);
    return () => { disposed = true; document.removeEventListener('click', click, true); document.removeEventListener('error', imageError, true); };
  }, []);
  return error ? <div className="toast-notice error" role="alert"><span>{error}</span><button type="button" onClick={() => setError('')}>Dismiss</button></div> : null;
}
