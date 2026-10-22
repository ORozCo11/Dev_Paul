const routes = { Admin: '/admin', Custodian: '/custodian', 'Maintenance Personnel': '/maintenance', 'Super Admin': '/superadmin' };

export function openImpersonatedWorkspace(user, data) {
  const route = routes[data.user.role];
  if (!route) throw new Error('This account has no supported workspace.');
  const original = localStorage.getItem('token') || sessionStorage.getItem('token');
  localStorage.setItem('impersonator_storage', localStorage.getItem('token') ? 'local' : 'session');
  localStorage.setItem('impersonator_token', original);
  localStorage.setItem('impersonator_name', user.name);
  localStorage.setItem('impersonator_role', user.role);
  localStorage.setItem('token', data.access_token);
  sessionStorage.removeItem('token');
  window.location.assign(route);
}
