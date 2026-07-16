import { NavigationContainer, DefaultTheme } from '@react-navigation/native';
import { createNativeStackNavigator } from '@react-navigation/native-stack';
import { ActivityIndicator, StyleSheet, View } from 'react-native';
import { COLORS } from '../config';
import { useAuth } from '../store/AuthContext';
import WelcomeScreen from '../screens/WelcomeScreen';
import RulesScreen from '../screens/RulesScreen';
import NicknameScreen from '../screens/NicknameScreen';
import InterestsSelectScreen from '../screens/InterestsSelectScreen';
import HomeScreen from '../screens/HomeScreen';
import MatchingScreen from '../screens/MatchingScreen';
import ChatScreen from '../screens/ChatScreen';
import PostChatScreen from '../screens/PostChatScreen';
import ProfileScreen from '../screens/ProfileScreen';

const Stack = createNativeStackNavigator();

const navTheme = {
  ...DefaultTheme,
  dark: true,
  colors: {
    ...DefaultTheme.colors,
    background: COLORS.bg,
    card: COLORS.surface,
    text: COLORS.textPrimary,
    border: COLORS.border,
    primary: COLORS.primary,
    notification: COLORS.danger,
  },
};

const screenOptions = {
  headerStyle: { backgroundColor: COLORS.surface },
  headerTintColor: COLORS.textPrimary,
  contentStyle: { backgroundColor: COLORS.bg },
};

function Splash() {
  return (
    <View style={styles.splash}>
      <ActivityIndicator size="large" color={COLORS.primary} />
    </View>
  );
}

function isOnboardingDone(user) {
  if (!user) return false;
  if (!user.ageConfirmed) return false;
  if (!user.rulesAcceptedAt) return false;
  if (!user.nickname || user.nickname.startsWith('Anon')) return false;
  return true;
}

function getInitialMainRoute(user) {
  if (user && Array.isArray(user.interestIds) && user.interestIds.length > 0) {
    return 'Home';
  }
  return 'InterestsSelect';
}

export default function AppNavigator() {
  const { bootstrapping, user } = useAuth();

  if (bootstrapping) return <Splash />;

  const onboardingDone = isOnboardingDone(user);

  return (
    <NavigationContainer theme={navTheme}>
      {!onboardingDone ? (
        <Stack.Navigator key="onboarding" initialRouteName="Welcome" screenOptions={screenOptions}>
          <Stack.Screen name="Welcome" component={WelcomeScreen} options={{ headerShown: false }} />
          <Stack.Screen name="Rules" component={RulesScreen} options={{ title: '' }} />
          <Stack.Screen name="Nickname" component={NicknameScreen} options={{ title: 'Rumuz Seç' }} />
        </Stack.Navigator>
      ) : (
        <Stack.Navigator
          key="main"
          initialRouteName={getInitialMainRoute(user)}
          screenOptions={screenOptions}
        >
          <Stack.Screen name="InterestsSelect" component={InterestsSelectScreen} options={{ title: 'İlgi Alanların' }} />
          <Stack.Screen name="Home" component={HomeScreen} options={{ headerShown: false }} />
          <Stack.Screen name="Matching" component={MatchingScreen} options={{ headerShown: false }} />
          <Stack.Screen name="Chat" component={ChatScreen} options={{ headerShown: false }} />
          <Stack.Screen name="PostChat" component={PostChatScreen} options={{ title: '' }} />
          <Stack.Screen name="Profile" component={ProfileScreen} options={{ title: 'Profil' }} />
        </Stack.Navigator>
      )}
    </NavigationContainer>
  );
}

const styles = StyleSheet.create({
  splash: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: COLORS.bg,
  },
});
