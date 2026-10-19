import { BrowserRouter as Router, Routes, Route, Navigate, Link } from 'react-router-dom';
import { ProtectedRoute } from './components/ProtectedRoute';
import ErrorBoundary from './components/ErrorBoundary';
import Login from './views/Login';
import Register from './views/Register';
import Privacy from './views/Privacy';
import Terms from './views/Terms';
import About from './views/About';
import Developers from './views/Developers';
import Support from './views/Support';
import Workspace from './views/Workspace';
import SuperAdminWorkspace from './views/SuperAdminWorkspace';
import './App.css';
import './DarkMode.css';
import './WorkflowDetails.css';

function Unauthorized() {
  return (
    <main className="auth-page">
      <section className="auth-card">
        <p className="eyebrow">Access Control</p>
        <h1>Access denied</h1>
        <p>Your account role does not have permission to view that workspace.</p>
        <Link className="primary-link" to="/login">Return to sign in</Link>
      </section>
    </main>
  );
}

function App() {
  return (
    <Router>
      <ErrorBoundary>
        <Routes>
          <Route path="/login" element={<Login />} />
          <Route path="/register" element={<Register />} />
          <Route path="/privacy" element={<Privacy />} />
          <Route path="/terms" element={<Terms />} />
          <Route path="/about" element={<About />} />
          <Route path="/developers" element={<Developers />} />
          <Route path="/support" element={<Support />} />
          <Route path="/unauthorized" element={<Unauthorized />} />
          <Route
            path="/admin/*"
            element={
              <ProtectedRoute allowedRoles={['Admin']}>
                <Workspace />
              </ProtectedRoute>
            }
          />
          <Route
            path="/custodian/*"
            element={
              <ProtectedRoute allowedRoles={['Custodian']}>
                <Workspace />
              </ProtectedRoute>
            }
          />
          <Route
            path="/maintenance/*"
            element={
              <ProtectedRoute allowedRoles={['Maintenance Personnel']}>
                <Workspace />
              </ProtectedRoute>
            }
          />
          <Route
            path="/superadmin/*"
            element={
              <ProtectedRoute allowedRoles={['Super Admin']}>
                <SuperAdminWorkspace />
              </ProtectedRoute>
            }
          />
          <Route path="*" element={<Navigate to="/login" replace />} />
        </Routes>
      </ErrorBoundary>
    </Router>
  );
}

export default App;
