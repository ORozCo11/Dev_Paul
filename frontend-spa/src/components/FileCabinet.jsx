import { useEffect, useId, useRef, useState } from 'react';
import Icon from './Icon';
import './FileCabinet.css';

function PendingFileLink({ file }) {
  const linkRef = useRef(null);
  useEffect(() => {
    const next = URL.createObjectURL(file);
    if (linkRef.current) linkRef.current.href = next;
    return () => URL.revokeObjectURL(next);
  }, [file]);
  return <a ref={linkRef} target="_blank" rel="noopener noreferrer">{file.name}</a>;
}

export default function FileCabinet({ files = [], existingFiles = [], onChange, onRemoveExisting, multiple = false, accept, required, name, title = 'Files' }) {
  const inputId = useId();
  const inputRef = useRef(null);
  const [selected, setSelected] = useState([]);
  const [expanded, setExpanded] = useState(false);
  const [dragging, setDragging] = useState(false);
  const [error, setError] = useState('');
  const rows = [...existingFiles.map((file, i) => ({ ...file, key: `existing-${file.id ?? i}`, existing: true })), ...files.map((file, i) => ({ file, key: `pending-${i}`, index: i }))];
  const addFiles = (incoming) => {
    const candidates = Array.from(incoming ?? []);
    const rules = (accept ?? '').split(',').map((rule) => rule.trim().toLowerCase()).filter(Boolean);
    const accepted = candidates.filter((file) => !rules.length || rules.some((rule) => rule.startsWith('.') ? file.name.toLowerCase().endsWith(rule) : rule.endsWith('/*') ? file.type.startsWith(rule.slice(0, -1)) : file.type === rule));
    if (accepted.length !== candidates.length) setError(`Choose files of these types: ${accept}.`);
    else if (!multiple && candidates.length > 1) setError('This field accepts one file. The first file was selected.');
    else setError('');
    if (!accepted.length) return;
    onChange(multiple ? [...files, ...accepted] : accepted.slice(0, 1));
    setSelected([]);
  };
  const removeSelected = () => {
    rows.filter((row) => selected.includes(row.key) && row.existing).forEach((row) => onRemoveExisting?.(row.original));
    onChange(files.filter((_, i) => !selected.includes(`pending-${i}`)));
    setSelected([]);
  };
  const removable = rows.some((row) => selected.includes(row.key) && (!row.existing || onRemoveExisting));
  return <div className={`file-cabinet${expanded ? ' is-expanded' : ''}${dragging ? ' is-dragging' : ''}`}>
    <div className="file-cabinet-toolbar">
      <strong>{title}</strong>
      <div className="file-cabinet-tools">
        <button type="button" aria-label={expanded ? 'Collapse files' : 'Expand files'} aria-expanded={expanded} onClick={() => setExpanded(!expanded)}><Icon name="columns" size={15} /></button>
        <button type="button" aria-label={multiple ? 'Add files' : 'Choose or replace file'} onClick={() => inputRef.current?.click()}><Icon name="plus" size={15} /></button>
        <button type="button" aria-label="Remove selected files" disabled={!removable} onClick={removeSelected}><Icon name="trash" size={15} /></button>
      </div>
    </div>
    <div className="file-cabinet-list">
      {rows.length ? rows.map((row) => <div className="file-cabinet-row" key={row.key}>
        <input type="checkbox" aria-label={`Select ${row.file?.name ?? row.name}`} checked={selected.includes(row.key)} onChange={(event) => setSelected((current) => event.target.checked ? [...current, row.key] : current.filter((key) => key !== row.key))} />
        <Icon name="clipboard" size={17} />
        <div className="file-cabinet-info">
          {row.existing ? <a href={row.url} target="_blank" rel="noopener noreferrer">{row.name}</a> : <PendingFileLink file={row.file} />}
          <small>{row.existing ? 'Saved file' : `${Math.max(1, Math.round(row.file.size / 1024))} KB · Ready to upload`}</small>
        </div>
      </div>) : <p className="file-cabinet-empty">No files attached</p>}
    </div>
    <div className="file-cabinet-drop" onDragOver={(event) => { event.preventDefault(); setDragging(true); }} onDragLeave={() => setDragging(false)} onDrop={(event) => { event.preventDefault(); setDragging(false); addFiles(event.dataTransfer.files); }}>
      <input ref={inputRef} id={inputId} aria-label={`Choose ${title.toLowerCase()} for ${name}`} type="file" name={name} accept={accept} multiple={multiple} required={required && !rows.length} onChange={(event) => { addFiles(event.target.files); event.target.value = ''; }} />
      <button type="button" onClick={() => inputRef.current?.click()}>
        <svg width="38" height="38" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.7" strokeLinecap="round" strokeLinejoin="round"><path d="M7 18H6a4 4 0 0 1-.8-7.9A7 7 0 0 1 18.5 8a5 5 0 0 1-.5 10h-1" /><path d="M12 21V10m-4 4 4-4 4 4" /></svg>
        <span>Drag {multiple ? 'files' : 'a file'} here</span>
        <small>or click to browse{!multiple && ' · One file'}</small>
      </button>
    </div>
    {error && <p className="file-cabinet-error" role="alert">{error}</p>}
  </div>;
}
