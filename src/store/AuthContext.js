import AsyncStorage from '@react-native-async-storage/async-storage';
import * as Application from 'expo-application';
import { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react';
import { api, setAuthToken } from '../services/api';
import { connectSocket, disconnectSocket } from '../services/socket';

const STORAGE_KEYS = {
  deviceId: '@anonchat/deviceId',
  token: '@anonchat/token',
  user: '@anonchat/user',
};

const AuthContext = createContext(null);

async function getOrCreateDeviceId() {
  let id = await AsyncStorage.getItem(STORAGE_KEYS.deviceId);
  if (!id) {
    try {
      const androidId = Application.getAndroidId?.();
      const iosId = await Application.getIosIdForVendorAsync?.();
      id = androidId || iosId || null;
    } catch {}
    if (!id) {
      id = `${Date.now()}-${Math.random().toString(36).slice(2, 12)}`;
    }
    await AsyncStorage.setItem(STORAGE_KEYS.deviceId, id);
  }
  return id;
}

export function AuthProvider({ children }) {
  const [bootstrapping, setBootstrapping] = useState(true);
  const [user, setUser] = useState(null);
  const [token, setTokenState] = useState(null);
  const [error, setError] = useState(null);

  const bootstrap = useCallback(async () => {
    setError(null);
    try {
      const deviceId = await getOrCreateDeviceId();
      const cachedToken = await AsyncStorage.getItem(STORAGE_KEYS.token);
      const cachedUser = await AsyncStorage.getItem(STORAGE_KEYS.user);

      let activeToken = cachedToken;

      if (!activeToken) {
        const res = await api.authDevice(deviceId);
        activeToken = res.token;
        await AsyncStorage.setItem(STORAGE_KEYS.token, activeToken);
        await AsyncStorage.setItem(STORAGE_KEYS.user, JSON.stringify(res.user));
      }

      setAuthToken(activeToken);
      setTokenState(activeToken);

      if (cachedUser) {
        try {
          setUser(JSON.parse(cachedUser));
        } catch {}
      }

      try {
        const fresh = await api.getMe();
        setUser(fresh.user);
        await AsyncStorage.setItem(STORAGE_KEYS.user, JSON.stringify(fresh.user));
      } catch {}

      connectSocket(activeToken);
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
      refreshUser,
      updateUser,
    }),
    [bootstrapping, user, token, error, refreshUser, updateUser]
  );

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth() {
  const ctx = useContext(AuthContext);
  if (!ctx) throw new Error('useAuth must be used inside AuthProvider');
  return ctx;
}
