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

const MALE_PARAMS = {
  facialHairProbability: 80,
  facialHair: 'beardMajestic',
};

const FEMALE_STYLES = ['lorelei'];
const MALE_STYLES = ['avataaars'];

function pickDicebearStyle(label, gender) {
  if (gender === 'female') {
    const i = label.charCodeAt(0) % FEMALE_STYLES.length;
    return FEMALE_STYLES[i];
  }
  if (gender === 'male') {
    const i = label.charCodeAt(0) % MALE_STYLES.length;
    return MALE_STYLES[i];
  }
  return 'adventurer';
}

export function avatarUrl({ seed, style = DEFAULT_AVATAR_STYLE, gender, size = 200 }) {
  if (!seed) seed = 'anon';
  const effectiveStyle = pickDicebearStyle(style, gender);

  let url;
  if (gender === 'female') {
    url = `https://api.dicebear.com/7.x/${effectiveStyle}/png?seed=${encodeURIComponent(seed)}&size=${size}`;
  } else if (gender === 'male') {
    const params = new URLSearchParams({
      seed,
      size: String(size),
      facialHairProbability: String(MALE_PARAMS.facialHairProbability),
      facialHair: MALE_PARAMS.facialHair,
    });
    url = `https://api.dicebear.com/7.x/${effectiveStyle}/png?${params.toString()}`;
  } else {
    const s = AVATAR_STYLES.find((x) => x.id === style) ? style : DEFAULT_AVATAR_STYLE;
    const dbStyle = pickDicebearStyle(s, undefined);
    url = `https://api.dicebear.com/7.x/${dbStyle}/png?seed=${encodeURIComponent(seed)}&size=${size}`;
  }
  return url;
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