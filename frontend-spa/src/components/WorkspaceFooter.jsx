import VMSLogo from './VMSLogo';
import { Link } from 'react-router-dom';

// Footer for the authenticated app shell.
export default function WorkspaceFooter() {
  return (
    <footer className="workspace-footer">
      <div className="workspace-footer-brand">
        <div className="workspace-footer-wordmark">
          <VMSLogo variant="white" size={28} className="workspace-footer-gear-icon" />
          <span className="vms-wordmark vms-wordmark-md workspace-footer-vms-text">vms</span>
        </div>
        <p className="workspace-footer-tagline">A system to manage your barangays fleet</p>
      </div>
      <div className="workspace-footer-actions">
        <p className="workspace-footer-copyright">
          © {new Date().getFullYear()} Barangay Vehicle Management System. All rights reserved.
        </p>
        <div className="workspace-footer-links">
          <Link to="/privacy">Privacy Policy</Link>
          <Link to="/terms">Terms of Service</Link>
        </div>
      </div>
    </footer>
  );
}
