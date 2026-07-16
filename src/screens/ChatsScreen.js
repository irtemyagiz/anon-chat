import { useNavigation } from '@react-navigation/native';
import { useCallback, useEffect, useRef, useState } from 'react';
import {
  ActivityIndicator,
  Alert,
  Animated,
  FlatList,
  PanResponder,
  Pressable,
  RefreshControl,
  StyleSheet,
  Text,
  View,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import Avatar from '../components/Avatar';
import { COLORS, RADIUS } from '../config';
import { api } from '../services/api';

const SWIPE_THRESHOLD = 80;

function ChatRow({ item, onPress, onPin, onDelete, isPinned, canPin }) {
  const translateX = useRef(new Animated.Value(0)).current;
  const [revealed, setRevealed] = useState(null);

  const panResponder = useRef(
    PanResponder.create({
      onMoveShouldSetPanResponder: (_, g) =>
        Math.abs(g.dx) > 8 && Math.abs(g.dx) > Math.abs(g.dy) * 1.5,
      onPanResponderMove: (_, g) => {
        if (g.dx < 0) {
          translateX.setValue(Math.max(g.dx, -160));
          setRevealed('actions');
        } else if (g.dx > 0 && revealed === 'actions') {
          translateX.setValue(Math.min(g.dx - 160, 0));
        }
      },
      onPanResponderRelease: (_, g) => {
        if (g.dx < -SWIPE_THRESHOLD) {
          Animated.spring(translateX, {
            toValue: -160,
            useNativeDriver: true,
          }).start();
        } else {
          Animated.spring(translateX, {
            toValue: 0,
            useNativeDriver: true,
          }).start(() => setRevealed(null));
        }
      },
    })
  ).current;

  const closeActions = () => {
    Animated.spring(translateX, { toValue: 0, useNativeDriver: true }).start(() =>
      setRevealed(null)
    );
  };

  const handlePress = () => {
    if (revealed === 'actions') {
      closeActions();
      return;
    }
    onPress();
  };

  const last = item.lastMessage;
  const preview = last
    ? (last.senderId === item.peer.id ? '' : 'Sen: ') + last.content
    : 'Henüz mesaj yok';

  return (
    <View style={styles.rowWrap}>
      <View style={styles.actionRow}>
        <Pressable
          style={[styles.actionButton, styles.pinButton, !canPin && !isPinned && styles.actionDisabled]}
          onPress={() => {
            closeActions();
            if (isPinned) onDelete.action = null;
            else if (canPin) onPin.pin();
            else
              Alert.alert('Limit doldu', 'En fazla 3 sohbet sabitleyebilirsin. Birini sabitlemeden önce diğerini çıkar.');
          }}
        >
          <Text style={styles.actionEmoji}>{isPinned ? '📌' : '📍'}</Text>
          <Text style={styles.actionLabel}>{isPinned ? 'Çıkar' : 'Sabitle'}</Text>
        </Pressable>
        <Pressable
          style={[styles.actionButton, styles.deleteButton]}
          onPress={() => {
            closeActions();
            onDelete.delete();
          }}
        >
          <Text style={styles.actionEmoji}>🗑️</Text>
          <Text style={styles.actionLabel}>Sil</Text>
        </Pressable>
      </View>

      <Animated.View
        style={[styles.rowFront, { transform: [{ translateX }] }]}
        {...panResponder.panHandlers}
      >
        <Pressable style={styles.row} onPress={handlePress}>
          {item.unreadCount > 0 && (
            <View style={styles.unreadDot}>
              <Text style={styles.unreadText}>
                {item.unreadCount > 9 ? '9+' : item.unreadCount}
              </Text>
            </View>
          )}
          <Avatar
            seed={item.peer.id || item.peer.avatarSeed}
            size={50}
            avatarStyle={item.peer.avatarStyle}
            photoUrl={item.peer.photoUrl}
            isPlus={!!item.peer.isPlus}
          />
          <View style={styles.rowInfo}>
            <View style={styles.rowNameRow}>
              <Text style={styles.rowName} numberOfLines={1}>
                {item.peer.nickname}
              </Text>
              {isPinned && <Text style={styles.pinIcon}>📌</Text>}
              {last?.createdAt && (
                <Text style={styles.rowTime}>
                  {formatTime(last.createdAt)}
                </Text>
              )}
            </View>
            <Text
              style={[styles.rowPreview, item.unreadCount > 0 && styles.rowPreviewUnread]}
              numberOfLines={1}
            >
              {preview}
            </Text>
          </View>
        </Pressable>
      </Animated.View>
    </View>
  );
}

function formatTime(dateStr) {
  const d = new Date(dateStr);
  const now = new Date();
  const diffMs = now - d;
  const diffMin = Math.floor(diffMs / 60000);
  const diffHr = Math.floor(diffMs / 3600000);
  const diffDay = Math.floor(diffMs / 86400000);
  if (diffMin < 1) return 'şimdi';
  if (diffMin < 60) return `${diffMin}dk`;
  if (diffHr < 24) return `${diffHr}sa`;
  if (diffDay < 7) return `${diffDay}g`;
  return d.toLocaleDateString('tr-TR', { day: '2-digit', month: '2-digit' });
}

export default function ChatsScreen() {
  const navigation = useNavigation();
  const [chats, setChats] = useState([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);

  const load = useCallback(async () => {
    try {
      const res = await api.getChats();
      setChats(res.chats || []);
    } catch (err) {
      console.warn('[chats load]', err);
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, []);

  useEffect(() => {
    const unsub = navigation.addListener('focus', load);
    load();
    return unsub;
  }, [navigation, load]);

  const pinnedCount = chats.filter((c) => c.isPinned).length;

  const openChat = (item) => {
    navigation.navigate('Chat', {
      targetUserId: item.peer.id,
      peer: item.peer,
      roomId: item.roomId,
    });
  };

  const pinChat = async (peerId) => {
    try {
      await api.pinChat(peerId);
      await load();
    } catch (err) {
      Alert.alert('Hata', err.message || 'Sabitlenemedi');
    }
  };

  const unpinChat = async (peerId) => {
    try {
      await api.unpinChat(peerId);
      await load();
    } catch (err) {
      Alert.alert('Hata', err.message);
    }
  };

  const deleteChat = (peerId, nickname) => {
    Alert.alert(
      'Sohbeti Sil',
      `${nickname} ile olan sohbet listeden kaldırılacak.`,
      [
        { text: 'Vazgeç', style: 'cancel' },
        {
          text: 'Sil',
          style: 'destructive',
          onPress: async () => {
            try {
              await api.deleteChat(peerId);
              await load();
            } catch (err) {
              Alert.alert('Hata', err.message);
            }
          },
        },
      ]
    );
  };

  const renderItem = ({ item }) => (
    <ChatRow
      item={item}
      onPress={() => openChat(item)}
      onPin={{
        pin: () => (item.isPinned ? unpinChat(item.peer.id) : pinChat(item.peer.id)),
      }}
      onDelete={{ delete: () => deleteChat(item.peer.id, item.peer.nickname) }}
      isPinned={item.isPinned}
      canPin={pinnedCount < 3 || item.isPinned}
    />
  );

  if (loading) {
    return (
      <View style={styles.center}>
        <ActivityIndicator size="large" color={COLORS.primary} />
      </View>
    );
  }

  return (
    <SafeAreaView style={styles.safe} edges={['top', 'left', 'right']}>
      <View style={styles.header}>
        <Text style={styles.title}>Mesajlar</Text>
        {pinnedCount > 0 && (
          <Text style={styles.pinCount}>📌 {pinnedCount}/3 sabit</Text>
        )}
      </View>

      {chats.length === 0 ? (
        <View style={styles.empty}>
          <Text style={styles.emptyEmoji}>💬</Text>
          <Text style={styles.emptyTitle}>Henüz sohbet yok</Text>
          <Text style={styles.emptySub}>
            Keşfet sekmesinden biriyle eşleşince sohbetler burada görünür.
          </Text>
        </View>
      ) : (
        <FlatList
          data={chats}
          keyExtractor={(item) => item.roomId}
          renderItem={renderItem}
          contentContainerStyle={styles.list}
          ItemSeparatorComponent={() => <View style={{ height: 8 }} />}
          refreshControl={
            <RefreshControl
              refreshing={refreshing}
              onRefresh={() => {
                setRefreshing(true);
                load();
              }}
              tintColor={COLORS.primary}
            />
          }
        />
      )}
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  safe: { flex: 1, backgroundColor: COLORS.bg },
  center: { flex: 1, alignItems: 'center', justifyContent: 'center', backgroundColor: COLORS.bg },
  header: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: 20,
    paddingTop: 8,
    paddingBottom: 12,
  },
  title: { color: COLORS.textPrimary, fontSize: 28, fontWeight: '800' },
  pinCount: { color: COLORS.textMuted, fontSize: 12, fontWeight: '700' },
  list: { paddingHorizontal: 16, paddingBottom: 24 },
  rowWrap: {
    position: 'relative',
    borderRadius: RADIUS.md,
    overflow: 'hidden',
  },
  actionRow: {
    position: 'absolute',
    right: 0,
    top: 0,
    bottom: 0,
    width: 160,
    flexDirection: 'row',
  },
  actionButton: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    gap: 4,
  },
  pinButton: { backgroundColor: COLORS.primary },
  deleteButton: { backgroundColor: COLORS.danger },
  actionDisabled: { opacity: 0.4 },
  actionEmoji: { fontSize: 20 },
  actionLabel: { color: '#FFFFFF', fontSize: 11, fontWeight: '800' },
  rowFront: { backgroundColor: COLORS.bg },
  row: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: COLORS.surface,
    padding: 12,
    borderRadius: RADIUS.md,
    borderWidth: 1,
    borderColor: COLORS.border,
    gap: 12,
  },
  unreadDot: {
    position: 'absolute',
    top: 4,
    left: 4,
    minWidth: 20,
    height: 20,
    borderRadius: 10,
    backgroundColor: COLORS.danger,
    alignItems: 'center',
    justifyContent: 'center',
    paddingHorizontal: 6,
    zIndex: 2,
  },
  unreadText: { color: '#FFFFFF', fontSize: 10, fontWeight: '900' },
  rowInfo: { flex: 1, minWidth: 0 },
  rowNameRow: { flexDirection: 'row', alignItems: 'center', gap: 6 },
  rowName: { color: COLORS.textPrimary, fontSize: 15, fontWeight: '700', flexShrink: 1 },
  pinIcon: { fontSize: 12 },
  rowTime: { color: COLORS.textMuted, fontSize: 11, marginLeft: 'auto', fontWeight: '600' },
  rowPreview: { color: COLORS.textMuted, fontSize: 13, marginTop: 2 },
  rowPreviewUnread: { color: COLORS.textPrimary, fontWeight: '700' },
  empty: { flex: 1, alignItems: 'center', justifyContent: 'center', padding: 24 },
  emptyEmoji: { fontSize: 60, marginBottom: 12 },
  emptyTitle: { color: COLORS.textPrimary, fontSize: 18, fontWeight: '800', marginBottom: 8 },
  emptySub: { color: COLORS.textMuted, fontSize: 13, textAlign: 'center', lineHeight: 18 },
});