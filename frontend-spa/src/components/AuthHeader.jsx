import VMSLogo from './VMSLogo';
import { useContext } from 'react';
import { Link } from 'react-router-dom';
import { AuthContext } from '../context/AuthContextObject';

const roleRoutes = {
  Admin: '/admin',
  Custodian: '/custodian',
  'Maintenance Personnel': '/maintenance',
  'Super Admin': '/superadmin',
};

// Shared header for the public-facing pages (Login, About, Developers) —
// the sign-in-side counterpart to WorkspaceFooter, so those pages read as
// one connected site instead of disconnected standalone screens.
export default function AuthHeader() {
  const { user } = useContext(AuthContext);
  // Signed-in visitors can return directly to their dashboard.
  const dashboardPath = user ? (roleRoutes[user.role] ?? '/admin') : null;

  return (
    <header className="auth-page-header">
      <div className="auth-page-header-inner">
        <Link to={dashboardPath ?? '/login'} className="auth-page-header-brand">
          <VMSLogo variant="white" size={32} className="auth-page-header-gear" />
          <div className="auth-page-header-brand-text">
            <span className="vms-wordmark auth-page-header-wordmark">vms</span>
            <span className="auth-page-header-subtext">vehicle management system</span>
          </div>
        </Link>
        <div className="auth-page-header-right">
          <nav className="auth-page-nav">
            <Link to={dashboardPath ?? '/login'}>Home</Link>
            <Link to="/about">About</Link>
            <Link to="/developers">Team</Link>
            <Link to="/support">Support Center</Link>
          </nav>
          {dashboardPath ? (
            <Link to={dashboardPath} className="auth-page-header-login-btn">Back to Dashboard</Link>
          ) : (
            <>
              <Link to="/register" className="auth-page-header-register-btn">Register</Link>
              <Link to="/login" className="auth-page-header-login-btn">Login</Link>
            </>
          )}
        </div>
      </div>
    </header>
  );
}
