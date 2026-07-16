import { useNavigation } from '@react-navigation/native';
import { useState } from 'react';
import { Pressable, ScrollView, StyleSheet, Text, TextInput, View } from 'react-native';
import { AVATAR_COLORS, COLORS, RADIUS } from '../config';
import { useAuth } from '../store/AuthContext';

const STARTER_NICKNAMES = [
  'Yalnız Tilki', 'Sessiz Kedi', 'Gece Kuşu', 'Gölge',
  'Gezgin', 'Sis', 'Mavi Ay', 'Kutup Yıldızı',
  'Yankı', 'Buz', 'Sis Perdesi', 'Kuzey Rüzgarı',
];

export default function NicknameScreen() {
  const navigation = useNavigation();
  const { user, updateUser } = useAuth();
  const [nickname, setNickname] = useState(
    user?.nickname && !user.nickname.startsWith('Anon') ? user.nickname : ''
  );
  const [color, setColor] = useState(user?.avatarColor || AVATAR_COLORS[0]);
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState(null);

  const submit = async () => {
    const trimmed = nickname.trim();
    if (trimmed.length < 1) {
      setError('Bir rumuz yaz');
      return;
    }
    if (trimmed.length > 20) {
      setError('En fazla 20 karakter');
      return;
    }
    setSubmitting(true);
    setError(null);
    try {
      await updateUser({ nickname: trimmed, avatarColor: color });
      navigation.navigate('InterestsSelect');
    } catch (err) {
      setError(err.message || 'Kaydedilemedi');
    } finally {
      setSubmitting(false);
    }
  };

  const pickStarter = (name) => {
    setNickname(name);
    setError(null);
  };

  return (
    <ScrollView style={styles.container} contentContainerStyle={styles.content} keyboardShouldPersistTaps="handled">
      <Text style={styles.title}>Rumuzunu seç</Text>
      <Text style={styles.subtitle}>Sana nasıl hitap edelim? Gerçek adını kullanma.</Text>

      <View style={styles.previewRow}>
        <View style={[styles.previewAvatar, { backgroundColor: color }]}>
          <Text style={styles.previewLetter}>{(nickname.trim()[0] || '?').toUpperCase()}</Text>
        </View>
        <View style={{ flex: 1 }}>
          <Text style={styles.previewName}>{nickname.trim() || '...'}</Text>
          <Text style={styles.previewMeta}>Rastgele avatar</Text>
        </View>
      </View>

      <TextInput
        style={styles.input}
        value={nickname}
        onChangeText={(t) => { setNickname(t); setError(null); }}
        placeholder="Rumuz yaz..."
        placeholderTextColor={COLORS.textDim}
        maxLength={20}
        autoCapitalize="words"
      />

      <Text style={styles.label}>Veya hazır birini seç:</Text>
      <View style={styles.starterGrid}>
        {STARTER_NICKNAMES.map((n) => (
          <Pressable
            key={n}
            style={({ pressed }) => [
              styles.starterPill,
              nickname === n && styles.starterPillActive,
              pressed && { opacity: 0.7 },
            ]}
            onPress={() => pickStarter(n)}
          >
            <Text style={[styles.starterText, nickname === n && styles.starterTextActive]}>{n}</Text>
          </Pressable>
        ))}
      </View>

      <Text style={styles.label}>Avatar rengi:</Text>
      <View style={styles.colorRow}>
        {AVATAR_COLORS.map((c) => (
          <Pressable
            key={c}
            style={[styles.colorDot, { backgroundColor: c }, color === c && styles.colorDotActive]}
            onPress={() => setColor(c)}
          />
        ))}
      </View>

      {error ? <Text style={styles.errorText}>{error}</Text> : null}

      <Pressable
        style={({ pressed }) => [styles.button, pressed && styles.buttonPressed, submitting && styles.buttonDisabled]}
        onPress={submit}
        disabled={submitting}
      >
        <Text style={styles.buttonText}>{submitting ? 'Kaydediliyor...' : 'Devam'}</Text>
      </Pressable>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: COLORS.bg },
  content: { padding: 24, paddingBottom: 40 },
  title: { fontSize: 26, fontWeight: '800', color: COLORS.textPrimary },
  subtitle: { fontSize: 14, color: COLORS.textMuted, marginTop: 6 },
  previewRow: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: COLORS.surface,
    padding: 14,
    borderRadius: RADIUS.md,
    borderWidth: 1,
    borderColor: COLORS.border,
    marginTop: 20,
    marginBottom: 18,
  },
  previewAvatar: {
    width: 52,
    height: 52,
    borderRadius: 26,
    alignItems: 'center',
    justifyContent: 'center',
    marginRight: 14,
  },
  previewLetter: { color: '#FFFFFF', fontSize: 22, fontWeight: '800' },
  previewName: { color: COLORS.textPrimary, fontSize: 17, fontWeight: '700' },
  previewMeta: { color: COLORS.textMuted, fontSize: 12, marginTop: 2 },
  input: {
    backgroundColor: COLORS.surface,
    color: COLORS.textPrimary,
    paddingHorizontal: 14,
    paddingVertical: 14,
    borderRadius: RADIUS.md,
    borderWidth: 1,
    borderColor: COLORS.border,
    fontSize: 16,
  },
  label: { color: COLORS.textMuted, fontSize: 13, marginTop: 18, marginBottom: 8 },
  starterGrid: { flexDirection: 'row', flexWrap: 'wrap', gap: 8 },
  starterPill: {
    paddingHorizontal: 14,
    paddingVertical: 8,
    borderRadius: RADIUS.pill,
    backgroundColor: COLORS.surface,
    borderWidth: 1,
    borderColor: COLORS.border,
  },
  starterPillActive: { backgroundColor: COLORS.primary, borderColor: COLORS.primary },
  starterText: { color: COLORS.textSecondary, fontSize: 13, fontWeight: '600' },
  starterTextActive: { color: '#FFFFFF' },
  colorRow: { flexDirection: 'row', flexWrap: 'wrap', gap: 12, marginTop: 4 },
  colorDot: {
    width: 36,
    height: 36,
    borderRadius: 18,
    borderWidth: 2,
    borderColor: 'transparent',
  },
  colorDotActive: { borderColor: COLORS.textPrimary },
  errorText: { color: COLORS.danger, fontSize: 13, marginTop: 14, textAlign: 'center' },
  button: {
    backgroundColor: COLORS.primary,
    paddingVertical: 16,
    borderRadius: 14,
    alignItems: 'center',
    marginTop: 28,
  },
  buttonPressed: { backgroundColor: COLORS.primaryPressed, transform: [{ scale: 0.98 }] },
  buttonDisabled: { opacity: 0.6 },
  buttonText: { color: '#FFFFFF', fontSize: 16, fontWeight: '700' },
});
