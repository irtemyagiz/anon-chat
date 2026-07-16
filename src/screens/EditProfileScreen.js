import { useNavigation } from '@react-navigation/native';
import { useEffect, useState } from 'react';
import { Pressable, ScrollView, StyleSheet, Switch, Text, TextInput, View } from 'react-native';
import Avatar, { AVATAR_STYLES, DEFAULT_AVATAR_STYLE } from '../components/Avatar';
import { COLORS, RADIUS } from '../config';
import { useAuth } from '../store/AuthContext';
import { useInterests } from '../store/InterestsContext';

export default function EditProfileScreen() {
  const navigation = useNavigation();
  const { user, updateUser, logout } = useAuth();
  const { interests } = useInterests();
  const [bio, setBio] = useState(user?.bio || '');
  const [anonymityEnabled, setAnonymityEnabled] = useState(user?.anonymityEnabled ?? true);
  const [avatarStyle, setAvatarStyle] = useState(user?.avatarStyle || DEFAULT_AVATAR_STYLE);
  const [selectedInterests, setSelectedInterests] = useState(
    Array.isArray(user?.interestIds) ? user.interestIds : []
  );
  const [saving, setSaving] = useState(false);
  const [savedAt, setSavedAt] = useState(null);

  useEffect(() => {
    navigation.setOptions({ title: 'Profili Düzenle' });
  }, [navigation]);

  const toggleInterest = (id) => {
    setSelectedInterests((p) => (p.includes(id) ? p.filter((x) => x !== id) : [...p, id]));
  };

  const save = async () => {
    if (saving) return;
    setSaving(true);
    try {
      await updateUser({
        bio: bio.trim() || null,
        anonymityEnabled,
        avatarStyle,
        interestIds: selectedInterests,
      });
      setSavedAt(Date.now());
    } catch (err) {
      console.warn('[save]', err);
    } finally {
      setSaving(false);
    }
  };

  const onLogout = async () => {
    await logout();
  };

  return (
    <ScrollView style={styles.container} contentContainerStyle={styles.content}>
      <View style={styles.header}>
        <Avatar
          seed={user?.id || user?.avatarSeed}
          size={90}
          avatarStyle={avatarStyle}
          photoUrl={user?.photoUrl}
        />
        <Text style={styles.name}>{user?.nickname}</Text>
        {user?.username && <Text style={styles.username}>@{user?.username}</Text>}
      </View>

      <Text style={styles.label}>Avatar Stili</Text>
      <View style={styles.styleGrid}>
        {AVATAR_STYLES.map((s) => (
          <Pressable
            key={s.id}
            style={[styles.styleCard, avatarStyle === s.id && styles.styleCardActive]}
            onPress={() => setAvatarStyle(s.id)}
          >
            <Avatar seed={user?.id || 'me'} size={48} avatarStyle={s.id} />
            <Text style={[styles.styleLabel, avatarStyle === s.id && styles.styleLabelActive]}>
              {s.label}
            </Text>
          </Pressable>
        ))}
      </View>

      <Text style={styles.label}>Bio</Text>
      <TextInput
        style={styles.textarea}
        value={bio}
        onChangeText={setBio}
        multiline
        maxLength={200}
        placeholder="Kendinden bahset..."
        placeholderTextColor={COLORS.textDim}
      />
      <Text style={styles.hint}>{bio.length}/200</Text>

      <Text style={styles.label}>İlgi Alanların</Text>
      <View style={styles.interestGrid}>
        {interests.map((it) => {
          const active = selectedInterests.includes(it.id);
          return (
            <Pressable
              key={it.id}
              style={[styles.pill, active && styles.pillActive]}
              onPress={() => toggleInterest(it.id)}
            >
              <Text style={styles.pillEmoji}>{it.emoji}</Text>
              <Text style={[styles.pillText, active && styles.pillTextActive]}>{it.nameTr}</Text>
            </Pressable>
          );
        })}
      </View>

      <View style={styles.switchRow}>
        <View style={{ flex: 1 }}>
          <Text style={styles.switchLabel}>Anonim Mod</Text>
          <Text style={styles.switchHint}>Açıkken sohbette rumuzun görünür, fotoğrafın gizli.</Text>
        </View>
        <Switch
          value={anonymityEnabled}
          onValueChange={setAnonymityEnabled}
          trackColor={{ true: COLORS.primary, false: COLORS.border }}
          thumbColor="#fff"
        />
      </View>

      <Pressable
        style={({ pressed }) => [styles.saveBtn, pressed && styles.savePressed, saving && styles.disabled]}
        onPress={save}
        disabled={saving}
      >
        <Text style={styles.saveText}>{saving ? 'Kaydediliyor...' : 'Kaydet'}</Text>
      </Pressable>

      {savedAt && <Text style={styles.savedHint}>✓ Kaydedildi</Text>}

      {!user?.isPlus && (
        <Pressable style={styles.upgradeBtn} onPress={() => navigation.navigate('Paywall')}>
          <Text style={styles.upgradeText}>⚡ Plus'a Geç</Text>
        </Pressable>
      )}

      <Pressable onPress={onLogout} style={styles.logoutBtn}>
        <Text style={styles.logoutText}>Çıkış Yap</Text>
      </Pressable>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: COLORS.bg },
  content: { padding: 20, paddingBottom: 60 },
  header: { alignItems: 'center', marginVertical: 16 },
  name: { color: COLORS.textPrimary, fontSize: 20, fontWeight: '800', marginTop: 12 },
  username: { color: COLORS.textSecondary, fontSize: 13, marginTop: 2 },
  label: { color: COLORS.textMuted, fontSize: 12, fontWeight: '700', marginTop: 18, marginBottom: 8, letterSpacing: 0.5 },
  textarea: {
    backgroundColor: COLORS.surface,
    color: COLORS.textPrimary,
    padding: 14,
    borderRadius: RADIUS.md,
    borderWidth: 1,
    borderColor: COLORS.border,
    fontSize: 14,
    minHeight: 80,
    textAlignVertical: 'top',
  },
  hint: { color: COLORS.textDim, fontSize: 11, marginTop: 4, textAlign: 'right' },
  styleGrid: { flexDirection: 'row', flexWrap: 'wrap', gap: 8 },
  styleCard: {
    width: 70,
    paddingVertical: 8,
    alignItems: 'center',
    backgroundColor: COLORS.surface,
    borderRadius: RADIUS.md,
    borderWidth: 2,
    borderColor: COLORS.border,
  },
  styleCardActive: { borderColor: COLORS.primary, backgroundColor: COLORS.surfaceAlt },
  styleLabel: { color: COLORS.textMuted, fontSize: 10, fontWeight: '700', marginTop: 4 },
  styleLabelActive: { color: COLORS.primary },
  interestGrid: { flexDirection: 'row', flexWrap: 'wrap', gap: 8 },
  pill: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 12,
    paddingVertical: 8,
    borderRadius: RADIUS.pill,
    backgroundColor: COLORS.surface,
    borderWidth: 1,
    borderColor: COLORS.border,
  },
  pillActive: { backgroundColor: COLORS.primary, borderColor: COLORS.primary },
  pillEmoji: { fontSize: 14, marginRight: 5 },
  pillText: { color: COLORS.textSecondary, fontSize: 13, fontWeight: '600' },
  pillTextActive: { color: '#FFFFFF' },
  switchRow: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: COLORS.surface,
    padding: 14,
    borderRadius: RADIUS.md,
    borderWidth: 1,
    borderColor: COLORS.border,
    marginTop: 20,
  },
  switchLabel: { color: COLORS.textPrimary, fontSize: 15, fontWeight: '700' },
  switchHint: { color: COLORS.textMuted, fontSize: 11, marginTop: 2, lineHeight: 16 },
  saveBtn: { backgroundColor: COLORS.primary, paddingVertical: 14, borderRadius: 14, alignItems: 'center', marginTop: 24 },
  savePressed: { backgroundColor: COLORS.primaryPressed, transform: [{ scale: 0.98 }] },
  disabled: { opacity: 0.6 },
  saveText: { color: '#FFFFFF', fontSize: 16, fontWeight: '700' },
  savedHint: { color: COLORS.success, fontSize: 13, textAlign: 'center', marginTop: 10 },
  upgradeBtn: { backgroundColor: COLORS.surface, borderWidth: 1, borderColor: COLORS.primary, padding: 14, borderRadius: RADIUS.md, alignItems: 'center', marginTop: 18 },
  upgradeText: { color: COLORS.primary, fontWeight: '800', fontSize: 15 },
  logoutBtn: { padding: 14, alignItems: 'center', marginTop: 8 },
  logoutText: { color: COLORS.danger, fontWeight: '700' },
});