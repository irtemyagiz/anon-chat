import { useNavigation } from '@react-navigation/native';
import { useState } from 'react';
import { Pressable, ScrollView, StyleSheet, Text, TextInput, View } from 'react-native';
import { COLORS, RADIUS } from '../config';
import { useAuth } from '../store/AuthContext';

export default function RegisterScreen() {
  const navigation = useNavigation();
  const { register } = useAuth();
  const [email, setEmail] = useState('');
  const [username, setUsername] = useState('');
  const [nickname, setNickname] = useState('');
  const [password, setPassword] = useState('');
  const [confirm, setConfirm] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState(null);

  const submit = async () => {
    setError(null);
    if (!email.trim() || !username || !nickname || !password) {
      setError('Tüm alanları doldur');
      return;
    }
    if (password !== confirm) {
      setError('Şifreler eşleşmiyor');
      return;
    }
    if (password.length < 6) {
      setError('Şifre en az 6 karakter');
      return;
    }
    if (username.length < 3) {
      setError('Kullanıcı adı en az 3 karakter');
      return;
    }
    setSubmitting(true);
    try {
      await register({
        email: email.trim(),
        password,
        username: username.trim(),
        nickname: nickname.trim(),
      });
    } catch (err) {
      const map = {
        invalid_email: 'Geçersiz e-posta',
        weak_password: 'Şifre en az 6 karakter',
        invalid_username: 'Kullanıcı adı 3-30 harf/rakam/_',
        invalid_nickname: 'Rumuz 1-20 karakter',
        email_taken: 'Bu e-posta zaten kayıtlı',
        username_taken: 'Bu kullanıcı adı alınmış',
      };
      setError(map[err.message] || 'Kayıt başarısız');
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <ScrollView style={styles.container} contentContainerStyle={styles.content} keyboardShouldPersistTaps="handled">
      <Text style={styles.title}>Kayıt Ol</Text>
      <Text style={styles.subtitle}>Yeni hesap oluştur</Text>

      <Text style={styles.label}>E-posta</Text>
      <TextInput style={styles.input} value={email} onChangeText={setEmail} placeholder="ornek@mail.com" placeholderTextColor={COLORS.textDim} autoCapitalize="none" keyboardType="email-address" />

      <Text style={styles.label}>Kullanıcı adı</Text>
      <TextInput style={styles.input} value={username} onChangeText={(t) => setUsername(t.toLowerCase().replace(/[^a-z0-9_]/g, ''))} placeholder="@kullanici" placeholderTextColor={COLORS.textDim} autoCapitalize="none" maxLength={30} />
      <Text style={styles.hint}>3-30 harf, rakam veya alt çizgi. Başkaları görecek.</Text>

      <Text style={styles.label}>Rumuz (takma ad)</Text>
      <TextInput style={styles.input} value={nickname} onChangeText={setNickname} placeholder="Görünür isim" placeholderTextColor={COLORS.textDim} maxLength={20} />

      <Text style={styles.label}>Şifre</Text>
      <TextInput style={styles.input} value={password} onChangeText={setPassword} placeholder="En az 6 karakter" placeholderTextColor={COLORS.textDim} secureTextEntry />

      <Text style={styles.label}>Şifre (tekrar)</Text>
      <TextInput style={styles.input} value={confirm} onChangeText={setConfirm} placeholder="Aynı şifre" placeholderTextColor={COLORS.textDim} secureTextEntry />

      {error ? <Text style={styles.error}>{error}</Text> : null}

      <Pressable style={({ pressed }) => [styles.button, pressed && styles.buttonPressed, submitting && styles.buttonDisabled]} onPress={submit} disabled={submitting}>
        <Text style={styles.buttonText}>{submitting ? 'Kayıt yapılıyor...' : 'Kayıt Ol'}</Text>
      </Pressable>

      <Pressable onPress={() => navigation.navigate('Login')}>
        <Text style={styles.linkText}>
          Zaten hesabın var mı? <Text style={styles.linkAccent}>Giriş Yap</Text>
        </Text>
      </Pressable>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: COLORS.bg },
  content: { padding: 24, paddingTop: 16, paddingBottom: 40 },
  title: { color: COLORS.textPrimary, fontSize: 28, fontWeight: '800', marginBottom: 4 },
  subtitle: { color: COLORS.textMuted, fontSize: 14, marginBottom: 20 },
  label: { color: COLORS.textMuted, fontSize: 12, fontWeight: '700', marginBottom: 6, marginTop: 14, letterSpacing: 0.5 },
  hint: { color: COLORS.textDim, fontSize: 11, marginTop: 4 },
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
  button: { backgroundColor: COLORS.primary, paddingVertical: 16, borderRadius: 14, alignItems: 'center', marginTop: 24 },
  buttonPressed: { backgroundColor: COLORS.primaryPressed, transform: [{ scale: 0.98 }] },
  buttonDisabled: { opacity: 0.6 },
  buttonText: { color: '#FFFFFF', fontSize: 16, fontWeight: '700' },
  linkText: { color: COLORS.textMuted, fontSize: 14, textAlign: 'center', marginTop: 18 },
  linkAccent: { color: COLORS.primary, fontWeight: '700' },
});
