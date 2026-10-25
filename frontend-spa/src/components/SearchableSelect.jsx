import { Children, Fragment, useEffect, useId, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import './SearchableSelect.css';

const optionText = (node) => Children.toArray(node).map((child) => typeof child === 'object' ? optionText(child.props?.children) : String(child)).join('');
function collectOptions(children) {
  return Children.toArray(children).flatMap((child) => {
    if (!child || typeof child !== 'object') return [];
    if (child.type === Fragment || child.type === 'optgroup') return collectOptions(child.props.children);
    if (child.type !== 'option') return [];
    return [{ value: String(child.props.value ?? optionText(child.props.children)), label: optionText(child.props.children), disabled: child.props.disabled }];
  });
}

function menuPosition(input) {
  const bounds = input.getBoundingClientRect();
  const below = window.innerHeight - bounds.bottom;
  const above = below < 180 && bounds.top > below;
  return { left: bounds.left, width: bounds.width, maxHeight: Math.max(100, Math.min(280, (above ? bounds.top : below) - 10)), ...(above ? { bottom: window.innerHeight - bounds.top + 4 } : { top: bounds.bottom + 4 }) };
}

// Typing filters valid choices; IDs and permission-sensitive enums remain unchanged.
export default function SearchableSelect({ children, value = '', defaultValue, onChange, onFocus, onBlur, onKeyDown, name, id, disabled, required, className = '', menuClassName = '', style, ...props }) {
  const generatedId = useId();
  const inputId = id || generatedId;
  const listId = `${generatedId}-options`;
  const inputRef = useRef(null);
  const [open, setOpen] = useState(false);
  const [query, setQuery] = useState('');
  const [active, setActive] = useState(0);
  const [position, setPosition] = useState({});
  const [uncontrolled, setUncontrolled] = useState(defaultValue ?? '');
  const options = collectOptions(children);
  const controlledValue = onChange ? value : uncontrolled;
  const selected = options.find((option) => option.value === String(controlledValue ?? ''));
  const matches = options.filter((option) => !query || option.label.toLowerCase().includes(query.toLowerCase()));
  const enabled = matches.filter((option) => !option.disabled);
  useEffect(() => {
    if (!open) return;
    const reposition = (event) => {
      const menu = document.getElementById(listId);
      if (event.type !== 'scroll' || !menu?.contains(event.target)) setPosition(menuPosition(inputRef.current));
    };
    window.addEventListener('scroll', reposition, true);
    window.addEventListener('resize', reposition);
    return () => { window.removeEventListener('scroll', reposition, true); window.removeEventListener('resize', reposition); };
  }, [open, listId]);
  useEffect(() => {
    if (open) document.getElementById(`${listId}-${options.indexOf(enabled[active])}`)?.scrollIntoView({ block: 'nearest' });
  }, [active, open, listId, options, enabled]);
  const pick = (option) => {
    if (!option || option.disabled) return;
    setUncontrolled(option.value);
    onChange?.({ target: { value: option.value, name }, currentTarget: { value: option.value, name } });
    setOpen(false);
    setQuery('');
  };
  const show = () => {
    if (disabled) return;
    setPosition(menuPosition(inputRef.current));
    setQuery('');
    setActive(0);
    setOpen(true);
  };
  return <div className="searchable-select" style={{ ...(className ? { width: 'auto' } : {}), ...style }}>
    <input {...props} className={className} ref={inputRef} id={inputId} type="text" role="combobox" autoComplete="off" disabled={disabled} required={required} aria-expanded={open} aria-controls={listId} aria-autocomplete="list" aria-activedescendant={open && enabled[active] ? `${listId}-${options.indexOf(enabled[active])}` : undefined}
      value={open ? query : selected?.value ? selected.label : ''} placeholder={props.placeholder || options.find((option) => !option.value)?.label || 'Type to search'}
      onFocus={(event) => { show(); onFocus?.(event); }}
      onClick={() => { if (!open) show(); }}
      onChange={(event) => { setQuery(event.target.value); setActive(0); }}
      onBlur={(event) => { setOpen(false); setQuery(''); onBlur?.({ ...event, target: { ...event.target, value: controlledValue, name } }); }}
      onKeyDown={(event) => {
        if (event.key === 'Escape') { event.preventDefault(); setOpen(false); return; }
        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') { event.preventDefault(); if (!open) show(); else setActive((index) => Math.max(0, Math.min(enabled.length - 1, index + (event.key === 'ArrowDown' ? 1 : -1)))); return; }
        if (event.key === 'Enter' && open) { event.preventDefault(); pick(enabled[active]); return; }
        onKeyDown?.(event);
      }} />
    {name && <input type="hidden" name={name} value={controlledValue ?? ''} />}
    {open && createPortal(<div id={listId} role="listbox" className={`searchable-select-menu${menuClassName ? ` ${menuClassName}` : ''}`} style={position} onMouseDown={(event) => event.preventDefault()} onWheel={(event) => event.stopPropagation()}>
      {matches.map((option) => <button key={option.value} id={`${listId}-${options.indexOf(option)}`} type="button" role="option" aria-selected={String(controlledValue ?? '') === option.value} disabled={option.disabled} className={enabled[active] === option ? 'is-highlighted' : ''} onClick={() => pick(option)}>{option.label}</button>)}
      {!matches.length && <div className="searchable-select-empty">No matching options</div>}
    </div>, document.body)}
  </div>;
}
