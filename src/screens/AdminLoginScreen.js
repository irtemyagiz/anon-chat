import { useNavigation } from '@react-navigation/native';
import { useState } from 'react';
import { ActivityIndicator, Alert, Pressable, StyleSheet, Text, TextInput, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { COLORS, RADIUS } from '../config';
import { api } from '../services/api';

export default function AdminLoginScreen() {
  const navigation = useNavigation();
  const [email, setEmail] = useState('admin@admin.com');
  const [password, setPassword] = useState('20042005Qq!q');
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState(null);

  const submit = async () => {
    if (submitting) return;
    setError(null);
    setSubmitting(true);
    try {
      const res = await api.adminLogin(email, password);
      navigation.replace('AdminDashboard', { token: res.token });
    } catch (err) {
      setError('Giriş başarısız');
      Alert.alert('Hata', 'Email veya şifre hatalı');
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <SafeAreaView style={styles.container} edges={['top', 'left', 'right', 'bottom']}>
      <View style={styles.card}>
        <Text style={styles.title}>🔒 Admin Girişi</Text>
        <Text style={styles.subtitle}>Yönetim paneline erişmek için giriş yap</Text>

        <Text style={styles.label}>E-posta</Text>
        <TextInput
          style={styles.input}
          value={email}
          onChangeText={setEmail}
          autoCapitalize="none"
          keyboardType="email-address"
          placeholderTextColor={COLORS.textDim}
        />

        <Text style={styles.label}>Şifre</Text>
        <TextInput
          style={styles.input}
          value={password}
          onChangeText={setPassword}
          secureTextEntry
          placeholderTextColor={COLORS.textDim}
        />

        {error ? <Text style={styles.error}>{error}</Text> : null}

        <Pressable
          style={({ pressed }) => [styles.button, pressed && styles.buttonPressed, submitting && styles.buttonDisabled]}
          onPress={submit}
          disabled={submitting}
        >
          {submitting ? (
            <ActivityIndicator color="#fff" />
          ) : (
            <Text style={styles.buttonText}>Giriş Yap</Text>
          )}
        </Pressable>

        <Pressable onPress={() => navigation.goBack()}>
          <Text style={styles.backLink}>← Geri</Text>
        </Pressable>
      </View>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: COLORS.bg, padding: 24, justifyContent: 'center' },
  card: { backgroundColor: COLORS.surface, padding: 24, borderRadius: RADIUS.lg, borderWidth: 1, borderColor: COLORS.border },
  title: { color: COLORS.textPrimary, fontSize: 24, fontWeight: '800', textAlign: 'center' },
  subtitle: { color: COLORS.textMuted, fontSize: 13, marginTop: 6, textAlign: 'center', marginBottom: 24 },
  label: { color: COLORS.textMuted, fontSize: 12, fontWeight: '700', marginBottom: 6, marginTop: 12, letterSpacing: 0.5 },
  input: {
    backgroundColor: COLORS.surfaceAlt,
    color: COLORS.textPrimary,
    paddingHorizontal: 14,
    paddingVertical: 14,
    borderRadius: RADIUS.md,
    borderWidth: 1,
    borderColor: COLORS.border,
    fontSize: 16,
  },
  error: { color: COLORS.danger, fontSize: 13, marginTop: 14, textAlign: 'center' },
  button: { backgroundColor: COLORS.danger, paddingVertical: 16, borderRadius: 14, alignItems: 'center', marginTop: 24 },
  buttonPressed: { backgroundColor: '#DC2626', transform: [{ scale: 0.98 }] },
  buttonDisabled: { opacity: 0.6 },
  buttonText: { color: '#FFFFFF', fontSize: 16, fontWeight: '700' },
  backLink: { color: COLORS.textMuted, fontSize: 14, textAlign: 'center', marginTop: 18, fontWeight: '700' },
});