import { createContext, useCallback, useContext, useEffect, useMemo, useRef, useState } from 'react';
import { getSocket } from '../services/socket';

const MatchContext = createContext(null);

export function MatchProvider({ children }) {
  const [status, setStatus] = useState('idle');
  const [roomId, setRoomId] = useState(null);
  const [peer, setPeer] = useState(null);
  const handlerRef = useRef(null);

  useEffect(() => {
    const socket = getSocket();
    if (!socket) return;

    const onWaiting = () => setStatus('waiting');
    const onFound = ({ roomId: rid, peer: p }) => {
      setRoomId(rid);
      setPeer(p);
      setStatus('matched');
    };
    const onCancelled = () => setStatus('idle');
    const onTimeout = () => setStatus('idle');
    const onEnded = () => {
      setStatus('idle');
      setRoomId(null);
      setPeer(null);
    };

    socket.on('match:waiting', onWaiting);
    socket.on('match:found', onFound);
    socket.on('match:cancelled', onCancelled);
    socket.on('match:timeout', onTimeout);
    socket.on('chat:ended', onEnded);

    handlerRef.current = { onWaiting, onFound, onCancelled, onTimeout, onEnded };

    return () => {
      if (!handlerRef.current) return;
      const h = handlerRef.current;
      socket.off('match:waiting', h.onWaiting);
      socket.off('match:found', h.onFound);
      socket.off('match:cancelled', h.onCancelled);
      socket.off('match:timeout', h.onTimeout);
      socket.off('chat:ended', h.onEnded);
    };
  }, []);

  const start = useCallback((interestIds = []) => {
    const socket = getSocket();
    if (!socket) return;
    setStatus('waiting');
    setRoomId(null);
    setPeer(null);
    socket.emit('match:start', { interestIds });
  }, []);

  const cancel = useCallback(() => {
    const socket = getSocket();
    if (!socket) return;
    socket.emit('match:cancel');
  }, []);

  const leave = useCallback(() => {
    const socket = getSocket();
    if (!socket) return;
    socket.emit('chat:leave');
    setStatus('idle');
    setRoomId(null);
    setPeer(null);
  }, []);

  const next = useCallback(() => {
    leave();
  }, [leave]);

  const reset = useCallback(() => {
    setStatus('idle');
    setRoomId(null);
    setPeer(null);
  }, []);

  const value = useMemo(
    () => ({ status, roomId, peer, start, cancel, leave, next, reset }),
    [status, roomId, peer, start, cancel, leave, next, reset]
  );

  return <MatchContext.Provider value={value}>{children}</MatchContext.Provider>;
}

export function useMatch() {
  const ctx = useContext(MatchContext);
  if (!ctx) throw new Error('useMatch must be inside MatchProvider');
  return ctx;
}
