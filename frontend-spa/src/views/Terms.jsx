import { Link } from 'react-router-dom';
import Aurora from '../components/Aurora';
import Icon from '../components/Icon';
import AuthHeader from '../components/AuthHeader';
import AuthFooter from '../components/AuthFooter';

export default function Terms() {
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

        <h1 className="auth-login-heading" style={{ marginBottom: '0.25rem' }}>Terms of Service</h1>
        <p style={{ fontSize: '0.8rem', opacity: 0.7, marginBottom: '1rem' }}>Effective: January 1, 2025</p>

        <TermsSection title="1. Acceptance of Terms">
          By accessing or using the Barangay Vehicle Management System (VMS), you confirm that
          you are an authorized barangay staff member and that you agree to comply with these
          Terms of Service. Unauthorized access is strictly prohibited and may be subject to
          disciplinary or legal action in accordance with applicable laws and barangay policy.
        </TermsSection>

        <TermsSection title="2. Authorized Use Only">
          This system is intended exclusively for official barangay fleet management purposes.
          Authorized uses include:
          <ul>
            <li>Recording and managing vehicle information, conditions, and maintenance schedules</li>
            <li>Submitting, processing, and resolving maintenance tickets</li>
            <li>Viewing and generating administrative reports for barangay operations</li>
            <li>Logging vehicle locations and usage history for operational accountability</li>
          </ul>
          Any use of the system outside of official barangay duties is prohibited.
        </TermsSection>

        <TermsSection title="3. Account Responsibilities">
          Each user is assigned a personal account with a role-specific level of access. You are
          responsible for:
          <ul>
            <li>Keeping your login credentials confidential and not sharing them with others</li>
            <li>Logging out of the system when not in use, especially on shared devices</li>
            <li>Reporting any suspected unauthorized access to the system administrator immediately</li>
            <li>Ensuring that all information you enter is accurate and complete to the best of your knowledge</li>
          </ul>
        </TermsSection>

        <TermsSection title="4. Role-Based Access Control">
          Access to system features is determined by your assigned role:
          <ul>
            <li><strong>Super Admin</strong> — general platform oversight and cross-barangay administrative functions, including registration codes, concern reports, and user account management; does not access any barangay's specific fleet operational data (vehicles, tickets, or maintenance records)</li>
            <li><strong>Admin</strong> — full access to all modules, user management, and system reports</li>
            <li><strong>Custodian</strong> — access to vehicle records, issue reporting, condition checks, location tracking, and maintenance scheduling</li>
            <li><strong>Maintenance Personnel</strong> — access to assigned maintenance tickets and work order records</li>
          </ul>
          Attempting to access features or data outside your assigned role is a violation of these Terms.
        </TermsSection>

        <TermsSection title="5. Data Accuracy and Integrity">
          Users must enter accurate and truthful data. Falsification of vehicle records, maintenance
          reports, issue reports, or any other system data is a serious violation of barangay policy
          and may constitute misconduct. Admins are responsible for maintaining data integrity
          through regular audits of the activity log.
        </TermsSection>

        <TermsSection title="6. System Availability">
          The barangay makes reasonable efforts to keep the VMS available at all times. However,
          the system may be temporarily unavailable due to maintenance, updates, or technical issues.
          The barangay is not liable for any inconvenience caused by downtime.
        </TermsSection>

        <TermsSection title="7. Prohibited Actions">
          The following actions are strictly prohibited:
          <ul>
            <li>Attempting to access, modify, or delete data outside your assigned permissions</li>
            <li>Sharing account credentials with other persons</li>
            <li>Using automated tools, scripts, or bots to interact with the system</li>
            <li>Attempting to reverse-engineer, exploit, or disrupt the system</li>
            <li>Entering false, misleading, or fabricated records into the system</li>
          </ul>
        </TermsSection>

        <TermsSection title="8. Monitoring and Audit">
          All actions performed within the system are recorded in an activity log, including the
          user, action type, timestamp, and affected record. These logs may be reviewed by system
          administrators for compliance, audit, and accountability purposes.
        </TermsSection>

        <TermsSection title="9. Termination of Access">
          Access to the system may be suspended or revoked at any time by the system administrator
          for violations of these Terms, end of employment or assignment, or at the discretion of
          barangay management. Upon termination, the user must immediately cease all use of the system.
        </TermsSection>

        <TermsSection title="10. Changes to These Terms">
          These Terms of Service may be updated from time to time. The system administrator will
          inform staff of material changes. Continued use of the system after changes constitutes
          acceptance of the revised Terms.
        </TermsSection>

        <TermsSection title="11. Governing Policy">
          These Terms are governed by the internal policies of the Barangay local government unit
          and applicable Philippine laws including, but not limited to, the Data Privacy Act of
          2012 (Republic Act No. 10173).
        </TermsSection>

        <p className="auth-legal" style={{ marginTop: '1.5rem' }}>
          <Link to="/login">← Back to Sign In</Link>
          {' · '}
          <Link to="/privacy">Privacy Policy</Link>
        </p>

      </section>
      </main>
      <AuthFooter />
    </div>
  );
}

function TermsSection({ title, children }) {
  return (
    <div style={{ marginBottom: '1rem' }}>
      <h3 style={{ fontSize: '0.95rem', fontWeight: 600, marginBottom: '0.4rem' }}>{title}</h3>
      <p style={{ fontSize: '0.875rem', lineHeight: 1.7, opacity: 0.8 }}>{children}</p>
    </div>
  );
}
