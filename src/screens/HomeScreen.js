import { useNavigation } from '@react-navigation/native';
import { useEffect, useState } from 'react';
import { Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { COLORS, RADIUS } from '../config';
import { useInterests } from '../store/InterestsContext';
import { useAuth } from '../store/AuthContext';

export default function HomeScreen() {
  const navigation = useNavigation();
  const { user } = useAuth();
  const { interests, loading } = useInterests();
  const [selected, setSelected] = useState([]);

  useEffect(() => {
    navigation.setOptions({
      headerRight: () => (
        <Pressable onPress={() => navigation.navigate('Profile')}>
          <View style={[styles.headerAvatar, { backgroundColor: user?.avatarColor || COLORS.primary }]}>
            <Text style={styles.headerAvatarText}>
              {(user?.nickname || '?').slice(0, 1).toUpperCase()}
            </Text>
          </View>
        </Pressable>
      ),
    });
  }, [navigation, user]);

  const toggle = (id) =>
    setSelected((p) => (p.includes(id) ? p.filter((x) => x !== id) : [...p, id]));

  const start = () => {
    navigation.navigate('Matching', { interestIds: selected });
  };

  return (
    <ScrollView style={styles.container} contentContainerStyle={styles.content}>
      <View style={styles.hero}>
        <Text style={styles.heroHello}>Selam,</Text>
        <Text style={styles.heroName}>{user?.nickname || '...'}</Text>
        <Text style={styles.heroSub}>Kimi bulalım bugün?</Text>
      </View>

      <Pressable style={({ pressed }) => [styles.cta, pressed && styles.ctaPressed]} onPress={start}>
        <Text style={styles.ctaText}>Rastgele Eşleş</Text>
        <Text style={styles.ctaEmoji}>🎲</Text>
      </Pressable>

      <Text style={styles.sectionTitle}>İlgi Alanlarına Göre</Text>
      <Text style={styles.sectionSub}>Seçtiklerinle benzer kişileri eşleştir.</Text>

      <View style={styles.grid}>
        {interests.map((it) => {
          const active = selected.includes(it.id);
          return (
            <Pressable
              key={it.id}
              style={[styles.pill, active && styles.pillActive]}
              onPress={() => toggle(it.id)}
              disabled={loading}
            >
              <Text style={styles.pillEmoji}>{it.emoji}</Text>
              <Text style={[styles.pillText, active && styles.pillTextActive]}>{it.nameTr}</Text>
            </Pressable>
          );
        })}
      </View>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: COLORS.bg },
  content: { padding: 24, paddingBottom: 40 },
  headerAvatar: {
    width: 36,
    height: 36,
    borderRadius: 18,
    alignItems: 'center',
    justifyContent: 'center',
  },
  headerAvatarText: { color: '#FFFFFF', fontWeight: '800' },
  hero: { marginTop: 8, marginBottom: 24 },
  heroHello: { color: COLORS.textMuted, fontSize: 16 },
  heroName: { color: COLORS.textPrimary, fontSize: 32, fontWeight: '800', marginTop: 4 },
  heroSub: { color: COLORS.textSecondary, fontSize: 15, marginTop: 6 },
  cta: {
    flexDirection: 'row',
    backgroundColor: COLORS.primary,
    paddingVertical: 18,
    paddingHorizontal: 22,
    borderRadius: RADIUS.lg,
    alignItems: 'center',
    justifyContent: 'center',
    shadowColor: COLORS.primary,
    shadowOpacity: 0.4,
    shadowRadius: 12,
    shadowOffset: { width: 0, height: 8 },
    elevation: 6,
  },
  ctaPressed: { backgroundColor: COLORS.primaryPressed, transform: [{ scale: 0.98 }] },
  ctaText: { color: '#FFFFFF', fontSize: 17, fontWeight: '800', letterSpacing: 0.3 },
  ctaEmoji: { fontSize: 20, marginLeft: 10 },
  sectionTitle: { color: COLORS.textPrimary, fontSize: 18, fontWeight: '700', marginTop: 28 },
  sectionSub: { color: COLORS.textMuted, fontSize: 13, marginTop: 4, marginBottom: 12 },
  grid: { flexDirection: 'row', flexWrap: 'wrap', gap: 10 },
  pill: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 14,
    paddingVertical: 10,
    borderRadius: RADIUS.pill,
    backgroundColor: COLORS.surface,
    borderWidth: 1,
    borderColor: COLORS.border,
  },
  pillActive: { backgroundColor: COLORS.primary, borderColor: COLORS.primary },
  pillEmoji: { fontSize: 16, marginRight: 6 },
  pillText: { color: COLORS.textSecondary, fontSize: 14, fontWeight: '600' },
  pillTextActive: { color: '#FFFFFF' },
});
