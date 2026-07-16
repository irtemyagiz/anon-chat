import { useNavigation } from '@react-navigation/native';
import { useCallback, useEffect, useState } from 'react';
import {
  ActivityIndicator,
  Alert,
  FlatList,
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
import { useAuth } from '../store/AuthContext';

const TABS = [
  { id: 'following', label: 'Takip Ettiklerin' },
  { id: 'followers', label: 'Takipçiler' },
  { id: 'requests', label: 'İstekler' },
];

export default function FriendsScreen() {
  const navigation = useNavigation();
  const { user, refreshUser } = useAuth();
  const [tab, setTab] = useState('following');
  const [following, setFollowing] = useState([]);
  const [followers, setFollowers] = useState({ count: 0, blurred: false, items: [] });
  const [requests, setRequests] = useState({ count: 0, blurred: false, items: [] });
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [acting, setActing] = useState(false);

  const load = useCallback(async () => {
    try {
      const [f, fr, rq] = await Promise.all([
        api.getMyFollowing(),
        api.getMyFollowers(),
        api.getFollowRequests(),
      ]);
      setFollowing(f.following || []);
      setFollowers(fr);
      setRequests(rq);
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

  useEffect(() => {
    navigation.setOptions({
      tabBarBadge: requests.blurred ? undefined : (requests.count || undefined),
    });
  }, [navigation, requests]);

  const accept = async (id) => {
    if (acting) return;
    setActing(true);
    try {
      await api.acceptFollow(id);
      await load();
      await refreshUser();
    } catch (err) {
      Alert.alert('Hata', err.message);
    } finally {
      setActing(false);
    }
  };

  const reject = async (id) => {
    if (acting) return;
    setActing(true);
    try {
      await api.rejectFollow(id);
      await load();
    } catch (err) {
      Alert.alert('Hata', err.message);
    } finally {
      setActing(false);
    }
  };

  const unfollow = async (id) => {
    try {
      await api.unfollow(id);
      await load();
    } catch (err) {
      Alert.alert('Hata', err.message);
    }
  };

  if (loading) {
    return (
      <View style={styles.center}>
        <ActivityIndicator size="large" color={COLORS.primary} />
      </View>
    );
  }

  const renderFollowing = ({ item }) => (
    <View style={styles.row}>
      <Avatar seed={item.id} size={48} avatarStyle={item.avatarStyle} photoUrl={item.photoUrl} isPlus={!!item.isPlus} />
      <Pressable style={styles.rowInfo} onPress={() => navigation.navigate('UserProfile', { userId: item.id })}>
        <Text style={styles.rowName}>{item.nickname}</Text>
        <Text style={styles.rowMeta}>@{item.username || 'anonim'}</Text>
      </Pressable>
      <Pressable style={styles.unfollowBtn} onPress={() => unfollow(item.id)}>
        <Text style={styles.unfollowText}>Takipten Çık</Text>
      </Pressable>
    </View>
  );

  const renderFollowerItem = ({ item }) => (
    <Pressable style={styles.row} onPress={() => navigation.navigate('UserProfile', { userId: item.id })}>
      <Avatar seed={item.id} size={48} avatarStyle={item.avatarStyle} photoUrl={item.photoUrl} isPlus={!!item.isPlus} />
      <View style={styles.rowInfo}>
        <Text style={styles.rowName}>{item.nickname}</Text>
        <Text style={styles.rowMeta}>@{item.username || 'anonim'}</Text>
      </View>
    </Pressable>
  );

  const renderRequest = ({ item }) => (
    <View style={styles.row}>
      <Avatar seed={item.id} size={48} avatarStyle={item.avatarStyle} photoUrl={item.photoUrl} isPlus={!!item.isPlus} />
      <Pressable style={styles.rowInfo} onPress={() => navigation.navigate('UserProfile', { userId: item.id })}>
        <Text style={styles.rowName}>{item.nickname}</Text>
        <Text style={styles.rowMeta}>@{item.username || 'anonim'}</Text>
      </Pressable>
      <View style={styles.requestActions}>
        <Pressable
          style={[styles.reqBtn, styles.acceptBtn, acting && styles.disabled]}
          onPress={() => accept(item.id)}
          disabled={acting}
        >
          <Text style={styles.acceptText}>✓</Text>
        </Pressable>
        <Pressable
          style={[styles.reqBtn, styles.rejectBtn, acting && styles.disabled]}
          onPress={() => reject(item.id)}
          disabled={acting}
        >
          <Text style={styles.rejectText}>✕</Text>
        </Pressable>
      </View>
    </View>
  );

  const renderBlurred = ({ item, type }) => (
    <View style={[styles.row, styles.blurredRow]}>
      <View style={styles.blurredAvatar}>
        <Text style={styles.blurredAvatarText}>?</Text>
      </View>
      <View style={styles.rowInfo}>
        <Text style={styles.blurredName}>Gizli Kullanıcı</Text>
        <Text style={styles.blurredMeta}>
          {type === 'followers' ? 'Takipçi' : 'İstek'} — bilgileri görmek için Plus Ol
        </Text>
      </View>
      {type === 'requests' && (
        <View style={styles.requestActions}>
          <Pressable
            style={[styles.reqBtn, styles.acceptBtn, acting && styles.disabled]}
            onPress={() => accept(item.placeholderId)}
            disabled
          >
            <Text style={styles.acceptText}>✓</Text>
          </Pressable>
        </View>
      )}
    </View>
  );

  return (
    <SafeAreaView style={styles.safe} edges={['top', 'left', 'right']}>
      <View style={styles.header}>
        <Text style={styles.title}>Sosyal</Text>
      </View>
      <View style={styles.tabBar}>
        {TABS.map((t) => {
          const count = t.id === 'followers' ? followers.count : t.id === 'requests' ? requests.count : following.length;
          const active = tab === t.id;
          return (
            <Pressable
              key={t.id}
              style={[styles.tabBtn, active && styles.tabBtnActive]}
              onPress={() => setTab(t.id)}
            >
              <Text style={[styles.tabText, active && styles.tabTextActive]}>{t.label}</Text>
              {count > 0 && <Text style={[styles.tabCount, active && styles.tabCountActive]}>{count}</Text>}
            </Pressable>
          );
        })}
      </View>

      {tab === 'following' && (
        <FlatList
          data={following}
          keyExtractor={(item) => item.id}
          renderItem={renderFollowing}
          contentContainerStyle={styles.list}
          ItemSeparatorComponent={() => <View style={{ height: 8 }} />}
          refreshControl={<RefreshControl refreshing={refreshing} onRefresh={() => { setRefreshing(true); load(); }} tintColor={COLORS.primary} />}
          ListEmptyComponent={
            <View style={styles.empty}>
              <Text style={styles.emptyEmoji}>👥</Text>
              <Text style={styles.emptyTitle}>Henüz kimseyi takip etmiyorsun</Text>
              <Text style={styles.emptySub}>Keşfet sekmesinden insanları takip edebilirsin.</Text>
            </View>
          }
        />
      )}

      {tab === 'followers' && (
        followers.blurred ? (
          <View style={styles.paywall}>
            <Text style={styles.paywallEmoji}>🔒</Text>
            <Text style={styles.paywallTitle}>{followers.count} Takipçin Var</Text>
            <Text style={styles.paywallSub}>
              Takipçilerini görmek, istek kabul etmek ve daha fazlası için Plus'a geç.
            </Text>
            <Pressable style={styles.paywallBtn} onPress={() => navigation.navigate('Paywall')}>
              <Text style={styles.paywallBtnText}>Plus'a Geç</Text>
            </Pressable>
            <View style={{ marginTop: 20, width: '100%' }}>
              {Array.from({ length: Math.min(followers.count, 4) }).map((_, i) => (
                <View key={i} style={{ marginBottom: 8 }}>
                  {renderBlurred({ item: { placeholderId: i }, type: 'followers' })}
                </View>
              ))}
            </View>
          </View>
        ) : (
          <FlatList
            data={followers.followers || []}
            keyExtractor={(item) => item.id}
            renderItem={renderFollowerItem}
            contentContainerStyle={styles.list}
            ItemSeparatorComponent={() => <View style={{ height: 8 }} />}
            refreshControl={<RefreshControl refreshing={refreshing} onRefresh={() => { setRefreshing(true); load(); }} tintColor={COLORS.primary} />}
            ListEmptyComponent={
              <View style={styles.empty}>
                <Text style={styles.emptyEmoji}>👀</Text>
                <Text style={styles.emptyTitle}>Henüz takipçin yok</Text>
                <Text style={styles.emptySub}>İlgi çekici bir profil oluştur, takipçi kazan!</Text>
              </View>
            }
          />
        )
      )}

      {tab === 'requests' && (
        requests.blurred ? (
          <View style={styles.paywall}>
            <Text style={styles.paywallEmoji}>📩</Text>
            <Text style={styles.paywallTitle}>{requests.count} Takip İsteği</Text>
            <Text style={styles.paywallSub}>
              İstekleri kabul etmek, bilgilerini görmek ve profillere erişmek için Plus'a geç.
            </Text>
            <Pressable style={styles.paywallBtn} onPress={() => navigation.navigate('Paywall')}>
              <Text style={styles.paywallBtnText}>Plus'a Geç</Text>
            </Pressable>
            <View style={{ marginTop: 20, width: '100%' }}>
              {Array.from({ length: Math.min(requests.count, 3) }).map((_, i) => (
                <View key={i} style={{ marginBottom: 8 }}>
                  {renderBlurred({ item: { placeholderId: i }, type: 'requests' })}
                </View>
              ))}
            </View>
          </View>
        ) : (
          <FlatList
            data={requests.requests || []}
            keyExtractor={(item) => item.id}
            renderItem={renderRequest}
            contentContainerStyle={styles.list}
            ItemSeparatorComponent={() => <View style={{ height: 8 }} />}
            refreshControl={<RefreshControl refreshing={refreshing} onRefresh={() => { setRefreshing(true); load(); }} tintColor={COLORS.primary} />}
            ListEmptyComponent={
              <View style={styles.empty}>
                <Text style={styles.emptyEmoji}>📭</Text>
                <Text style={styles.emptyTitle}>İstek yok</Text>
                <Text style={styles.emptySub}>Yeni takip istekleri burada görünür.</Text>
              </View>
            }
          />
        )
      )}
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  safe: { flex: 1, backgroundColor: COLORS.bg },
  center: { flex: 1, alignItems: 'center', justifyContent: 'center', backgroundColor: COLORS.bg },
  header: { paddingHorizontal: 20, paddingTop: 8, paddingBottom: 8 },
  title: { color: COLORS.textPrimary, fontSize: 28, fontWeight: '800' },
  tabBar: { flexDirection: 'row', paddingHorizontal: 16, paddingVertical: 8, gap: 6 },
  tabBtn: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 6,
    paddingVertical: 10,
    borderRadius: RADIUS.pill,
    backgroundColor: COLORS.surface,
    borderWidth: 1,
    borderColor: COLORS.border,
  },
  tabBtnActive: { backgroundColor: COLORS.primary, borderColor: COLORS.primary },
  tabText: { color: COLORS.textSecondary, fontWeight: '700', fontSize: 12 },
  tabTextActive: { color: '#FFFFFF' },
  tabCount: { color: COLORS.textMuted, fontSize: 11, fontWeight: '700', backgroundColor: COLORS.surfaceAlt, paddingHorizontal: 6, paddingVertical: 2, borderRadius: 8, overflow: 'hidden' },
  tabCountActive: { color: COLORS.primary, backgroundColor: '#FFFFFF' },
  list: { padding: 16 },
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
  rowInfo: { flex: 1, minWidth: 0 },
  rowName: { color: COLORS.textPrimary, fontSize: 15, fontWeight: '700' },
  rowMeta: { color: COLORS.textMuted, fontSize: 12, marginTop: 2 },
  unfollowBtn: { backgroundColor: COLORS.surfaceAlt, paddingHorizontal: 12, paddingVertical: 8, borderRadius: RADIUS.sm, borderWidth: 1, borderColor: COLORS.border },
  unfollowText: { color: COLORS.textPrimary, fontSize: 12, fontWeight: '700' },
  requestActions: { flexDirection: 'row', gap: 6 },
  reqBtn: { width: 36, height: 36, borderRadius: 18, alignItems: 'center', justifyContent: 'center' },
  acceptBtn: { backgroundColor: COLORS.success },
  acceptText: { color: '#FFFFFF', fontSize: 16, fontWeight: '900' },
  rejectBtn: { backgroundColor: COLORS.danger },
  rejectText: { color: '#FFFFFF', fontSize: 14, fontWeight: '900' },
  disabled: { opacity: 0.5 },
  empty: { alignItems: 'center', paddingVertical: 60 },
  emptyEmoji: { fontSize: 48, marginBottom: 12 },
  emptyTitle: { color: COLORS.textPrimary, fontSize: 16, fontWeight: '700', marginBottom: 6 },
  emptySub: { color: COLORS.textMuted, fontSize: 13, textAlign: 'center', lineHeight: 18, paddingHorizontal: 20 },
  paywall: { padding: 24, alignItems: 'center' },
  paywallEmoji: { fontSize: 60, marginBottom: 12 },
  paywallTitle: { color: COLORS.textPrimary, fontSize: 20, fontWeight: '800', marginBottom: 8 },
  paywallSub: { color: COLORS.textMuted, fontSize: 14, textAlign: 'center', marginBottom: 16, lineHeight: 20, paddingHorizontal: 10 },
  paywallBtn: { backgroundColor: COLORS.primary, paddingHorizontal: 32, paddingVertical: 14, borderRadius: RADIUS.pill },
  paywallBtnText: { color: '#FFFFFF', fontWeight: '800', fontSize: 15 },
  blurredRow: { opacity: 0.85 },
  blurredAvatar: { width: 48, height: 48, borderRadius: 24, backgroundColor: COLORS.surfaceAlt, alignItems: 'center', justifyContent: 'center', borderWidth: 1, borderColor: COLORS.border },
  blurredAvatarText: { color: COLORS.textMuted, fontSize: 22, fontWeight: '800' },
  blurredName: { color: COLORS.textMuted, fontSize: 15, fontWeight: '700' },
  blurredMeta: { color: COLORS.textDim, fontSize: 11, marginTop: 2 },
});