import { useNavigation } from '@react-navigation/native';
import { useCallback, useEffect, useState } from 'react';
import { ActivityIndicator, Alert, Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { COLORS, RADIUS } from '../config';
import { api } from '../services/api';
import { useAuth } from '../store/AuthContext';

const FEATURES = [
  { emoji: '♾️', title: 'Sınırsız Keşfet', desc: '30 yerine sınırsız kullanıcı gör' },
  { emoji: '👁️', title: 'Takipçilerini Gör', desc: 'Seni kimlerin takip ettiğini öğren' },
  { emoji: '📩', title: 'Takip İstekleri', desc: 'Gelen takip isteklerini kabul/reddet' },
  { emoji: '📸', title: 'Tek Görmelik Foto', desc: 'Sohbette tek seferlik foto gönder' },
  { emoji: '✨', title: 'Altın Çerçeve', desc: 'Avatarının etrafında özel çerçeve' },
  { emoji: '🚫', title: 'Reklamsız', desc: 'Hiç reklam görmeden kullan' },
];

export default function PlusScreen() {
  const navigation = useNavigation();
  const { user, updateUser, logout } = useAuth();
  const [info, setInfo] = useState(null);
  const [loading, setLoading] = useState(true);
  const [acting, setActing] = useState(false);

  const load = useCallback(async () => {
    try {
      const res = await api.plusInfo();
      setInfo(res);
    } catch (err) {
      console.warn('[plus info]', err);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    load();
  }, [load]);

  const buy = async (tier) => {
    if (acting) return;
    setActing(true);
    try {
      await api.upgradePlus(tier);
      await updateUser({});
      await load();
      Alert.alert('Tebrikler!', 'Plus üyeliğin aktif edildi.');
    } catch (err) {
      Alert.alert('Hata', err.message || 'İşlem başarısız');
    } finally {
      setActing(false);
    }
  };

  const cancel = async () => {
    Alert.alert(
      'Üyeliği İptal Et',
      'Mevcut Plus süren dolduğunda otomatik yenilenmez. Devam edilsin mi?',
      [
        { text: 'Vazgeç', style: 'cancel' },
        {
          text: 'İptal Et',
          style: 'destructive',
          onPress: async () => {
            try {
              await api.downgradePlus();
              await updateUser({});
              await load();
              Alert.alert('Tamam', 'Üyeliğin iptal edildi.');
            } catch (err) {
              Alert.alert('Hata', err.message);
            }
          },
        },
      ]
    );
  };

  const onLogout = async () => {
    await logout();
  };

  if (loading) {
    return (
      <View style={styles.center}>
        <ActivityIndicator size="large" color={COLORS.primary} />
      </View>
    );
  }

  const isPlus = info?.isPlus;

  return (
    <ScrollView style={styles.container} contentContainerStyle={styles.content}>
      <View style={styles.header}>
        <Text style={styles.crown}>⚡</Text>
        <Text style={styles.title}>Plus Üyeliği</Text>
        {isPlus ? (
          <>
            <Text style={styles.subtitle}>Aktif</Text>
            <View style={styles.statusBox}>
              <Text style={styles.statusLabel}>Kalan Süre</Text>
              <Text style={styles.statusValue}>{info.remainingDays} gün</Text>
              <Text style={styles.statusHint}>
                Bitiş: {new Date(info.expiresAt).toLocaleDateString('tr-TR')}
              </Text>
            </View>
          </>
        ) : (
          <Text style={styles.subtitle}>Tüm özelliklerin kilidi açılsın</Text>
        )}
      </View>

      <View style={styles.featureList}>
        {FEATURES.map((f) => (
          <View key={f.title} style={styles.featureRow}>
            <Text style={styles.featureEmoji}>{f.emoji}</Text>
            <View style={{ flex: 1 }}>
              <Text style={styles.featureTitle}>{f.title}</Text>
              <Text style={styles.featureDesc}>{f.desc}</Text>
            </View>
            <Text style={styles.featureCheck}>✓</Text>
          </View>
        ))}
      </View>

      {!isPlus && (
        <>
          <Text style={styles.packagesTitle}>Paket Seç</Text>
          <View style={styles.packageGrid}>
            {(info?.tiers || []).map((t) => (
              <Pressable
                key={t.id}
                style={({ pressed }) => [styles.pkgCard, pressed && styles.pkgCardPressed, acting && styles.disabled]}
                onPress={() => buy(t.id)}
                disabled={acting}
              >
                <Text style={styles.pkgLabel}>{t.label}</Text>
                <Text style={styles.pkgPrice}>{t.price} ₺</Text>
                <Text style={styles.pkgBtn}>{acting ? '...' : 'Seç'}</Text>
              </Pressable>
            ))}
          </View>
          <Text style={styles.testHint}>* Test modu: ödeme alınmadan Plus aktif olur.</Text>
        </>
      )}

      {isPlus && (
        <Pressable style={styles.cancelBtn} onPress={cancel}>
          <Text style={styles.cancelText}>Üyeliği İptal Et</Text>
        </Pressable>
      )}

      <Pressable style={styles.logoutBtn} onPress={onLogout}>
        <Text style={styles.logoutText}>Çıkış Yap</Text>
      </Pressable>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: COLORS.bg },
  content: { padding: 20, paddingBottom: 60 },
  center: { flex: 1, alignItems: 'center', justifyContent: 'center', backgroundColor: COLORS.bg },
  header: { alignItems: 'center', marginBottom: 24 },
  crown: { fontSize: 56 },
  title: { color: COLORS.textPrimary, fontSize: 26, fontWeight: '800', marginTop: 8 },
  subtitle: { color: COLORS.textMuted, fontSize: 14, marginTop: 6 },
  statusBox: {
    marginTop: 16,
    padding: 16,
    backgroundColor: COLORS.surface,
    borderRadius: RADIUS.md,
    borderWidth: 1,
    borderColor: COLORS.success,
    alignItems: 'center',
    minWidth: 200,
  },
  statusLabel: { color: COLORS.textMuted, fontSize: 11, fontWeight: '700', letterSpacing: 1 },
  statusValue: { color: COLORS.success, fontSize: 32, fontWeight: '800', marginTop: 4 },
  statusHint: { color: COLORS.textMuted, fontSize: 11, marginTop: 4 },
  featureList: { gap: 8 },
  featureRow: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: COLORS.surface,
    padding: 14,
    borderRadius: RADIUS.md,
    borderWidth: 1,
    borderColor: COLORS.border,
    gap: 12,
  },
  featureEmoji: { fontSize: 22 },
  featureTitle: { color: COLORS.textPrimary, fontSize: 14, fontWeight: '700' },
  featureDesc: { color: COLORS.textMuted, fontSize: 12, marginTop: 2 },
  featureCheck: { color: COLORS.success, fontSize: 18, fontWeight: '800' },
  packagesTitle: { color: COLORS.textPrimary, fontSize: 18, fontWeight: '700', marginTop: 28, marginBottom: 12 },
  packageGrid: { flexDirection: 'row', gap: 10 },
  pkgCard: {
    flex: 1,
    backgroundColor: COLORS.surface,
    borderRadius: RADIUS.lg,
    padding: 16,
    alignItems: 'center',
    borderWidth: 1,
    borderColor: COLORS.border,
  },
  pkgCardPressed: { backgroundColor: COLORS.surfaceAlt, transform: [{ scale: 0.98 }] },
  pkgLabel: { color: COLORS.textMuted, fontSize: 12, fontWeight: '700' },
  pkgPrice: { color: COLORS.textPrimary, fontSize: 22, fontWeight: '800', marginVertical: 8 },
  pkgBtn: { color: COLORS.primary, fontSize: 13, fontWeight: '700' },
  disabled: { opacity: 0.5 },
  testHint: { color: COLORS.textDim, fontSize: 11, textAlign: 'center', marginTop: 12 },
  cancelBtn: {
    backgroundColor: COLORS.surface,
    borderWidth: 1,
    borderColor: COLORS.danger,
    padding: 14,
    borderRadius: RADIUS.md,
    alignItems: 'center',
    marginTop: 28,
  },
  cancelText: { color: COLORS.danger, fontWeight: '800', fontSize: 15 },
  logoutBtn: { padding: 14, alignItems: 'center', marginTop: 8 },
  logoutText: { color: COLORS.textMuted, fontWeight: '700' },
});