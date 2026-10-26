import { Link } from 'react-router-dom';
import VMSLogo from './VMSLogo';

// Public-page footer: fleet tagline and a centered copyright/legal row.
export default function AuthFooter() {
  return (
    <footer className="auth-footer">
      <div className="auth-footer-main">
        <div className="auth-footer-brand">
          <VMSLogo variant="white" size={36} />
          <h2 className="auth-footer-tagline">A System to Manage your Barangay's fleet</h2>
        </div>
      </div>
      <div className="auth-footer-bottom">
        <p className="auth-footer-copyright">
          © {new Date().getFullYear()} Barangay Vehicle Management System
        </p>
        <div className="auth-footer-legal">
          <Link to="/privacy">Privacy Policy</Link>
          <Link to="/terms">Terms of Service</Link>
        </div>
      </div>
    </footer>
  );
}
