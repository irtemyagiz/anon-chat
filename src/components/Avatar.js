import { Image, View } from 'react-native';

export const AVATAR_STYLES = [
  { id: 'adventurer', label: 'Maceracı', emoji: '🧝' },
  { id: 'avataaars', label: 'Klasik', emoji: '👤' },
  { id: 'bottts', label: 'Robot', emoji: '🤖' },
  { id: 'fun-emoji', label: 'Emoji', emoji: '😀' },
  { id: 'micah', label: 'Minimal', emoji: '◽' },
  { id: 'personas', label: 'İllüstrasyon', emoji: '🎨' },
  { id: 'thumbs', label: 'Parmak', emoji: '👍' },
  { id: 'shapes', label: 'Şekiller', emoji: '🔷' },
];

export const DEFAULT_AVATAR_STYLE = 'adventurer';

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
  ...props
}) {
  const url =
    photoUrl ||
    avatarUrl({
      seed: seed || 'anon',
      style: avatarStyle || DEFAULT_AVATAR_STYLE,
      size: size * 2,
    });

  if (photoUrl) {
    return (
      <Image
        source={{ uri: photoUrl }}
        style={[
          { width: size, height: size, borderRadius: size / 2, backgroundColor },
          containerStyle,
        ]}
        {...props}
      />
    );
  }

  return (
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
      {...props}
    >
      <Image
        source={{ uri: url }}
        style={{ width: size, height: size }}
        resizeMode="cover"
      />
    </View>
  );
}