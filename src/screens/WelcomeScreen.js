import { useEffect, useRef } from 'react';
import { Animated, Easing, Pressable, StyleSheet, Text, View } from 'react-native';
import { colors, radius, spacing, typography } from '../theme';

const FEATURES = [
  { emoji: '🕶️', title: 'Tamamen Anonim', desc: 'Kayıt yok, kimlik yok, sadece sohbet.' },
  { emoji: '⚡', title: 'Anlık Eşleşme', desc: 'Saniyeler içinde yeni biriyle tanış.' },
  { emoji: '🔒', title: 'Güvenli Bağlantı', desc: 'Mesajların uçtan uca korunur.' },
];

export default function WelcomeScreen({ onStart }) {
  const fade = useRef(new Animated.Value(0)).current;
  const slide = useRef(new Animated.Value(24)).current;
  const logoScale = useRef(new Animated.Value(0.6)).current;

  useEffect(() => {
    Animated.parallel([
      Animated.timing(fade, { toValue: 1, duration: 600, useNativeDriver: true }),
      Animated.timing(slide, {
        toValue: 0,
        duration: 600,
        easing: Easing.out(Easing.cubic),
        useNativeDriver: true,
      }),
      Animated.spring(logoScale, { toValue: 1, friction: 5, useNativeDriver: true }),
    ]).start();
  }, [fade, slide, logoScale]);

  return (
    <View style={styles.container}>
      <Animated.View style={[styles.topSection, { opacity: fade }]}>
        <Animated.View style={[styles.logoCircle, { transform: [{ scale: logoScale }] }]}>
          <Text style={styles.logoEmoji}>💬</Text>
        </Animated.View>
        <View style={styles.badge}>
          <View style={styles.badgeDot} />
          <Text style={styles.badgeText}>Çevrimiçi: 2.4k+</Text>
        </View>
      </Animated.View>

      <Animated.View style={[styles.middleSection, { opacity: fade, transform: [{ translateY: slide }] }]}>
        <Text style={styles.title}>Anonim Chat</Text>
        <Text style={styles.subtitle}>Kimliğin gizli, sohbet özgür.</Text>

        <View style={styles.featureList}>
          {FEATURES.map((f) => (
            <View key={f.title} style={styles.featureRow}>
              <Text style={styles.featureEmoji}>{f.emoji}</Text>
              <View style={styles.featureTextWrap}>
                <Text style={styles.featureTitle}>{f.title}</Text>
                <Text style={styles.featureDesc}>{f.desc}</Text>
              </View>
            </View>
          ))}
        </View>
      </Animated.View>

      <Animated.View style={[styles.bottomSection, { opacity: fade }]}>
        <Pressable
          style={({ pressed }) => [styles.button, pressed && styles.buttonPressed]}
          onPress={onStart}
          android_ripple={{ color: '#4338CA' }}
        >
          <Text style={styles.buttonText}>Sohbete Başla</Text>
          <Text style={styles.buttonArrow}>→</Text>
        </Pressable>

        <Text style={styles.footerText}>
          Başlayarak kullanım koşullarını kabul etmiş olursun.
        </Text>
      </Animated.View>
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: colors.background,
    paddingHorizontal: spacing.lg,
    paddingTop: 80,
    paddingBottom: spacing.xl,
    justifyContent: 'space-between',
  },
  topSection: {
    alignItems: 'center',
  },
  logoCircle: {
    width: 120,
    height: 120,
    borderRadius: 60,
    backgroundColor: colors.surface,
    alignItems: 'center',
    justifyContent: 'center',
    borderWidth: 2,
    borderColor: colors.primary,
    shadowColor: colors.primary,
    shadowOffset: { width: 0, height: 0 },
    shadowOpacity: 0.6,
    shadowRadius: 18,
    elevation: 8,
  },
  logoEmoji: {
    fontSize: 56,
  },
  badge: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: colors.surface,
    paddingHorizontal: spacing.md,
    paddingVertical: spacing.xs + 2,
    borderRadius: radius.pill,
    marginTop: spacing.md,
    borderWidth: 1,
    borderColor: colors.border,
  },
  badgeDot: {
    width: 8,
    height: 8,
    borderRadius: 4,
    backgroundColor: colors.success,
    marginRight: spacing.sm,
  },
  badgeText: {
    color: colors.textSecondary,
    fontSize: 13,
    fontWeight: '600',
  },
  middleSection: {
    alignItems: 'center',
  },
  title: {
    ...typography.title,
    color: colors.textPrimary,
    marginBottom: spacing.sm + 4,
  },
  subtitle: {
    ...typography.subtitle,
    color: colors.textSecondary,
    marginBottom: spacing.lg,
  },
  featureList: {
    width: '100%',
    gap: spacing.md,
  },
  featureRow: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: colors.surface,
    padding: spacing.md,
    borderRadius: radius.md,
    borderWidth: 1,
    borderColor: colors.border,
  },
  featureEmoji: {
    fontSize: 26,
    marginRight: spacing.md,
  },
  featureTextWrap: {
    flex: 1,
  },
  featureTitle: {
    color: colors.textPrimary,
    fontSize: 15,
    fontWeight: '700',
    marginBottom: 2,
  },
  featureDesc: {
    color: colors.textMuted,
    fontSize: 13,
  },
  bottomSection: {
    width: '100%',
  },
  button: {
    backgroundColor: colors.primary,
    paddingVertical: 16,
    borderRadius: radius.md + 2,
    alignItems: 'center',
    justifyContent: 'center',
    flexDirection: 'row',
    shadowColor: colors.primary,
    shadowOffset: { width: 0, height: 8 },
    shadowOpacity: 0.4,
    shadowRadius: 12,
    elevation: 6,
  },
  buttonPressed: {
    backgroundColor: colors.primaryPressed,
    transform: [{ scale: 0.98 }],
  },
  buttonText: {
    ...typography.button,
    color: '#FFFFFF',
  },
  buttonArrow: {
    color: '#FFFFFF',
    fontSize: 18,
    fontWeight: '700',
    marginLeft: spacing.sm,
  },
  footerText: {
    color: colors.textDim,
    ...typography.caption,
    textAlign: 'center',
    marginTop: spacing.md,
  },
});
