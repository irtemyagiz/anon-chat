import { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react';
import { api } from '../services/api';

const InterestsContext = createContext(null);

export function InterestsProvider({ children }) {
  const [interests, setInterests] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await api.getInterests();
      setInterests(res.interests || []);
    } catch (err) {
      setError(err.message || 'failed');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    load();
  }, [load]);

  const value = useMemo(
    () => ({ interests, loading, error, reload: load }),
    [interests, loading, error, load]
  );

  return <InterestsContext.Provider value={value}>{children}</InterestsContext.Provider>;
}

export function useInterests() {
  const ctx = useContext(InterestsContext);
  if (!ctx) throw new Error('useInterests must be inside InterestsProvider');
  return ctx;
}
