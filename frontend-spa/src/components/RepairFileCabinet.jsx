import { useRef, useState } from 'react';
import Icon from './Icon';

const ACCEPT = '.jpg,.jpeg,.png,.pdf,.doc,.docx';

export default function RepairFileCabinet({ files, savedFiles = [], onChange, onError }) {
  const input = useRef(null);
  const [selected, setSelected] = useState([]);
  const [dragging, setDragging] = useState(false);
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
    const name = window.prompt('File name', file.name.slice(0, -extension.length));
    if (!name?.trim()) return;
    onChange(files.map((f, i) => i === index ? new File([f], name.trim().replace(/[\\/]/g, '-') + extension, { type: f.type, lastModified: f.lastModified }) : f));
  };
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
    </section>
  );
}
