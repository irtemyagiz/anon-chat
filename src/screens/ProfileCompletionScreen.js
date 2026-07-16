import { useNavigation } from '@react-navigation/native';
import * as ImagePicker from 'expo-image-picker';
import { useEffect, useState } from 'react';
import { ActivityIndicator, Image, Pressable, ScrollView, StyleSheet, Switch, Text, TextInput, View } from 'react-native';
import { AVATAR_COLORS, COLORS, RADIUS } from '../config';
import { useInterests } from '../store/InterestsContext';
import { useAuth } from '../store/AuthContext';

const GENDERS = [
  { id: 'male', label: 'Erkek', emoji: '👨' },
  { id: 'female', label: 'Kadın', emoji: '👩' },
  { id: 'other', label: 'Diğer', emoji: '🧑' },
];

export default function ProfileCompletionScreen() {
  const navigation = useNavigation();
  const { user, updateUser } = useAuth();
  const { interests, loading } = useInterests();

  const [photoBase64, setPhotoBase64] = useState(user?.photoBase64 || null);
  const [photoUri, setPhotoUri] = useState(user?.photoUrl || null);
  const [bio, setBio] = useState(user?.bio || '');
  const [gender, setGender] = useState(user?.gender || null);
  const [age, setAge] = useState(user?.age ? String(user.age) : '');
  const [nickname, setNickname] = useState(user?.nickname || '');
  const [avatarColor, setAvatarColor] = useState(user?.avatarColor || AVATAR_COLORS[0]);
  const [anonymityEnabled, setAnonymityEnabled] = useState(user?.anonymityEnabled ?? true);
  const [selectedInterests, setSelectedInterests] = useState(
    Array.isArray(user?.interestIds) ? user.interestIds : []
  );
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState(null);

  useEffect(() => {
    navigation.setOptions({ title: 'Profilini Tamamla' });
  }, [navigation]);

  const pickPhoto = async () => {
    try {
      const perm = await ImagePicker.requestMediaLibraryPermissionsAsync();
      if (!perm.granted) {
        setError('Galeri izni gerekli');
        return;
      }
      const res = await ImagePicker.launchImageLibraryAsync({
        mediaTypes: ImagePicker.MediaTypeOptions.Images,
        allowsEditing: true,
        aspect: [1, 1],
        quality: 0.5,
        base64: true,
      });
      if (!res.canceled && res.assets && res.assets[0]) {
        const a = res.assets[0];
        if (a.base64 && a.base64.length > 2_800_000) {
          setError('Fotoğraf çok büyük (max 2MB)');
          return;
        }
        setPhotoBase64(a.base64);
        setPhotoUri(a.uri);
        setError(null);
      }
    } catch (err) {
      console.warn('[imagePicker]', err);
      setError('Fotoğraf seçilemedi');
    }
  };

  const toggleInterest = (id) => {
    setSelectedInterests((p) => (p.includes(id) ? p.filter((x) => x !== id) : [...p, id]));
  };

  const submit = async () => {
    if (submitting) return;
    if (!nickname.trim()) {
      setError('Rumuz gerekli');
      return;
    }
    if (bio.length > 200) {
      setError('Bio en fazla 200 karakter');
      return;
    }
    if (age) {
      const a = parseInt(age, 10);
      if (!Number.isInteger(a) || a < 18 || a > 99) {
        setError('Yaş 18-99 arası olmalı');
        return;
      }
    }
    setSubmitting(true);
    setError(null);
    try {
      await updateUser({
        nickname: nickname.trim(),
        avatarColor,
        bio: bio.trim() || null,
        gender: gender || null,
        age: age ? parseInt(age, 10) : null,
        anonymityEnabled,
        photoBase64: photoBase64 || null,
        ageConfirmed: true,
        rulesAcceptedAt: new Date().toISOString(),
        interestIds: selectedInterests,
      });
    } catch (err) {
      setError(err.message || 'Kaydedilemedi');
    } finally {
      setSubmitting(false);
    }
  };

  if (loading) {
    return (
      <View style={styles.center}>
        <ActivityIndicator size="large" color={COLORS.primary} />
      </View>
    );
  }

  return (
    <ScrollView style={styles.container} contentContainerStyle={styles.content} keyboardShouldPersistTaps="handled">
      <View style={styles.photoSection}>
        <Pressable style={styles.photoWrap} onPress={pickPhoto}>
          {photoUri ? (
            <Image source={{ uri: photoUri }} style={styles.photo} />
          ) : (
            <View style={[styles.photoPlaceholder, { backgroundColor: avatarColor }]}>
              <Text style={styles.photoLetter}>{(nickname || '?').slice(0, 1).toUpperCase()}</Text>
              <Text style={styles.photoAdd}>+ Foto</Text>
            </View>
          )}
        </Pressable>
        <Text style={styles.hint}>İsteğe bağlı. Eklenmezse rumuzun görünür.</Text>
      </View>

      <Text style={styles.label}>Rumuz</Text>
      <TextInput style={styles.input} value={nickname} onChangeText={setNickname} maxLength={20} placeholder="Görünür isim" placeholderTextColor={COLORS.textDim} />

      <Text style={styles.label}>Avatar rengi (fotoğraf yoksa)</Text>
      <View style={styles.colorRow}>
        {AVATAR_COLORS.map((c) => (
          <Pressable
            key={c}
            style={[styles.colorDot, { backgroundColor: c }, avatarColor === c && styles.colorDotActive]}
            onPress={() => setAvatarColor(c)}
          />
        ))}
      </View>

      <Text style={styles.label}>Bio</Text>
      <TextInput
        style={[styles.input, styles.textarea]}
        value={bio}
        onChangeText={setBio}
        maxLength={200}
        multiline
        placeholder="Kendinden bahset... (max 200)"
        placeholderTextColor={COLORS.textDim}
      />
      <Text style={styles.hint}>{bio.length}/200</Text>

      <Text style={styles.label}>Yaş</Text>
      <TextInput
        style={styles.input}
        value={age}
        onChangeText={(t) => setAge(t.replace(/[^0-9]/g, '').slice(0, 2))}
        placeholder="18-99"
        placeholderTextColor={COLORS.textDim}
        keyboardType="number-pad"
      />

      <Text style={styles.label}>Cinsiyet (opsiyonel)</Text>
      <View style={styles.genderRow}>
        {GENDERS.map((g) => (
          <Pressable
            key={g.id}
            style={[styles.genderBtn, gender === g.id && styles.genderBtnActive]}
            onPress={() => setGender(gender === g.id ? null : g.id)}
          >
            <Text style={styles.genderEmoji}>{g.emoji}</Text>
            <Text style={[styles.genderLabel, gender === g.id && styles.genderLabelActive]}>{g.label}</Text>
          </Pressable>
        ))}
      </View>

      <Text style={styles.label}>İlgi alanların</Text>
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
          <Text style={styles.switchHint}>Açıkken sohbette rumuzun + avatarın görünür. Fotoğrafın gizli kalır.</Text>
        </View>
        <Switch
          value={anonymityEnabled}
          onValueChange={setAnonymityEnabled}
          trackColor={{ true: COLORS.primary, false: COLORS.border }}
          thumbColor="#fff"
        />
      </View>

      {error ? <Text style={styles.error}>{error}</Text> : null}

      <Pressable
        style={({ pressed }) => [styles.button, pressed && styles.buttonPressed, submitting && styles.buttonDisabled]}
        onPress={submit}
        disabled={submitting}
      >
        <Text style={styles.buttonText}>{submitting ? 'Kaydediliyor...' : 'Profili Kaydet'}</Text>
      </Pressable>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: COLORS.bg },
  content: { padding: 20, paddingBottom: 60 },
  center: { flex: 1, alignItems: 'center', justifyContent: 'center', backgroundColor: COLORS.bg },
  photoSection: { alignItems: 'center', marginVertical: 12 },
  photoWrap: { width: 110, height: 110, borderRadius: 55, overflow: 'hidden' },
  photo: { width: 110, height: 110 },
  photoPlaceholder: {
    width: 110,
    height: 110,
    borderRadius: 55,
    alignItems: 'center',
    justifyContent: 'center',
    borderWidth: 2,
    borderColor: COLORS.border,
  },
  photoLetter: { color: '#FFFFFF', fontSize: 40, fontWeight: '800' },
  photoAdd: { color: 'rgba(255,255,255,0.9)', fontSize: 10, fontWeight: '700', marginTop: 2 },
  hint: { color: COLORS.textDim, fontSize: 11, marginTop: 6, textAlign: 'center' },
  label: { color: COLORS.textMuted, fontSize: 12, fontWeight: '700', marginTop: 18, marginBottom: 8, letterSpacing: 0.5 },
  input: {
    backgroundColor: COLORS.surface,
    color: COLORS.textPrimary,
    paddingHorizontal: 14,
    paddingVertical: 12,
    borderRadius: RADIUS.md,
    borderWidth: 1,
    borderColor: COLORS.border,
    fontSize: 16,
  },
  textarea: { minHeight: 80, textAlignVertical: 'top' },
  colorRow: { flexDirection: 'row', flexWrap: 'wrap', gap: 12 },
  colorDot: { width: 36, height: 36, borderRadius: 18, borderWidth: 2, borderColor: 'transparent' },
  colorDotActive: { borderColor: COLORS.textPrimary },
  genderRow: { flexDirection: 'row', gap: 10 },
  genderBtn: {
    flex: 1,
    backgroundColor: COLORS.surface,
    borderRadius: RADIUS.md,
    paddingVertical: 12,
    alignItems: 'center',
    borderWidth: 1,
    borderColor: COLORS.border,
  },
  genderBtnActive: { backgroundColor: COLORS.primary, borderColor: COLORS.primary },
  genderEmoji: { fontSize: 22 },
  genderLabel: { color: COLORS.textSecondary, fontSize: 13, fontWeight: '700', marginTop: 4 },
  genderLabelActive: { color: '#FFFFFF' },
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
  error: { color: COLORS.danger, fontSize: 13, marginTop: 14, textAlign: 'center' },
  button: { backgroundColor: COLORS.primary, paddingVertical: 16, borderRadius: 14, alignItems: 'center', marginTop: 22 },
  buttonPressed: { backgroundColor: COLORS.primaryPressed, transform: [{ scale: 0.98 }] },
  buttonDisabled: { opacity: 0.6 },
  buttonText: { color: '#FFFFFF', fontSize: 16, fontWeight: '700' },
});
