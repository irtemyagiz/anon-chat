import { useNavigation } from '@react-navigation/native';
import { Pressable, StyleSheet, Text, View } from 'react-native';
import { COLORS, RADIUS } from '../config';

export default function WelcomeScreen() {
  const navigation = useNavigation();

  return (
    <View style={styles.container}>
      <View style={styles.top}>
        <View style={styles.logo}>
          <Text style={styles.logoEmoji}>💬</Text>
        </View>
        <View style={styles.badge}>
          <View style={styles.badgeDot} />
          <Text style={styles.badgeText}>Anonim • Anlık • Özgür</Text>
        </View>
      </View>

      <View style={styles.middle}>
        <Text style={styles.title}>Anonim Chat</Text>
        <Text style={styles.subtitle}>Kimliğin gizli, sohbet özgür.</Text>
      </View>

      <View style={styles.bottom}>
        <Pressable
          style={({ pressed }) => [styles.button, pressed && styles.buttonPressed]}
          onPress={() => navigation.navigate('Rules')}
        >
          <Text style={styles.buttonText}>Başla</Text>
        </Pressable>
        <Text style={styles.footer}>
          Devam ederek kullanım koşullarını ve 18+ yaş sınırını kabul edersin.
        </Text>
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: COLORS.bg,
    paddingHorizontal: 24,
    paddingTop: 100,
    paddingBottom: 40,
    justifyContent: 'space-between',
  },
  top: { alignItems: 'center' },
  logo: {
    width: 110,
    height: 110,
    borderRadius: 55,
    backgroundColor: COLORS.surface,
    alignItems: 'center',
    justifyContent: 'center',
    borderWidth: 2,
    borderColor: COLORS.primary,
    shadowColor: COLORS.primary,
    shadowOpacity: 0.5,
    shadowRadius: 16,
    shadowOffset: { width: 0, height: 0 },
    elevation: 6,
  },
  logoEmoji: { fontSize: 52 },
  badge: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: COLORS.surface,
    paddingHorizontal: 14,
    paddingVertical: 6,
    borderRadius: RADIUS.pill,
    marginTop: 18,
    borderWidth: 1,
    borderColor: COLORS.border,
  },
  badgeDot: {
    width: 8,
    height: 8,
    borderRadius: 4,
    backgroundColor: COLORS.success,
    marginRight: 8,
  },
  badgeText: { color: COLORS.textSecondary, fontSize: 13, fontWeight: '600' },
  middle: { alignItems: 'center' },
  title: {
    fontSize: 40,
    fontWeight: '800',
    color: COLORS.textPrimary,
    letterSpacing: 0.5,
  },
  subtitle: {
    fontSize: 17,
    color: COLORS.textSecondary,
    marginTop: 10,
    textAlign: 'center',
  },
  bottom: { width: '100%' },
  button: {
    backgroundColor: COLORS.primary,
    paddingVertical: 16,
    borderRadius: 14,
    alignItems: 'center',
  },
  buttonPressed: { backgroundColor: COLORS.primaryPressed, transform: [{ scale: 0.98 }] },
  buttonText: { color: '#FFFFFF', fontSize: 17, fontWeight: '700' },
  footer: {
    color: COLORS.textDim,
    fontSize: 12,
    textAlign: 'center',
    marginTop: 16,
  },
});
