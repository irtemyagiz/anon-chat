import { useNavigation } from '@react-navigation/native';
import { Platform, ScrollView, StyleSheet, Text, View } from 'react-native';
import { AVATAR_COLORS, COLORS, RADIUS } from '../config';
import { useAuth } from '../store/AuthContext';

export default function ProfileScreen() {
  const navigation = useNavigation();
  const { user } = useAuth();

  return (
    <ScrollView style={styles.container} contentContainerStyle={styles.content}>
      <View style={styles.header}>
        <View style={[styles.avatar, { backgroundColor: user?.avatarColor || COLORS.primary }]}>
          <Text style={styles.avatarText}>
            {(user?.nickname || '?').slice(0, 1).toUpperCase()}
          </Text>
        </View>
        <Text style={styles.name}>{user?.nickname || '...'}</Text>
        <Text style={styles.idLabel}>Anonim ID</Text>
        <Text style={styles.idValue}>{(user?.id || '').slice(0, 8)}</Text>
      </View>

      <View style={styles.section}>
        <Text style={styles.sectionTitle}>İstatistikler</Text>
        <View style={styles.statsRow}>
          <View style={styles.statBox}>
            <Text style={styles.statValue}>{user?.totalChats ?? 0}</Text>
            <Text style={styles.statLabel}>Toplam Sohbet</Text>
          </View>
          <View style={styles.statBox}>
            <Text style={styles.statValue}>{user?.ageConfirmed ? '✓' : '—'}</Text>
            <Text style={styles.statLabel}>18+ Onay</Text>
          </View>
        </View>
      </View>

      <View style={styles.section}>
        <Text style={styles.sectionTitle}>Hakkında</Text>
        <View style={styles.row}>
          <Text style={styles.rowKey}>Avatar rengi</Text>
          <View style={[styles.colorChip, { backgroundColor: user?.avatarColor }]} />
        </View>
        <View style={styles.row}>
          <Text style={styles.rowKey}>Ülke</Text>
          <Text style={styles.rowVal}>{user?.countryCode || '—'}</Text>
        </View>
        <View style={styles.row}>
          <Text style={styles.rowKey}>Kayıt</Text>
          <Text style={styles.rowVal}>{user?.id ? 'Anonim' : '...'}</Text>
        </View>
      </View>

      <View style={styles.footer}>
        <Text style={styles.footerText}>Anonim Chat · v1.0.0 MVP</Text>
      </View>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: COLORS.bg },
  content: { paddingBottom: 40 },
  header: { alignItems: 'center', paddingTop: 20, paddingBottom: 24 },
  avatar: {
    width: 90,
    height: 90,
    borderRadius: 45,
    alignItems: 'center',
    justifyContent: 'center',
  },
  avatarText: { color: '#FFFFFF', fontSize: 38, fontWeight: '800' },
  name: { color: COLORS.textPrimary, fontSize: 22, fontWeight: '800', marginTop: 12 },
  idLabel: { color: COLORS.textDim, fontSize: 11, marginTop: 6 },
  idValue: { color: COLORS.textMuted, fontSize: 12, fontFamily: Platform?.OS === 'ios' ? 'Menlo' : 'monospace' },
  section: {
    paddingHorizontal: 24,
    paddingVertical: 16,
    borderTopWidth: 1,
    borderTopColor: COLORS.border,
  },
  sectionTitle: { color: COLORS.textMuted, fontSize: 12, fontWeight: '700', marginBottom: 12, letterSpacing: 1 },
  statsRow: { flexDirection: 'row', gap: 12 },
  statBox: {
    flex: 1,
    backgroundColor: COLORS.surface,
    padding: 16,
    borderRadius: RADIUS.md,
    borderWidth: 1,
    borderColor: COLORS.border,
    alignItems: 'center',
  },
  statValue: { color: COLORS.textPrimary, fontSize: 22, fontWeight: '800' },
  statLabel: { color: COLORS.textMuted, fontSize: 12, marginTop: 4 },
  row: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingVertical: 12,
    borderBottomWidth: 1,
    borderBottomColor: COLORS.border,
  },
  rowKey: { color: COLORS.textMuted, fontSize: 14 },
  rowVal: { color: COLORS.textPrimary, fontSize: 14, fontWeight: '600' },
  colorChip: { width: 22, height: 22, borderRadius: 11, borderWidth: 1, borderColor: COLORS.border },
  footer: { padding: 24, alignItems: 'center' },
  footerText: { color: COLORS.textDim, fontSize: 11 },
});
