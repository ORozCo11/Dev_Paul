import { useContext } from 'react';
import EndImpersonationAction from './EndImpersonationAction';
import Icon from './Icon';
import { AuthContext } from '../context/AuthContextObject';
import './ImpersonationBanner.css';

// Shown on every page of an impersonated session, whichever workspace the
// impersonated account lands on, so nobody mistakes it for their own login.
export default function ImpersonationBanner() {
  const { user } = useContext(AuthContext);
  if (!user?.is_impersonating) return null;
  const startedBy = localStorage.getItem('impersonator_name');
  return <div className="impersonation-banner" role="region" aria-label="Impersonation session">
    <Icon name="eye" size={18} />
    <p>
      <strong>Impersonating {user.name} ({user.role})</strong>
      <span>{startedBy ? `Started by ${startedBy}. ` : ''}This session is recorded in the Activity Log.</span>
    </p>
    <EndImpersonationAction compact />
  </div>;
}
