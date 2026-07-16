import { useNavigation, useRoute } from '@react-navigation/native';
import { useCallback, useEffect, useState } from 'react';
import {
  ActivityIndicator,
  Alert,
  FlatList,
  Pressable,
  RefreshControl,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  View,
} from 'react-native';
import Avatar from '../components/Avatar';
import { COLORS, RADIUS } from '../config';
import { api, setAuthToken } from '../services/api';

function StatCard({ label, value, color = COLORS.primary }) {
  return (
    <View style={styles.statCard}>
      <Text style={[styles.statValue, { color }]}>{value}</Text>
      <Text style={styles.statLabel}>{label}</Text>
    </View>
  );
}

export default function AdminDashboardScreen() {
  const navigation = useNavigation();
  const route = useRoute();
  const token = route.params?.token;

  const [stats, setStats] = useState(null);
  const [users, setUsers] = useState([]);
  const [search, setSearch] = useState('');
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);

  const load = useCallback(async (q = '') => {
    if (!token) return;
    setAuthToken(token);
    try {
      const [s, u] = await Promise.all([api.adminStats(), api.adminUsers({ q, limit: '100' })]);
      setStats(s);
      setUsers(u.users || []);
    } catch (err) {
      Alert.alert('Hata', err.message || 'Yüklenemedi');
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, [token]);

  useEffect(() => {
    load();
  }, [load]);

  useEffect(() => {
    const t = setTimeout(() => load(search), 300);
    return () => clearTimeout(t);
  }, [search]);

  const action = async (id, op) => {
    try {
      const res = await api.adminGetUser(id);
      const u = res.user;
      if (op === 'plus') {
        await api.adminPatchUser(id, { isPlus: true, plusDays: 30 });
      } else if (op === 'unplus') {
        await api.adminPatchUser(id, { isPlus: false });
      } else if (op === 'ban') {
        await api.adminPatchUser(id, { isBanned: !u.isBanned });
      } else if (op === 'delete') {
        await new Promise((resolve, reject) =>
          Alert.alert('Kullanıcıyı Sil', `${u.nickname} silinecek. Emin misin?`, [
            { text: 'Vazgeç', style: 'cancel', onPress: resolve },
            {
              text: 'Sil',
              style: 'destructive',
              onPress: async () => {
                try {
                  await api.adminDeleteUser(id);
                  load(search);
                  resolve();
                } catch (e) {
                  Alert.alert('Hata', e.message);
                  reject(e);
                }
              },
            },
          ])
        );
        return;
      }
      load(search);
    } catch (err) {
      Alert.alert('Hata', err.message);
    }
  };

  const renderUser = ({ item }) => (
    <View style={styles.userRow}>
      <Avatar
        seed={item.id}
        size={44}
        avatarStyle={item.avatarStyle}
        photoUrl={item.photoUrl}
        isPlus={!!item.isPlus}
      />
      <View style={styles.userInfo}>
        <Text style={styles.userName}>{item.nickname} {item.isPlus ? '⚡' : ''}</Text>
        <Text style={styles.userMeta}>@{item.username || '?'} · {item.email || '—'}</Text>
        <Text style={styles.userStats}>
          {item.totalChats} sohbet · {item.isBanned ? '🚫 BANLI' : '✓ aktif'}
        </Text>
      </View>
      <View style={styles.userActions}>
        <Pressable
          style={[styles.actionBtn, item.isPlus ? styles.unplusBtn : styles.plusBtn]}
          onPress={() => action(item.id, item.isPlus ? 'unplus' : 'plus')}
        >
          <Text style={styles.actionBtnText}>{item.isPlus ? 'Plus Çıkar' : 'Plus'}</Text>
        </Pressable>
        <Pressable
          style={[styles.actionBtn, styles.banBtn]}
          onPress={() => action(item.id, 'ban')}
        >
          <Text style={styles.actionBtnText}>{item.isBanned ? 'Aç' : 'Banla'}</Text>
        </Pressable>
      </View>
    </View>
  );

  if (loading) {
    return (
      <View style={styles.center}>
        <ActivityIndicator size="large" color={COLORS.danger} />
      </View>
    );
  }

  return (
    <View style={styles.container}>
      <ScrollView
        refreshControl={<RefreshControl refreshing={refreshing} onRefresh={() => { setRefreshing(true); load(search); }} tintColor={COLORS.primary} />}
      >
        <View style={styles.header}>
          <Text style={styles.headerTitle}>🔒 Admin Dashboard</Text>
          <Pressable onPress={() => navigation.popToTop()}>
            <Text style={styles.exitText}>Çıkış</Text>
          </Pressable>
        </View>

        {stats && (
          <View style={styles.statsGrid}>
            <StatCard label="Toplam" value={stats.totalUsers} />
            <StatCard label="Çevrimiçi" value={stats.onlineUsers} color={COLORS.success} />
            <StatCard label="Plus" value={stats.plusUsers} color="#FFD700" />
            <StatCard label="Yeni (24s)" value={stats.newToday} color={COLORS.success} />
            <StatCard label="Mesajlar" value={stats.totalMessages} />
            <StatCard label="Sohbetler" value={stats.totalRooms} />
            <StatCard label="Takip" value={stats.totalFollows} />
            <StatCard label="Arkadaşlık" value={stats.totalFriendships} />
            <StatCard label="Rapor (24s)" value={stats.reportsLast24h} color={COLORS.warning} />
            <StatCard label="Banlar" value={stats.activeBans} color={COLORS.danger} />
            <StatCard label="Banlı" value={stats.bannedUsers} color={COLORS.danger} />
            <StatCard label="Foto" value={stats.totalPhotos} />
          </View>
        )}

        {stats?.genderBreakdown && (
          <View style={styles.genderBox}>
            <Text style={styles.sectionTitle}>Cinsiyet Dağılımı</Text>
            <View style={styles.genderRow}>
              {stats.genderBreakdown.map((g) => (
                <Text key={String(g.gender)} style={styles.genderPill}>
                  {g.gender || 'belirsiz'}: {g.count}
                </Text>
              ))}
            </View>
          </View>
        )}

        <View style={styles.usersSection}>
          <Text style={styles.sectionTitle}>Kullanıcılar ({users.length})</Text>
          <TextInput
            style={styles.search}
            value={search}
            onChangeText={setSearch}
            placeholder="Email, kullanıcı adı, rumuz ara..."
            placeholderTextColor={COLORS.textDim}
          />
          <FlatList
            data={users}
            keyExtractor={(u) => u.id}
            renderItem={renderUser}
            scrollEnabled={false}
            ItemSeparatorComponent={() => <View style={{ height: 8 }} />}
          />
        </View>
      </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: COLORS.bg },
  center: { flex: 1, alignItems: 'center', justifyContent: 'center', backgroundColor: COLORS.bg },
  header: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: 20,
    paddingVertical: 16,
    backgroundColor: COLORS.surface,
    borderBottomWidth: 1,
    borderBottomColor: COLORS.border,
  },
  headerTitle: { color: COLORS.danger, fontSize: 20, fontWeight: '800' },
  exitText: { color: COLORS.textMuted, fontWeight: '700' },
  statsGrid: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    padding: 16,
    gap: 10,
  },
  statCard: {
    width: '23%',
    minWidth: 80,
    backgroundColor: COLORS.surface,
    padding: 12,
    borderRadius: RADIUS.md,
    borderWidth: 1,
    borderColor: COLORS.border,
    alignItems: 'center',
  },
  statValue: { fontSize: 22, fontWeight: '800' },
  statLabel: { color: COLORS.textMuted, fontSize: 10, marginTop: 4, textAlign: 'center' },
  genderBox: {
    margin: 16,
    padding: 14,
    backgroundColor: COLORS.surface,
    borderRadius: RADIUS.md,
    borderWidth: 1,
    borderColor: COLORS.border,
  },
  sectionTitle: { color: COLORS.textPrimary, fontSize: 16, fontWeight: '800', marginBottom: 10 },
  genderRow: { flexDirection: 'row', flexWrap: 'wrap', gap: 8 },
  genderPill: { color: COLORS.textSecondary, fontSize: 12, backgroundColor: COLORS.surfaceAlt, paddingHorizontal: 10, paddingVertical: 6, borderRadius: RADIUS.pill },
  usersSection: { padding: 16 },
  search: {
    backgroundColor: COLORS.surface,
    color: COLORS.textPrimary,
    paddingHorizontal: 14,
    paddingVertical: 12,
    borderRadius: RADIUS.md,
    borderWidth: 1,
    borderColor: COLORS.border,
    fontSize: 14,
    marginBottom: 12,
  },
  userRow: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: COLORS.surface,
    padding: 12,
    borderRadius: RADIUS.md,
    borderWidth: 1,
    borderColor: COLORS.border,
    gap: 10,
  },
  userInfo: { flex: 1, minWidth: 0 },
  userName: { color: COLORS.textPrimary, fontSize: 14, fontWeight: '700' },
  userMeta: { color: COLORS.textMuted, fontSize: 11, marginTop: 2 },
  userStats: { color: COLORS.textSecondary, fontSize: 11, marginTop: 2 },
  userActions: { gap: 4 },
  actionBtn: { paddingHorizontal: 10, paddingVertical: 6, borderRadius: RADIUS.sm },
  plusBtn: { backgroundColor: COLORS.primary },
  unplusBtn: { backgroundColor: COLORS.surfaceAlt, borderWidth: 1, borderColor: COLORS.border },
  banBtn: { backgroundColor: COLORS.danger },
  actionBtnText: { color: '#FFFFFF', fontSize: 11, fontWeight: '800' },
});