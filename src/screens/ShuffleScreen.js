import { useNavigation } from '@react-navigation/native';
import { useCallback, useEffect, useState } from 'react';
import { ActivityIndicator, Pressable, StyleSheet, Text, View } from 'react-native';
import Avatar from '../components/Avatar';
import { COLORS, RADIUS } from '../config';
import { api } from '../services/api';
import { useAuth } from '../store/AuthContext';

export default function ShuffleScreen() {
  const navigation = useNavigation();
  const { user } = useAuth();
  const [profile, setProfile] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [remaining, setRemaining] = useState(null);
  const [isPlus, setIsPlus] = useState(user?.isPlus || false);

  const loadNext = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await api.shuffleNext();
      setProfile(res.user);
      setRemaining(res.remaining);
      setIsPlus(res.isPlus);
    } catch (err) {
      if (err.message === 'daily_limit_reached') {
        setError('limit');
        setRemaining(0);
      } else {
        setError(err.message || 'Hata');
      }
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    loadNext();
  }, [loadNext]);

  if (loading) {
    return (
      <View style={styles.center}>
        <ActivityIndicator size="large" color={COLORS.primary} />
      </View>
    );
  }

  if (error === 'limit') {
    return (
      <View style={styles.center}>
        <Text style={styles.emoji}>⚡</Text>
        <Text style={styles.title}>Günlük limit doldu</Text>
        <Text style={styles.sub}>Bugün 50 kişi gördün. Yarın tekrar gel!</Text>
        <Text style={styles.sub}>Sınırsız shuffle için Plus'a geç.</Text>
        <Pressable style={styles.cta} onPress={() => navigation.navigate('Paywall')}>
          <Text style={styles.ctaText}>Plus Ol</Text>
        </Pressable>
        <Pressable onPress={loadNext} style={styles.secondaryBtn}>
          <Text style={styles.secondaryText}>Yine de dene</Text>
        </Pressable>
      </View>
    );
  }

  if (!profile) {
    return (
      <View style={styles.center}>
        <Text style={styles.emoji}>😴</Text>
        <Text style={styles.title}>Şu an kimse yok</Text>
        <Text style={styles.sub}>Biraz sonra tekrar dene.</Text>
        <Pressable onPress={loadNext} style={styles.cta}>
          <Text style={styles.ctaText}>Yenile</Text>
        </Pressable>
      </View>
    );
  }

  return (
    <View style={styles.container}>
      <View style={styles.counter}>
        {!isPlus && remaining !== null && (
          <Text style={styles.counterText}>
            Bugün <Text style={styles.counterNum}>{remaining}</Text> hakkın kaldı
          </Text>
        )}
        {isPlus && (
          <View style={styles.plusBadge}>
            <Text style={styles.plusBadgeText}>⚡ Plus Aktif</Text>
          </View>
        )}
      </View>

      <View style={styles.card}>
        <Avatar
          seed={profile.id || profile.avatarSeed}
          size={200}
          avatarStyle={profile.avatarStyle}
          photoUrl={profile.photoUrl}
          containerStyle={{ alignSelf: 'center' }}
        />

        <View style={styles.cardInfo}>
          <View style={styles.nameRow}>
            <Text style={styles.name}>{profile.nickname}</Text>
            {profile.age && <Text style={styles.age}>{profile.age}</Text>}
          </View>
          {profile.username && <Text style={styles.username}>@{profile.username}</Text>}
          {profile.bio && <Text style={styles.bio}>{profile.bio}</Text>}
        </View>

        <View style={styles.actionRow}>
          <Pressable style={[styles.actionBtn, styles.skipBtn]} onPress={loadNext}>
            <Text style={styles.actionText}>Sıradaki</Text>
          </Pressable>
          <Pressable
            style={[styles.actionBtn, styles.chatBtn]}
            onPress={() => navigation.navigate('UserProfile', { userId: profile.id })}
          >
            <Text style={styles.actionTextWhite}>Sohbet</Text>
          </Pressable>
        </View>
      </View>

      <Pressable onPress={loadNext} style={styles.refreshBtn}>
        <Text style={styles.refreshText}>🔄 Yenile</Text>
      </Pressable>
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: COLORS.bg, padding: 20 },
  center: { flex: 1, alignItems: 'center', justifyContent: 'center', backgroundColor: COLORS.bg, padding: 24 },
  emoji: { fontSize: 64, marginBottom: 12 },
  title: { color: COLORS.textPrimary, fontSize: 22, fontWeight: '800', marginBottom: 8 },
  sub: { color: COLORS.textMuted, fontSize: 14, textAlign: 'center', marginBottom: 4 },
  counter: { alignItems: 'center', marginVertical: 12 },
  counterText: { color: COLORS.textMuted, fontSize: 13 },
  counterNum: { color: COLORS.textPrimary, fontWeight: '800' },
  plusBadge: { backgroundColor: COLORS.primary, paddingHorizontal: 12, paddingVertical: 4, borderRadius: RADIUS.pill },
  plusBadgeText: { color: '#FFFFFF', fontSize: 12, fontWeight: '800' },
  card: { flex: 1, backgroundColor: COLORS.surface, borderRadius: RADIUS.lg, padding: 16, marginVertical: 12, borderWidth: 1, borderColor: COLORS.border, alignItems: 'center' },
  cardInfo: { paddingHorizontal: 4, width: '100%', marginTop: 14 },
  nameRow: { flexDirection: 'row', alignItems: 'baseline', gap: 8, justifyContent: 'center' },
  name: { color: COLORS.textPrimary, fontSize: 22, fontWeight: '800' },
  age: { color: COLORS.textMuted, fontSize: 18, fontWeight: '600' },
  username: { color: COLORS.textSecondary, fontSize: 13, marginTop: 2, textAlign: 'center' },
  bio: { color: COLORS.textPrimary, fontSize: 14, marginTop: 10, lineHeight: 20, textAlign: 'center' },
  actionRow: { flexDirection: 'row', gap: 10, marginTop: 16, width: '100%' },
  actionBtn: { flex: 1, paddingVertical: 14, borderRadius: RADIUS.md, alignItems: 'center' },
  skipBtn: { backgroundColor: COLORS.surfaceAlt, borderWidth: 1, borderColor: COLORS.border },
  chatBtn: { backgroundColor: COLORS.primary },
  actionText: { color: COLORS.textPrimary, fontWeight: '700' },
  actionTextWhite: { color: '#FFFFFF', fontWeight: '700' },
  refreshBtn: { alignItems: 'center', paddingVertical: 14 },
  refreshText: { color: COLORS.textSecondary, fontWeight: '700' },
  cta: { backgroundColor: COLORS.primary, paddingVertical: 14, paddingHorizontal: 40, borderRadius: RADIUS.pill, marginTop: 20 },
  ctaText: { color: '#FFFFFF', fontWeight: '800', fontSize: 16 },
  secondaryBtn: { paddingVertical: 12, marginTop: 8 },
  secondaryText: { color: COLORS.textMuted, fontWeight: '700' },
});