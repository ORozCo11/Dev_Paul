export function clearImpersonationSession() {
  ['impersonator_token', 'impersonator_name', 'impersonator_role', 'impersonator_storage'].forEach((key) => localStorage.removeItem(key));
}

export function impersonationRootToken(user) {
  if (!user?.is_impersonating && ['Impersonator', 'Super Admin'].includes(user?.role) && user.abilities?.includes('impersonation.start')) {
    return localStorage.getItem('token') || sessionStorage.getItem('token');
  }
  return user?.is_impersonating === true && ['Impersonator', 'Super Admin'].includes(localStorage.getItem('impersonator_role'))
    ? localStorage.getItem('impersonator_token') : null;
}

const routes = { Admin: '/admin', Custodian: '/custodian', 'Maintenance Personnel': '/maintenance', 'Super Admin': '/superadmin' };

export function openImpersonatedWorkspace(user, data) {
  const route = routes[data.user.role];
  if (!route) throw new Error('This account has no supported workspace.');
  if (!localStorage.getItem('impersonator_token')) {
    const original = localStorage.getItem('token') || sessionStorage.getItem('token');
    localStorage.setItem('impersonator_storage', localStorage.getItem('token') ? 'local' : 'session');
    localStorage.setItem('impersonator_token', original);
    localStorage.setItem('impersonator_name', user.name);
    localStorage.setItem('impersonator_role', user.role);
  }
  localStorage.setItem('token', data.access_token);
  sessionStorage.removeItem('token');
  window.location.assign(route);
}
