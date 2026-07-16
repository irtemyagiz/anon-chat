import { useNavigation, useRoute } from '@react-navigation/native';
import { useEffect, useRef, useState } from 'react';
import {
  Animated,
  FlatList,
  KeyboardAvoidingView,
  Modal,
  Platform,
  Pressable,
  StyleSheet,
  Text,
  TextInput,
  View,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import Avatar from '../components/Avatar';
import { COLORS, MESSAGE_MAX_LENGTH, RADIUS } from '../config';
import { ChatProvider, useChat } from '../store/ChatContext';
import { MatchProvider, useMatch } from '../store/MatchContext';
import { useAuth } from '../store/AuthContext';
import { getSocket } from '../services/socket';

function MessageBubble({ item, isMine }) {
  return (
    <View style={[styles.bubbleRow, isMine ? styles.bubbleRowMine : styles.bubbleRowPeer]}>
      <View style={[styles.bubble, isMine ? styles.bubbleMine : styles.bubblePeer]}>
        <Text style={[styles.bubbleText, isMine ? styles.bubbleTextMine : styles.bubbleTextPeer]}>
          {item.content}
        </Text>
        {item.pending ? <Text style={styles.pendingText}>...</Text> : null}
</View>
      </KeyboardAvoidingView>
    </SafeAreaView>
  );
}

function ChatInner() {
  const navigation = useNavigation();
  const route = useRoute();
  const { user } = useAuth();
  const matchContext = useMatch();
  const peerFromMatch = matchContext.peer;
  const roomFromMatch = matchContext.roomId;
  const directPeer = route.params?.peer;
  const directRoom = route.params?.roomId;

  const peer = directPeer || peerFromMatch;
  const roomId = directRoom || roomFromMatch;
  const isDirect = !!directRoom;

  const { messages, peerTyping, send, setTyping, report } = useChat();
  const [text, setText] = useState('');
  const [revealed, setRevealed] = useState(false);
  const [revealedPeer, setRevealedPeer] = useState(null);
  const [showRevealModal, setShowRevealModal] = useState(false);
  const [showMenu, setShowMenu] = useState(false);
  const listRef = useRef(null);
  const typingTimer = useRef(null);
  const [joinedRoom, setJoinedRoom] = useState(false);

  useEffect(() => {
    if (!roomId) return;
    if (joinedRoom) return;
    const socket = getSocket();
    if (!socket) return;

    socket.emit('chat:join', { roomId });
    setJoinedRoom(true);

    const onMessage = (msg) => {
      // messages handled via ChatContext
    };
    const onRevealed = (payload) => {
      setRevealed(true);
      setRevealedPeer(payload.user);
    };
    socket.on('chat:message', onMessage);
    socket.on('chat:revealed', onRevealed);
    return () => {
      socket.off('chat:message', onMessage);
      socket.off('chat:revealed', onRevealed);
    };
  }, [roomId, joinedRoom]);

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
    if (isDirect) {
      navigation.goBack();
      return;
    }
    matchContext.leave();
    navigation.replace('PostChat', { reason: 'left', peer });
  };

  const handleReport = () => {
    setShowMenu(false);
    report('inappropriate');
    navigation.replace('PostChat', { reason: 'reported', peer });
  };

  const toggleReveal = () => {
    const socket = getSocket();
    if (!socket || !roomId) return;
    const next = !revealed;
    socket.emit('chat:reveal', { reveal: next });
    setRevealed(next);
    setShowRevealModal(false);
  };

  const showRealName = revealed || !user?.anonymityEnabled;
  const displayPeer = revealedPeer
    ? { ...peer, ...revealedPeer }
    : peer;

  return (
    <SafeAreaView style={styles.safe} edges={['top', 'left', 'right']}>
      <KeyboardAvoidingView
        style={styles.container}
        behavior={Platform.OS === 'ios' ? 'padding' : undefined}
      >
        <View style={styles.header}>
        <Pressable onPress={handleLeave} style={styles.headerBtn}>
          <Text style={styles.headerBtnText}>←</Text>
        </Pressable>
        <View style={styles.headerCenter}>
          <Avatar
            seed={displayPeer?.id || displayPeer?.avatarSeed || displayPeer?.nickname || 'peer'}
            size={36}
            avatarStyle={displayPeer?.avatarStyle}
            photoUrl={revealed && displayPeer?.photoUrl ? displayPeer.photoUrl : null}
            isPlus={!!displayPeer?.isPlus}
          />
          <View>
            <Text style={styles.peerName}>
              {showRealName ? displayPeer?.nickname : 'Anonim Kullanıcı'}
              {displayPeer?.isPlus ? ' ⚡' : ''}
            </Text>
            <Text style={styles.peerStatus}>
              {peerTyping ? 'yazıyor...' : (showRealName && displayPeer?.username ? `@${displayPeer.username}` : 'anonim')}
              {revealed && showRealName ? ' · ✓ Kimlik açık' : ''}
            </Text>
          </View>
        </View>
        <View style={styles.headerRight}>
          <Pressable onPress={() => setShowRevealModal(true)} style={styles.headerBtn}>
            <Text style={styles.headerBtnText}>{revealed ? '🔓' : '🔒'}</Text>
          </Pressable>
          {!isDirect && (
            <Pressable onPress={() => setShowMenu(true)} style={styles.headerBtn}>
              <Text style={styles.headerBtnText}>⋮</Text>
            </Pressable>
          )}
        </View>
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

      <Modal visible={showRevealModal} transparent animationType="fade" onRequestClose={() => setShowRevealModal(false)}>
        <Pressable style={styles.modalBackdrop} onPress={() => setShowRevealModal(false)}>
          <Pressable style={styles.modalSheet} onPress={(e) => e.stopPropagation()}>
            <Text style={styles.modalTitle}>🔓 Kimlik Göster</Text>
            <Text style={styles.modalSub}>
              {revealed
                ? 'Şu anda kimliğin bu sohbette karşındakine görünür.'
                : 'Bu sohbette rumuzun yerine "Anonim" görünüyor. Karşındakine gerçek kimliğini göstermek istersen:'}
            </Text>
            <Pressable style={[styles.modalBtn, styles.modalBtnPrimary]} onPress={toggleReveal}>
              <Text style={styles.modalBtnText}>{revealed ? '🔒 Gizle' : '🔓 Kimliğimi Göster'}</Text>
            </Pressable>
            <Pressable style={styles.modalBtnClose} onPress={() => setShowRevealModal(false)}>
              <Text style={styles.modalBtnCloseText}>İptal</Text>
            </Pressable>
          </Pressable>
        </Pressable>
      </Modal>

      <Modal visible={showMenu} transparent animationType="fade" onRequestClose={() => setShowMenu(false)}>
        <Pressable style={styles.modalBackdrop} onPress={() => setShowMenu(false)}>
          <Pressable style={styles.modalSheet} onPress={(e) => e.stopPropagation()}>
            <Text style={styles.modalTitle}>Sohbet Menüsü</Text>
            <Pressable style={[styles.modalBtn, styles.modalBtnSecondary]} onPress={handleReport}>
              <Text style={styles.modalBtnTextDark}>🚩 Bildir</Text>
            </Pressable>
            <Pressable style={[styles.modalBtn, styles.modalBtnSecondary]} onPress={handleLeave}>
              <Text style={styles.modalBtnTextDark}>👋 Sohbetten Ayrıl</Text>
            </Pressable>
            <Pressable style={styles.modalBtnClose} onPress={() => setShowMenu(false)}>
              <Text style={styles.modalBtnCloseText}>İptal</Text>
            </Pressable>
          </Pressable>
        </Pressable>
      </Modal>
    </KeyboardAvoidingView>
  );
}

export default function ChatScreen({ route }) {
  const initialRoomId = route?.params?.roomId;
  const initialPeer = route?.params?.peer;
  return (
    <MatchProvider initialRoomId={initialRoomId} initialPeer={initialPeer}>
      <ChatProvider roomId={initialRoomId}>
        <ChatInner />
      </ChatProvider>
    </MatchProvider>
  );
}

const styles = StyleSheet.create({
  safe: { flex: 1, backgroundColor: COLORS.bg },
  container: { flex: 1, backgroundColor: COLORS.bg },
  header: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: 8,
    paddingVertical: 10,
    backgroundColor: COLORS.surface,
    borderBottomWidth: 1,
    borderBottomColor: COLORS.border,
  },
  headerBtn: { width: 40, height: 40, alignItems: 'center', justifyContent: 'center' },
  headerBtnText: { color: COLORS.textPrimary, fontSize: 20 },
  headerRight: { flexDirection: 'row' },
  headerCenter: { flexDirection: 'row', alignItems: 'center', gap: 10 },
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
  sendBtn: { width: 44, height: 44, borderRadius: 22, backgroundColor: COLORS.primary, alignItems: 'center', justifyContent: 'center' },
  sendBtnPressed: { backgroundColor: COLORS.primaryPressed, transform: [{ scale: 0.95 }] },
  sendBtnDisabled: { backgroundColor: COLORS.surfaceAlt, opacity: 0.6 },
  sendText: { color: '#FFFFFF', fontSize: 18, fontWeight: '700' },
  modalBackdrop: { flex: 1, backgroundColor: 'rgba(0,0,0,0.65)', justifyContent: 'flex-end' },
  modalSheet: {
    backgroundColor: COLORS.surface,
    borderTopLeftRadius: 24,
    borderTopRightRadius: 24,
    padding: 24,
    paddingBottom: 40,
    borderTopWidth: 1,
    borderTopColor: COLORS.border,
  },
  modalTitle: { color: COLORS.textPrimary, fontSize: 20, fontWeight: '800', marginBottom: 8, textAlign: 'center' },
  modalSub: { color: COLORS.textMuted, fontSize: 14, textAlign: 'center', marginBottom: 20, lineHeight: 20 },
  modalBtn: { paddingVertical: 16, borderRadius: RADIUS.md, alignItems: 'center', marginTop: 10 },
  modalBtnPrimary: { backgroundColor: COLORS.primary },
  modalBtnSecondary: { backgroundColor: COLORS.surfaceAlt, borderWidth: 1, borderColor: COLORS.border },
  modalBtnText: { color: '#FFFFFF', fontWeight: '700', fontSize: 15 },
  modalBtnTextDark: { color: COLORS.textPrimary, fontWeight: '700', fontSize: 15 },
  modalBtnClose: { paddingVertical: 12, alignItems: 'center', marginTop: 6 },
  modalBtnCloseText: { color: COLORS.textMuted, fontWeight: '700' },
});