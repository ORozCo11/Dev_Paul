import { useState, useEffect } from 'react';
import api from '../api/axios';
import { clearImpersonationSession } from '../utils/impersonation';
import { AuthContext } from './AuthContextObject';

export const AuthProvider = ({ children }) => {
  const [user, setUser] = useState(null);
  // "Remember me" decides WHERE the token lives: localStorage survives a
  // closed browser, sessionStorage clears the moment the tab/window closes.
  // Read both on boot since either could hold the active session.
  const [token, setToken] = useState(localStorage.getItem('token') || sessionStorage.getItem('token') || null);
  const [loading, setLoading] = useState(true);

  const logout = () => {
    const logoutToken = localStorage.getItem('token') || sessionStorage.getItem('token');
    // Clear local state immediately so the UI reacts right away,
    // then invalidate the server-side token in the background.
    localStorage.removeItem('token');
    sessionStorage.removeItem('token');
    clearImpersonationSession();
    setToken(null);
    setUser(null);
    if (logoutToken) {
      return api.post('/logout', null, { headers: { Authorization: `Bearer ${logoutToken}` } }).catch(() => {});
    }
  };

  // A session can end while the page is open (it expires, or someone signs the account out). Without this the page
  // just kept failing quietly; now any "not signed in" answer sends the person back to the login page with a reason.
  useEffect(() => {
    const id = api.interceptors.response.use((response) => response, (error) => {
      const current = localStorage.getItem('token') || sessionStorage.getItem('token');
      const sent = error.config?.headers?.Authorization;
      const url = error.config?.url ?? '';
      if (error.response?.status === 401 && current && !/\/(login|logout)$/.test(url) && (!sent || sent === `Bearer ${current}`)) {
        try { sessionStorage.setItem('vms_session_ended', '1'); } catch { /* the redirect still happens */ }
        logout();
      }
      return Promise.reject(error);
    });
    return () => api.interceptors.response.eject(id);
  }, []);

  useEffect(() => {
    let cancelled = false;
    const currentToken = () => localStorage.getItem('token') || sessionStorage.getItem('token');
    const checkUserSession = async () => {
      if (token) {
        try {
          const response = await api.get('/user', { headers: { Authorization: `Bearer ${token}` } });
          if (cancelled || currentToken() !== token) return;
          if (!response.data.is_impersonating) clearImpersonationSession();
          setUser(response.data);
        } catch (error) {
          if (!cancelled && currentToken() === token && error.response?.status === 401) logout();
        }
      }
      if (!cancelled) setLoading(false);
    };
    checkUserSession();
    return () => { cancelled = true; };
  }, [token]);

  const login = (userData, userToken, remember = true) => {
    if (!userData.is_impersonating) clearImpersonationSession();
    // Only one storage ever holds the token at a time — clear the other so a
    // stale copy can't linger and outlive the choice the user just made.
    if (remember) {
      localStorage.setItem('token', userToken);
      sessionStorage.removeItem('token');
    } else {
      sessionStorage.setItem('token', userToken);
      localStorage.removeItem('token');
    }
    setToken(userToken);
    setUser(userData);
  };

  // Re-pull the logged-in user from the API (e.g. after they edit their own
  // profile) so the topbar name/avatar reflect the change without a reload.
  const refreshUser = async () => {
    const refreshToken = localStorage.getItem('token') || sessionStorage.getItem('token');
    if (!refreshToken) return;
    try {
      const response = await api.get('/user', { headers: { Authorization: `Bearer ${refreshToken}` } });
      const currentToken = localStorage.getItem('token') || sessionStorage.getItem('token');
      if (currentToken === refreshToken) setUser(response.data);
    } catch {
      // keep the current user if the refresh fails
    }
  };

  return (
    <AuthContext.Provider value={{ user, token, login, logout, loading, refreshUser }}>
      {children}
    </AuthContext.Provider>
  );
};
