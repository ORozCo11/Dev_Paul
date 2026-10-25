import { createPortal } from 'react-dom';
import VMSLogo from './VMSLogo';
import './LoadingOverlay.css';

export default function LoadingOverlay({ label = 'Loading...' }) {
  return createPortal(
    <div className="vms-loading-overlay" role="status" aria-live="polite" aria-busy="true">
      <div className="vms-loading-card">
        <VMSLogo size={26} className="vms-loading-logo" />
        <span>{label}</span>
      </div>
    </div>,
    document.body,
  );
}
