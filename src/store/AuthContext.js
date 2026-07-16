import AsyncStorage from '@react-native-async-storage/async-storage';
import { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react';
import { api, setAuthToken } from '../services/api';
import { connectSocket, disconnectSocket } from '../services/socket';

const STORAGE_KEYS = {
  token: '@anonchat/token',
  user: '@anonchat/user',
};

const AuthContext = createContext(null);

export function AuthProvider({ children }) {
  const [bootstrapping, setBootstrapping] = useState(true);
  const [user, setUser] = useState(null);
  const [token, setTokenState] = useState(null);
  const [error, setError] = useState(null);

  const finishAuth = useCallback(async (tokenValue, userValue) => {
    setAuthToken(tokenValue);
    setTokenState(tokenValue);
    setUser(userValue);
    await AsyncStorage.setItem(STORAGE_KEYS.token, tokenValue);
    await AsyncStorage.setItem(STORAGE_KEYS.user, JSON.stringify(userValue));
    connectSocket(tokenValue);
  }, []);

  const bootstrap = useCallback(async () => {
    setError(null);
    try {
      const cachedToken = await AsyncStorage.getItem(STORAGE_KEYS.token);
      const cachedUserRaw = await AsyncStorage.getItem(STORAGE_KEYS.user);

      if (cachedToken && cachedUserRaw) {
        let cachedUser = null;
        try {
          cachedUser = JSON.parse(cachedUserRaw);
        } catch {}
        setAuthToken(cachedToken);
        setTokenState(cachedToken);
        if (cachedUser) setUser(cachedUser);
        connectSocket(cachedToken);

        try {
          const fresh = await api.getMe();
          setUser(fresh.user);
          await AsyncStorage.setItem(STORAGE_KEYS.user, JSON.stringify(fresh.user));
        } catch (err) {
          console.warn('[auth bootstrap refresh]', err.message);
          if (err.status === 401 || err.status === 403) {
            await AsyncStorage.multiRemove([STORAGE_KEYS.token, STORAGE_KEYS.user]);
            setAuthToken(null);
            setTokenState(null);
            setUser(null);
            disconnectSocket();
          }
        }
      }
    } catch (err) {
      console.error('[auth bootstrap]', err);
      setError(err.message || 'auth_failed');
    } finally {
      setBootstrapping(false);
    }
  }, []);

  useEffect(() => {
    bootstrap();
    return () => disconnectSocket();
  }, [bootstrap]);

  const login = useCallback(async (email, password) => {
    const res = await api.login(email, password);
    await finishAuth(res.token, res.user);
    return res.user;
  }, [finishAuth]);

  const register = useCallback(async ({ email, password, username, nickname }) => {
    const res = await api.register({ email, password, username, nickname });
    await finishAuth(res.token, res.user);
    return res.user;
  }, [finishAuth]);

  const logout = useCallback(async () => {
    await AsyncStorage.multiRemove([STORAGE_KEYS.token, STORAGE_KEYS.user]);
    setAuthToken(null);
    setTokenState(null);
    setUser(null);
    disconnectSocket();
  }, []);

  const refreshUser = useCallback(async () => {
    try {
      const res = await api.getMe();
      setUser(res.user);
      await AsyncStorage.setItem(STORAGE_KEYS.user, JSON.stringify(res.user));
      return res.user;
    } catch (err) {
      console.error('[refreshUser]', err);
      throw err;
    }
  }, []);

  const updateUser = useCallback(async (patch) => {
    const res = await api.updateMe(patch);
    setUser(res.user);
    await AsyncStorage.setItem(STORAGE_KEYS.user, JSON.stringify(res.user));
    return res.user;
  }, []);

  const value = useMemo(
    () => ({
      bootstrapping,
      user,
      token,
      error,
      login,
      register,
      logout,
      refreshUser,
      updateUser,
    }),
    [bootstrapping, user, token, error, login, register, logout, refreshUser, updateUser]
  );

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth() {
  const ctx = useContext(AuthContext);
  if (!ctx) throw new Error('useAuth must be used inside AuthProvider');
  return ctx;
}
