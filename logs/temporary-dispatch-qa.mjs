import { chromium } from 'playwright';
import assert from 'node:assert/strict';

const browser = await chromium.launch({ headless: true });
const errors = [];
try {
  const page = await browser.newPage({ viewport: { width: 1440, height: 1100 } });
  page.setDefaultTimeout(12000);
  page.on('pageerror', (error) => errors.push(error.message));
  const user = { id: 2, name: 'QA Custodian', role: 'Custodian', roles: ['Custodian'], is_active: true, abilities: ['vehicle.view', 'usage.view', 'usage.log', 'usage.temporary_release'] };
  const mechanic = { id: 3, name: 'QA Mechanic', role: 'Maintenance Personnel' };
  const vehicle = { vehicle_id: 1, vehicle_name: 'Rescue Truck', plate_number: 'QA-001', status: 'Under Maintenance', condition: 'Needs Repair', category: { category_id: 1, category_name: 'Fire Truck', domain: 'Land' } };
  const ticket = { ticket_id: 1, barangay_number: 1, ticket_title: 'MT-0001 - Cosmetic Repair', status: 'Active', priority: 'Low', vehicle_id: 1, vehicle, assigned_custodian_id: 2, assigned_custodian: { id: 2, name: 'QA Custodian' }, created_at: '2026-10-23T00:00:00Z', sub_issues: [{ sub_issue_id: 1, title: 'Paint repair', status: 'Under Repair', repair_type: 'in_house', assigned_mechanic_id: 3, assigned_mechanic: mechanic }], progress: { total: 1, done: 0, deferred: 0 }, dispatch_assessments: [] };
  let open = null;
  let requirements = ['Ticket #1: a current safe-operation assessment is required from QA Mechanic.'];
  const requests = [];
  const trips = [];
  await page.addInitScript(() => localStorage.setItem('token', 'qa-token'));
  await page.route('http://127.0.0.1:8001/api/**', async (route) => {
    const request = route.request();
    const key = new URL(request.url()).pathname.replace('/api', '');
    let data = [];
    if (key === '/user') data = user;
    else if (key === '/dashboard') data = {};
    else if (key === '/lookups' || key === '/tickets/lookups') data = { vehicles: [vehicle], categories: [vehicle.category], custodians: [ticket.assigned_custodian], maintenance_personnel: [mechanic], priorities: ['Low'], ticket_statuses: ['Active'] };
    else if (key === '/vehicles') data = [vehicle];
    else if (key === '/tickets/1') data = { ...ticket, temporary_dispatch: open };
    else if (key === '/tickets/1/dispatch-assessment') {
      const payload = request.postDataJSON(); requests.push({ key, payload });
      const row = { ...payload, id: 1, assessed_by: 3, current: true, ticket_number: 1, name: mechanic.name, assessed_at: '2026-10-23T03:00:00Z' };
      ticket.dispatch_assessments = [row]; requirements = []; data = row;
    } else if (key === '/vehicles/1/usage') {
      if (request.method() === 'POST') {
        const payload = request.postDataJSON(); requests.push({ key, payload });
        open = { usage_id: 1, ...payload, started_at: new Date().toISOString(), temporary_release: { reason: payload.release_reason, expected_return_at: payload.expected_return_at, ticket_ids: payload.ticket_ids }, logged_by: user };
        trips.unshift(open); data = open;
      } else data = { data: trips, total: trips.length, last_page: 1, open_trip: open, blocking_tickets: [ticket], can_temporary_release: user.role === 'Custodian', release_requirements: requirements, release_assessments: ticket.dispatch_assessments };
    } else if (key === '/usage-logs/1/end') { open.ended_at = new Date().toISOString(); open = null; data = trips[0]; }
    await route.fulfill({ json: data });
  });
  await page.goto('http://localhost:5173/custodian/vehicles/1/dispatch');
  await page.getByText(requirements[0], { exact: true }).waitFor();
  assert.equal(await page.getByText('Authorize temporary dispatch as Custodian', { exact: true }).count(), 0);

  user.role = 'Maintenance Personnel'; user.roles = [user.role]; user.id = 3; user.abilities = ['usage.assess_release', 'subissue.log_repair'];
  await page.goto('http://localhost:5173/maintenance/tickets/1');
  await page.getByRole('button', { name: 'Assess temporary dispatch', exact: true }).click();
  await page.locator('select[name="safe_to_operate"]').selectOption('yes');
  await page.getByLabel('Vehicle is fully assembled and operational', { exact: true }).check();
  await page.getByLabel('No unresolved safety-critical fault prevents operation', { exact: true }).check();
  await page.getByLabel('Assessment and operating restrictions', { exact: true }).fill('Cosmetic work only; no operating restriction.');
  await page.getByRole('button', { name: 'Record assessment', exact: true }).click();
  await page.getByText('Assessment recorded. The Custodian has been notified.', { exact: true }).waitFor();

  user.role = 'Custodian'; user.roles = [user.role]; user.id = 2; user.abilities = ['vehicle.view', 'usage.view', 'usage.log', 'usage.temporary_release'];
  await page.goto('http://localhost:5173/custodian/vehicles/1/dispatch');
  await page.getByLabel('Authorize temporary dispatch as Custodian', { exact: true }).check();
  await page.getByLabel('Driver', { exact: true }).fill('Responder');
  await page.getByLabel('Destination', { exact: true }).fill('City Hall');
  await page.getByLabel('Purpose', { exact: true }).fill('Emergency response');
  await page.getByLabel('Reason repairs can wait and why the vehicle is needed', { exact: true }).fill('Urgent response; cosmetic work can wait.');
  const expected = '2027-01-01T14:30';
  await page.getByLabel('Expected return (Philippine time)', { exact: true }).fill(expected);
  for (const name of ['personally_tested', 'safe_for_intended_use', 'fully_assembled', 'no_safety_critical_fault']) await page.locator(`input[name="${name}"]`).check();
  await page.setViewportSize({ width: 390, height: 844 });
  assert(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth));
  await page.screenshot({ path: 'logs/temporary-dispatch-qa.png', fullPage: true });
  await page.getByRole('button', { name: 'Authorize and dispatch', exact: true }).click();
  await page.getByRole('button', { name: 'Record return', exact: true }).waitFor();
  const release = requests.find((entry) => entry.key === '/vehicles/1/usage').payload;
  assert.equal(release.expected_return_at, '2027-01-01T06:30:00.000Z');
  assert.deepEqual(release.ticket_ids, [1]);
  assert.deepEqual(release.assessment_ids, [1]);
  assert.equal(release.temporary_release, true);
  assert.equal(release.personally_tested, true);
  await page.reload();
  await page.getByRole('button', { name: 'Record return', exact: true }).click();
  await page.getByLabel('Authorize temporary dispatch as Custodian', { exact: true }).waitFor();
  assert.equal(await page.getByRole('button', { name: 'Dispatch vehicle', exact: true }).count(), 0);
  assert.equal(ticket.status, 'Active');
  user.role = 'Admin'; user.roles = ['Admin']; user.id = 1; user.abilities = ['vehicle.view', 'usage.view', 'usage.log'];
  await page.goto('http://localhost:5173/admin/vehicles/1/dispatch');
  await page.getByRole('button', { name: 'View Ticket #1', exact: true }).waitFor();
  assert.equal(await page.getByLabel('Authorize temporary dispatch as Custodian', { exact: true }).count(), 0);
  assert.deepEqual(errors, []);
  console.log(JSON.stringify({ passed: true, checks: ['Missing assessment blocks release', 'Assigned mechanic assessment form', 'Custodian release form and readiness attestations', 'Philippine expected-return time conversion', 'Mobile layout', 'Return survives reload', 'Ticket stays open after return', 'Admin has no release override'], errors }));
} finally {
  await browser.close();
}
