import { Image, Text, View } from 'react-native';

export const AVATAR_STYLES = [
  { id: 'avataaars', label: 'Klasik', emoji: '👤', gender: 'male' },
  { id: 'lorelei', label: 'Lorelei', emoji: '💁‍♀️', gender: 'female' },
  { id: 'adventurer', label: 'Maceracı', emoji: '🧝', gender: 'any' },
  { id: 'bottts', label: 'Robot', emoji: '🤖', gender: 'any' },
  { id: 'fun-emoji', label: 'Emoji', emoji: '😀', gender: 'any' },
  { id: 'micah', label: 'Minimal', emoji: '◽', gender: 'any' },
  { id: 'personas', label: 'İllüstrasyon', emoji: '🎨', gender: 'any' },
  { id: 'thumbs', label: 'Parmak', emoji: '👍', gender: 'any' },
  { id: 'shapes', label: 'Şekiller', emoji: '🔷', gender: 'any' },
];

export const DEFAULT_AVATAR_STYLE = 'adventurer';

export function defaultStyleForGender(gender) {
  if (gender === 'female') return 'lorelei';
  if (gender === 'male') return 'avataaars';
  return DEFAULT_AVATAR_STYLE;
}

export function avatarUrl({ seed, style = DEFAULT_AVATAR_STYLE, size = 200 }) {
  if (!seed) seed = 'anon';
  const s = AVATAR_STYLES.find((x) => x.id === style) ? style : DEFAULT_AVATAR_STYLE;
  return `https://api.dicebear.com/7.x/${s}/png?seed=${encodeURIComponent(seed)}&size=${size}`;
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
  ...props
}) {
  const url =
    photoUrl ||
    avatarUrl({
      seed: seed || 'anon',
      style: avatarStyle || DEFAULT_AVATAR_STYLE,
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