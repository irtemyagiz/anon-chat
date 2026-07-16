import { useNavigation } from '@react-navigation/native';
import { useState } from 'react';
import { Pressable, StyleSheet, Text, TextInput, View } from 'react-native';
import { COLORS, RADIUS } from '../config';
import { useAuth } from '../store/AuthContext';

export default function LoginScreen() {
  const navigation = useNavigation();
  const { login } = useAuth();
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState(null);

  const submit = async () => {
    if (submitting) return;
    setError(null);
    if (!email.trim() || !password) {
      setError('E-posta ve şifre gerekli');
      return;
    }
    setSubmitting(true);
    try {
      await login(email.trim(), password);
    } catch (err) {
      const map = {
        invalid_credentials: 'E-posta veya şifre hatalı',
        missing_credentials: 'Alanları doldur',
      };
      setError(map[err.message] || 'Giriş başarısız');
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <View style={styles.container}>
      <View style={styles.header}>
        <Text style={styles.logo}>💬</Text>
        <Text style={styles.title}>Tekrar hoş geldin</Text>
        <Text style={styles.subtitle}>Hesabınla devam et</Text>
      </View>

      <View style={styles.form}>
        <Text style={styles.label}>E-posta</Text>
        <TextInput
          style={styles.input}
          value={email}
          onChangeText={setEmail}
          placeholder="ornek@mail.com"
          placeholderTextColor={COLORS.textDim}
          autoCapitalize="none"
          keyboardType="email-address"
          autoComplete="email"
        />
        <Text style={styles.label}>Şifre</Text>
        <TextInput
          style={styles.input}
          value={password}
          onChangeText={setPassword}
          placeholder="••••••••"
          placeholderTextColor={COLORS.textDim}
          secureTextEntry
          autoComplete="password"
        />

        {error ? <Text style={styles.error}>{error}</Text> : null}

        <Pressable
          style={({ pressed }) => [styles.button, pressed && styles.buttonPressed, submitting && styles.buttonDisabled]}
          onPress={submit}
          disabled={submitting}
        >
          <Text style={styles.buttonText}>{submitting ? 'Giriş yapılıyor...' : 'Giriş Yap'}</Text>
        </Pressable>

        <Pressable onPress={() => navigation.navigate('Register')}>
          <Text style={styles.linkText}>
            Hesabın yok mu? <Text style={styles.linkAccent}>Kayıt Ol</Text>
          </Text>
        </Pressable>
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: COLORS.bg, padding: 24, justifyContent: 'center' },
  header: { alignItems: 'center', marginBottom: 36 },
  logo: { fontSize: 56, marginBottom: 12 },
  title: { color: COLORS.textPrimary, fontSize: 28, fontWeight: '800' },
  subtitle: { color: COLORS.textMuted, fontSize: 14, marginTop: 6 },
  form: {},
  label: { color: COLORS.textMuted, fontSize: 12, fontWeight: '700', marginBottom: 6, marginTop: 12, letterSpacing: 0.5 },
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
  error: { color: COLORS.danger, fontSize: 13, marginTop: 14, textAlign: 'center' },
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
  linkText: { color: COLORS.textMuted, fontSize: 14, textAlign: 'center', marginTop: 20 },
  linkAccent: { color: COLORS.primary, fontWeight: '700' },
});
