import { useNavigation } from '@react-navigation/native';
import { useState } from 'react';
import { Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { COLORS, RADIUS } from '../config';
import { useAuth } from '../store/AuthContext';

const RULES = [
  { emoji: '🔞', title: '18+', desc: 'Yalnızca 18 yaş üstü kullanıcılar içindir. Onaylamalısın.' },
  { emoji: '🕶️', title: 'Anonim kal', desc: 'Gerçek ad, telefon, adres, sosyal medya paylaşma.' },
  { emoji: '🚫', title: 'Taciz yasak', desc: 'Cinsel içerik, tehdit, nefret söylemi, dolandırıcılık yasaktır.' },
  { emoji: '🛑', title: 'Sıradaki', desc: 'Hoşlanmadığın sohbeti anında bitir. Sınır koymak senin hakkın.' },
  { emoji: '🚨', title: 'Bildir', desc: 'Kural ihlalini gördüğünde bildir. 3 bildirim = otomatik ban.' },
];

export default function RulesScreen() {
  const navigation = useNavigation();
  const { updateUser } = useAuth();
  const [submitting, setSubmitting] = useState(false);

  const accept = async () => {
    if (submitting) return;
    setSubmitting(true);
    try {
      await updateUser({
        ageConfirmed: true,
        rulesAcceptedAt: new Date().toISOString(),
      });
      navigation.navigate('Nickname');
    } catch (err) {
      console.warn('[rules]', err);
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <ScrollView style={styles.container} contentContainerStyle={styles.content}>
      <Text style={styles.title}>Kurallar</Text>
      <Text style={styles.subtitle}>Sohbet edebilmemiz için bunları kabul etmelisin.</Text>

      <View style={styles.list}>
        {RULES.map((r) => (
          <View key={r.title} style={styles.row}>
            <Text style={styles.emoji}>{r.emoji}</Text>
            <View style={styles.rowText}>
              <Text style={styles.rowTitle}>{r.title}</Text>
              <Text style={styles.rowDesc}>{r.desc}</Text>
            </View>
          </View>
        ))}
      </View>

      <Pressable
        style={({ pressed }) => [styles.button, pressed && styles.buttonPressed, submitting && styles.buttonDisabled]}
        onPress={accept}
        disabled={submitting}
      >
        <Text style={styles.buttonText}>{submitting ? 'Kaydediliyor...' : 'Okudum, kabul ediyorum'}</Text>
      </Pressable>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: COLORS.bg },
  content: { padding: 24, paddingTop: 20, paddingBottom: 40 },
  title: { fontSize: 28, fontWeight: '800', color: COLORS.textPrimary },
  subtitle: { fontSize: 14, color: COLORS.textMuted, marginTop: 6, marginBottom: 24 },
  list: { gap: 12 },
  row: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    backgroundColor: COLORS.surface,
    padding: 14,
    borderRadius: RADIUS.md,
    borderWidth: 1,
    borderColor: COLORS.border,
  },
  emoji: { fontSize: 24, marginRight: 12, marginTop: 2 },
  rowText: { flex: 1 },
  rowTitle: { color: COLORS.textPrimary, fontSize: 15, fontWeight: '700' },
  rowDesc: { color: COLORS.textMuted, fontSize: 13, marginTop: 2, lineHeight: 18 },
  button: {
    backgroundColor: COLORS.primary,
    paddingVertical: 16,
    borderRadius: 14,
    alignItems: 'center',
    marginTop: 24,
  },
  buttonPressed: { backgroundColor: COLORS.primaryPressed, transform: [{ scale: 0.98 }] },
  buttonDisabled: { opacity: 0.6 },
  buttonText: { color: '#FFFFFF', fontSize: 16, fontWeight: '700' },
});
