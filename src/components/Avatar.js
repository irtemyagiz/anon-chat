import { Image, Text, View } from 'react-native';

export const AVATAR_STYLES = [
  { id: 'classic', label: 'Klasik', emoji: '👤' },
  { id: 'modern', label: 'Modern', emoji: '🧑' },
  { id: 'casual', label: 'Rahat', emoji: '😎' },
  { id: 'elegant', label: 'Şık', emoji: '🤵' },
  { id: 'artistic', label: 'Sanatsal', emoji: '🎨' },
  { id: 'friendly', label: 'Samimi', emoji: '😊' },
  { id: 'mystery', label: 'Gizemli', emoji: '🌙' },
  { id: 'playful', label: 'Eğlenceli', emoji: '🎉' },
];

export const DEFAULT_AVATAR_STYLE = 'classic';

export function defaultStyleForGender(gender) {
  if (gender === 'female') return 'elegant';
  if (gender === 'male') return 'classic';
  return DEFAULT_AVATAR_STYLE;
}

export function avatarUrl({ seed, style = DEFAULT_AVATAR_STYLE, gender, size = 200 }) {
  if (!seed) seed = 'anon';

  const params = new URLSearchParams({
    seed: String(seed),
    size: String(size),
  });

  let dicebearStyle = 'micah';
  if (gender === 'female') {
    params.set('facialHairProbability', '0');
    params.set('hairProbability', '80');
  } else if (gender === 'male') {
    params.set('facialHairProbability', '70');
    params.set('facialHair', 'beard01');
  } else {
    params.set('facialHairProbability', '30');
  }

  const i = style ? style.charCodeAt(0) % 3 : 0;
  if (gender === 'female') {
    const styles = ['micah', 'micah', 'micah'];
    dicebearStyle = styles[i];
  } else if (gender === 'male') {
    const styles = ['micah', 'micah', 'micah'];
    dicebearStyle = styles[i];
  } else {
    dicebearStyle = 'micah';
  }

  return `https://api.dicebear.com/7.x/${dicebearStyle}/png?${params.toString()}`;
}

export default function Avatar({
  seed = 'anon',
  size = 64,
  avatarStyle = DEFAULT_AVATAR_STYLE,
  photoUrl,
  backgroundColor,
  containerStyle,
  isPlus = false,
  showPlusBadge = false,
  gender,
  ...props
}) {
  const url =
    photoUrl ||
    avatarUrl({
      seed: seed || 'anon',
      style: avatarStyle,
      gender,
      size: size * 2,
    });

  const padding = isPlus ? 3 : 0;
  const ringSize = size + padding * 2;

  const inner = photoUrl ? (
    <Image
      source={{ uri: photoUrl }}
      style={{
        width: size,
        height: size,
        borderRadius: size / 2,
        backgroundColor,
      }}
    />
  ) : (
    <Image
      source={{ uri: url }}
      style={{ width: size, height: size, borderRadius: size / 2 }}
      resizeMode="cover"
    />
  );

  const wrapped = (
    <View
      style={[
        {
          width: size,
          height: size,
          borderRadius: size / 2,
          overflow: 'hidden',
          backgroundColor: backgroundColor || '#1E2638',
        },
        containerStyle,
      ]}
    >
      {inner}
      {showPlusBadge && !isPlus && (
        <View
          style={{
            position: 'absolute',
            right: -2,
            bottom: -2,
            width: 16,
            height: 16,
            borderRadius: 8,
            backgroundColor: '#6366F1',
            alignItems: 'center',
            justifyContent: 'center',
            borderWidth: 1.5,
            borderColor: '#0F172A',
          }}
        >
          <Text style={{ color: '#fff', fontSize: 9, fontWeight: '900' }}>+</Text>
        </View>
      )}
    </View>
  );

  if (!isPlus) return wrapped;

  return (
    <View
      style={[
        {
          width: ringSize,
          height: ringSize,
          borderRadius: ringSize / 2,
          padding,
          backgroundColor: '#FFD700',
          alignItems: 'center',
          justifyContent: 'center',
          shadowColor: '#FFD700',
          shadowOpacity: 0.6,
          shadowRadius: 8,
          shadowOffset: { width: 0, height: 0 },
          elevation: 6,
        },
        containerStyle,
      ]}
      {...props}
    >
      {wrapped}
    </View>
  );
}