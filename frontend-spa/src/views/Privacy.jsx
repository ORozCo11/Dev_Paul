import { Link } from 'react-router-dom';
import Aurora from '../components/Aurora';
import Icon from '../components/Icon';
import AuthHeader from '../components/AuthHeader';
import AuthFooter from '../components/AuthFooter';

export default function Privacy() {
  return (
    <div className="auth-page-shell">
      <AuthHeader />
      <main className="auth-page" style={{ alignItems: 'flex-start', padding: '2rem 1rem' }}>
      <Aurora colorStops={['#0b1220', '#1e3a5f', '#0f172a']} amplitude={0.6} blend={0.55} />
      <section className="auth-card policy-card" style={{ maxWidth: 680, width: '100%', textAlign: 'left', gap: '1rem' }}>

        <div className="policy-card-logo">
          <Icon name="gear" size={28} className="topbar-gear-icon" filled />
          <span className="vms-wordmark vms-wordmark-sm policy-card-logo-wordmark">vms</span>
        </div>

        <h1 className="auth-login-heading" style={{ marginBottom: '0.25rem' }}>Privacy Policy</h1>
        <p style={{ fontSize: '0.8rem', opacity: 0.7, marginBottom: '1rem' }}>Effective: January 1, 2025</p>

        <PolicySection title="1. Overview">
          The Barangay Vehicle Management System (VMS) is an internal fleet management platform
          operated by the Barangay local government unit. This Privacy Policy explains how we
          collect, use, and protect information entered into the system by authorized personnel.
        </PolicySection>

        <PolicySection title="2. Information We Collect">
          The system collects and stores the following information:
          <ul>
            <li>Account credentials (email address and hashed password) for authenticated staff</li>
            <li>Vehicle registration data, condition records, and maintenance histories</li>
            <li>Maintenance ticket details including reported issues and mechanic assignments</li>
            <li>Activity logs that record which user performed which action and when</li>
            <li>Vehicle location records and schedule entries entered by Custodian staff</li>
          </ul>
        </PolicySection>

        <PolicySection title="3. How We Use Your Information">
          Information in this system is used solely for the following purposes:
          <ul>
            <li>Authenticating and authorizing staff access based on assigned roles</li>
            <li>Tracking the operational status and maintenance lifecycle of barangay vehicles</li>
            <li>Generating administrative reports for fleet management and audit purposes</li>
            <li>Maintaining an activity log for accountability and transparency</li>
          </ul>
          No data is shared with third parties, sold, or used for advertising purposes.
        </PolicySection>

        <PolicySection title="4. Data Storage and Security">
          All data is stored within a secured local database managed by the barangay's IT
          infrastructure. Access is restricted by role-based permissions — Admin, Custodian, and
          Maintenance Personnel accounts each have distinct levels of access to a barangay's own
          fleet data. Super Admin accounts instead have general oversight and cross-barangay
          administrative access (registration codes, concern reports, and user account
          management) but do not access any barangay's specific fleet operational data, such as
          vehicles, tickets, or maintenance records. Passwords are
          encrypted using industry-standard hashing (bcrypt) and are never stored in plain text.
          API communications between the frontend and backend are protected via token-based
          authentication (Laravel Sanctum).
        </PolicySection>

        <PolicySection title="5. Data Retention">
          Vehicle records, maintenance tickets, and activity logs are retained for as long as
          they are operationally relevant. Archived tickets remain accessible to administrators
          for audit purposes. Account data is retained for the duration of the staff member's
          tenure and may be deactivated upon request to the system administrator.
        </PolicySection>

        <PolicySection title="6. Your Rights">
          Authorized users may request correction of inaccurate personal information stored in
          their account by contacting the system administrator. Access to the system is granted
          and revoked by administrators in accordance with barangay staff policy.
        </PolicySection>

        <PolicySection title="7. Changes to This Policy">
          This Privacy Policy may be updated to reflect changes in system features or applicable
          regulations. Significant changes will be communicated by the system administrator.
          Continued use of the system after changes constitutes acceptance of the updated policy.
        </PolicySection>

        <PolicySection title="8. Contact">
          For questions or concerns about this Privacy Policy or your data, contact your
          barangay system administrator.
        </PolicySection>

        <p className="auth-legal" style={{ marginTop: '1.5rem' }}>
          <Link to="/login">← Back to Sign In</Link>
          {' · '}
          <Link to="/terms">Terms of Service</Link>
        </p>

      </section>
      </main>
      <AuthFooter />
    </div>
  );
}

function PolicySection({ title, children }) {
  return (
    <div style={{ marginBottom: '1rem' }}>
      <h3 style={{ fontSize: '0.95rem', fontWeight: 600, marginBottom: '0.4rem' }}>{title}</h3>
      <p style={{ fontSize: '0.875rem', lineHeight: 1.7, opacity: 0.8 }}>{children}</p>
    </div>
  );
}
