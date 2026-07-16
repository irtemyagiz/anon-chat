import { useNavigation } from '@react-navigation/native';
import { useEffect, useRef, useState } from 'react';
import {
  FlatList,
  KeyboardAvoidingView,
  Platform,
  Pressable,
  StyleSheet,
  Text,
  TextInput,
  View,
} from 'react-native';
import { COLORS, MESSAGE_MAX_LENGTH, RADIUS } from '../config';
import { ChatProvider, useChat } from '../store/ChatContext';
import { MatchProvider, useMatch } from '../store/MatchContext';
import { useAuth } from '../store/AuthContext';

function MessageBubble({ item, isMine }) {
  return (
    <View style={[styles.bubbleRow, isMine ? styles.bubbleRowMine : styles.bubbleRowPeer]}>
      <View style={[styles.bubble, isMine ? styles.bubbleMine : styles.bubblePeer]}>
        <Text style={[styles.bubbleText, isMine ? styles.bubbleTextMine : styles.bubbleTextPeer]}>
          {item.content}
        </Text>
        {item.pending ? <Text style={styles.pendingText}>...</Text> : null}
      </View>
    </View>
  );
}

function ChatInner() {
  const navigation = useNavigation();
  const { user } = useAuth();
  const { peer, roomId, leave, next } = useMatch();
  const { messages, peerTyping, send, setTyping, report } = useChat();
  const [text, setText] = useState('');
  const listRef = useRef(null);
  const typingTimer = useRef(null);

  useEffect(() => {
    const t = setTimeout(() => listRef.current?.scrollToEnd({ animated: true }), 100);
    return () => clearTimeout(t);
  }, [messages.length]);

  const onChange = (v) => {
    setText(v);
    setTyping(true);
    if (typingTimer.current) clearTimeout(typingTimer.current);
    typingTimer.current = setTimeout(() => setTyping(false), 1200);
  };

  const submit = () => {
    const t = text.trim();
    if (!t) return;
    send(t);
    setText('');
    setTyping(false);
  };

  const handleLeave = () => {
    leave();
    navigation.replace('PostChat', { reason: 'left', peer });
  };

  const handleReport = () => {
    report('inappropriate');
    navigation.replace('PostChat', { reason: 'reported', peer });
  };

  return (
    <KeyboardAvoidingView
      style={styles.container}
      behavior={Platform.OS === 'ios' ? 'padding' : undefined}
      keyboardVerticalOffset={Platform.OS === 'ios' ? 0 : 0}
    >
      <View style={styles.header}>
        <Pressable onPress={handleLeave} style={styles.headerBtn}>
          <Text style={styles.headerBtnText}>←</Text>
        </Pressable>
        <View style={styles.headerCenter}>
          <View style={[styles.peerAvatar, { backgroundColor: peer?.avatarColor || COLORS.primary }]}>
            <Text style={styles.peerAvatarText}>
              {(peer?.nickname || '?').slice(0, 1).toUpperCase()}
            </Text>
          </View>
          <View>
            <Text style={styles.peerName}>{peer?.nickname || 'Anonim'}</Text>
            <Text style={styles.peerStatus}>
              {peerTyping ? 'yazıyor...' : 'çevrimiçi'}
            </Text>
          </View>
        </View>
        <Pressable onPress={handleReport} style={styles.headerBtn}>
          <Text style={styles.headerBtnText}>🚩</Text>
        </Pressable>
      </View>

      <FlatList
        ref={listRef}
        data={messages}
        keyExtractor={(m, i) => m.id || `i-${i}`}
        renderItem={({ item }) => (
          <MessageBubble item={item} isMine={!item.fromPeer} />
        )}
        contentContainerStyle={styles.listContent}
        onContentSizeChange={() => listRef.current?.scrollToEnd({ animated: false })}
      />

      <View style={styles.inputRow}>
        <TextInput
          style={styles.input}
          value={text}
          onChangeText={onChange}
          placeholder="Mesaj yaz..."
          placeholderTextColor={COLORS.textDim}
          multiline
          maxLength={MESSAGE_MAX_LENGTH}
        />
        <Pressable
          style={({ pressed }) => [styles.sendBtn, pressed && styles.sendBtnPressed, !text.trim() && styles.sendBtnDisabled]}
          onPress={submit}
          disabled={!text.trim()}
        >
          <Text style={styles.sendText}>➤</Text>
        </Pressable>
      </View>
    </KeyboardAvoidingView>
  );
}

export default function ChatScreen() {
  return (
    <MatchProvider>
      <ChatProvider>
        <ChatInner />
      </ChatProvider>
    </MatchProvider>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: COLORS.bg },
  header: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: 12,
    paddingVertical: 12,
    backgroundColor: COLORS.surface,
    borderBottomWidth: 1,
    borderBottomColor: COLORS.border,
  },
  headerBtn: { width: 44, alignItems: 'center' },
  headerBtnText: { color: COLORS.textPrimary, fontSize: 22 },
  headerCenter: { flexDirection: 'row', alignItems: 'center', gap: 10 },
  peerAvatar: {
    width: 36,
    height: 36,
    borderRadius: 18,
    alignItems: 'center',
    justifyContent: 'center',
  },
  peerAvatarText: { color: '#FFFFFF', fontWeight: '800' },
  peerName: { color: COLORS.textPrimary, fontSize: 15, fontWeight: '700' },
  peerStatus: { color: COLORS.textMuted, fontSize: 11 },
  listContent: { padding: 16, paddingBottom: 8 },
  bubbleRow: { marginBottom: 6, flexDirection: 'row' },
  bubbleRowMine: { justifyContent: 'flex-end' },
  bubbleRowPeer: { justifyContent: 'flex-start' },
  bubble: { maxWidth: '78%', paddingHorizontal: 14, paddingVertical: 10, borderRadius: RADIUS.lg },
  bubbleMine: { backgroundColor: COLORS.primary, borderBottomRightRadius: 4 },
  bubblePeer: { backgroundColor: COLORS.surface, borderBottomLeftRadius: 4, borderWidth: 1, borderColor: COLORS.border },
  bubbleText: { fontSize: 15, lineHeight: 20 },
  bubbleTextMine: { color: '#FFFFFF' },
  bubbleTextPeer: { color: COLORS.textPrimary },
  pendingText: { color: 'rgba(255,255,255,0.6)', fontSize: 10, marginTop: 2 },
  inputRow: {
    flexDirection: 'row',
    alignItems: 'flex-end',
    padding: 12,
    backgroundColor: COLORS.surface,
    borderTopWidth: 1,
    borderTopColor: COLORS.border,
    gap: 8,
  },
  input: {
    flex: 1,
    minHeight: 42,
    maxHeight: 120,
    backgroundColor: COLORS.surfaceAlt,
    color: COLORS.textPrimary,
    paddingHorizontal: 14,
    paddingVertical: 10,
    borderRadius: 22,
    fontSize: 15,
  },
  sendBtn: {
    width: 44,
    height: 44,
    borderRadius: 22,
    backgroundColor: COLORS.primary,
    alignItems: 'center',
    justifyContent: 'center',
  },
  sendBtnPressed: { backgroundColor: COLORS.primaryPressed, transform: [{ scale: 0.95 }] },
  sendBtnDisabled: { backgroundColor: COLORS.surfaceAlt, opacity: 0.6 },
  sendText: { color: '#FFFFFF', fontSize: 18, fontWeight: '700' },
});
