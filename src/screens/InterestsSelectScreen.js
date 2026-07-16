import { useNavigation } from '@react-navigation/native';
import { useEffect, useState } from 'react';
import { ActivityIndicator, Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { COLORS, RADIUS } from '../config';
import { useInterests } from '../store/InterestsContext';
import { useAuth } from '../store/AuthContext';

export default function InterestsSelectScreen() {
  const navigation = useNavigation();
  const { interests, loading, error: loadError } = useInterests();
  const { updateUser } = useAuth();
  const [selected, setSelected] = useState(
    Array.isArray(user?.interestIds) ? user.interestIds : []
  );
  const [submitting, setSubmitting] = useState(false);
  const [submitError, setSubmitError] = useState(null);

  useEffect(() => {
    navigation.setOptions({
      headerRight: () => (
        <Pressable onPress={() => navigation.replace('Home')}>
          <Text style={{ color: COLORS.textSecondary, fontWeight: '700' }}>Atla</Text>
        </Pressable>
      ),
    });
  }, [navigation]);

  const toggle = (id) => {
    setSelected((prev) =>
      prev.includes(id) ? prev.filter((x) => x !== id) : [...prev, id]
    );
    setSubmitError(null);
  };

  const submit = async () => {
    if (submitting) return;
    if (selected.length < 1) {
      setSubmitError('En az 1 ilgi seç (veya Atla de)');
      return;
    }
    setSubmitting(true);
    setSubmitError(null);
    try {
      await updateUser({ interestIds: selected });
      navigation.replace('Home');
    } catch (err) {
      setSubmitError(err.message || 'Kaydedilemedi');
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
    <ScrollView style={styles.container} contentContainerStyle={styles.content}>
      <Text style={styles.title}>İlgi alanların</Text>
      <Text style={styles.subtitle}>
        Seçtiğin etiketlere göre benzer ilgi alanlı kişilerle eşleşirsin.
      </Text>

      {loadError ? <Text style={styles.errorText}>Yüklenemedi: {loadError}</Text> : null}

      <View style={styles.grid}>
        {interests.map((it) => {
          const active = selected.includes(it.id);
          return (
            <Pressable
              key={it.id}
              style={[styles.pill, active && styles.pillActive]}
              onPress={() => toggle(it.id)}
            >
              <Text style={styles.pillEmoji}>{it.emoji}</Text>
              <Text style={[styles.pillText, active && styles.pillTextActive]}>
                {it.nameTr}
              </Text>
            </Pressable>
          );
        })}
      </View>

      <Text style={styles.counter}>{selected.length} seçili</Text>

      {submitError ? <Text style={styles.errorText}>{submitError}</Text> : null}

      <Pressable
        style={({ pressed }) => [styles.button, pressed && styles.buttonPressed, submitting && styles.buttonDisabled]}
        onPress={submit}
        disabled={submitting}
      >
        <Text style={styles.buttonText}>{submitting ? 'Kaydediliyor...' : 'Bitir ve Başla'}</Text>
      </Pressable>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: COLORS.bg },
  content: { padding: 24, paddingBottom: 40 },
  center: { flex: 1, alignItems: 'center', justifyContent: 'center', backgroundColor: COLORS.bg },
  title: { fontSize: 26, fontWeight: '800', color: COLORS.textPrimary },
  subtitle: { fontSize: 14, color: COLORS.textMuted, marginTop: 6, marginBottom: 20, lineHeight: 20 },
  grid: { flexDirection: 'row', flexWrap: 'wrap', gap: 10 },
  pill: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 14,
    paddingVertical: 10,
    borderRadius: RADIUS.pill,
    backgroundColor: COLORS.surface,
    borderWidth: 1,
    borderColor: COLORS.border,
  },
  pillActive: { backgroundColor: COLORS.primary, borderColor: COLORS.primary },
  pillEmoji: { fontSize: 16, marginRight: 6 },
  pillText: { color: COLORS.textSecondary, fontSize: 14, fontWeight: '600' },
  pillTextActive: { color: '#FFFFFF' },
  counter: { color: COLORS.textMuted, fontSize: 13, marginTop: 16, textAlign: 'right' },
  errorText: { color: COLORS.danger, fontSize: 13, marginTop: 12, textAlign: 'center' },
  button: {
    backgroundColor: COLORS.primary,
    paddingVertical: 16,
    borderRadius: 14,
    alignItems: 'center',
    marginTop: 22,
  },
  buttonPressed: { backgroundColor: COLORS.primaryPressed, transform: [{ scale: 0.98 }] },
  buttonDisabled: { opacity: 0.6 },
  buttonText: { color: '#FFFFFF', fontSize: 16, fontWeight: '700' },
});
