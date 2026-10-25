import PaginationControls from './PaginationControls';
import { cloneElement, Fragment, useEffect, useId, useRef, useState } from 'react';
import Icon from './Icon';
import ConfirmDialog from './ConfirmDialog';
import SuccessCheck from './SuccessCheck';

export function AdminConfirmDialog({ dialog, busy, onCancel, onConfirm }) {
  const current = useRef({ busy, onCancel });
  useEffect(() => { current.current = { busy, onCancel }; }, [busy, onCancel]);
  useEffect(() => {
    if (!dialog) return;
    const previous = document.activeElement;
    const panel = document.querySelector('.superadmin-shell .confirm-dialog');
    panel?.querySelector('button')?.focus();
    const keydown = (event) => {
      if (event.key === 'Escape' && !current.current.busy) current.current.onCancel();
      if (event.key !== 'Tab' || !panel) return;
      const buttons = [...panel.querySelectorAll('button:not(:disabled)')];
      if (!buttons.length) { event.preventDefault(); return; }
      if (!panel.contains(document.activeElement) || event.shiftKey && document.activeElement === buttons[0]) { event.preventDefault(); buttons.at(-1).focus(); }
      else if (!event.shiftKey && document.activeElement === buttons.at(-1)) { event.preventDefault(); buttons[0].focus(); }
    };
    document.addEventListener('keydown', keydown);
    return () => { document.removeEventListener('keydown', keydown); if (previous?.isConnected) previous.focus(); };
  }, [dialog]);
  return <ConfirmDialog dialog={dialog} busy={busy} onCancel={onCancel} onConfirm={onConfirm} />;
}

// Brief check-mark confirmation after an approval or a sent email; closes itself.
export function SuccessCelebration({ celebration, onDone }) {
  useEffect(() => {
    if (!celebration) return undefined;
    const timer = setTimeout(onDone, 2200);
    return () => clearTimeout(timer);
  }, [celebration, onDone]);
  if (!celebration) return null;
  return <div className="sa-celebration" role="status" onClick={onDone}>
    <div className="sa-celebration-card"><SuccessCheck size={120} /><strong>{celebration.title}</strong>{celebration.detail && <p>{celebration.detail}</p>}</div>
  </div>;
}

export function StatusBadge({ value }) {
  return <span className={`status-badge ${String(value ?? '').toLowerCase().replaceAll(' ', '-')}`}>{value ?? '\u2014'}</span>;
}

export function LocalSearchInput({ value, onChange, placeholder = 'Search...' }) {
  return <div className="sa-search"><Icon name="search" size={17} /><input type="search" aria-label={placeholder} value={value} onChange={(event) => onChange(event.target.value)} placeholder={placeholder} />{value && <button type="button" aria-label="Clear search" onClick={() => onChange('')}><Icon name="close" size={14} /></button>}</div>;
}

export function PaginatedTable({ columns, rows, emptyMessage = 'No matching records', emptyDescription = 'Try another search or adjust the filters.', emptyIcon, groupBy, rowClassName, initialPageSize = 25, pageSizeOptions = [10, 25, 50, 100], pagination, onPageChange, onPageSizeChange }) {
  const rowsKey = rows.map((row, index) => row.id ?? row.log_id ?? index).join(',');
  const [paging, setPaging] = useState({ page: 1, size: initialPageSize, rowsKey });
  // Notification polling and theme changes must not reset a table's page.
  if (paging.rowsKey !== rowsKey) setPaging({ ...paging, page: 1, rowsKey });
  if (!rows.length) return <AdminEmptyState icon={emptyIcon} title={emptyMessage} description={emptyDescription} />;
  const size = pagination?.size ?? paging.size;
  const total = pagination?.total ?? rows.length;
  const totalPages = Math.max(1, Math.ceil(total / size));
  const page = Math.min(pagination?.page ?? paging.page, totalPages);
  const start = (page - 1) * size;
  const visible = pagination ? rows : rows.slice(start, start + size);
  const changePage = (next) => pagination ? onPageChange(next) : setPaging({ ...paging, page: next });

  return <>
    <div className="table-shell sa-table"><table><thead><tr>{columns.map((column) => <th scope="col" className={column.className} key={column.label}>{column.label}</th>)}</tr></thead>
      <tbody>{visible.map((row, index) => <Fragment key={row.id ?? row.log_id ?? index}>
        {groupBy && (index === 0 || groupBy(row) !== groupBy(visible[index - 1])) && <tr className="sa-group-row"><th scope="rowgroup" colSpan={columns.length}><Icon name="pin" size={14} />{groupBy(row)}</th></tr>}
        <tr className={rowClassName?.(row)}>{columns.map((column) => <td className={column.className} data-label={column.label} key={column.label}>{column.render ? column.render(row) : (row[column.key] ?? '\u2014')}</td>)}</tr>
      </Fragment>)}</tbody></table></div>
    <PaginationControls page={page} totalPages={totalPages} pageSize={size} pageSizeOptions={pageSizeOptions}
      setPage={(next) => changePage(typeof next === 'function' ? next(page) : next)}
      setPageSize={(next) => pagination ? onPageSizeChange(next) : setPaging({ ...paging, size: next, page: 1 })} />
  </>;
}

export function AdminEmptyState({ icon = 'search', title, description, children }) {
  return <div className="sa-empty" role="status">
    <span className="sa-empty-icon"><Icon name={icon} size={24} /></span>
    <strong>{title}</strong><p>{description}</p>{children}
  </div>;
}

export function AdminFilterField({ label, children }) {
  const id = useId();
  return <div className="sa-filter-field"><label htmlFor={id}>{label}</label>{cloneElement(children, { id })}</div>;
}

export function AdminManagementCard({ summary, toolbar, children, className = '' }) {
  return <section className={`sa-management-card ${className}`.trim()}>
    {summary && <div className="sa-card-summary">{summary}</div>}
    {toolbar && <div className="sa-toolbar">{toolbar}</div>}
    {children}
  </section>;
}

export function AdminIdentity({ name, email }) {
  const initials = name?.trim().split(/\s+/).slice(0, 2).map((part) => part[0]).join('') || '?';
  return <div className="sa-identity"><span className="sa-avatar" aria-hidden="true">{initials}</span><div><strong>{name || 'Unknown'}</strong>{email && <span>{email}</span>}</div></div>;
}

export function AdminActionDrawer({ title, description, onClose, children, footer, centered = false, className = '' }) {
  const titleId = useId();
  const panelRef = useRef(null);
  const closeRef = useRef(onClose);
  useEffect(() => { closeRef.current = onClose; }, [onClose]);
  useEffect(() => {
    const previous = document.activeElement;
    const oldOverflow = document.body.style.overflow;
    document.body.style.overflow = 'hidden';
    const panel = panelRef.current;
    panel.querySelector('button')?.focus();
    const keydown = (event) => {
      // A confirmation dialog takes focus precedence over an open drawer.
      if (document.querySelector('.confirm-overlay')) return;
      if (event.key === 'Escape') closeRef.current();
      if (event.key !== 'Tab') return;
      const focusable = [...panel.querySelectorAll('button:not(:disabled), a[href], input:not(:disabled), select:not(:disabled), textarea:not(:disabled), summary, [tabindex="0"]')].filter((el) => el.getClientRects().length);
      const first = focusable[0];
      const last = focusable.at(-1);
      if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last?.focus(); }
      if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first?.focus(); }
    };
    document.addEventListener('keydown', keydown);
    return () => { document.body.style.overflow = oldOverflow; document.removeEventListener('keydown', keydown); if (previous?.isConnected) previous.focus(); };
  }, []);
  return <div className={`sa-drawer-overlay${centered ? ' sa-modal' : ''}`} onClick={onClose}>
    <section ref={panelRef} className={`sa-drawer ${className}`.trim()} role="dialog" aria-modal="true" aria-labelledby={titleId} onClick={(event) => event.stopPropagation()}>
      <header><div><h2 id={titleId}>{title}</h2>{description && <p>{description}</p>}</div><button className="ghost-button" type="button" onClick={onClose} aria-label="Close details"><Icon name="close" size={18} /></button></header>
      <div className="sa-drawer-body">{children}</div>
      {footer && <footer>{footer}</footer>}
    </section>
  </div>;
}
