import { useNavigation } from '@react-navigation/native';
import { useCallback, useEffect, useState } from 'react';
import {
  ActivityIndicator,
  FlatList,
  Modal,
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

export default function ShuffleScreen() {
  const navigation = useNavigation();
  const { user } = useAuth();
  const [users, setUsers] = useState([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [error, setError] = useState(null);
  const [remaining, setRemaining] = useState(null);
  const [isPlus, setIsPlus] = useState(user?.isPlus || false);
  const [selected, setSelected] = useState(null);

  const load = useCallback(async (refresh = false) => {
    if (refresh) setRefreshing(true);
    else setLoading(true);
    setError(null);
    try {
      const res = await api.shuffleNext({ limit: '30' });
      setUsers(res.users || []);
      setRemaining(res.remaining);
      setIsPlus(res.isPlus);
    } catch (err) {
      if (err.message === 'daily_limit_reached') {
        setError('limit');
      } else {
        setError(err.message || 'Hata');
      }
      setUsers([]);
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
      tabBarBadge: isPlus ? '⚡' : remaining,
    });
  }, [navigation, remaining, isPlus]);

  const renderCard = ({ item }) => (
    <Pressable style={styles.card} onPress={() => setSelected(item)}>
      <Avatar
        seed={item.id || item.avatarSeed}
        size={72}
        avatarStyle={item.avatarStyle}
        photoUrl={item.photoUrl}
        isPlus={!!item.isPlus}
      />
      <View style={styles.cardBody}>
        <View style={styles.nameRow}>
          <Text style={styles.name} numberOfLines={1}>{item.nickname}</Text>
          {item.age ? <Text style={styles.age}>{item.age}</Text> : null}
          {item.isPlus && <Text style={styles.plusBadge}>⚡</Text>}
        </View>
        {item.username ? <Text style={styles.username} numberOfLines={1}>@{item.username}</Text> : null}
        {item.bio ? <Text style={styles.bio} numberOfLines={2}>{item.bio}</Text> : null}
      </View>
    </Pressable>
  );

  return (
    <SafeAreaView style={styles.safe} edges={['top', 'left', 'right']}>
      <View style={styles.header}>
        <Text style={styles.title}>Keşfet</Text>
        <View style={styles.metaRow}>
          {!isPlus && remaining !== null && (
            <View style={styles.metaPill}>
              <Text style={styles.metaPillText}>
                Bugün <Text style={styles.metaNum}>{remaining}</Text> keşfet hakkı
              </Text>
            </View>
          )}
          {isPlus && (
            <View style={[styles.metaPill, styles.metaPlusPill]}>
              <Text style={styles.metaPillText}>⚡ Plus Aktif</Text>
            </View>
          )}
        </View>
      </View>

      {loading ? (
        <View style={styles.center}>
          <ActivityIndicator size="large" color={COLORS.primary} />
          <Text style={styles.centerSub}>Kullanıcılar aranıyor...</Text>
        </View>
      ) : error === 'limit' ? (
        <View style={styles.center}>
          <Text style={styles.emoji}>⚡</Text>
          <Text style={styles.centerTitle}>Günlük limit doldu</Text>
          <Text style={styles.centerSub}>Bugün 30 kişi gördün. Yarın tekrar gel!</Text>
          <Pressable style={styles.cta} onPress={() => navigation.navigate('Paywall')}>
            <Text style={styles.ctaText}>Plus'a Geç</Text>
          </Pressable>
        </View>
      ) : users.length === 0 ? (
        <View style={styles.center}>
          <Text style={styles.emoji}>😴</Text>
          <Text style={styles.centerTitle}>Kimse yok</Text>
          <Text style={styles.centerSub}>Biraz sonra tekrar dene.</Text>
          <Pressable style={styles.cta} onPress={() => load()}>
            <Text style={styles.ctaText}>Yenile</Text>
          </Pressable>
        </View>
      ) : (
        <FlatList
          data={users}
          keyExtractor={(item) => item.id}
          renderItem={renderCard}
          contentContainerStyle={styles.list}
          ItemSeparatorComponent={() => <View style={{ height: 10 }} />}
          refreshControl={
            <RefreshControl
              refreshing={refreshing}
              onRefresh={() => load(true)}
              tintColor={COLORS.primary}
              colors={[COLORS.primary]}
            />
          }
        />
      )}

      <Modal
        visible={!!selected}
        transparent
        animationType="fade"
        onRequestClose={() => setSelected(null)}
      >
        <Pressable style={styles.modalBackdrop} onPress={() => setSelected(null)}>
          <Pressable style={styles.modalSheet} onPress={(e) => e.stopPropagation()}>
            {selected && (
              <>
                <View style={styles.modalHeader}>
                  <Avatar
                    seed={selected.id || selected.avatarSeed}
                    size={80}
                    avatarStyle={selected.avatarStyle}
                    photoUrl={selected.photoUrl}
                    isPlus={!!selected.isPlus}
                  />
                  <View style={{ flex: 1, marginLeft: 14 }}>
                    <Text style={styles.modalName}>{selected.nickname}</Text>
                    {selected.username && <Text style={styles.modalUsername}>@{selected.username}</Text>}
                    {selected.bio && <Text style={styles.modalBio}>{selected.bio}</Text>}
                  </View>
                </View>
                <Pressable
                  style={[styles.modalBtn, styles.modalBtnPrimary]}
                  onPress={() => {
                    setSelected(null);
                    navigation.navigate('Matching', { targetUserId: selected.id, peer: selected });
                  }}
                >
                  <Text style={styles.modalBtnText}>💬 Mesaj Gönder</Text>
                </Pressable>
                <Pressable
                  style={[styles.modalBtn, styles.modalBtnSecondary]}
                  onPress={() => {
                    setSelected(null);
                    navigation.navigate('UserProfile', { userId: selected.id });
                  }}
                >
                  <Text style={styles.modalBtnTextDark}>👤 Profilini Gör</Text>
                </Pressable>
                <Pressable style={styles.modalBtnClose} onPress={() => setSelected(null)}>
                  <Text style={styles.modalBtnCloseText}>İptal</Text>
                </Pressable>
              </>
            )}
          </Pressable>
        </Pressable>
      </Modal>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  safe: { flex: 1, backgroundColor: COLORS.bg },
  header: { paddingHorizontal: 20, paddingTop: 8, paddingBottom: 12 },
  title: { color: COLORS.textPrimary, fontSize: 28, fontWeight: '800' },
  metaRow: { flexDirection: 'row', gap: 8, marginTop: 8 },
  metaPill: {
    backgroundColor: COLORS.surface,
    paddingHorizontal: 12,
    paddingVertical: 6,
    borderRadius: RADIUS.pill,
    borderWidth: 1,
    borderColor: COLORS.border,
  },
  metaPlusPill: { backgroundColor: COLORS.primary, borderColor: COLORS.primary },
  metaPillText: { color: COLORS.textSecondary, fontSize: 12, fontWeight: '700' },
  metaNum: { color: COLORS.textPrimary, fontWeight: '800' },
  list: { paddingHorizontal: 16, paddingBottom: 24 },
  card: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: COLORS.surface,
    borderRadius: RADIUS.lg,
    padding: 12,
    borderWidth: 1,
    borderColor: COLORS.border,
    gap: 12,
  },
  cardBody: { flex: 1, minWidth: 0 },
  nameRow: { flexDirection: 'row', alignItems: 'center', gap: 6 },
  name: { color: COLORS.textPrimary, fontSize: 16, fontWeight: '800', flexShrink: 1 },
  age: { color: COLORS.textMuted, fontSize: 14, fontWeight: '600' },
  plusBadge: { fontSize: 14 },
  username: { color: COLORS.textSecondary, fontSize: 12, marginTop: 2 },
  bio: { color: COLORS.textMuted, fontSize: 13, marginTop: 4, lineHeight: 17 },
  center: { flex: 1, alignItems: 'center', justifyContent: 'center', padding: 24 },
  centerTitle: { color: COLORS.textPrimary, fontSize: 22, fontWeight: '800', marginBottom: 8 },
  centerSub: { color: COLORS.textMuted, fontSize: 14, textAlign: 'center', marginBottom: 4 },
  emoji: { fontSize: 60, marginBottom: 12 },
  cta: {
    backgroundColor: COLORS.primary,
    paddingVertical: 14,
    paddingHorizontal: 40,
    borderRadius: RADIUS.pill,
    marginTop: 20,
  },
  ctaText: { color: '#FFFFFF', fontWeight: '800', fontSize: 16 },
  modalBackdrop: {
    flex: 1,
    backgroundColor: 'rgba(0,0,0,0.65)',
    justifyContent: 'flex-end',
  },
  modalSheet: {
    backgroundColor: COLORS.surface,
    borderTopLeftRadius: 24,
    borderTopRightRadius: 24,
    padding: 24,
    paddingBottom: 40,
    borderTopWidth: 1,
    borderTopColor: COLORS.border,
  },
  modalHeader: { flexDirection: 'row', alignItems: 'center', marginBottom: 20 },
  modalName: { color: COLORS.textPrimary, fontSize: 20, fontWeight: '800' },
  modalUsername: { color: COLORS.textSecondary, fontSize: 13, marginTop: 2 },
  modalBio: { color: COLORS.textPrimary, fontSize: 14, marginTop: 6, lineHeight: 18 },
  modalBtn: {
    paddingVertical: 16,
    borderRadius: RADIUS.md,
    alignItems: 'center',
    marginTop: 10,
  },
  modalBtnPrimary: { backgroundColor: COLORS.primary },
  modalBtnSecondary: { backgroundColor: COLORS.surfaceAlt, borderWidth: 1, borderColor: COLORS.border },
  modalBtnText: { color: '#FFFFFF', fontWeight: '700', fontSize: 15 },
  modalBtnTextDark: { color: COLORS.textPrimary, fontWeight: '700', fontSize: 15 },
  modalBtnClose: { paddingVertical: 12, alignItems: 'center', marginTop: 6 },
  modalBtnCloseText: { color: COLORS.textMuted, fontWeight: '700' },
});