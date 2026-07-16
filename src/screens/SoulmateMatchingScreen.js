import { useNavigation } from '@react-navigation/native';
import { useEffect, useRef, useState } from 'react';
import { ActivityIndicator, Animated, Easing, Pressable, StyleSheet, Text, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { COLORS, RADIUS } from '../config';
import { api } from '../services/api';
import { getSocket } from '../services/socket';
import { MatchProvider, useMatch } from '../store/MatchContext';

function SearchingInner() {
  const navigation = useNavigation();
  const { status, mode, cancel, peer, roomId, startSoulmate } = useMatch();
  const [limit, setLimit] = useState(null);
  const [isPlus, setIsPlus] = useState(false);
  const [hasStarted, setHasStarted] = useState(false);
  const [showReSearch, setShowReSearch] = useState(false);
  const socket = getSocket();
  const spin = useRef(new Animated.Value(0)).current;
  const pulse = useRef(new Animated.Value(0)).current;
  const orb1 = useRef(new Animated.Value(0)).current;
  const orb2 = useRef(new Animated.Value(0)).current;

  useEffect(() => {
    api.soulmateStatus().then((s) => {
      setIsPlus(!!s.isPlus);
      setLimit(s.remaining);
      // Auto-start when limit available (or Plus)
      if ((s.isPlus || s.remaining > 0) && !hasStarted) {
        const socket = getSocket();
        if (socket && socket.connected) {
          startSoulmate();
          setHasStarted(true);
        }
      }
    }).catch(() => {});
  }, [hasStarted, startSoulmate]);

  useEffect(() => {
    if (!socket) return;
    const onConnect = () => {
      if (!hasStarted && (isPlus || (limit !== null && limit > 0))) {
        startSoulmate();
        setHasStarted(true);
      }
    };
    socket.on('connect', onConnect);
    return () => socket.off('connect', onConnect);
  }, [hasStarted, isPlus, limit, startSoulmate]);

  useEffect(() => {
    const t = setTimeout(() => {
      if (status === 'waiting' && hasStarted) {
        setShowReSearch(true);
      }
    }, 20000);
    return () => clearTimeout(t);
  }, [status, hasStarted]);

  useEffect(() => {
    if (status !== 'matched' || !roomId || !peer) return;
    navigation.replace('Chat', { peer, roomId });
  }, [status, roomId, peer, navigation]);

  useEffect(() => {
    const rot = Animated.loop(
      Animated.timing(spin, { toValue: 1, duration: 1800, easing: Easing.linear, useNativeDriver: true })
    );
    const pul = Animated.loop(
      Animated.sequence([
        Animated.timing(pulse, { toValue: 1, duration: 1400, useNativeDriver: true }),
        Animated.timing(pulse, { toValue: 0, duration: 1400, useNativeDriver: true }),
      ])
    );
    const o1 = Animated.loop(
      Animated.timing(orb1, { toValue: 1, duration: 3000, easing: Easing.inOut(Easing.ease), useNativeDriver: true })
    );
    const o2 = Animated.loop(
      Animated.timing(orb2, { toValue: 1, duration: 4000, easing: Easing.inOut(Easing.ease), useNativeDriver: true })
    );
    rot.start(); pul.start(); o1.start(); o2.start();
    return () => { rot.stop(); pul.stop(); o1.stop(); o2.stop(); };
  }, [spin, pulse, orb1, orb2]);

  const onCancel = () => {
    cancel();
    navigation.goBack();
  };

  if (isPlus || (limit !== null && limit > 0)) {
    const rotate = spin.interpolate({ inputRange: [0, 1], outputRange: ['0deg', '360deg'] });
    const scale = pulse.interpolate({ inputRange: [0, 1], outputRange: [1, 1.1] });
    const o1y = orb1.interpolate({ inputRange: [0, 1], outputRange: [0, -20] });
    const o2y = orb2.interpolate({ inputRange: [0, 1], outputRange: [0, 20] });

    return (
      <SafeAreaView style={styles.safe} edges={['top', 'left', 'right']}>
        <View style={styles.container}>
          <View style={styles.topBar}>
            <Text style={styles.brand}>💫 Ruh Eşi</Text>
            <Pressable onPress={onCancel} style={styles.cancelMini}>
              <Text style={styles.cancelMiniText}>İptal</Text>
            </Pressable>
          </View>

          <View style={styles.center}>
            <Animated.View style={[styles.orbBig, { transform: [{ scale }] }]} />
            <Animated.View style={[styles.orbMid, { transform: [{ translateY: o1y }] }]} />
            <Animated.View style={[styles.orbSmall, { transform: [{ translateY: o2y }] }]} />
            <Animated.View style={[styles.ring, { transform: [{ rotate }] }]}>
              <View style={styles.dot} />
            </Animated.View>
            <Text style={styles.emoji}>💫</Text>
          </View>

          <View style={styles.bottom}>
            <Text style={styles.title}>Ruh eşin aranıyor...</Text>
            <Text style={styles.sub}>Anonim eşleşme, karşılıklı güvenli.</Text>

            {!isPlus && limit !== null && (
              <View style={styles.limitPill}>
                <Text style={styles.limitPillText}>Bugün {limit}/5 ruh eşi hakkın kaldı</Text>
              </View>
            )}
            {isPlus && (
              <View style={[styles.limitPill, styles.plusPill]}>
                <Text style={[styles.limitPillText, styles.plusPillText]}>⚡ Plus · Sınırsız ruh eşi</Text>
              </View>
            )}

            <Pressable style={styles.cancelBtn} onPress={onCancel}>
              <Text style={styles.cancelText}> Aramayı İptal Et </Text>
            </Pressable>

            {showReSearch && (
              <Pressable style={styles.retryBtn} onPress={() => { setShowReSearch(false); startSoulmate(); }}>
                <Text style={styles.retryText}>🔄 Tekrar Dene</Text>
              </Pressable>
            )}

            <Text style={styles.waitingHint}>
              {status === 'waiting'
                ? showReSearch
                  ? 'Eşleşme bulunamadı. Tekrar deneyin veya bekleyin.'
                  : 'Eşleşme aranıyor...'
                : ''}
            </Text>
          </View>
        </View>
      </SafeAreaView>
    );
  }

  return (
    <SafeAreaView style={styles.safe} edges={['top', 'left', 'right']}>
      <View style={styles.center}>
        <Text style={styles.bigEmoji}>💫</Text>
        <Text style={styles.title}>Günlük ruh eşi limitin doldu</Text>
        <Text style={styles.sub}>Bugün 5 hakkını kullandın. Sınırsız eşleşme için Plus'a geç.</Text>
        <Pressable style={styles.cta} onPress={() => navigation.replace('Paywall')}>
          <Text style={styles.ctaText}>⚡ Plus'a Geç</Text>
        </Pressable>
        <Pressable style={styles.cancelBtn} onPress={() => navigation.goBack()}>
          <Text style={styles.cancelText}> Geri Dön </Text>
        </Pressable>
      </View>
    </SafeAreaView>
  );
}

export default function SoulmateMatchingScreen() {
  return (
    <MatchProvider>
      <SearchingInner />
    </MatchProvider>
  );
}

const styles = StyleSheet.create({
  safe: { flex: 1, backgroundColor: COLORS.bg },
  container: { flex: 1, padding: 20 },
  topBar: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', paddingVertical: 8 },
  brand: { color: COLORS.textPrimary, fontSize: 20, fontWeight: '800' },
  cancelMini: { paddingHorizontal: 14, paddingVertical: 8, borderRadius: RADIUS.pill, backgroundColor: COLORS.surface, borderWidth: 1, borderColor: COLORS.border },
  cancelMiniText: { color: COLORS.textSecondary, fontWeight: '700' },
  center: { flex: 1, alignItems: 'center', justifyContent: 'center' },
  bigEmoji: { fontSize: 80 },
  title: { color: COLORS.textPrimary, fontSize: 22, fontWeight: '800', marginTop: 16, marginBottom: 8, textAlign: 'center' },
  sub: { color: COLORS.textMuted, fontSize: 14, marginBottom: 12, textAlign: 'center', lineHeight: 20, paddingHorizontal: 24 },
  limitPill: {
    backgroundColor: COLORS.surface,
    paddingHorizontal: 14,
    paddingVertical: 8,
    borderRadius: RADIUS.pill,
    marginTop: 12,
    borderWidth: 1,
    borderColor: COLORS.border,
  },
  plusPill: { backgroundColor: COLORS.primary, borderColor: COLORS.primary },
  limitPillText: { color: COLORS.textSecondary, fontSize: 13, fontWeight: '700' },
  plusPillText: { color: '#FFFFFF' },
  cta: { backgroundColor: COLORS.primary, paddingHorizontal: 32, paddingVertical: 14, borderRadius: RADIUS.pill, marginTop: 16 },
  ctaText: { color: '#FFFFFF', fontWeight: '800', fontSize: 16 },
  cancelBtn: { paddingVertical: 14, marginTop: 12 },
  cancelText: { color: COLORS.textMuted, fontWeight: '700' },
  retryBtn: {
    backgroundColor: COLORS.surfaceAlt,
    paddingVertical: 12,
    paddingHorizontal: 24,
    borderRadius: RADIUS.pill,
    marginTop: 12,
    borderWidth: 1,
    borderColor: '#EC4899',
  },
  retryText: { color: '#EC4899', fontWeight: '800', fontSize: 14 },
  waitingHint: { color: COLORS.textMuted, fontSize: 12, marginTop: 14, textAlign: 'center', fontStyle: 'italic' },
  orbBig: {
    position: 'absolute',
    width: 240,
    height: 240,
    borderRadius: 120,
    backgroundColor: '#EC4899',
    opacity: 0.12,
  },
  orbMid: {
    position: 'absolute',
    width: 160,
    height: 160,
    borderRadius: 80,
    backgroundColor: '#8B5CF6',
    opacity: 0.18,
  },
  orbSmall: {
    position: 'absolute',
    width: 100,
    height: 100,
    borderRadius: 50,
    backgroundColor: '#EC4899',
    opacity: 0.25,
  },
  ring: {
    width: 140,
    height: 140,
    borderRadius: 70,
    borderWidth: 4,
    borderColor: '#EC4899',
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
    backgroundColor: '#EC4899',
  },
  emoji: { fontSize: 60, position: 'absolute' },
  bottom: { alignItems: 'center', paddingBottom: 30 },
});