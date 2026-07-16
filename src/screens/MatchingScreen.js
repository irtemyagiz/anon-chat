import { useNavigation, useRoute } from '@react-navigation/native';
import { useEffect, useRef } from 'react';
import { Animated, Easing, Pressable, StyleSheet, Text, View } from 'react-native';
import { COLORS, RADIUS } from '../config';
import { MatchProvider, useMatch } from '../store/MatchContext';

function SearchingInner() {
  const navigation = useNavigation();
  const { status, cancel } = useMatch();
  const spin = useRef(new Animated.Value(0)).current;
  const pulse = useRef(new Animated.Value(0)).current;

  useEffect(() => {
    const rot = Animated.loop(
      Animated.timing(spin, { toValue: 1, duration: 1800, easing: Easing.linear, useNativeDriver: true })
    );
    const pul = Animated.loop(
      Animated.sequence([
        Animated.timing(pulse, { toValue: 1, duration: 900, useNativeDriver: true }),
        Animated.timing(pulse, { toValue: 0, duration: 900, useNativeDriver: true }),
      ])
    );
    rot.start();
    pul.start();
    return () => { rot.stop(); pul.stop(); };
  }, [spin, pulse]);

  useEffect(() => {
    if (status === 'matched') {
      navigation.replace('Chat');
    }
  }, [status, navigation]);

  const onCancel = () => {
    cancel();
    navigation.goBack();
  };

  const rotate = spin.interpolate({ inputRange: [0, 1], outputRange: ['0deg', '360deg'] });
  const scale = pulse.interpolate({ inputRange: [0, 1], outputRange: [1, 1.08] });

  return (
    <View style={styles.container}>
      <View style={styles.center}>
        <Animated.View style={[styles.pulseRing, { transform: [{ scale }] }]} />
        <Animated.View style={[styles.ring, { transform: [{ rotate }] }]}>
          <View style={styles.dot} />
        </Animated.View>
        <Text style={styles.emoji}>🔎</Text>
      </View>

      <Text style={styles.title}>Birini arıyoruz...</Text>
      <Text style={styles.sub}>Bu birkaç saniye sürebilir.</Text>

      <Pressable style={({ pressed }) => [styles.cancelBtn, pressed && styles.cancelBtnPressed]} onPress={onCancel}>
        <Text style={styles.cancelText}>İptal</Text>
      </Pressable>
    </View>
  );
}

export default function MatchingScreen() {
  const route = useRoute();
  const interestIds = route.params?.interestIds || [];
  return (
    <MatchProvider interestIds={interestIds}>
      <SearchingInner />
    </MatchProvider>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: COLORS.bg,
    padding: 24,
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingTop: 120,
    paddingBottom: 60,
  },
  center: { alignItems: 'center', justifyContent: 'center', height: 220 },
  pulseRing: {
    position: 'absolute',
    width: 180,
    height: 180,
    borderRadius: 90,
    backgroundColor: COLORS.primary,
    opacity: 0.15,
  },
  ring: {
    width: 140,
    height: 140,
    borderRadius: 70,
    borderWidth: 4,
    borderColor: COLORS.primary,
    borderTopColor: 'transparent',
    alignItems: 'center',
    justifyContent: 'center',
  },
  dot: {
    position: 'absolute',
    top: -8,
    width: 16,
    height: 16,
    borderRadius: 8,
    backgroundColor: COLORS.primary,
  },
  emoji: { fontSize: 56 },
  title: { color: COLORS.textPrimary, fontSize: 22, fontWeight: '800' },
  sub: { color: COLORS.textMuted, fontSize: 14, marginTop: 6 },
  cancelBtn: {
    paddingVertical: 14,
    paddingHorizontal: 40,
    borderRadius: RADIUS.pill,
    backgroundColor: COLORS.surface,
    borderWidth: 1,
    borderColor: COLORS.border,
  },
  cancelBtnPressed: { backgroundColor: COLORS.surfaceAlt },
  cancelText: { color: COLORS.textSecondary, fontWeight: '700', fontSize: 15 },
});
