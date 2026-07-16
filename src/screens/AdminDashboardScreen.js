import { useNavigation, useRoute } from '@react-navigation/native';
import { useCallback, useEffect, useState } from 'react';
import {
  ActivityIndicator,
  Alert,
  Pressable,
  RefreshControl,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  View,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import Avatar from '../components/Avatar';
import { COLORS, RADIUS } from '../config';
import { api } from '../services/api';

function StatCard({ label, value, color = COLORS.primary }) {
  return (
    <View style={styles.statCard}>
      <Text style={[styles.statValue, { color }]}>{value}</Text>
      <Text style={styles.statLabel}>{label}</Text>
    </View>
  );
}

function formatTime(dateStr) {
  if (!dateStr) return '';
  const d = new Date(dateStr);
  const diff = Date.now() - d.getTime();
  const min = Math.floor(diff / 60000);
  const hr = Math.floor(diff / 3600000);
  if (min < 1) return 'şimdi';
  if (min < 60) return `${min}dk`;
  if (hr < 24) return `${hr}sa`;
  return d.toLocaleDateString('tr-TR', { day: '2-digit', month: '2-digit' });
}

export default function AdminDashboardScreen() {
  const navigation = useNavigation();
  const route = useRoute();
  const [stats, setStats] = useState(null);
  const [reports, setReports] = useState([]);
  const [recentMessages, setRecentMessages] = useState([]);
  const [search, setSearch] = useState('');
  const [users, setUsers] = useState([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);

  const load = useCallback(async () => {
    try {
      const [s, r, m, u] = await Promise.all([
        api.adminStats(),
        api.adminReports(),
        api.adminRecentMessages(5),
        api.adminUsers({ q: search, limit: '50' }),
      ]);
      setStats(s);
      setReports(r.reports || []);
      setRecentMessages(m.messages || []);
      setUsers(u.users || []);
    } catch (err) {
      Alert.alert('Hata', err.message);
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, [search]);

  useEffect(() => {
    const t = setTimeout(() => load(), 250);
    return () => clearTimeout(t);
  }, [load]);

  const handleBanReport = (report) => {
    Alert.alert(
      'Kullanıcıyı Banla',
      `${report.reported?.nickname} 24 saat banlanacak. Şikayet silinecek.`,
      [
        { text: 'Vazgeç', style: 'cancel' },
        {
          text: 'Banla',
          style: 'destructive',
          onPress: async () => {
            try {
              await api.adminBanReport(report.id);
              load();
            } catch (err) {
              Alert.alert('Hata', err.message);
            }
          },
        },
      ]
    );
  };

  const handleDismissReport = (report) => {
    Alert.alert(
      'Şikayeti Reddet',
      `${report.reporter?.nickname} kullanıcısının şikayeti reddedilecek.`,
      [
        { text: 'Vazgeç', style: 'cancel' },
        {
          text: 'Reddet',
          onPress: async () => {
            try {
              await api.adminDismissReport(report.id);
              load();
            } catch (err) {
              Alert.alert('Hata', err.message);
            }
          },
        },
      ]
    );
  };

  const handleBanUser = (u) => {
    Alert.alert(
      'Kullanıcıyı Banla',
      `${u.nickname} 24 saat banlanacak.`,
      [
        { text: 'Vazgeç', style: 'cancel' },
        {
          text: 'Banla',
          style: 'destructive',
          onPress: async () => {
            try {
              await api.adminPatchUser(u.id, { isBanned: true });
              load();
            } catch (err) {
              Alert.alert('Hata', err.message);
            }
          },
        },
      ]
    );
  };

  const handleUnbanUser = async (u) => {
    try {
      await api.adminPatchUser(u.id, { isBanned: false });
      load();
    } catch (err) {
      Alert.alert('Hata', err.message);
    }
  };

  const handleTogglePlus = async (u) => {
    try {
      await api.adminPatchUser(u.id, {
        isPlus: !u.isPlus,
        plusDays: 30,
      });
      load();
    } catch (err) {
      Alert.alert('Hata', err.message);
    }
  };

  if (loading) {
    return (
      <View style={styles.center}>
        <ActivityIndicator size="large" color={COLORS.danger} />
      </View>
    );
  }

  return (
    <SafeAreaView style={styles.safe} edges={['top', 'left', 'right', 'bottom']}>
      <View style={styles.header}>
        <Text style={styles.headerTitle}>🔒 Admin</Text>
        <Pressable onPress={() => navigation.popToTop()}>
          <Text style={styles.exitText}>Çıkış</Text>
        </Pressable>
      </View>

      <ScrollView
        contentContainerStyle={styles.content}
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
      >
        {stats && (
          <View style={styles.statsGrid}>
            <StatCard label="Toplam" value={stats.totalUsers} />
            <StatCard label="Çevrimiçi" value={stats.onlineUsers} color={COLORS.success} />
            <StatCard label="Plus" value={stats.plusUsers} color="#FFD700" />
            <StatCard label="Yeni (24s)" value={stats.newToday} color={COLORS.success} />
            <StatCard label="Mesajlar" value={stats.totalMessages} />
            <StatCard label="Şikayetler" value={stats.pendingReports} color={COLORS.warning} />
            <StatCard label="Sohbetler" value={stats.totalRooms} />
            <StatCard label="Takip" value={stats.totalFollows} />
            <StatCard label="Aktif Ban" value={stats.activeBans} color={COLORS.danger} />
            <StatCard label="Foto" value={stats.totalPhotos} />
            <StatCard label="Arkadaş" value={stats.totalFriendships} />
            <StatCard label="Banlı" value={stats.bannedUsers} color={COLORS.danger} />
          </View>
        )}

        <View style={styles.section}>
          <Text style={styles.sectionTitle}>
            Şikayetler ({reports.length})
          </Text>
          {reports.length === 0 ? (
            <View style={styles.emptyCard}>
              <Text style={styles.emptyEmoji}>✨</Text>
              <Text style={styles.emptyText}>Şikayet yok</Text>
            </View>
          ) : (
            reports.slice(0, 10).map((r) => (
              <View key={r.id} style={styles.reportCard}>
                <View style={styles.reportHeader}>
                  <View style={styles.reportUserBlock}>
                    <Avatar
                      seed={r.reported?.id}
                      size={32}
                      avatarStyle={r.reported?.avatarStyle}
                      photoUrl={r.reported?.photoUrl}
                    />
                    <View style={{ marginLeft: 10, flex: 1 }}>
                      <Text style={styles.reportTitle}>
                        {r.reported?.nickname || 'Bilinmeyen'}
                      </Text>
                      <Text style={styles.reportSub}>
                        Sebep: {r.reason} · {formatTime(r.createdAt)}
                      </Text>
                    </View>
                  </View>
                </View>

                {r.lastMessage && (
                  <View style={styles.messagePreview}>
                    <Text style={styles.messagePreviewLabel}>Son mesaj:</Text>
                    <Text style={styles.messagePreviewText} numberOfLines={3}>
                      {r.lastMessage.content}
                    </Text>
                  </View>
                )}

                <Text style={styles.reporterLine}>
                  Şikayet eden: {r.reporter?.nickname || 'Anonim'} (@{r.reporter?.username || '?'})
                </Text>

                <View style={styles.reportActions}>
                  <Pressable
                    style={[styles.reportBtn, styles.reportBanBtn]}
                    onPress={() => handleBanReport(r)}
                  >
                    <Text style={styles.reportBtnText}>🚫 Banla</Text>
                  </Pressable>
                  <Pressable
                    style={[styles.reportBtn, styles.reportDismissBtn]}
                    onPress={() => handleDismissReport(r)}
                  >
                    <Text style={styles.reportBtnText}>✕ Reddet</Text>
                  </Pressable>
                </View>
              </View>
            ))
          )}
        </View>

        <View style={styles.section}>
          <Text style={styles.sectionTitle}>Son Mesajlar ({recentMessages.length})</Text>
          {recentMessages.length === 0 ? (
            <View style={styles.emptyCard}>
              <Text style={styles.emptyText}>Henüz mesaj yok</Text>
            </View>
          ) : (
            recentMessages.map((m) => (
              <View key={m.id} style={styles.messageCard}>
                <View style={styles.messageHeader}>
                  <Avatar
                    seed={m.senderId}
                    size={28}
                    avatarStyle={m.sender?.avatarStyle}
                    photoUrl={m.sender?.photoUrl}
                  />
                  <View style={{ marginLeft: 10, flex: 1 }}>
                    <Text style={styles.messageSender}>
                      {m.sender?.nickname || 'Anonim'}
                    </Text>
                    <Text style={styles.messageTime}>{formatTime(m.createdAt)}</Text>
                  </View>
                  {m.flagged && <Text style={styles.flaggedBadge}>⚠️</Text>}
                </View>
                <Text style={styles.messageContent}>{m.content}</Text>
              </View>
            ))
          )}
        </View>

        <View style={styles.section}>
          <Text style={styles.sectionTitle}>Kullanıcılar ({users.length})</Text>
          <TextInput
            style={styles.search}
            value={search}
            onChangeText={setSearch}
            placeholder="Email, kullanıcı adı, rumuz ara..."
            placeholderTextColor={COLORS.textDim}
          />
          {users.map((u) => (
            <View key={u.id} style={styles.userCard}>
              <Avatar
                seed={u.id}
                size={40}
                avatarStyle={u.avatarStyle}
                photoUrl={u.photoUrl}
                isPlus={!!u.isPlus}
              />
              <View style={styles.userInfo}>
                <Text style={styles.userName}>
                  {u.nickname} {u.isPlus ? '⚡' : ''} {u.isBanned ? '🚫' : ''}
                </Text>
                <Text style={styles.userMeta}>
                  @{u.username || '?'} · {u.email || '—'}
                </Text>
              </View>
              <View style={styles.userActions}>
                <Pressable
                  style={[styles.userBtn, u.isPlus ? styles.unplusBtn : styles.plusBtn]}
                  onPress={() => handleTogglePlus(u)}
                >
                  <Text style={styles.userBtnText}>{u.isPlus ? '⚡' : '+'}</Text>
                </Pressable>
                <Pressable
                  style={[styles.userBtn, u.isBanned ? styles.banOffBtn : styles.banOnBtn]}
                  onPress={() => (u.isBanned ? handleUnbanUser(u) : handleBanUser(u))}
                >
                  <Text style={styles.userBtnText}>{u.isBanned ? '↻' : '🚫'}</Text>
                </Pressable>
              </View>
            </View>
          ))}
        </View>

        <View style={{ height: 40 }} />
      </ScrollView>
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
    paddingVertical: 14,
    backgroundColor: COLORS.surface,
    borderBottomWidth: 1,
    borderBottomColor: COLORS.border,
  },
  headerTitle: { color: COLORS.danger, fontSize: 18, fontWeight: '800' },
  exitText: { color: COLORS.textMuted, fontWeight: '700' },
  content: { padding: 14, paddingBottom: 32 },
  statsGrid: { flexDirection: 'row', flexWrap: 'wrap', gap: 8, marginBottom: 18 },
  statCard: {
    width: '23.5%',
    minWidth: 70,
    backgroundColor: COLORS.surface,
    padding: 10,
    borderRadius: RADIUS.md,
    borderWidth: 1,
    borderColor: COLORS.border,
    alignItems: 'center',
  },
  statValue: { fontSize: 18, fontWeight: '800' },
  statLabel: { color: COLORS.textMuted, fontSize: 10, marginTop: 2, textAlign: 'center' },
  section: { marginBottom: 22 },
  sectionTitle: {
    color: COLORS.textPrimary,
    fontSize: 14,
    fontWeight: '800',
    marginBottom: 10,
    letterSpacing: 0.5,
    textTransform: 'uppercase',
  },
  emptyCard: {
    padding: 24,
    backgroundColor: COLORS.surface,
    borderRadius: RADIUS.md,
    alignItems: 'center',
    borderWidth: 1,
    borderColor: COLORS.border,
  },
  emptyEmoji: { fontSize: 32 },
  emptyText: { color: COLORS.textMuted, fontSize: 13, marginTop: 6 },
  reportCard: {
    backgroundColor: COLORS.surface,
    borderRadius: RADIUS.md,
    padding: 12,
    marginBottom: 8,
    borderWidth: 1,
    borderColor: COLORS.border,
    gap: 10,
  },
  reportHeader: { flexDirection: 'row' },
  reportUserBlock: { flexDirection: 'row', alignItems: 'center', flex: 1 },
  reportTitle: { color: COLORS.textPrimary, fontSize: 14, fontWeight: '700' },
  reportSub: { color: COLORS.textMuted, fontSize: 11, marginTop: 2 },
  messagePreview: {
    backgroundColor: COLORS.surfaceAlt,
    padding: 10,
    borderRadius: RADIUS.sm,
    borderLeftWidth: 3,
    borderLeftColor: COLORS.warning,
  },
  messagePreviewLabel: { color: COLORS.textMuted, fontSize: 11, fontWeight: '700' },
  messagePreviewText: { color: COLORS.textPrimary, fontSize: 13, marginTop: 4, lineHeight: 18 },
  reporterLine: { color: COLORS.textMuted, fontSize: 11 },
  reportActions: { flexDirection: 'row', gap: 8 },
  reportBtn: {
    flex: 1,
    paddingVertical: 10,
    borderRadius: RADIUS.sm,
    alignItems: 'center',
  },
  reportBanBtn: { backgroundColor: COLORS.danger },
  reportDismissBtn: { backgroundColor: COLORS.surfaceAlt, borderWidth: 1, borderColor: COLORS.border },
  reportBtnText: { color: '#FFFFFF', fontWeight: '800', fontSize: 12 },
  messageCard: {
    backgroundColor: COLORS.surface,
    padding: 10,
    borderRadius: RADIUS.md,
    marginBottom: 6,
    borderWidth: 1,
    borderColor: COLORS.border,
  },
  messageHeader: { flexDirection: 'row', alignItems: 'center' },
  messageSender: { color: COLORS.textPrimary, fontSize: 12, fontWeight: '700' },
  messageTime: { color: COLORS.textMuted, fontSize: 10, marginTop: 1 },
  flaggedBadge: { fontSize: 14 },
  messageContent: { color: COLORS.textPrimary, fontSize: 13, marginTop: 6, lineHeight: 17 },
  search: {
    backgroundColor: COLORS.surface,
    color: COLORS.textPrimary,
    paddingHorizontal: 12,
    paddingVertical: 10,
    borderRadius: RADIUS.md,
    borderWidth: 1,
    borderColor: COLORS.border,
    fontSize: 13,
    marginBottom: 8,
  },
  userCard: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: COLORS.surface,
    padding: 10,
    borderRadius: RADIUS.md,
    borderWidth: 1,
    borderColor: COLORS.border,
    marginBottom: 6,
    gap: 10,
  },
  userInfo: { flex: 1, minWidth: 0 },
  userName: { color: COLORS.textPrimary, fontSize: 13, fontWeight: '700' },
  userMeta: { color: COLORS.textMuted, fontSize: 11, marginTop: 2 },
  userActions: { flexDirection: 'row', gap: 4 },
  userBtn: { width: 32, height: 32, borderRadius: 16, alignItems: 'center', justifyContent: 'center' },
  plusBtn: { backgroundColor: COLORS.primary },
  unplusBtn: { backgroundColor: COLORS.surfaceAlt, borderWidth: 1, borderColor: COLORS.border },
  banOnBtn: { backgroundColor: COLORS.danger },
  banOffBtn: { backgroundColor: COLORS.surfaceAlt, borderWidth: 1, borderColor: COLORS.success },
  userBtnText: { color: '#FFFFFF', fontSize: 12, fontWeight: '800' },
});