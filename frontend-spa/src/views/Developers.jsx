import { useEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import AuthHeader from '../components/AuthHeader';
import AuthFooter from '../components/AuthFooter';
import Icon from '../components/Icon';

const TEAM = [
  { id: 'precious-dignos', fullName: 'Precious Dignos', role: 'Project Manager / QA Specialist', photo: '/team/precious-dignos.jpg', primaryFocus: 'Project management, stakeholder coordination, and quality assurance', about: 'Precious supports the VMS capstone as Project Manager and QA Specialist, helping keep the project coordinated, practical, and responsive to the needs identified during development.', responsibilities: ['Coordinates project updates, planning, and new ideas for the team.', 'Reviews reported bugs and quality concerns with the QA process.', 'Arranges interviews with barangay and BDRRMO stakeholders to gather supporting data and information.'], contributions: ['Provided stakeholder-informed project inputs through barangay and BDRRMO interviews.', 'Supported project direction, refinement ideas, and quality review for the VMS system.'] },
  { id: 'john-paul-orozco', fullName: 'John Paul Orozco', role: 'Project Lead / Full-Stack Developer', photo: '/team/paul-orozco.jpg', primaryFocus: 'Project leadership, full-stack implementation, and technical decisions', about: 'John Paul leads the VMS capstone team and serves as the project’s core technical decision-maker, coordinating delivery while contributing across the full stack.', responsibilities: ['Guides the team’s development work and final technical decisions.', 'Manages project direction, implementation priorities, and coding work.', 'Contributes full-stack development support across the VMS application.'], contributions: ['Led overall technical direction and delivery decisions for the VMS project.', 'Contributed full-stack implementation and project coordination.'] },
  { id: 'justine-belia', fullName: 'Justine Belia', role: 'Documentation / QA Specialist', photo: '/team/justine-mae-belia.jpg', primaryFocus: 'System documentation, research, and quality assurance', about: 'Justine documents the system’s requirements and supports the research and QA work that explains why a Vehicle Management System is needed for barangay operations.', responsibilities: ['Documents system needs and project requirements.', 'Researches supporting articles and references for the system rationale.', 'Tests the system, finds bugs, and reports improvements or items that should be adjusted or removed.'], contributions: ['Prepared system documentation and requirement records for VMS.', 'Supported research that informs the need for the Vehicle Management System.', 'Contributed QA testing, issue reporting, and improvement feedback.'] },
  { id: 'jake-engana', fullName: 'Jake Engaña', role: 'Backend Developer', photo: '/team/jake-engana.png', primaryFocus: 'Backend functions, data structures, workflows, and database support', about: 'Jake focuses on the functions behind the VMS system, with emphasis on backend behavior, data structures, workflows, and database support while contributing to full-stack implementation when needed.', responsibilities: ['Builds and supports system functions behind the VMS interface.', 'Works with data structures, workflows, and database-related implementation.', 'Provides supporting frontend and full-stack development where needed.'], contributions: ['Contributed backend functionality for VMS workflows and system data.', 'Supported the application database, data structures, and full-stack implementation.'] },
];

function DecoArrow({ className, gradientId }) { return <svg className={className} viewBox="0 0 100 400" preserveAspectRatio="none" aria-hidden="true"><defs><linearGradient id={gradientId} x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stopColor="#bfe3fb" stopOpacity="0.85" /><stop offset="100%" stopColor="#bfe3fb" stopOpacity="0" /></linearGradient></defs><polygon points="50,0 92,58 66,58 66,400 34,400 34,58 8,58" fill={`url(#${gradientId})`} /></svg>; }

function TeamProfileModal({ member, onClose, restoreFocus }) {
  const dialogRef = useRef(null); const closeRef = useRef(null);
  useEffect(() => {
    if (!member) return undefined;
    const previousOverflow = document.body.style.overflow;
    const trigger = restoreFocus.current;
    document.body.style.overflow = 'hidden';
    closeRef.current?.focus();
    const onKeyDown = (event) => {
      if (event.key === 'Escape') { event.preventDefault(); onClose(); return; }
      if (event.key !== 'Tab') return;
      const focusable = dialogRef.current?.querySelectorAll('button:not([disabled]), [href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])');
      if (!focusable?.length) return;
      const first = focusable[0]; const last = focusable[focusable.length - 1];
      if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
      else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
    };
    document.addEventListener('keydown', onKeyDown);
    return () => { document.body.style.overflow = previousOverflow; document.removeEventListener('keydown', onKeyDown); trigger?.focus(); };
  }, [member, onClose, restoreFocus]);
  if (!member) return null;
  return createPortal(<div className="team-modal-backdrop" onMouseDown={(event) => { if (event.target === event.currentTarget) onClose(); }}>
    <section className="team-profile-modal" ref={dialogRef} role="dialog" aria-modal="true" aria-labelledby="team-profile-name" aria-describedby="team-profile-about">
      <button className="team-modal-close" type="button" ref={closeRef} onClick={onClose} aria-label={`Close ${member.fullName}'s profile`}><Icon name="close" size={20} /></button>
      <div className="team-modal-scroll"><div className="team-modal-profile"><div className="team-modal-photo"><img src={member.photo} alt={member.fullName} /></div><div><p className="team-modal-eyebrow">VMS Capstone Team</p><h2 id="team-profile-name">{member.fullName.split(' ').map((part, index) => <span className="team-name-part" style={{ '--name-delay': `${index * 70}ms` }} key={part}>{part}{' '}</span>)}</h2><p className="team-modal-role">{member.role}</p><div className="team-focus"><span>Primary focus</span><strong>{member.primaryFocus}</strong></div></div></div>
      <div className="team-modal-details"><section><h3>About the Member</h3><p id="team-profile-about">{member.about}</p></section><section><h3>Responsibilities</h3><ul>{member.responsibilities.map((item) => <li key={item}>{item}</li>)}</ul></section><section className="team-modal-wide"><h3>System Contributions</h3><ul>{member.contributions.map((item) => <li key={item}>{item}</li>)}</ul></section></div></div>
    </section>
  </div>, document.body);
}

export default function Developers() {
  const [selectedMember, setSelectedMember] = useState(null); const triggerRef = useRef(null);
  const openProfile = (member, trigger) => { triggerRef.current = trigger; setSelectedMember(member); };
  const closeProfile = () => setSelectedMember(null);
  return <div className="auth-page-shell"><AuthHeader /><main className="auth-hero"><DecoArrow className="dev-team-deco-arrow dev-team-deco-arrow-left" gradientId="devArrowLeft" /><DecoArrow className="dev-team-deco-arrow dev-team-deco-arrow-right" gradientId="devArrowRight" /><div className="auth-hero-inner"><p className="auth-hero-eyebrow">Barangay <span className="vms-brand">VMS</span></p><h1 className="auth-hero-title">Meet the Team</h1><p className="auth-hero-subtitle">This system was built as a capstone project by a small, dedicated team committed to giving barangays a better way to manage their vehicle fleet.</p><div className="dev-team-carousel"><div className="dev-team-grid">{TEAM.map((member) => <button className="dev-team-card" type="button" key={member.id} onClick={(event) => openProfile(member, event.currentTarget)} aria-label={`View ${member.fullName}'s profile`}><div className="dev-team-photo"><img src={member.photo} alt={member.fullName} /><div className="dev-team-caption"><p className="dev-team-name">{member.fullName}</p><p className="dev-team-role">{member.role}</p><span className="dev-team-view">View profile <Icon name="chevronRight" size={15} /></span></div></div></button>)}</div></div></div></main><AuthFooter /><TeamProfileModal member={selectedMember} onClose={closeProfile} restoreFocus={triggerRef} /></div>;
}
