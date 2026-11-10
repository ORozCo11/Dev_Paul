import { useEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import Icon from './Icon';

const ACCEPT = '.jpg,.jpeg,.png,.pdf,.doc,.docx';

export default function RepairFileCabinet({ files, savedFiles = [], onChange, onError }) {
  const input = useRef(null);
  const [selected, setSelected] = useState([]);
  const [dragging, setDragging] = useState(false);
  // In-app rename dialog (null when closed) instead of the browser's own prompt().
  const [renaming, setRenaming] = useState(null);
  const previewFile = (file) => {
    const url = URL.createObjectURL(file);
    window.open(url, '_blank', 'noopener,noreferrer');
    setTimeout(() => URL.revokeObjectURL(url), 60000);
  };
  const addFiles = (incoming) => {
    const next = Array.from(incoming);
    if (files.length + next.length > 10) { onError?.('Attach up to 10 files per repair log.'); return; }
    if (next.some((file) => !/\.(jpe?g|png|pdf|docx?)$/i.test(file.name) || file.size > 8 * 1024 * 1024)) {
      onError?.('Choose JPG, PNG, PDF, DOC, or DOCX files up to 8 MB each.'); return;
    }
    onChange([...files, ...next]);
  };
  const rename = () => {
    const index = selected[0];
    const file = files[index];
    const extension = file.name.match(/\.[^.]+$/)?.[0] || '';
    setRenaming({ index, extension, name: file.name.slice(0, file.name.length - extension.length) });
  };
  const confirmRename = () => {
    const name = renaming?.name.trim().replace(/[\\/]/g, '-');
    if (!name) return;
    onChange(files.map((f, i) => i === renaming.index ? new File([f], name + renaming.extension, { type: f.type, lastModified: f.lastModified }) : f));
    setRenaming(null);
  };
  useEffect(() => {
    if (!renaming) return undefined;
    const onKey = (e) => { if (e.key === 'Escape') setRenaming(null); };
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, [renaming]);
  return (
    <section className="repair-file-cabinet" aria-label="Repair evidence files">
      <header className="repair-file-cabinet-header">
        <h4><Icon name="archive" size={16} />Files</h4>
        <div className="row-actions">
          <button type="button" className="lr-icon-btn btn-view-action" title="Preview selected file" aria-label="Preview selected file" disabled={selected.length !== 1} onClick={() => previewFile(files[selected[0]])}><Icon name="eye" size={15} /></button>
          <button type="button" className="lr-icon-btn btn-confirm-action" title="Add files" aria-label="Add files" onClick={() => input.current.click()}><Icon name="plus" size={15} /></button>
          <button type="button" className="lr-icon-btn btn-edit-action" title="Rename selected file" aria-label="Rename selected file" disabled={selected.length !== 1} onClick={rename}><Icon name="edit" size={15} /></button>
          <button type="button" className="lr-icon-btn btn-delete-action" title="Remove selected files" aria-label="Remove selected files" disabled={!selected.length} onClick={() => { onChange(files.filter((_, i) => !selected.includes(i))); setSelected([]); }}><Icon name="trash" size={15} /></button>
        </div>
      </header>
      <ul className="repair-file-list">
        {savedFiles.map((file, i) => <li key={file.url ?? i}><Icon name="clipboard" size={18} /><div><a href={file.url} target="_blank" rel="noreferrer">{file.name || 'Repair attachment'}</a><small>{file.added_by ? `Added by ${file.added_by}` : 'Previously attached'}</small></div></li>)}
        {files.map((file, i) => <li key={i}><input type="checkbox" aria-label={`Select ${file.name}`} checked={selected.includes(i)} onChange={(e) => setSelected(e.target.checked ? [...selected, i] : selected.filter((n) => n !== i))} /><Icon name="clipboard" size={18} /><div><button type="button" className="link-button" onClick={() => previewFile(file)}>{file.name}</button><small>{Math.max(1, Math.round(file.size / 1024))} KB · Ready to upload</small></div></li>)}
      </ul>
      <button type="button" className={`repair-file-drop${dragging ? ' is-dragging' : ''}`} onClick={() => input.current.click()} onDragOver={(e) => { e.preventDefault(); setDragging(true); }} onDragLeave={() => setDragging(false)} onDrop={(e) => { e.preventDefault(); setDragging(false); addFiles(e.dataTransfer.files); }}>
        <Icon name="archive" size={30} /><span>Drag files here</span><small>or click to browse</small>
      </button>
      <input ref={input} type="file" multiple accept={ACCEPT} className="lr-file-input" onChange={(e) => { addFiles(e.target.files); e.target.value = ''; }} />
      <p className="lr-hint">Repair evidence · up to 10 files, 8 MB each. Files upload when you submit the repair log.</p>
      {renaming && createPortal(
        <div className="confirm-overlay" onClick={() => setRenaming(null)}>
          <div className="confirm-dialog" role="dialog" aria-modal="true" aria-labelledby="rename-file-title" onClick={(e) => e.stopPropagation()}>
            <div className="confirm-dialog-icon" aria-hidden="true"><Icon name="edit" size={22} /></div>
            <div className="confirm-dialog-copy">
              <p className="eyebrow">Rename file</p>
              <h3 id="rename-file-title">File name</h3>
              <p>Choose a clear name for this file. The file type ({renaming.extension || 'none'}) stays the same.</p>
            </div>
            <label className="auth-field">
              <span>File name</span>
              <div className="auth-input-wrapper auth-input-plain">
                <input
                  autoFocus
                  type="text"
                  value={renaming.name}
                  onChange={(e) => setRenaming({ ...renaming, name: e.target.value })}
                  onFocus={(e) => e.target.select()}
                  onKeyDown={(e) => { if (e.key === 'Enter') { e.preventDefault(); confirmRename(); } }}
                />
              </div>
            </label>
            <div className="confirm-dialog-actions">
              <button className="ghost-button" type="button" onClick={() => setRenaming(null)}>Cancel</button>
              <button className="primary-button" type="button" disabled={!renaming.name.trim()} onClick={confirmRename}>Save name</button>
            </div>
          </div>
        </div>,
        document.body
      )}
    </section>
  );
}
