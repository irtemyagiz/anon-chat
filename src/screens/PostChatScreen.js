import { useNavigation, useRoute } from '@react-navigation/native';
import { Pressable, StyleSheet, Text, View } from 'react-native';
import { COLORS, RADIUS } from '../config';
import { useAuth } from '../store/AuthContext';

export default function PostChatScreen() {
  const navigation = useNavigation();
  const route = useRoute();
  const { user } = useAuth();
  const peer = route.params?.peer;
  const reason = route.params?.reason || 'left';

  const handleNext = () => navigation.replace('Matching');
  const handleHome = () => navigation.popToTop();

  return (
    <View style={styles.container}>
      <View style={styles.top}>
        <Text style={styles.emoji}>{reason === 'reported' ? '🚩' : '👋'}</Text>
        <Text style={styles.title}>
          {reason === 'reported' ? 'Bildirimin alındı' : 'Sohbet bitti'}
        </Text>
        <Text style={styles.sub}>
          {reason === 'reported'
            ? 'Ekibimiz inceleyecek. Sana daha iyi bir deneyim için teşekkürler.'
            : `${peer?.nickname || 'Karşındaki'} ile bağlantı kesildi.`}
        </Text>
      </View>

      <View style={styles.bottom}>
        <Pressable style={({ pressed }) => [styles.cta, pressed && styles.ctaPressed]} onPress={handleNext}>
          <Text style={styles.ctaText}>Sıradaki →</Text>
        </Pressable>
        <Pressable style={({ pressed }) => [styles.secondary, pressed && styles.secondaryPressed]} onPress={handleHome}>
          <Text style={styles.secondaryText}>Ana Sayfa</Text>
        </Pressable>
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: COLORS.bg,
    padding: 24,
    justifyContent: 'space-between',
  },
  top: { alignItems: 'center', marginTop: 100 },
  emoji: { fontSize: 60 },
  title: { color: COLORS.textPrimary, fontSize: 26, fontWeight: '800', marginTop: 18 },
  sub: { color: COLORS.textMuted, fontSize: 14, marginTop: 10, textAlign: 'center', lineHeight: 20 },
  bottom: { width: '100%' },
  cta: {
    backgroundColor: COLORS.primary,
    paddingVertical: 16,
    borderRadius: RADIUS.md,
    alignItems: 'center',
  },
  ctaPressed: { backgroundColor: COLORS.primaryPressed, transform: [{ scale: 0.98 }] },
  ctaText: { color: '#FFFFFF', fontSize: 17, fontWeight: '700' },
  secondary: {
    paddingVertical: 14,
    borderRadius: RADIUS.md,
    alignItems: 'center',
    marginTop: 12,
    backgroundColor: COLORS.surface,
    borderWidth: 1,
    borderColor: COLORS.border,
  },
  secondaryPressed: { backgroundColor: COLORS.surfaceAlt },
  secondaryText: { color: COLORS.textSecondary, fontSize: 15, fontWeight: '700' },
});
