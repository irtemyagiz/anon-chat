import { createContext, useCallback, useContext, useEffect, useMemo, useRef, useState } from 'react';
import { api } from '../services/api';
import { getSocket } from '../services/socket';

const ChatContext = createContext(null);

export function ChatProvider({ children, roomId, peerId }) {
  const [messages, setMessages] = useState([]);
  const [peerTyping, setPeerTyping] = useState(false);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const handlerRef = useRef(null);
  const typingTimerRef = useRef(null);
  const mountedRef = useRef(true);

  useEffect(() => {
    mountedRef.current = true;
    return () => {
      mountedRef.current = false;
    };
  }, []);

  useEffect(() => {
    if (!roomId) return;
    setLoading(true);
    setError(null);
    setMessages([]);
    setPeerTyping(false);

    api.getChatMessages(roomId, 100)
      .then((res) => {
        if (!mountedRef.current) return;
        setMessages(
          (res.messages || []).map((m) => ({
            id: String(m.id),
            content: m.content,
            senderId: m.senderId,
            createdAt: m.createdAt,
            flagged: m.flagged,
            fromPeer: false,
          }))
        );
      })
      .catch((e) => {
        if (!mountedRef.current) return;
        setError(e.message || 'Yüklenemedi');
      })
      .finally(() => {
        if (!mountedRef.current) return;
        setLoading(false);
      });

    if (peerId) {
      api.markChatRead(peerId).catch(() => {});
    }
  }, [roomId, peerId]);

  useEffect(() => {
    if (!roomId) return;
    const socket = getSocket();
    if (!socket) return;

    const onMessage = (msg) => {
      setMessages((prev) => {
        if (prev.some((m) => String(m.id) === String(msg.id))) return prev;
        return [
          ...prev,
          {
            id: String(msg.id),
            content: msg.content,
            senderId: msg.senderId,
            createdAt: msg.createdAt,
            flagged: msg.flagged,
            fromPeer: true,
          },
        ];
      });
    };
    const onSent = ({ id, createdAt }) => {
      setMessages((prev) => {
        const next = prev.slice();
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
    const onEnded = () => {
      // stay in chat, peer left
    };
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

  const send = useCallback(
    (content) => {
      const socket = getSocket();
      if (!socket || !roomId) return;
      const text = (content || '').trim();
      if (!text) return;
      const tempId = `temp-${Date.now()}-${Math.random().toString(36).slice(2, 6)}`;
      setMessages((prev) => [
        ...prev,
        { id: tempId, content: text, pending: true, createdAt: new Date().toISOString(), fromPeer: false },
      ]);
      socket.emit('chat:message', { content: text, roomId });
      socket.emit('chat:typing', { typing: false, roomId });
    },
    [roomId]
  );

  const setTyping = useCallback(
    (typing) => {
      const socket = getSocket();
      if (!socket || !roomId) return;
      socket.emit('chat:typing', { typing, roomId });
    },
    [roomId]
  );

  const report = useCallback(
    (reason = 'inappropriate', note) => {
      const socket = getSocket();
      if (!socket) return;
      socket.emit('chat:report', { reason, note });
    },
    []
  );

  const value = useMemo(
    () => ({ messages, peerTyping, loading, error, send, setTyping, report }),
    [messages, peerTyping, loading, error, send, setTyping, report]
  );

  return <ChatContext.Provider value={value}>{children}</ChatContext.Provider>;
}

export function useChat() {
  const ctx = useContext(ChatContext);
  if (!ctx) throw new Error('useChat must be inside ChatProvider');
  return ctx;
}