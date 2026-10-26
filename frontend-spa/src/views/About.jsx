import { useEffect, useRef, useState } from 'react';
import AuthHeader from '../components/AuthHeader';
import AuthFooter from '../components/AuthFooter';
import Icon from '../components/Icon';
import './About.css';

// Every statement on this page describes behavior that exists in the current
// build (see VMS Docs/System-Capabilities.md); keep it that way when editing.

const OVERVIEW = [
  {
    icon: 'info',
    title: 'What VMS is',
    text: "One shared place for a barangay's emergency vehicles, such as ambulances, fire trucks and rescue boats, and all the work that keeps them running.",
  },
  {
    icon: 'flag',
    title: 'Why it was built',
    text: 'Emergency vehicles must be ready at a moment\'s notice. Repairs, schedules and records are easy to lose track of when they live in notebooks and chat threads.',
  },
  {
    icon: 'alert',
    title: 'What it solves',
    text: 'Unclear vehicle readiness, missed preventive maintenance, repairs nobody double-checked, and records scattered across people and places.',
  },
  {
    icon: 'checkCircle',
    title: 'How it helps',
    text: 'Everyone sees the same vehicle status, every step has a clear owner, and every action is recorded automatically.',
  },
];

const FEATURES = [
  {
    icon: 'vehicle',
    title: 'Fleet Management',
    desc: "Keep every vehicle's availability, condition and readiness in one place.",
    details: [
      'Register land and water vehicles in three short steps: basic information, specifications, then photo and location.',
      'Availability (Available, Under Maintenance, Inactive, Decommissioned) and physical condition (Good, Needs Inspection, Needs Repair) are tracked separately.',
      'Custodians confirm a vehicle is ready to respond after personally checking and operating it. A readiness check stays fresh for 24 hours.',
      'Each vehicle has its own profile with its status, location and files.',
    ],
  },
  {
    icon: 'ticket',
    title: 'Maintenance Workflow',
    desc: 'From a reported issue, through inspection and repair, to a verified return to service.',
    details: [
      'A Custodian proposes a maintenance ticket; an Administrator reviews it, then approves it, asks for corrections or declines it.',
      'On approval, the Administrator assigns Maintenance Personnel to each repair item.',
      'Repairs can be done in-house, with parts taken from another vehicle, or at an external shop.',
      'The assigned Custodian personally tests the vehicle, then approves the repair or returns it for rework. Mechanics can never verify their own work.',
    ],
  },
  {
    icon: 'calendar',
    title: 'Preventive Maintenance Scheduling',
    desc: 'Plan routine service ahead of time, including recurring service.',
    details: [
      'Custodians book maintenance schedules; an Administrator approves or declines them.',
      'A schedule can repeat every 1 to 60 months, and the next one is created automatically when the current one is completed.',
      'The system warns about schedules that clash on the same day.',
      'When a schedule comes due, the system turns it into a ticket proposal for the Administrator to review.',
    ],
  },
  {
    icon: 'pin',
    title: 'Vehicle Location',
    desc: 'See where every vehicle is stationed on an interactive map.',
    details: [
      'Vehicles are grouped onto named stations (hubs) pinned on the map.',
      "Switch between map and satellite views, with the barangay's boundary shown when it is on file.",
      'Download the current map view as an image.',
      'A location record keeps the history of where each vehicle has been stationed.',
    ],
  },
  {
    icon: 'alert',
    title: 'Issue Reporting',
    desc: 'Log problems the moment they are found and follow them to resolution.',
    details: [
      'Custodians and Maintenance Personnel can report an issue with its type, severity, description and photos or documents.',
      'Reporting an issue flags the vehicle as Needs Inspection and notifies the Administrators.',
      'Reports move from Pending to Under Review, In Maintenance and Resolved, in step with the repair.',
      'Custodians also run routine condition checks to catch problems before they become breakdowns.',
    ],
  },
  {
    icon: 'shield',
    title: 'Role-Based Access',
    desc: 'Each person sees and does only what their role requires.',
    details: [
      "Staff register with their barangay's registration code, and every account is approved before it can sign in.",
      'Administrators, Custodians and Maintenance Personnel each get the tools for their own part of the work.',
      "Each barangay only sees its own vehicles and records.",
    ],
  },
  {
    icon: 'history',
    title: 'Activity History',
    desc: 'An automatic timeline of every action taken across the fleet.',
    details: [
      'Each vehicle keeps a history of changes such as status updates, location moves, issue reports and closed repairs.',
      'Filter the history by activity type and date range, search it, and export it.',
      'Administrators also see an activity log of who did what, and under which role.',
    ],
  },
  {
    icon: 'chart',
    title: 'Reports',
    desc: 'Printable summaries for oversight, audits and planning ahead.',
    details: [
      'Seven report types: Vehicle Inventory, Vehicle Type, Vehicle Location, Vehicle Issue, Vehicle Maintenance, Vehicle Maintenance Schedule and Vehicle History.',
      'Filter each report by the fields that matter to it, such as location, severity or date range.',
      'Preview in the browser, then print or export to CSV.',
    ],
  },
];

const STEPS = [
  {
    icon: 'alert',
    title: 'Report the issue',
    text: 'A Custodian or mechanic reports a problem, a routine check finds one, or a preventive schedule comes due.',
    who: 'Custodian · Maintenance Personnel',
  },
  {
    icon: 'clipboard',
    title: 'Assess and approve',
    text: 'The Custodian proposes a maintenance ticket. The Administrator reviews it, approves it and assigns a mechanic.',
    who: 'Custodian · Administrator',
  },
  {
    icon: 'wrench',
    title: 'Repair the vehicle',
    text: 'The assigned mechanic carries out the work and records the repair notes, parts and cost.',
    who: 'Maintenance Personnel',
  },
  {
    icon: 'checklist',
    title: 'Verify the work',
    text: 'The assigned Custodian personally tests the vehicle, then approves the repair or sends it back for rework.',
    who: 'Custodian',
  },
  {
    icon: 'checkCircle',
    title: 'Update records and readiness',
    text: "A maintenance record and the vehicle's history are saved, and a fresh readiness check returns the vehicle to service.",
    who: 'Automatic · Custodian',
  },
];

const ROLES = [
  {
    icon: 'shield',
    tone: 'blue',
    name: 'Administrator',
    desc: "Oversees the barangay's fleet and team.",
    duties: [
      'Approves maintenance tickets and schedules',
      'Assigns mechanics to repairs',
      'Manages staff accounts and vehicles',
      'Generates fleet reports',
    ],
  },
  {
    icon: 'checklist',
    tone: 'green',
    name: 'Custodian',
    desc: 'Looks after the vehicles day to day.',
    duties: [
      'Registers vehicles and runs condition and readiness checks',
      'Reports issues and proposes maintenance tickets',
      'Books preventive maintenance',
      'Verifies completed repairs',
    ],
  },
  {
    icon: 'wrench',
    tone: 'amber',
    name: 'Maintenance Personnel',
    desc: 'Keeps the vehicles in working order.',
    duties: [
      'Reports issues found during work',
      'Carries out assigned repairs',
      'Records repair notes, parts and cost',
      'Completes assigned scheduled maintenance',
    ],
  },
  {
    icon: 'gear',
    tone: 'slate',
    name: 'Super Administrator',
    desc: 'Runs the platform across all barangays.',
    duties: [
      "Approves each barangay's first Administrator",
      'Manages barangays and registration codes',
      'Responds to Support Center concerns',
      "Does not access barangays' fleet records",
    ],
  },
];

// Native <dialog> gives focus containment, Escape-to-close and focus return for free.
function FeatureDialog({ feature, onClose }) {
  const ref = useRef(null);

  useEffect(() => {
    const dialog = ref.current;
    if (feature && dialog && !dialog.open) dialog.showModal();
  }, [feature]);

  if (!feature) return null;

  return (
    <dialog
      ref={ref}
      className="about-dialog"
      aria-labelledby="about-dialog-title"
      onClose={onClose}
      onClick={(e) => { if (e.target === e.currentTarget) e.currentTarget.close(); }}
    >
      <div className="about-dialog-body">
        <div className="about-dialog-head">
          <span className="about-icon-tile"><Icon name={feature.icon} size={22} /></span>
          <h3 id="about-dialog-title">{feature.title}</h3>
          <button type="button" className="about-dialog-close" onClick={() => ref.current?.close()} aria-label="Close">
            <Icon name="close" size={20} />
          </button>
        </div>
        <p className="about-dialog-lead">{feature.desc}</p>
        <ul className="about-check-list">
          {feature.details.map((d) => (
            <li key={d}><Icon name="checkCircle" size={18} />{d}</li>
          ))}
        </ul>
      </div>
    </dialog>
  );
}

export default function About() {
  const [openFeature, setOpenFeature] = useState(null);

  return (
    <div className="auth-page-shell about-page">
      <AuthHeader />

      <main>
        <section className="about-hero">
          <span className="about-hero-shape about-hero-shape-a" aria-hidden="true" />
          <span className="about-hero-shape about-hero-shape-b" aria-hidden="true" />
          <div className="about-hero-inner">
            <p className="about-eyebrow about-eyebrow-light">About Barangay <span className="vms-brand">VMS</span></p>
            <h1>Smarter Fleet Management for Every Barangay</h1>
            <p className="about-hero-lead">
              A centralized platform designed to help barangays manage emergency
              response vehicles, monitor readiness, coordinate maintenance, and
              maintain accurate fleet records.
            </p>
            <nav className="about-hero-links" aria-label="On this page">
              <a href="#features">Features</a>
              <a href="#how-it-works">How it works</a>
              <a href="#roles">Roles</a>
            </nav>
          </div>
        </section>

        <section className="about-section" aria-labelledby="overview-title">
          <div className="about-container">
            <header className="about-section-head">
              <p className="about-eyebrow">System overview</p>
              <h2 id="overview-title">Built to keep emergency vehicles ready</h2>
            </header>
            <div className="about-overview-grid">
              {OVERVIEW.map((o) => (
                <article className="about-card about-overview-card" key={o.title}>
                  <span className="about-icon-tile is-soft"><Icon name={o.icon} size={22} /></span>
                  <h3>{o.title}</h3>
                  <p>{o.text}</p>
                </article>
              ))}
            </div>
          </div>
        </section>

        <section className="about-section is-tinted" id="features" aria-labelledby="features-title">
          <div className="about-container">
            <header className="about-section-head">
              <p className="about-eyebrow">Core features</p>
              <h2 id="features-title">Everything your fleet needs in one place</h2>
              <p>Select a feature to see what it includes.</p>
            </header>
            <div className="about-feature-grid">
              {FEATURES.map((f) => (
                <button
                  type="button"
                  className="about-card about-feature"
                  key={f.title}
                  onClick={() => setOpenFeature(f)}
                  aria-haspopup="dialog"
                >
                  <span className="about-icon-tile"><Icon name={f.icon} size={22} /></span>
                  <span className="about-feature-title">{f.title}</span>
                  <span className="about-feature-text">{f.desc}</span>
                  <span className="about-feature-more">
                    Learn more <Icon name="chevronRight" size={16} />
                  </span>
                </button>
              ))}
            </div>
          </div>
        </section>

        <section className="about-section" id="how-it-works" aria-labelledby="workflow-title">
          <div className="about-container">
            <header className="about-section-head">
              <p className="about-eyebrow">The repair lifecycle</p>
              <h2 id="workflow-title">How VMS Works</h2>
              <p>Every repair follows the same five steps, each with a clear owner.</p>
            </header>
            <ol className="about-timeline">
              {STEPS.map((s, i) => (
                <li className="about-step" key={s.title}>
                  <div className="about-step-marker" aria-hidden="true">
                    <Icon name={s.icon} size={22} />
                  </div>
                  <div className="about-step-body">
                    <p className="about-step-num">Step {i + 1}</p>
                    <h3>{s.title}</h3>
                    <p>{s.text}</p>
                    <span className="about-step-who">{s.who}</span>
                  </div>
                </li>
              ))}
            </ol>
          </div>
        </section>

        <section className="about-section is-tinted" id="roles" aria-labelledby="roles-title">
          <div className="about-container">
            <header className="about-section-head">
              <p className="about-eyebrow">Who uses VMS</p>
              <h2 id="roles-title">Built for Every Role</h2>
              <p>Each person is responsible for their own part of the process.</p>
            </header>
            <div className="about-role-grid">
              {ROLES.map((r) => (
                <article className={`about-card about-role is-${r.tone}`} key={r.name}>
                  <div className="about-role-head">
                    <span className="about-role-icon"><Icon name={r.icon} size={22} /></span>
                    <div>
                      <h3>{r.name}</h3>
                      <p>{r.desc}</p>
                    </div>
                  </div>
                  <ul className="about-role-duties">
                    {r.duties.map((d) => <li key={d}>{d}</li>)}
                  </ul>
                </article>
              ))}
            </div>
          </div>
        </section>
      </main>

      <FeatureDialog feature={openFeature} onClose={() => setOpenFeature(null)} />
      <AuthFooter />
    </div>
  );
}
