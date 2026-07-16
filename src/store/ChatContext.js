import { createContext, useCallback, useContext, useEffect, useMemo, useRef, useState } from 'react';
import { getSocket } from '../services/socket';

const ChatContext = createContext(null);

export function ChatProvider({ children, roomId }) {
  const [messages, setMessages] = useState([]);
  const [peerTyping, setPeerTyping] = useState(false);
  const [ended, setEnded] = useState(false);
  const handlerRef = useRef(null);

  useEffect(() => {
    if (!roomId) {
      setMessages([]);
      setPeerTyping(false);
      setEnded(false);
      return;
    }
    const socket = getSocket();
    if (!socket) return;

    const onMessage = (msg) => {
      setMessages((prev) => [
        ...prev,
        {
          id: String(msg.id),
          content: msg.content,
          senderId: msg.senderId,
          createdAt: msg.createdAt,
          flagged: msg.flagged,
          fromPeer: true,
        },
      ]);
    };
    const onSent = ({ id, createdAt }) => {
      setMessages((prev) => {
        const next = [...prev];
        for (let i = next.length - 1; i >= 0; i--) {
          if (next[i].pending) {
            next[i] = { ...next[i], id: String(id), createdAt, pending: false };
            break;
          }
        }
        return next;
      });
    };
    const onTyping = ({ typing }) => setPeerTyping(!!typing);
    const onEnded = () => setEnded(true);
    const onError = ({ error }) => console.warn('[chat error]', error);

    socket.on('chat:message', onMessage);
    socket.on('chat:sent', onSent);
    socket.on('chat:typing', onTyping);
    socket.on('chat:ended', onEnded);
    socket.on('chat:error', onError);

    handlerRef.current = { onMessage, onSent, onTyping, onEnded, onError };

    return () => {
      if (!handlerRef.current) return;
      const h = handlerRef.current;
      socket.off('chat:message', h.onMessage);
      socket.off('chat:sent', h.onSent);
      socket.off('chat:typing', h.onTyping);
      socket.off('chat:ended', h.onEnded);
      socket.off('chat:error', h.onError);
    };
  }, [roomId]);

  const send = useCallback((content) => {
    const socket = getSocket();
    if (!socket || !roomId) return;
    const text = (content || '').trim();
    if (!text) return;
    const tempId = `temp-${Date.now()}-${Math.random().toString(36).slice(2, 6)}`;
    setMessages((prev) => [
      ...prev,
      { id: tempId, content: text, pending: true, createdAt: new Date().toISOString() },
    ]);
    socket.emit('chat:message', { content: text, roomId });
  }, [roomId]);

  const setTyping = useCallback((typing) => {
    const socket = getSocket();
    if (!socket) return;
    socket.emit('chat:typing', { typing });
  }, []);

  const report = useCallback((reason = 'inappropriate', note) => {
    const socket = getSocket();
    if (!socket) return;
    socket.emit('chat:report', { reason, note });
  }, []);

  const value = useMemo(
    () => ({ messages, peerTyping, ended, send, setTyping, report }),
    [messages, peerTyping, ended, send, setTyping, report]
  );

  return <ChatContext.Provider value={value}>{children}</ChatContext.Provider>;
}

export function useChat() {
  const ctx = useContext(ChatContext);
  if (!ctx) throw new Error('useChat must be inside ChatProvider');
  return ctx;
}
