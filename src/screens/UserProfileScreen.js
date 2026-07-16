import { useNavigation, useRoute } from '@react-navigation/native';
import { useCallback, useEffect, useState } from 'react';
import { ActivityIndicator, Alert, Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import Avatar from '../components/Avatar';
import { COLORS, RADIUS } from '../config';
import { api } from '../services/api';
import { useAuth } from '../store/AuthContext';

export default function UserProfileScreen() {
  const navigation = useNavigation();
  const route = useRoute();
  const { user } = useAuth();
  const userId = route.params?.userId;
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [acting, setActing] = useState(false);

  const load = useCallback(async () => {
    if (!userId) return;
    setLoading(true);
    setError(null);
    try {
      const res = await api.getUser(userId);
      setData(res);
    } catch (err) {
      setError(err.message || 'Yüklenemedi');
    } finally {
      setLoading(false);
    }
  }, [userId]);

  useEffect(() => {
    load();
  }, [load]);

  const followToggle = async () => {
    if (!data || acting) return;
    setActing(true);
    try {
      if (data.viewerState.isFollowing) {
        await api.unfollow(data.profile.id);
        setData({
          ...data,
          viewerState: { ...data.viewerState, isFollowing: false },
          stats: { ...data.stats, followerCount: Math.max(0, data.stats.followerCount - 1) },
        });
      } else {
        await api.follow(data.profile.id);
        setData({
          ...data,
          viewerState: { ...data.viewerState, isFollowing: true },
          stats: { ...data.stats, followerCount: data.stats.followerCount + 1 },
        });
      }
    } catch (err) {
      console.warn('[follow]', err);
    } finally {
      setActing(false);
    }
  };

  if (loading) {
    return (
      <View style={styles.center}>
        <ActivityIndicator size="large" color={COLORS.primary} />
      </View>
    );
  }
  if (error || !data) {
    return (
      <View style={styles.center}>
        <Text style={styles.errorText}>Kullanıcı bulunamadı</Text>
      </View>
    );
  }

  const p = data.profile;
  const isMe = p.id === user?.id;
  const showFollowers = data.followInfo !== null;

  return (
    <ScrollView style={styles.container} contentContainerStyle={styles.content}>
      <View style={styles.header}>
        <Avatar
          seed={p.id || p.avatarSeed}
          size={100}
          avatarStyle={p.avatarStyle}
          photoUrl={p.photoUrl}
          containerStyle={{ marginBottom: 12 }}
        />
        <Text style={styles.name}>{p.nickname}</Text>
        {p.username && <Text style={styles.username}>@{p.username}</Text>}
        {p.bio && <Text style={styles.bio}>{p.bio}</Text>}
      </View>

      <View style={styles.stats}>
        <View style={styles.statBox}>
          <Text style={styles.statNum}>{data.stats.followerCount}</Text>
          <Text style={styles.statLabel}>{showFollowers ? 'Takipçi' : 'Takipçi*'}</Text>
        </View>
        <View style={styles.statBox}>
          <Text style={styles.statNum}>{data.stats.followingCount}</Text>
          <Text style={styles.statLabel}>Takip</Text>
        </View>
        <View style={styles.statBox}>
          <Text style={styles.statNum}>{data.viewerState.chatCountWithYou}</Text>
          <Text style={styles.statLabel}>Sohbet</Text>
        </View>
      </View>

      {!showFollowers && (
        <Text style={styles.plusHint}>
          ⚡ Takipçilerini görmek için <Text style={styles.plusLink} onPress={() => navigation.navigate('Paywall')}>Plus Ol</Text>
        </Text>
      )}

      {!isMe && (
        <View style={styles.actions}>
          <Pressable
            style={[styles.actionBtn, data.viewerState.isFollowing ? styles.unfollowBtn : styles.followBtn, acting && styles.disabled]}
            onPress={followToggle}
            disabled={acting}
          >
            <Text style={data.viewerState.isFollowing ? styles.unfollowText : styles.followText}>
              {data.viewerState.isFollowing ? 'Takibi Bırak' : 'Takip Et'}
            </Text>
          </Pressable>

          <Pressable
            style={[styles.actionBtn, styles.chatBtn]}
            onPress={() => navigation.navigate('Matching', { targetUserId: p.id, peer: p })}
          >
            <Text style={styles.chatText}>Mesaj Gönder</Text>
          </Pressable>
        </View>
      )}

      {data.viewerState.chatCountWithYou >= 10 && !data.viewerState.isFriend && !isMe && (
        <Pressable
          style={[styles.actionBtn, styles.friendBtn]}
          onPress={async () => {
            try {
              await api.addFriend(p.id);
              setData({
                ...data,
                viewerState: { ...data.viewerState, isFriend: true },
              });
              Alert.alert('Arkadaş eklendi', `${p.nickname} artık arkadaşın`);
            } catch (err) {
              console.warn('[addFriend]', err);
            }
          }}
        >
          <Text style={styles.friendText}>👥 Arkadaş Ekle</Text>
        </Pressable>
      )}

      {data.viewerState.isFriend && !isMe && (
        <View style={styles.friendBadge}>
          <Text style={styles.friendBadgeText}>✓ Arkadaş</Text>
        </View>
      )}
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: COLORS.bg },
  content: { padding: 20 },
  center: { flex: 1, alignItems: 'center', justifyContent: 'center', backgroundColor: COLORS.bg },
  errorText: { color: COLORS.danger, fontSize: 14 },
  header: { alignItems: 'center', paddingVertical: 16 },
  name: { color: COLORS.textPrimary, fontSize: 22, fontWeight: '800' },
  username: { color: COLORS.textSecondary, fontSize: 13, marginTop: 2 },
  bio: { color: COLORS.textPrimary, fontSize: 14, marginTop: 12, textAlign: 'center', lineHeight: 20 },
  stats: { flexDirection: 'row', gap: 10, marginTop: 20 },
  statBox: {
    flex: 1,
    backgroundColor: COLORS.surface,
    padding: 14,
    borderRadius: RADIUS.md,
    borderWidth: 1,
    borderColor: COLORS.border,
    alignItems: 'center',
  },
  statNum: { color: COLORS.textPrimary, fontSize: 20, fontWeight: '800' },
  statLabel: { color: COLORS.textMuted, fontSize: 11, marginTop: 4 },
  plusHint: { color: COLORS.textMuted, fontSize: 11, textAlign: 'center', marginTop: 10 },
  plusLink: { color: COLORS.primary, fontWeight: '700' },
  actions: { flexDirection: 'row', gap: 10, marginTop: 20 },
  actionBtn: { flex: 1, paddingVertical: 14, borderRadius: RADIUS.md, alignItems: 'center' },
  followBtn: { backgroundColor: COLORS.primary },
  unfollowBtn: { backgroundColor: COLORS.surfaceAlt, borderWidth: 1, borderColor: COLORS.border },
  followText: { color: '#FFFFFF', fontWeight: '700' },
  unfollowText: { color: COLORS.textPrimary, fontWeight: '700' },
  chatBtn: { backgroundColor: COLORS.surfaceAlt, borderWidth: 1, borderColor: COLORS.primary },
  chatText: { color: COLORS.primary, fontWeight: '700' },
  disabled: { opacity: 0.6 },
  friendBtn: { backgroundColor: COLORS.success, paddingVertical: 14, borderRadius: RADIUS.md, alignItems: 'center', marginTop: 12 },
  friendText: { color: '#FFFFFF', fontWeight: '800' },
  friendBadge: { backgroundColor: COLORS.surfaceAlt, padding: 12, borderRadius: RADIUS.md, alignItems: 'center', marginTop: 12, borderWidth: 1, borderColor: COLORS.success },
  friendBadgeText: { color: COLORS.success, fontWeight: '800' },
});