import { createContext, useCallback, useContext, useEffect, useMemo, useRef, useState } from 'react';
import { getSocket } from '../services/socket';

const MatchContext = createContext(null);

export function MatchProvider({ children, initialRoomId, initialPeer }) {
  const [status, setStatus] = useState('idle');
  const [mode, setMode] = useState('idle');
  const [roomId, setRoomId] = useState(initialRoomId || null);
  const [peer, setPeer] = useState(initialPeer || null);
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
    const onSoulmateWaiting = () => { setStatus('waiting'); setMode('soulmate'); };
    const onSoulmateFound = ({ roomId: rid, peer: p }) => {
      setRoomId(rid);
      setPeer(p);
      setStatus('matched');
      setMode('soulmate');
    };
    const onCancelled = () => { setStatus('idle'); setMode('idle'); };
    const onSoulmateCancelled = () => { setStatus('idle'); setMode('idle'); };
    const onTimeout = () => { setStatus('idle'); setMode('idle'); };
    const onSoulmateTimeout = () => { setStatus('idle'); setMode('idle'); };
    const onSoulmateLimit = () => { setStatus('idle'); setMode('idle'); };
    const onEnded = () => { setStatus('idle'); setMode('idle'); setRoomId(null); setPeer(null); };

    socket.on('match:waiting', onWaiting);
    socket.on('match:found', onFound);
    socket.on('match:cancelled', onCancelled);
    socket.on('match:timeout', onTimeout);
    socket.on('match:soulmate:waiting', onSoulmateWaiting);
    socket.on('match:soulmate:found', onSoulmateFound);
    socket.on('match:soulmate:cancelled', onSoulmateCancelled);
    socket.on('match:soulmate:timeout', onSoulmateTimeout);
    socket.on('match:soulmate:limit', onSoulmateLimit);
    socket.on('chat:ended', onEnded);

    handlerRef.current = {
      onWaiting, onFound, onCancelled, onTimeout,
      onSoulmateWaiting, onSoulmateFound, onSoulmateCancelled, onSoulmateTimeout, onSoulmateLimit,
      onEnded,
    };

    return () => {
      if (!handlerRef.current) return;
      const h = handlerRef.current;
      socket.off('match:waiting', h.onWaiting);
      socket.off('match:found', h.onFound);
      socket.off('match:cancelled', h.onCancelled);
      socket.off('match:timeout', h.onTimeout);
      socket.off('match:soulmate:waiting', h.onSoulmateWaiting);
      socket.off('match:soulmate:found', h.onSoulmateFound);
      socket.off('match:soulmate:cancelled', h.onSoulmateCancelled);
      socket.off('match:soulmate:timeout', h.onSoulmateTimeout);
      socket.off('match:soulmate:limit', h.onSoulmateLimit);
      socket.off('chat:ended', h.onEnded);
    };
  }, []);

  const start = useCallback((interestIds = []) => {
    const socket = getSocket();
    if (!socket) return;
    setStatus('waiting');
    setMode('shuffle');
    setRoomId(null);
    setPeer(null);
    socket.emit('match:start', { interestIds });
  }, []);

  const startSoulmate = useCallback(() => {
    const socket = getSocket();
    if (!socket) return;
    setStatus('waiting');
    setMode('soulmate');
    setRoomId(null);
    setPeer(null);
    socket.emit('match:soulmate:start');
  }, []);

  const cancel = useCallback(() => {
    const socket = getSocket();
    if (!socket) return;
    if (mode === 'soulmate') socket.emit('match:soulmate:cancel');
    else socket.emit('match:cancel');
  }, [mode]);

  const leave = useCallback(() => {
    const socket = getSocket();
    if (!socket) return;
    socket.emit('chat:leave');
    setStatus('idle');
    setMode('idle');
    setRoomId(null);
    setPeer(null);
  }, []);

  const next = useCallback(() => {
    leave();
  }, [leave]);

  const reset = useCallback(() => {
    setStatus('idle');
    setMode('idle');
    setRoomId(null);
    setPeer(null);
  }, []);

  const setDirect = useCallback((rid, p) => {
    setStatus('matched');
    setMode('direct');
    setRoomId(rid);
    setPeer(p);
  }, []);

  const value = useMemo(
    () => ({ status, mode, roomId, peer, start, startSoulmate, cancel, leave, next, reset, setDirect }),
    [status, mode, roomId, peer, start, startSoulmate, cancel, leave, next, reset, setDirect]
  );

  return <MatchContext.Provider value={value}>{children}</MatchContext.Provider>;
}

export function useMatch() {
  const ctx = useContext(MatchContext);
  if (!ctx) throw new Error('useMatch must be inside MatchProvider');
  return ctx;
}