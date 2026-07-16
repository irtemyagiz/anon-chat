import { useNavigation } from '@react-navigation/native';
import { useState } from 'react';
import { Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { COLORS, RADIUS } from '../config';
import { api } from '../services/api';
import { useAuth } from '../store/AuthContext';

const FEATURES = [
  { emoji: '♾️', title: 'Sınırsız Shuffle', desc: 'Günde 50 değil, sınırsız kullanıcı gör.' },
  { emoji: '👁️', title: 'Takipçilerini Gör', desc: 'Seni kimlerin takip ettiğini öğren.' },
  { emoji: '📸', title: 'Tek Görmelik Foto', desc: 'Sohbette tek seferlik foto gönder/al.' },
  { emoji: '🎨', title: 'Özel Temalar', desc: 'Uygulamayı kendi tarzına göre renklendir.' },
  { emoji: '🚫', title: 'Reklamsız', desc: 'Hiç reklam görmeden sohbet et.' },
  { emoji: '⚡', title: 'Öncelik Eşleşme', desc: 'Sohbet kuyruğunda öne geç.' },
];

export default function PaywallScreen() {
  const navigation = useNavigation();
  const { user, updateUser } = useAuth();
  const [acting, setActing] = useState(false);

  const upgrade = async () => {
    if (acting) return;
    setActing(true);
    try {
      await api.upgradePlus();
      await updateUser({});
      navigation.goBack();
    } catch (err) {
      console.warn('[upgrade]', err);
    } finally {
      setActing(false);
    }
  };

  return (
    <ScrollView style={styles.container} contentContainerStyle={styles.content}>
      <View style={styles.header}>
        <Text style={styles.crown}>⚡</Text>
        <Text style={styles.title}>Anonim Chat Plus</Text>
        <Text style={styles.subtitle}>Tüm özelliklerin kilidi açılsın</Text>
      </View>

      <View style={styles.list}>
        {FEATURES.map((f) => (
          <View key={f.title} style={styles.row}>
            <Text style={styles.emoji}>{f.emoji}</Text>
            <View style={{ flex: 1 }}>
              <Text style={styles.rowTitle}>{f.title}</Text>
              <Text style={styles.rowDesc}>{f.desc}</Text>
            </View>
            <Text style={styles.check}>✓</Text>
          </View>
        ))}
      </View>

      <View style={styles.pricing}>
        <View style={styles.priceBox}>
          <Text style={styles.priceAmount}>49.99 ₺</Text>
          <Text style={styles.pricePeriod}>/ ay</Text>
        </View>
        <Text style={styles.priceNote}>3 gün ücretsiz dene. İstediğin zaman iptal et.</Text>
      </View>

      <Pressable
        style={({ pressed }) => [styles.cta, pressed && styles.ctaPressed, acting && styles.disabled]}
        onPress={upgrade}
        disabled={acting}
      >
        <Text style={styles.ctaText}>{acting ? 'İşleniyor...' : user?.isPlus ? 'Plus Aktif' : 'Şimdi Plus Ol (Test)'}</Text>
      </Pressable>

      {!user?.isPlus && (
        <Text style={styles.testHint}>
          * Test modu: ödeme alınmadan Plus aktif olur. App Store bağlantısı sonra eklenecek.
        </Text>
      )}

      <Pressable onPress={() => navigation.goBack()}>
        <Text style={styles.cancelText}>Belki sonra</Text>
      </Pressable>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: COLORS.bg },
  content: { padding: 24, paddingTop: 40, paddingBottom: 60 },
  header: { alignItems: 'center', marginBottom: 28 },
  crown: { fontSize: 60 },
  title: { color: COLORS.textPrimary, fontSize: 26, fontWeight: '800', marginTop: 12 },
  subtitle: { color: COLORS.textMuted, fontSize: 14, marginTop: 6 },
  list: { gap: 10 },
  row: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: COLORS.surface,
    padding: 14,
    borderRadius: RADIUS.md,
    borderWidth: 1,
    borderColor: COLORS.border,
    gap: 12,
  },
  emoji: { fontSize: 24 },
  rowTitle: { color: COLORS.textPrimary, fontSize: 15, fontWeight: '700' },
  rowDesc: { color: COLORS.textMuted, fontSize: 12, marginTop: 2 },
  check: { color: COLORS.success, fontSize: 18, fontWeight: '800' },
  pricing: { alignItems: 'center', marginTop: 28, marginBottom: 20 },
  priceBox: { flexDirection: 'row', alignItems: 'baseline' },
  priceAmount: { color: COLORS.textPrimary, fontSize: 36, fontWeight: '800' },
  pricePeriod: { color: COLORS.textMuted, fontSize: 16, marginLeft: 4 },
  priceNote: { color: COLORS.textMuted, fontSize: 12, marginTop: 6 },
  cta: { backgroundColor: COLORS.primary, paddingVertical: 18, borderRadius: 14, alignItems: 'center', marginTop: 8 },
  ctaPressed: { backgroundColor: COLORS.primaryPressed, transform: [{ scale: 0.98 }] },
  disabled: { opacity: 0.6 },
  ctaText: { color: '#FFFFFF', fontSize: 17, fontWeight: '800' },
  testHint: { color: COLORS.textDim, fontSize: 11, textAlign: 'center', marginTop: 12 },
  cancelText: { color: COLORS.textMuted, fontSize: 14, textAlign: 'center', marginTop: 18, fontWeight: '700' },
});
