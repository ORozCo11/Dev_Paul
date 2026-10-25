import { useState } from 'react';
import Icon from './Icon';

export default function ThemeToggle() {
  const [theme, setTheme] = useState(() => document.documentElement.dataset.theme === 'dark' ? 'dark' : 'light');
  const next = theme === 'dark' ? 'light' : 'dark';
  const toggle = () => {
    document.documentElement.dataset.theme = next;
    document.documentElement.style.colorScheme = next;
    try { localStorage.setItem('theme', next); } catch { /* Keep the current page usable without storage. */ }
    setTheme(next);
  };
  return <button type="button" className="icon-btn theme-toggle-btn" onClick={toggle} title={`Switch to ${next} mode`} aria-label={`Switch to ${next} mode`}><Icon name={theme === 'dark' ? 'sun' : 'moon'} size={20} /></button>;
}
