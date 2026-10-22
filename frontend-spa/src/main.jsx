import React from 'react'
import ReactDOM from 'react-dom/client'
import './index.css'
import App from './App.jsx'
import { AuthProvider } from './context/AuthContext'

// The application uses a single light appearance.
document.documentElement.setAttribute('data-theme', 'light');
try { localStorage.removeItem('theme'); } catch { /* Storage may be unavailable. */ }

ReactDOM.createRoot(document.getElementById('root')).render(
  <React.StrictMode>
    <AuthProvider>
      <App />
    </AuthProvider>
  </React.StrictMode>,
)
