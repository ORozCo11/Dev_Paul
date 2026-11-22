import { useEffect, useRef, useState } from 'react';
import Icon from './Icon';

// "2 hours ago" style label, computed once per open so rendering stays pure.
function ago(value, now) {
  const minutes = Math.max(0, Math.floor((now - new Date(value).getTime()) / 60000));
  if (minutes < 1) return 'just now';
  if (minutes < 60) return `${minutes} min ago`;
  const hours = Math.floor(minutes / 60);
  if (hours < 24) return `${hours} hour${hours === 1 ? '' : 's'} ago`;
  const days = Math.floor(hours / 24);
  return `${days} day${days === 1 ? '' : 's'} ago`;
}

/**
 * The notice list. Every notice the system sends (a proposal waiting, a repair to verify, a decline and why) shows here
 * with its full text; clicking one opens the ticket, schedule, vehicle or report it is about and marks it read.
 */
export default function NotificationBell({ notifications, onOpen, onMarkAllRead }) {
  const [open, setOpen] = useState(false);
  const [now, setNow] = useState(() => Date.now());
  const ref = useRef(null);
  const unread = notifications.filter((n) => !n.read_at).length;

  useEffect(() => {
    if (!open) return undefined;
    const close = (event) => { if (ref.current && !ref.current.contains(event.target)) setOpen(false); };
    const key = (event) => { if (event.key === 'Escape') setOpen(false); };
    document.addEventListener('mousedown', close);
    document.addEventListener('keydown', key);
    return () => { document.removeEventListener('mousedown', close); document.removeEventListener('keydown', key); };
  }, [open]);

  const toggle = () => { if (!open) setNow(Date.now()); setOpen((value) => !value); };

  return (
    <div className="notice-bell" ref={ref}>
      <button type="button" className="icon-btn notice-bell-btn" onClick={toggle} aria-label={unread ? `Notices (${unread} unread)` : 'Notices'} aria-expanded={open} title="Notices">
        <Icon name="bell" size={20} />
        {unread > 0 && <span className="notice-bell-count">{unread > 9 ? '9+' : unread}</span>}
      </button>
      {open && (
        <div className="notice-panel" role="dialog" aria-label="Notices">
          <div className="notice-panel-head">
            <strong>Notices</strong>
            <button type="button" className="notice-markall" disabled={!unread} onClick={onMarkAllRead}>Mark all as read</button>
          </div>
          {!notifications.length ? (
            <p className="notice-empty">No notices yet. Things that need you, such as a repair to verify or a proposal to review, will show up here.</p>
          ) : (
            <ul className="notice-list">
              {notifications.slice(0, 20).map((n) => (
                <li key={n.notification_id}>
                  <button type="button" className={`notice-item${n.read_at ? '' : ' is-unread'}`} onClick={() => { setOpen(false); onOpen(n); }}>
                    <span className="notice-item-title">{n.title}</span>
                    <span className="notice-item-text">{n.message}</span>
                    <span className="notice-item-time">{ago(n.created_at, now)}</span>
                  </button>
                </li>
              ))}
            </ul>
          )}
        </div>
      )}
    </div>
  );
}
