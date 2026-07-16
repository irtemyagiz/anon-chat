import { useNavigation } from '@react-navigation/native';
import { useCallback, useEffect, useState } from 'react';
import { ActivityIndicator, FlatList, Image, Pressable, RefreshControl, StyleSheet, Text, View } from 'react-native';
import { COLORS, RADIUS } from '../config';
import { api } from '../services/api';

export default function FriendsScreen() {
  const navigation = useNavigation();
  const [tab, setTab] = useState('friends');
  const [friends, setFriends] = useState([]);
  const [suggestions, setSuggestions] = useState([]);
  const [threshold, setThreshold] = useState(10);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);

  const load = useCallback(async () => {
    try {
      const [f, s] = await Promise.all([api.getFriends(), api.getFriendSuggestions().catch(() => ({ suggestions: [], threshold: 10 }))]);
      setFriends(f.friends || []);
      setSuggestions(s.suggestions || []);
      setThreshold(s.threshold || 10);
    } catch (err) {
      console.warn('[friends load]', err);
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, []);

  useEffect(() => {
    load();
  }, [load]);

  const renderFriend = ({ item }) => (
    <Pressable style={styles.row} onPress={() => navigation.navigate('UserProfile', { userId: item.id })}>
      {item.photoUrl ? (
        <Image source={{ uri: item.photoUrl }} style={styles.avatar} />
      ) : (
        <View style={[styles.avatar, styles.placeholder, { backgroundColor: item.avatarColor }]}>
          <Text style={styles.avatarLetter}>{(item.nickname || '?').slice(0, 1).toUpperCase()}</Text>
        </View>
      )}
      <View style={styles.rowInfo}>
        <Text style={styles.rowName}>{item.nickname}</Text>
        <Text style={styles.rowMeta}>{item.mutualChats} karşılıklı sohbet</Text>
      </View>
      <Text style={styles.chevron}>›</Text>
    </Pressable>
  );

  const renderSuggestion = ({ item }) => (
    <View style={styles.suggestion}>
      {item.photoUrl ? (
        <Image source={{ uri: item.photoUrl }} style={styles.suggestAvatar} />
      ) : (
        <View style={[styles.suggestAvatar, styles.placeholder, { backgroundColor: item.avatarColor }]}>
          <Text style={styles.avatarLetter}>{(item.nickname || '?').slice(0, 1).toUpperCase()}</Text>
        </View>
      )}
      <View style={{ flex: 1 }}>
        <Text style={styles.rowName}>{item.nickname}</Text>
        <Text style={styles.rowMeta}>{item.mutualChats} / {threshold} sohbet</Text>
      </View>
      <Pressable
        style={styles.addBtn}
        onPress={async () => {
          try {
            await api.addFriend(item.id);
            setSuggestions((p) => p.filter((s) => s.id !== item.id));
            load();
          } catch (err) {
            console.warn('[addFriend]', err);
          }
        }}
      >
        <Text style={styles.addText}>Ekle</Text>
      </Pressable>
    </View>
  );

  if (loading) {
    return (
      <View style={styles.center}>
        <ActivityIndicator size="large" color={COLORS.primary} />
      </View>
    );
  }

  return (
    <View style={styles.container}>
      <View style={styles.tabRow}>
        <Pressable style={[styles.tab, tab === 'friends' && styles.tabActive]} onPress={() => setTab('friends')}>
          <Text style={[styles.tabText, tab === 'friends' && styles.tabTextActive]}>Arkadaşlar ({friends.length})</Text>
        </Pressable>
        <Pressable style={[styles.tab, tab === 'suggestions' && styles.tabActive]} onPress={() => setTab('suggestions')}>
          <Text style={[styles.tabText, tab === 'suggestions' && styles.tabTextActive]}>Öneriler ({suggestions.length})</Text>
        </Pressable>
      </View>

      {tab === 'friends' ? (
        <FlatList
          data={friends}
          keyExtractor={(item) => item.id}
          renderItem={renderFriend}
          contentContainerStyle={styles.list}
          refreshControl={<RefreshControl refreshing={refreshing} onRefresh={() => { setRefreshing(true); load(); }} tintColor={COLORS.primary} />}
          ListEmptyComponent={
            <View style={styles.empty}>
              <Text style={styles.emptyEmoji}>👥</Text>
              <Text style={styles.emptyTitle}>Henüz arkadaşın yok</Text>
              <Text style={styles.emptySub}>{threshold} karşılıklı sohbetten sonra birini arkadaş olarak ekleyebilirsin.</Text>
            </View>
          }
        />
      ) : (
        <FlatList
          data={suggestions}
          keyExtractor={(item) => item.id}
          renderItem={renderSuggestion}
          contentContainerStyle={styles.list}
          refreshControl={<RefreshControl refreshing={refreshing} onRefresh={() => { setRefreshing(true); load(); }} tintColor={COLORS.primary} />}
          ListEmptyComponent={
            <View style={styles.empty}>
              <Text style={styles.emptyEmoji}>🌱</Text>
              <Text style={styles.emptyTitle}>Öneri yok</Text>
              <Text style={styles.emptySub}>{threshold} sohbete ulaşan kişiler burada görünür.</Text>
            </View>
          }
        />
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: COLORS.bg },
  center: { flex: 1, alignItems: 'center', justifyContent: 'center', backgroundColor: COLORS.bg },
  tabRow: { flexDirection: 'row', paddingHorizontal: 20, paddingTop: 12, gap: 8 },
  tab: { flex: 1, paddingVertical: 10, borderRadius: RADIUS.pill, backgroundColor: COLORS.surface, alignItems: 'center', borderWidth: 1, borderColor: COLORS.border },
  tabActive: { backgroundColor: COLORS.primary, borderColor: COLORS.primary },
  tabText: { color: COLORS.textSecondary, fontWeight: '700', fontSize: 13 },
  tabTextActive: { color: '#FFFFFF' },
  list: { padding: 20, gap: 10 },
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
  suggestion: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: COLORS.surface,
    padding: 12,
    borderRadius: RADIUS.md,
    borderWidth: 1,
    borderColor: COLORS.border,
    gap: 12,
  },
  avatar: { width: 48, height: 48, borderRadius: 24 },
  suggestAvatar: { width: 44, height: 44, borderRadius: 22 },
  placeholder: { alignItems: 'center', justifyContent: 'center' },
  avatarLetter: { color: '#FFFFFF', fontWeight: '800', fontSize: 18 },
  rowInfo: { flex: 1 },
  rowName: { color: COLORS.textPrimary, fontSize: 15, fontWeight: '700' },
  rowMeta: { color: COLORS.textMuted, fontSize: 12, marginTop: 2 },
  chevron: { color: COLORS.textMuted, fontSize: 22 },
  addBtn: { backgroundColor: COLORS.primary, paddingVertical: 8, paddingHorizontal: 14, borderRadius: RADIUS.pill },
  addText: { color: '#FFFFFF', fontWeight: '800', fontSize: 12 },
  empty: { alignItems: 'center', paddingVertical: 60 },
  emptyEmoji: { fontSize: 48, marginBottom: 12 },
  emptyTitle: { color: COLORS.textPrimary, fontSize: 16, fontWeight: '700', marginBottom: 6 },
  emptySub: { color: COLORS.textMuted, fontSize: 13, textAlign: 'center', lineHeight: 18, paddingHorizontal: 20 },
});
