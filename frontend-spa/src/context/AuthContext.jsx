import { useState, useEffect } from 'react';
import api from '../api/axios';
import { AuthContext } from './AuthContextObject';

export const AuthProvider = ({ children }) => {
  const [user, setUser] = useState(null);
  // "Remember me" decides WHERE the token lives: localStorage survives a
  // closed browser, sessionStorage clears the moment the tab/window closes.
  // Read both on boot since either could hold the active session.
  const [token, setToken] = useState(localStorage.getItem('token') || sessionStorage.getItem('token') || null);
  const [loading, setLoading] = useState(true);

  const logout = () => {
    // Clear local state immediately so the UI reacts right away,
    // then invalidate the server-side token in the background.
    localStorage.removeItem('token');
    sessionStorage.removeItem('token');
    setToken(null);
    setUser(null);
    api.post('/logout').catch(() => {});
  };

  useEffect(() => {
    const checkUserSession = async () => {
      if (token) {
        try {
          const response = await api.get('/user');
          setUser(response.data);
        } catch {
          logout();
        }
      }
      setLoading(false);
    };
    checkUserSession();
  }, [token]);

  const login = (userData, userToken, remember = true) => {
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
    try {
      const response = await api.get('/user');
      setUser(response.data);
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
