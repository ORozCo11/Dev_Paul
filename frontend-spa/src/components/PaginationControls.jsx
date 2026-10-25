import './PaginationControls.css';

function paginationPageList(current, total) {
  const delta = 2;
  const pages = [];
  for (let i = 1; i <= total; i += 1) {
    if (i === 1 || i === total || (i >= current - delta && i <= current + delta)) pages.push(i);
  }
  const withGaps = [];
  let prev;
  pages.forEach((p) => {
    if (prev != null && p - prev > 1) withGaps.push('…');
    withGaps.push(p);
    prev = p;
  });
  return withGaps;
}

export default function PaginationControls({ page, setPage, pageSize, setPageSize, pageSizeOptions = [10, 25, 50, 100], totalPages, showPageSize = true }) {
  if (totalPages <= 1 && (!showPageSize || pageSizeOptions.length <= 1)) return null;
  const pageList = paginationPageList(page, totalPages);
  return (
    <nav className="table-pagination" aria-label="Pagination">
      <div className="table-pagination-pages">
        <button type="button" className="pagination-arrow" disabled={page <= 1} onClick={() => setPage((p) => p - 1)} aria-label="Previous page">‹</button>
        {pageList.map((p, i) => (
          p === '…'
            ? <span key={`gap-${i}`} className="pagination-ellipsis">…</span>
            : <button key={p} type="button" className={`pagination-page${p === page ? ' active' : ''}`} aria-label={`Page ${p}`} aria-current={p === page ? 'page' : undefined} onClick={() => setPage(p)}>{p}</button>
        ))}
        <button type="button" className="pagination-arrow" disabled={page >= totalPages} onClick={() => setPage((p) => p + 1)} aria-label="Next page">›</button>
      </div>
      {showPageSize && <div className="table-pagination-size">
        <span className="muted">Page size:</span>
        {pageSizeOptions.map((n) => (
          <button key={n} type="button" className={`pagination-page${n === pageSize ? ' active' : ''}`} aria-label={`Show ${n} per page`} aria-pressed={n === pageSize} onClick={() => setPageSize(n)}>{n}</button>
        ))}
      </div>}
    </nav>
  );
}
