import React from 'react'
import ReactDOM from 'react-dom/client'
import './index.css'
import App from './App.jsx'
import { AuthProvider } from './context/AuthContext'

let theme = 'light';
try { if (localStorage.getItem('theme') === 'dark') theme = 'dark'; } catch { /* Use the default when storage is unavailable. */ }
document.documentElement.dataset.theme = theme;
document.documentElement.style.colorScheme = theme;

ReactDOM.createRoot(document.getElementById('root')).render(
  <React.StrictMode>
    <AuthProvider>
      <App />
    </AuthProvider>
  </React.StrictMode>,
)
