import { useNavigation } from '@react-navigation/native';
import { useEffect, useState } from 'react';
import * as ImagePicker from 'expo-image-picker';
import { ActivityIndicator, Pressable, ScrollView, StyleSheet, Switch, Text, TextInput, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import Avatar, { AVATAR_STYLES, defaultStyleForGender } from '../components/Avatar';
import { COLORS, RADIUS } from '../config';
import { useAuth } from '../store/AuthContext';
import { useInterests } from '../store/InterestsContext';

export default function EditProfileScreen() {
  const navigation = useNavigation();
  const { user, updateUser, logout } = useAuth();
  const { interests } = useInterests();
  const [bio, setBio] = useState(user?.bio || '');
  const [photoBase64, setPhotoBase64] = useState(user?.photoBase64 || null);
  const [photoUri, setPhotoUri] = useState(user?.photoUrl || null);
  const [anonymityEnabled, setAnonymityEnabled] = useState(user?.anonymityEnabled ?? true);
  const [avatarStyle, setAvatarStyle] = useState(user?.avatarStyle || defaultStyleForGender(user?.gender));
  const [selectedInterests, setSelectedInterests] = useState(
    Array.isArray(user?.interestIds) ? user.interestIds : []
  );
  const [saving, setSaving] = useState(false);
  const [savedAt, setSavedAt] = useState(null);
  const [uploadingPhoto, setUploadingPhoto] = useState(false);

  useEffect(() => {
    navigation.setOptions({ title: 'Profili Düzenle' });
  }, [navigation]);

  const pickPhoto = async () => {
    try {
      const perm = await ImagePicker.requestMediaLibraryPermissionsAsync();
      if (!perm.granted) return;
      const res = await ImagePicker.launchImageLibraryAsync({
        mediaTypes: [ImagePicker.MediaType.Images],
        allowsEditing: true,
        aspect: [1, 1],
        quality: 0.5,
        base64: true,
      });
      if (!res.canceled && res.assets && res.assets[0]) {
        const a = res.assets[0];
        if (a.base64 && a.base64.length > 2_800_000) return;
        setPhotoBase64(a.base64);
        setPhotoUri(a.uri);
      }
    } catch (err) {
      console.warn('[imagePicker]', err);
    }
  };

  const removePhoto = () => {
    setPhotoBase64(null);
    setPhotoUri(null);
  };

  const toggleInterest = (id) => {
    setSelectedInterests((p) => (p.includes(id) ? p.filter((x) => x !== id) : [...p, id]));
  };

  const save = async () => {
    if (saving) return;
    setSaving(true);
    try {
      const patch = {
        bio: bio.trim() || null,
        anonymityEnabled,
        avatarStyle,
        interestIds: selectedInterests,
      };
      if (photoBase64 !== user?.photoBase64) {
        patch.photoBase64 = photoBase64 || null;
      }
      await updateUser(patch);
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

  const visibleStyles = AVATAR_STYLES;

  return (
    <SafeAreaView style={styles.safe} edges={['top', 'left', 'right']}>
      <ScrollView style={styles.container} contentContainerStyle={styles.content}>
        <View style={styles.header}>
        <View style={styles.photoWrap}>
          {uploadingPhoto && <ActivityIndicator size="small" color={COLORS.primary} style={{ position: 'absolute', zIndex: 2 }} />}
          <Avatar
            seed={`preview_${avatarStyle}`}
            size={90}
            avatarStyle={avatarStyle}
            gender={user?.gender}
            photoUrl={photoUri}
            isPlus={!!user?.isPlus}
          />
        </View>
        <Text style={styles.name}>{user?.nickname}</Text>
        {user?.username && <Text style={styles.username}>@{user?.username}</Text>}
        <View style={styles.photoActions}>
          <Pressable style={styles.photoBtn} onPress={pickPhoto}>
            <Text style={styles.photoBtnText}>{photoUri ? '📷 Fotoğrafı Değiştir' : '📷 Fotoğraf Ekle'}</Text>
          </Pressable>
          {photoUri && (
            <Pressable style={styles.removeBtn} onPress={removePhoto}>
              <Text style={styles.removeBtnText}>× Kaldır</Text>
            </Pressable>
          )}
        </View>
      </View>

      <Text style={styles.label}>Avatar Stili {user?.gender && `(${user.gender === 'female' ? 'Kadın' : 'Erkek'})`}</Text>
      <View style={styles.styleGrid}>
        {AVATAR_STYLES.map((s) => (
          <Pressable
            key={s.id}
            style={[styles.styleCard, avatarStyle === s.id && styles.styleCardActive]}
            onPress={() => setAvatarStyle(s.id)}
          >
            <Avatar seed={`preview_${s.id}`} size={48} avatarStyle={s.id} gender={user?.gender} />
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
          <Text style={styles.switchLabel}>Anonim Mod (varsayılan)</Text>
          <Text style={styles.switchHint}>Açıkken sohbette rumuzun görünür. Sohbet içinde özel olarak açabilirsin.</Text>
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

      <Pressable onPress={onLogout} style={styles.logoutBtn}>
        <Text style={styles.logoutText}>Çıkış Yap</Text>
      </Pressable>

      <Pressable
        style={styles.adminBtn}
        onPress={() => navigation.navigate('AdminLogin')}
      >
        <Text style={styles.adminBtnText}>🔒 Admin Paneli</Text>
      </Pressable>
    </ScrollView>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  safe: { flex: 1, backgroundColor: COLORS.bg },
  container: { flex: 1, backgroundColor: COLORS.bg },
  content: { padding: 20, paddingBottom: 60 },
  header: { alignItems: 'center', marginVertical: 16 },
  photoWrap: { marginBottom: 8 },
  name: { color: COLORS.textPrimary, fontSize: 20, fontWeight: '800', marginTop: 12 },
  username: { color: COLORS.textSecondary, fontSize: 13, marginTop: 2 },
  photoActions: { flexDirection: 'row', gap: 8, marginTop: 12 },
  photoBtn: { backgroundColor: COLORS.surface, paddingHorizontal: 14, paddingVertical: 8, borderRadius: RADIUS.pill, borderWidth: 1, borderColor: COLORS.border },
  photoBtnText: { color: COLORS.textPrimary, fontSize: 12, fontWeight: '700' },
  removeBtn: { backgroundColor: COLORS.surface, paddingHorizontal: 14, paddingVertical: 8, borderRadius: RADIUS.pill, borderWidth: 1, borderColor: COLORS.danger },
  removeBtnText: { color: COLORS.danger, fontSize: 12, fontWeight: '700' },
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
  logoutBtn: { padding: 14, alignItems: 'center', marginTop: 8 },
  logoutText: { color: COLORS.danger, fontWeight: '700' },
  adminBtn: {
    marginTop: 18,
    backgroundColor: COLORS.surface,
    padding: 14,
    borderRadius: RADIUS.md,
    alignItems: 'center',
    borderWidth: 1,
    borderColor: COLORS.border,
  },
  adminBtnText: { color: COLORS.textMuted, fontWeight: '700', fontSize: 13 },
});