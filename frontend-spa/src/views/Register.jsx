import LoadingOverlay from '../components/LoadingOverlay';
import './Register.css';
import { useContext, useEffect, useMemo, useRef, useState } from 'react';
import { MapContainer, Polygon, TileLayer } from 'react-leaflet';
import { Link, Navigate } from 'react-router-dom';
import api from '../api/axios';
import Icon from '../components/Icon';
import Aurora from '../components/Aurora';
import AuthHeader from '../components/AuthHeader';
import AuthFooter from '../components/AuthFooter';
import { AuthContext } from '../context/AuthContextObject';
import { geoJsonToPolygons } from '../utils/boundary';

const roleRoutes = {
  Admin: '/admin',
  Custodian: '/custodian',
  'Maintenance Personnel': '/maintenance',
  'Super Admin': '/superadmin',
};

const emptyForm = {
  first_name: '',
  middle_name: '',
  last_name: '',
  email: '',
  phone: '',
  address: '',
  province_id: '',
  city_id: '',
  barangay_id: '',
  requested_role: '',
  staff_code: '',
  password: '',
  password_confirmation: '',
};

// Letters, spaces, and the punctuation that legitimately shows up in PH
// names (hyphenated surnames, "Ñ", "D'Angelo"-style apostrophes, "Jr.").
const NAME_CHARS = /[^A-Za-zÀ-ÖØ-öø-ÿ.'\- ]/g;
const NAME_PATTERN = /^[A-Za-zÀ-ÖØ-öø-ÿ.'\- ]+$/;
const EMAIL_PATTERN = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
// PH mobile format: 09 + 9 digits = 11 digits total.
const PHONE_PATTERN = /^09\d{9}$/;

const STEP_FIELDS = [
  ['first_name', 'middle_name', 'last_name', 'email', 'phone'],
  ['province_id', 'city_id', 'barangay_id', 'address'],
  ['requested_role', 'staff_code', 'password', 'password_confirmation'],
];

function validateStep(values, step, isFirstForBarangay) {
  const errors = {};
  if (step === 0) {
    for (const [name, label] of [['first_name', 'First name'], ['middle_name', 'Middle name'], ['last_name', 'Last name']]) {
      const value = values[name].trim();
      if (!value && name !== 'middle_name') errors[name] = `${label} is required.`;
      else if (value && !NAME_PATTERN.test(value)) errors[name] = `${label} may only contain letters and name punctuation.`;
    }
    if (!values.email.trim()) errors.email = 'Email address is required.';
    else if (!EMAIL_PATTERN.test(values.email.trim())) errors.email = 'Enter a valid email address.';
    if (!values.phone) errors.phone = 'Phone number is required.';
    else if (!PHONE_PATTERN.test(values.phone)) errors.phone = 'Enter an 11-digit mobile number starting with 09.';
  } else if (step === 1) {
    if (!values.province_id) errors.province_id = 'Select your province.';
    if (!values.city_id) errors.city_id = 'Select your city or municipality.';
    if (!values.barangay_id) errors.barangay_id = 'Select your barangay from the list.';
    if (!values.address.trim()) errors.address = 'Street address is required.';
  } else {
    if (!isFirstForBarangay && !values.requested_role) errors.requested_role = 'Select your role.';
    if (!values.staff_code.trim()) errors.staff_code = 'Enter the staff registration code from your barangay office.';
    if (!values.password) errors.password = 'Password is required.';
    else if (values.password.length < 8) errors.password = 'Use at least 8 characters.';
    if (!values.password_confirmation) errors.password_confirmation = 'Confirm your password.';
    else if (values.password !== values.password_confirmation) errors.password_confirmation = 'Passwords do not match.';
  }
  return errors;
}

function RegistrationFieldError({ name, errors }) {
  return errors[name] ? <small id={`register-error-${name}`} className="register-field-error">{errors[name]}</small> : null;
}

function Register() {
  const { token, user } = useContext(AuthContext);
  const [step, setStep] = useState(0);
  const stepHeading = useRef(null);
  useEffect(() => { stepHeading.current?.focus(); }, [step]);
  const [form, setForm] = useState(emptyForm);
  const [provinces, setProvinces] = useState([]);
  const [cities, setCities] = useState([]);
  const [barangays, setBarangays] = useState([]);
  const [citiesLoading, setCitiesLoading] = useState(false);
  const [barangaysLoading, setBarangaysLoading] = useState(false);
  const [locationError, setLocationError] = useState('');
  const [locationRetry, setLocationRetry] = useState(0);
  const [error, setError] = useState('');
  const [fieldErrors, setFieldErrors] = useState({});
  const formRef = useRef(null);
  const errorFocus = useRef(null);
  useEffect(() => {
    if (errorFocus.current) {
      formRef.current?.elements.namedItem(errorFocus.current)?.focus();
      errorFocus.current = null;
    }
  }, [fieldErrors, step]);
  const [submitting, setSubmitting] = useState(false);
  const [showPassword, setShowPassword] = useState(false);
  const [submitted, setSubmitted] = useState(false);
  // null = not checked yet. true/false once we know whether this barangay
  // already has anyone registered — drives whether the role/staff-code
  // fields even need to be shown, and doubles as the client-side hint for
  // "you'll become this barangay's Administrator". The backend
  // independently re-derives this at submit time regardless of what this
  // says — this is purely a UX preview, never the source of truth.
  const [isFirstForBarangay, setIsFirstForBarangay] = useState(null);
  const [boundaryPreview, setBoundaryPreview] = useState(null);

  useEffect(() => {
    api.get('/provinces')
      .then((response) => setProvinces(response.data))
      .catch(() => setProvinces([]));
  }, []);

  // Ignore late responses after a parent selection changes.
  useEffect(() => {
    if (!form.province_id) return;
    let cancelled = false;
    api.get('/cities', { params: { province_id: form.province_id } })
      .then((response) => { if (!cancelled) setCities(response.data); })
      .catch(() => { if (!cancelled) setLocationError('Could not load cities. Please try again.'); })
      .finally(() => { if (!cancelled) setCitiesLoading(false); });
    return () => { cancelled = true; };
  }, [form.province_id, locationRetry]);

  useEffect(() => {
    if (!form.city_id) return;
    let cancelled = false;
    api.get('/barangays', { params: { city_id: form.city_id } })
      .then((response) => { if (!cancelled) setBarangays(response.data); })
      .catch(() => { if (!cancelled) setLocationError('Could not load barangays. Please try again.'); })
      .finally(() => { if (!cancelled) setBarangaysLoading(false); });
    return () => { cancelled = true; };
  }, [form.city_id, locationRetry]);

  useEffect(() => {
    if (!form.city_id || !form.barangay_id) return;
    let cancelled = false;
    api.get('/registration-status', { params: { city_id: form.city_id, barangay_id: form.barangay_id } })
      .then((response) => { if (!cancelled) setIsFirstForBarangay(response.data.is_first); })
      .catch(() => { if (!cancelled) setIsFirstForBarangay(null); });
    return () => { cancelled = true; };
  }, [form.city_id, form.barangay_id]);

  useEffect(() => {
    if (!form.barangay_id) { setBoundaryPreview(null); return; }
    let cancelled = false;
    setBoundaryPreview(null);
    api.get(`/barangays/${form.barangay_id}`).then(({ data }) => { if (!cancelled) setBoundaryPreview(data); }).catch(() => { if (!cancelled) setBoundaryPreview({ error: true }); });
    return () => { cancelled = true; };
  }, [form.barangay_id]);

  const boundaryPolygons = useMemo(() => geoJsonToPolygons(boundaryPreview?.boundary), [boundaryPreview]);

  if (token && user) {
    return <Navigate to={roleRoutes[user.role] ?? '/unauthorized'} replace />;
  }

  const handleChange = (event) => {
    const { name, value } = event.target;
    setForm((current) => ({ ...current, [name]: value }));
    if (name === 'barangay_id') setIsFirstForBarangay(null);
    clearFieldErrors(name, ...(name === 'password' ? ['password_confirmation'] : []));
  };

  const clearFieldErrors = (...names) => {
    setFieldErrors((current) => {
      const next = { ...current };
      names.forEach((name) => { delete next[name]; });
      return next;
    });
  };

  const fieldErrorProps = (name) => ({
    'aria-invalid': fieldErrors[name] ? true : undefined,
    'aria-describedby': fieldErrors[name] ? `register-error-${name}` : undefined,
  });

  const showFieldErrors = (errors) => {
    const nextStep = STEP_FIELDS.findIndex((fields) => fields.some((name) => errors[name]));
    errorFocus.current = STEP_FIELDS.flat().find((name) => errors[name]) ?? null;
    setFieldErrors(errors);
    if (nextStep >= 0) setStep(nextStep);
  };

  const handleProvinceChange = (event) => {
    const { value } = event.target;
    setForm((current) => ({
      ...current,
      province_id: value,
      city_id: '',
      barangay_id: '',
    }));
    setCities([]);
    setBarangays([]);
    setCitiesLoading(Boolean(value));
    setBarangaysLoading(false);
    setLocationError('');
    setIsFirstForBarangay(null);
    clearFieldErrors('province_id', 'city_id', 'barangay_id');
  };

  const handleCityChange = (event) => {
    const { value } = event.target;
    setForm((current) => ({
      ...current,
      city_id: value,
      barangay_id: '',
    }));
    setBarangays([]);
    setBarangaysLoading(Boolean(value));
    setLocationError('');
    setIsFirstForBarangay(null);
    clearFieldErrors('city_id', 'barangay_id');
  };

  // Strips anything that isn't a letter/space/name-punctuation as the user
  // types, instead of only complaining after they hit submit.
  const handleNameChange = (event) => {
    const { name, value } = event.target;
    setForm((current) => ({ ...current, [name]: value.replace(NAME_CHARS, '') }));
    clearFieldErrors(name);
  };

  // Digits only, capped at 11 — the length of a PH mobile number.
  const handlePhoneChange = (event) => {
    const digits = event.target.value.replace(/\D/g, '').slice(0, 11);
    setForm((current) => ({ ...current, phone: digits }));
    clearFieldErrors('phone');
  };

  const handleSubmit = async (event) => {
    event.preventDefault();
    if (submitting) return;
    setError('');
    const errors = step < 2
      ? validateStep(form, step, isFirstForBarangay)
      : Object.assign({}, ...STEP_FIELDS.map((_, index) => validateStep(form, index, isFirstForBarangay)));
    if (Object.keys(errors).length) { showFieldErrors(errors); return; }
    setFieldErrors({});
    if (step < 2) {
      setStep((current) => current + 1);
      return;
    }

    setSubmitting(true);

    try {
      const name = [form.first_name, form.middle_name, form.last_name]
        .map((part) => part.trim())
        .filter(Boolean)
        .join(' ');
      await api.post('/register', { ...form, name });
      setSubmitted(true);
    } catch (requestError) {
      const messages = requestError.response?.data?.errors;
      const inlineErrors = Object.fromEntries(Object.entries(messages ?? {}).map(([name, value]) => [
        name === 'name' ? 'first_name' : name,
        Array.isArray(value) ? value[0] : value,
      ]));
      showFieldErrors(inlineErrors);
      const failed = Object.keys(messages ?? {});
      if (failed.some((key) => ['name','first_name','middle_name','last_name','email','phone'].includes(key))) setStep(0);
      else if (failed.some((key) => ['province_id','city_id','barangay_id','barangay_name','address'].includes(key))) setStep(1);

      // If we thought we'd be the first (and so hid the role/staff-code
      // requirement) but the backend disagrees, someone else just claimed
      // that barangay's first-Admin slot while this form was open. Reveal
      // the now-required fields instead of leaving the user stuck unable to
      // fix an error on a field they can't even see, and re-check the real
      // status rather than just assuming.
      if (isFirstForBarangay && messages && (messages.requested_role || messages.staff_code)) {
        setIsFirstForBarangay(false);
        setError('Someone else just registered as this barangay\'s first Administrator. Please select your role and enter the staff registration code below, then submit again.');
        const params = { city_id: form.city_id };
        params.barangay_id = form.barangay_id;
        api.get('/registration-status', { params })
          .then((response) => setIsFirstForBarangay(response.data.is_first))
          .catch(() => {});
      } else {
        const allMessages = Object.entries(inlineErrors)
          .filter(([name]) => !STEP_FIELDS.flat().includes(name))
          .map(([, message]) => message).filter(Boolean);
        setError(
          allMessages.length ? allMessages.join(' ') :
            Object.keys(inlineErrors).length ? 'Please check the highlighted fields.' :
            requestError.response?.data?.message ??
            'Unable to register. Please check your connection and try again.',
        );
      }
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <div className="auth-page-shell register-page slide-in">
      <AuthHeader />

      <main className="auth-split-page">
      {submitting && <LoadingOverlay />}
      <section className="auth-split-panel" aria-labelledby="register-title">
        <div className="auth-logo-header">
          <h1 id="register-title" className="auth-login-heading">Create your account</h1>
          <p className="register-intro">Join your barangay's vehicle management team.</p>
        </div>

        {submitted ? (
          <div>
            <p className="notice success">
              {isFirstForBarangay
                ? 'Registration submitted. A Super Admin must review and approve your account as this barangay\'s first Administrator before you can sign in.'
                : 'Registration submitted. An administrator must approve your account before you can sign in.'}
            </p>
            <Link className="primary-button auth-submit-btn" to="/login" style={{ display: 'flex', textDecoration: 'none' }}>
              Back to Login
            </Link>
          </div>
        ) : (
          <form ref={formRef} className="auth-form auth-form-grid" autoComplete="off" onSubmit={handleSubmit} noValidate>
            <ol className="register-progress" aria-label="Registration progress">{['Your details', 'Your barangay', 'Account access'].map((label, index) => <li key={label} aria-current={step === index ? 'step' : undefined} className={step >= index ? 'is-current' : ''}><span>{index + 1}</span>{label}</li>)}</ol>
            <h2 className="register-step-title" ref={stepHeading} tabIndex={-1}>{['Tell us about yourself', 'Where are you based?', 'Secure your account'][step]}</h2>
            <p className="register-step-help">Step {step + 1} of 3 · {['Enter your name and contact information.', 'Choose your barangay and enter your street address.', 'Enter your registration code and choose a strong password.'][step]}</p>
            <fieldset className="register-fields" hidden={step !== 0} disabled={step !== 0}><legend className="sr-only">Personal details</legend>
            <label className="auth-field">
              <span>First Name</span>
              <div className="auth-input-wrapper auth-input-plain">
                <input
                  autoComplete="off"
                  maxLength={60}
                  name="first_name"
                  {...fieldErrorProps('first_name')}
                  onChange={handleNameChange}
                  placeholder="First name"
                  required
                  type="text"
                  value={form.first_name}
                />
              </div>
              <RegistrationFieldError name="first_name" errors={fieldErrors} />
            </label>

            <label className="auth-field">
              <span>Middle Name</span>
              <div className="auth-input-wrapper auth-input-plain">
                <input
                  autoComplete="off"
                  maxLength={60}
                  name="middle_name"
                  {...fieldErrorProps('middle_name')}
                  onChange={handleNameChange}
                  placeholder="Middle name (optional)"
                  type="text"
                  value={form.middle_name}
                />
              </div>
              <RegistrationFieldError name="middle_name" errors={fieldErrors} />
            </label>

            <label className="auth-field auth-field-full">
              <span>Last Name</span>
              <div className="auth-input-wrapper auth-input-plain">
                <input
                  autoComplete="off"
                  maxLength={60}
                  name="last_name"
                  {...fieldErrorProps('last_name')}
                  onChange={handleNameChange}
                  placeholder="Last name"
                  required
                  type="text"
                  value={form.last_name}
                />
              </div>
              <RegistrationFieldError name="last_name" errors={fieldErrors} />
            </label>

            <label className="auth-field auth-field-full">
              <span>Email Address</span>
              <div className="auth-input-wrapper auth-input-plain">
                <input
                  autoComplete="off"
                  name="email"
                  {...fieldErrorProps('email')}
                  onChange={handleChange}
                  placeholder="Email address"
                  required
                  type="email"
                  value={form.email}
                />
              </div>
              <RegistrationFieldError name="email" errors={fieldErrors} />
            </label>

            <label className="auth-field">
              <span>Phone Number</span>
              <div className="auth-input-wrapper auth-input-plain">
                <input
                  autoComplete="off"
                  inputMode="numeric"
                  maxLength={11}
                  name="phone"
                  {...fieldErrorProps('phone')}
                  onChange={handlePhoneChange}
                  placeholder="09XXXXXXXXX"
                  required
                  type="tel"
                  value={form.phone}
                />
              </div>
              <RegistrationFieldError name="phone" errors={fieldErrors} />
            </label>

            </fieldset><fieldset className="register-fields" hidden={step !== 1} disabled={step !== 1}><legend className="sr-only">Barangay and address</legend>
            <label className="auth-field">
              <span>Province</span>
              <div className="auth-input-wrapper auth-input-plain">
                <select
                  name="province_id"
                  {...fieldErrorProps('province_id')}
                  onChange={handleProvinceChange}
                  required
                  value={form.province_id}
                >
                  <option value="" disabled>Select your province</option>
                  {provinces.map((province) => (
                    <option key={province.id} value={province.id}>{province.name}</option>
                  ))}
                </select>
              </div>
              <RegistrationFieldError name="province_id" errors={fieldErrors} />
            </label>

            <label className="auth-field">
              <span>City / Municipality</span>
              <div className="auth-input-wrapper auth-input-plain">
                <select
                  disabled={!form.province_id || citiesLoading}
                  name="city_id"
                  {...fieldErrorProps('city_id')}
                  onChange={handleCityChange}
                  required
                  value={form.city_id}
                >
                  <option value="" disabled>
                    {citiesLoading ? 'Loading cities?' : form.province_id ? 'Select your city/municipality' : 'Select a province first'}
                  </option>
                  {cities.map((city) => (
                    <option key={city.id} value={city.id}>{city.name}</option>
                  ))}
                </select>
              </div>
              <RegistrationFieldError name="city_id" errors={fieldErrors} />
            </label>

            <label className="auth-field auth-field-full">
              <span>Barangay</span>
              <div className="auth-input-wrapper auth-input-plain">
                <select
                  name="barangay_id"
                  {...fieldErrorProps('barangay_id')}
                  onChange={handleChange}
                  disabled={!form.city_id || barangaysLoading || !barangays.length}
                  required
                  value={form.barangay_id}
                >
                  <option value="" disabled>{barangaysLoading ? 'Loading barangays?' : !form.city_id ? 'Select a city/municipality first' : barangays.length ? 'Select your barangay' : 'No barangays available'}</option>
                  {barangays.map((barangay) => <option key={barangay.id} value={barangay.id}>{barangay.name}</option>)}
                </select>
              </div>
              <RegistrationFieldError name="barangay_id" errors={fieldErrors} />
            </label>
            {locationError && <div className="notice error auth-field-full" role="alert">{locationError} <button type="button" className="link-button" onClick={() => { setLocationError(''); setCitiesLoading(Boolean(form.province_id)); setBarangaysLoading(Boolean(form.city_id)); setLocationRetry((value) => value + 1); }}>Try again</button></div>}
            {form.city_id && !barangaysLoading && !barangays.length && !locationError && <p className="notice auth-field-full">No barangay reference is available for this city. Contact the system administrator.</p>}
            {form.barangay_id && <p className="field-hint auth-field-full">Your selected barangay sets your account&apos;s service area. Its map outline may require administrator review.</p>}
            {form.barangay_id && <section className="register-boundary-preview auth-field-full" aria-live="polite">
              <header><strong>Boundary preview</strong><span>PSGC {boundaryPreview?.psgc_code ?? 'Loading…'}</span></header>
              {boundaryPolygons.length ? <div className="register-boundary-map"><MapContainer key={JSON.stringify(boundaryPreview.boundary)} bounds={boundaryPolygons.flat(2)} style={{ height: '100%', width: '100%' }} scrollWheelZoom={false}>
                <TileLayer attribution="&copy; OpenStreetMap contributors" url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png" />
                {boundaryPolygons.map((polygon, index) => <Polygon key={index} positions={polygon} pathOptions={{ color: '#ef4444', weight: 2, fillOpacity: .12 }} />)}
              </MapContainer></div> : <p>{boundaryPreview?.error ? 'Boundary preview could not be loaded.' : boundaryPreview ? 'No authoritative reference boundary is available for this PSGC record.' : 'Retrieving the PSGC-matched boundary…'}</p>}
              <small>Reference preview only; it is not a legal boundary certification.</small>
            </section>}

            {isFirstForBarangay === true && (
              <p className="notice success auth-field-full">
                You&apos;ll be the first to register here — you&apos;ll become this barangay&apos;s Administrator once a Super Admin reviews and approves your account.
              </p>
            )}

            <label className="auth-field auth-field-full">
              <span>Street Address</span>
              <div className="auth-input-wrapper auth-input-plain">
                <input
                  autoComplete="off"
                  name="address"
                  {...fieldErrorProps('address')}
                  onChange={handleChange}
                  placeholder="House no. / street / purok"
                  required
                  type="text"
                  value={form.address}
                />
              </div>
              <RegistrationFieldError name="address" errors={fieldErrors} />
            </label>

            </fieldset><fieldset className="register-fields" hidden={step !== 2} disabled={step !== 2}><legend className="sr-only">Account access</legend>
            {isFirstForBarangay === true && <p className="notice success auth-field-full">First Administrator registration. Super Admin approval is required before sign-in.</p>}
            {isFirstForBarangay !== true && (
              <label className="auth-field">
                <span>I am a...</span>
                <div className="auth-input-wrapper auth-input-plain">
                  <select
                    name="requested_role"
                    {...fieldErrorProps('requested_role')}
                    onChange={handleChange}
                    required
                    value={form.requested_role}
                  >
                    <option value="" disabled>Select your role</option>
                    <option value="Custodian">Custodian</option>
                    <option value="Maintenance Personnel">Maintenance Personnel</option>
                  </select>
                </div>
                <RegistrationFieldError name="requested_role" errors={fieldErrors} />
              </label>
            )}

            <label className="auth-field">
              <span>Staff Registration Code</span>
              <div className="auth-input-wrapper auth-input-plain">
                <input
                  autoComplete="off"
                  name="staff_code"
                  {...fieldErrorProps('staff_code')}
                  onChange={handleChange}
                  placeholder="Given to you by your barangay office"
                  required
                  type="text"
                  value={form.staff_code}
                />
              </div>
              <RegistrationFieldError name="staff_code" errors={fieldErrors} />
            </label>

            <label className="auth-field">
              <span>Password</span>
              <div className="auth-input-wrapper auth-input-plain">
                <input
                  autoComplete="new-password"
                  name="password"
                  {...fieldErrorProps('password')}
                  onChange={handleChange}
                  placeholder="Password"
                  required
                  type={showPassword ? 'text' : 'password'}
                  value={form.password}
                />
                <button
                  type="button"
                  className="password-toggle-btn"
                  onClick={() => setShowPassword((v) => !v)}
                  aria-label={showPassword ? 'Hide password' : 'Show password'}
                  title={showPassword ? 'Hide password' : 'Show password'}
                >
                  <Icon name={showPassword ? 'eyeOff' : 'eye'} size={18} />
                </button>
              </div>
              <RegistrationFieldError name="password" errors={fieldErrors} />
            </label>

            <label className="auth-field">
              <span>Confirm Password</span>
              <div className="auth-input-wrapper auth-input-plain">
                <input
                  autoComplete="new-password"
                  name="password_confirmation"
                  {...fieldErrorProps('password_confirmation')}
                  onChange={handleChange}
                  placeholder="Confirm password"
                  required
                  type={showPassword ? 'text' : 'password'}
                  value={form.password_confirmation}
                />
              </div>
              <RegistrationFieldError name="password_confirmation" errors={fieldErrors} />
            </label>

            </fieldset>
            {error ? <p role="alert" className="notice error auth-field-full">{error}</p> : null}
            <div className="register-actions">{step > 0 && <button type="button" className="ghost-button" disabled={submitting} onClick={() => { setStep((current) => current - 1); setError(''); }}>Back</button>}

            <button className="primary-button auth-submit-btn auth-field-full" disabled={submitting} type="submit">
              {submitting ? (
                <span className="btn-loading">
                  <span className="btn-spinner" aria-hidden="true" />
                  Submitting…
                </span>
              ) : step < 2 ? 'Continue' : 'Create account'}
            </button>
            </div>
          </form>
        )}

        <p className="auth-legal">
          Already have an account? <Link to="/login">Sign in</Link>
        </p>
      </section>

      <div className="auth-split-hero">
        <Aurora colorStops={['#0b1220', '#1e3a5f', '#0f172a']} amplitude={0.6} blend={0.55} />
        <div className="auth-split-hero-content">
          <h2>Join Your Barangay's Fleet Team</h2>
          <p>
            Create an account to help track vehicle readiness, report
            issues, and coordinate maintenance for your barangay's fleet.
          </p>
        </div>
      </div>
      </main>

      <AuthFooter />
    </div>
  );
}

export default Register;
