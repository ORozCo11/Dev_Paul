import { useContext, useEffect, useRef } from 'react';
import { Link } from 'react-router-dom';
import AuthHeader from '../components/AuthHeader';
import AuthFooter from '../components/AuthFooter';
import Icon from '../components/Icon';
import { AuthContext } from '../context/AuthContextObject';
import dashboardPreview from '../assets/dashboard-preview.webp';
import './Home.css';

// Public landing page. Every statement describes behavior in the current build
// (see the PRD / VMS Docs/Requirements-Traceability.md); keep it that way.
// The dashboard image is a real screenshot of the Administrator dashboard
// with the seeded sample data, so it is labeled as such.

const roleRoutes = {
  Admin: '/admin',
  Custodian: '/custodian',
  'Maintenance Personnel': '/maintenance',
  'Super Admin': '/superadmin',
  Impersonator: '/impersonation',
};

const HIGHLIGHTS = [
  { icon: 'shield', text: 'Role-based access' },
  { icon: 'pin', text: 'Each barangay sees only its own fleet' },
  { icon: 'checkCircle', text: 'Every repair independently verified' },
  { icon: 'history', text: 'Every action recorded' },
];

const FEATURES = [
  { icon: 'vehicle', title: 'Fleet Management', text: 'Register vehicles with type-specific details, keep their documents in one profile, and track availability and physical condition separately.' },
  { icon: 'wrench', title: 'Maintenance Workflow', text: 'Custodians propose repairs, Administrators approve them and assign mechanics, and a Custodian verifies the work before the ticket closes.' },
  { icon: 'calendar', title: 'Preventive Maintenance Scheduling', text: 'Book maintenance in advance, with optional recurrence. When a schedule falls due, VMS turns it into a repair proposal automatically.' },
  { icon: 'pin', title: 'Vehicle Location', text: 'Record where each vehicle is stationed using named hubs on a map of your barangay, with every move kept on record.' },
  { icon: 'history', title: 'Activity History', text: 'Each vehicle has its own timeline, and the activity log shows who did what, and when, across the barangay.' },
  { icon: 'chart', title: 'Reports', text: 'Generate inventory, issue, maintenance, schedule, location and history reports, then print them or export to CSV.' },
];

const STEPS = [
  { icon: 'flag', title: 'Report', text: 'A Custodian reports the problem and proposes a repair ticket.' },
  { icon: 'clipboard', title: 'Review', text: 'An Administrator approves the proposal and assigns maintenance personnel.' },
  { icon: 'wrench', title: 'Repair', text: 'Mechanics log the work, parts, cost and evidence.' },
  { icon: 'checklist', title: 'Verify', text: 'The assigned Custodian tests the vehicle and approves or returns the work.' },
  { icon: 'checkCircle', title: 'Return to Service', text: 'The ticket closes, and a fresh readiness check marks the vehicle ready to respond.' },
];

const ROLES = [
  {
    icon: 'users', title: 'Administrator', tone: 'blue',
    summary: 'Runs the barangay fleet and makes the decisions.',
    duties: ['Approves repair proposals and schedule bookings', 'Assigns maintenance personnel', 'Manages vehicles, users and reports'],
  },
  {
    icon: 'clipboard', title: 'Custodian', tone: 'green',
    summary: 'Looks after the vehicles day to day.',
    duties: ['Records condition and readiness checks', 'Proposes repairs and books maintenance', 'Verifies completed repairs'],
  },
  {
    icon: 'tools', title: 'Maintenance Personnel', tone: 'amber',
    summary: 'Carries out the repairs and maintenance.',
    duties: ['Works on assigned work orders', 'Logs repairs, parts and evidence', 'Completes scheduled maintenance'],
  },
];

// Six points per tooth form tapered mechanical teeth with flat tips and roots.
const GEAR_OUTLINE = Array.from({ length: 20 * 6 }, (_, index) => {
  const radius = [132, 132, 148, 148, 132, 132][index % 6];
  const angle = (index / 120) * Math.PI * 2;
  return `${150 + Math.cos(angle) * radius},${150 + Math.sin(angle) * radius}`;
}).join(' ');

function HeroGear({ side }) {
  return <svg className={`home-gear home-gear-${side}`} viewBox="0 0 300 300" aria-hidden="true" focusable="false">
    <defs><linearGradient id={`home-gear-${side}-blue`} x1="0" y1="0" x2="1" y2="1"><stop stopColor="#93C5FD" /><stop offset="1" stopColor="#60A5FA" /></linearGradient></defs>
    <g className="home-gear-rotor" stroke={`url(#home-gear-${side}-blue)`} strokeWidth="1.3" fill="none" strokeLinejoin="round">
      <polygon points={GEAR_OUTLINE} fill="#1D4ED8" fillOpacity=".08" />
      <circle cx="150" cy="150" r="108" /><circle cx="150" cy="150" r="31" /><circle cx="150" cy="150" r="20" strokeOpacity=".55" />
      {Array.from({ length: 6 }, (_, index) => <path key={index} d="M141 118L137 44H163L159 118Z" transform={`rotate(${index * 60} 150 150)`} fill="#93C5FD" fillOpacity=".12" strokeOpacity=".45" />)}
    </g>
  </svg>;
}

export default function Home() {
  const pageRef = useRef(null);
  const { user } = useContext(AuthContext);
  const dashboardPath = user ? (roleRoutes[user.role] ?? '/admin') : null;

  useEffect(() => {
    const root = pageRef.current;
    const preference = window.matchMedia('(prefers-reduced-motion: reduce)');
    if (!root || !('IntersectionObserver' in window)) return undefined;
    const elements = root.querySelectorAll('.home-section-head, .home-feature, .home-timeline, .home-timeline > li, .home-role, .home-role-note, .home-cta-inner, .auth-footer');
    const observer = new IntersectionObserver((entries) => {
      entries.forEach(({ target, isIntersecting }) => {
        if (!isIntersecting) return;
        target.classList.remove('home-reveal-pending');
        target.classList.add('home-revealed');
        observer.unobserve(target);
      });
    }, { threshold: 0.08 });
    const revealAll = () => elements.forEach((element) => element.classList.remove('home-reveal-pending'));
    const setup = () => {
      observer.disconnect();
      if (preference.matches) { revealAll(); return; }
      elements.forEach((element) => {
        if (element.classList.contains('home-revealed')) return;
        // Only prepare offscreen content; visible content never flashes away.
        if (element.getBoundingClientRect().top > window.innerHeight) element.classList.add('home-reveal-pending');
        observer.observe(element);
      });
    };
    setup();
    preference.addEventListener('change', setup);
    return () => { observer.disconnect(); revealAll(); preference.removeEventListener('change', setup); };
  }, []);

  return (
    <div className="auth-page-shell home-page" ref={pageRef}>
      <AuthHeader />
      <main id="main-content">
        <section className="home-hero" aria-labelledby="home-title">
          <div className="home-hero-gears" aria-hidden="true">
            <HeroGear side="right" />
          </div>
          <div className="home-container home-hero-inner">
            <p className="home-eyebrow">Smarter barangay operations</p>
            <h1 id="home-title" className="home-title"><span className="home-title-line">Every Vehicle.</span> <span className="home-title-line">Every Record.</span> <span className="home-title-line home-title-accent">One System.</span></h1>
            <p className="home-lead">Manage your barangay fleet, monitor vehicle readiness, and simplify maintenance through one centralized platform.</p>
            <div className="home-actions">
              {dashboardPath
                ? <Link to={dashboardPath} className="home-btn home-btn-primary">Go to Dashboard <Icon name="chevronRight" size={18} /></Link>
                : <Link to="/register" className="home-btn home-btn-primary">Get Started <Icon name="chevronRight" size={18} /></Link>}
              <a href="#features" className="home-btn home-btn-secondary">Explore Features</a>
            </div>
            <ul className="home-highlights" aria-label="Highlights">
              {HIGHLIGHTS.map((item) => <li key={item.text}><Icon name={item.icon} size={16} />{item.text}</li>)}
            </ul>
          </div>

          <figure className="home-container home-showcase">
            <div className="home-browser">
              <div className="home-browser-bar" aria-hidden="true"><span /><span /><span /><em>Administrator Dashboard</em></div>
              <img src={dashboardPreview} width="2160" height="1350" alt="The VMS Administrator dashboard: pending approvals, active and overdue maintenance, available vehicles, a fleet condition chart, maintenance status and an action queue." />
            </div>
            <figcaption>Actual Administrator dashboard, shown with sample data.</figcaption>
          </figure>
        </section>

        <section className="home-section" id="features" aria-labelledby="features-title">
          <div className="home-container">
            <div className="home-section-head">
              <p className="home-kicker">Core features</p>
              <h2 id="features-title">Everything your fleet team needs, in one place</h2>
              <p>From registering a vehicle to returning it to service after a repair, each step has a clear owner and a permanent record.</p>
            </div>
            <div className="home-feature-grid">
              {FEATURES.map((feature) => <article className="home-feature" key={feature.title}>
                <span className="home-feature-icon" aria-hidden="true"><Icon name={feature.icon} size={24} /></span>
                <h3>{feature.title}</h3>
                <p>{feature.text}</p>
              </article>)}
            </div>
          </div>
        </section>

        <section className="home-section home-section-tint" id="how-it-works" aria-labelledby="how-title">
          <div className="home-container">
            <div className="home-section-head">
              <p className="home-kicker">How VMS works</p>
              <h2 id="how-title">From reported problem to ready vehicle</h2>
              <p>Every repair follows the same five steps, with a different person responsible for approving, doing and checking the work.</p>
            </div>
            <ol className="home-timeline">
              {STEPS.map((step, index) => <li key={step.title}>
                <span className="home-step-badge" aria-hidden="true"><Icon name={step.icon} size={22} /></span>
                <div>
                  <span className="home-step-number">Step {index + 1}</span>
                  <h3>{step.title}</h3>
                  <p>{step.text}</p>
                </div>
              </li>)}
            </ol>
          </div>
        </section>

        <section className="home-section" id="roles" aria-labelledby="roles-title">
          <div className="home-container">
            <div className="home-section-head">
              <p className="home-kicker">Built for every role</p>
              <h2 id="roles-title">Clear responsibilities for every member of the team</h2>
              <p>Each person sees the tools for their job, and the system keeps approving, repairing and verifying in separate hands.</p>
            </div>
            <div className="home-role-grid">
              {ROLES.map((role) => <article className={`home-role home-role-${role.tone}`} key={role.title}>
                <span className="home-role-icon" aria-hidden="true"><Icon name={role.icon} size={24} /></span>
                <h3>{role.title}</h3>
                <p>{role.summary}</p>
                <ul>{role.duties.map((duty) => <li key={duty}><Icon name="checkCircle" size={16} />{duty}</li>)}</ul>
              </article>)}
            </div>
            <p className="home-role-note"><Icon name="info" size={16} />New barangays and their first Administrators are reviewed and approved by the VMS platform team.</p>
          </div>
        </section>

        <section className="home-cta" aria-labelledby="cta-title">
          <div className="home-container home-cta-inner">
            <h2 id="cta-title">Ready to Simplify Your Barangay Fleet Management?</h2>
            <p>Register your barangay or sign in to continue where you left off.</p>
            <div className="home-actions">
              {dashboardPath
                ? <Link to={dashboardPath} className="home-btn home-btn-light">Go to Dashboard</Link>
                : <>
                  <Link to="/register" className="home-btn home-btn-light">Register</Link>
                  <Link to="/login" className="home-btn home-btn-outline">Login</Link>
                </>}
            </div>
          </div>
        </section>
      </main>
      <AuthFooter />
    </div>
  );
}
