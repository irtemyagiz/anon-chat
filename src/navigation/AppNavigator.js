import { NavigationContainer, DefaultTheme } from '@react-navigation/native';
import { createNativeStackNavigator } from '@react-navigation/native-stack';
import { createBottomTabNavigator } from '@react-navigation/bottom-tabs';
import { ActivityIndicator, StyleSheet, Text, View } from 'react-native';
import { COLORS } from '../config';
import { useAuth } from '../store/AuthContext';

import LoginScreen from '../screens/LoginScreen';
import RegisterScreen from '../screens/RegisterScreen';
import ProfileCompletionScreen from '../screens/ProfileCompletionScreen';

import ShuffleScreen from '../screens/ShuffleScreen';
import FriendsScreen from '../screens/FriendsScreen';
import EditProfileScreen from '../screens/EditProfileScreen';

import ChatScreen from '../screens/ChatScreen';
import MatchingScreen from '../screens/MatchingScreen';
import PostChatScreen from '../screens/PostChatScreen';
import UserProfileScreen from '../screens/UserProfileScreen';
import PaywallScreen from '../screens/PaywallScreen';

const AuthStack = createNativeStackNavigator();
const MainTabs = createBottomTabNavigator();
const RootStack = createNativeStackNavigator();

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

function AuthFlow() {
  return (
    <AuthStack.Navigator screenOptions={{ headerShown: false }}>
      <AuthStack.Screen name="Login" component={LoginScreen} />
      <AuthStack.Screen name="Register" component={RegisterScreen} />
      <AuthStack.Screen name="ProfileCompletion" component={ProfileCompletionScreen} options={{ headerShown: true, title: 'Profil', headerStyle: { backgroundColor: COLORS.bg }, headerTintColor: COLORS.textPrimary }} />
    </AuthStack.Navigator>
  );
}

function TabIcon({ emoji, focused }) {
  return (
    <Text style={{ fontSize: focused ? 24 : 20, opacity: focused ? 1 : 0.55 }}>{emoji}</Text>
  );
}

function MainFlow() {
  return (
    <MainTabs.Navigator
      screenOptions={{
        headerShown: false,
        tabBarStyle: { backgroundColor: COLORS.surface, borderTopColor: COLORS.border, height: 64, paddingTop: 8, paddingBottom: 8 },
        tabBarActiveTintColor: COLORS.primary,
        tabBarInactiveTintColor: COLORS.textMuted,
        tabBarLabelStyle: { fontSize: 11, fontWeight: '700' },
      }}
    >
      <MainTabs.Screen
        name="ShuffleTab"
        component={ShuffleScreen}
        options={{ title: 'Keşfet', tabBarIcon: ({ focused }) => <TabIcon emoji="🎲" focused={focused} /> }}
      />
      <MainTabs.Screen
        name="FriendsTab"
        component={FriendsScreen}
        options={{ title: 'Arkadaşlar', tabBarIcon: ({ focused }) => <TabIcon emoji="👥" focused={focused} /> }}
      />
      <MainTabs.Screen
        name="ProfileTab"
        component={EditProfileScreen}
        options={{ title: 'Profil', tabBarIcon: ({ focused }) => <TabIcon emoji="👤" focused={focused} /> }}
      />
    </MainTabs.Navigator>
  );
}

export default function AppNavigator() {
  const { bootstrapping, user, token } = useAuth();

  if (bootstrapping) return <Splash />;

  const hasAuth = !!token && !!user;
  const needsProfile = user && (!user.ageConfirmed || !user.bio);

  return (
    <NavigationContainer theme={navTheme}>
      <RootStack.Navigator screenOptions={screenOptions}>
        {!hasAuth ? (
          <RootStack.Screen name="Auth" component={AuthFlow} options={{ headerShown: false }} />
        ) : needsProfile ? (
          <RootStack.Screen name="ProfileCompletion" component={ProfileCompletionScreen} options={{ title: 'Profilini Tamamla' }} />
        ) : (
          <>
            <RootStack.Screen name="Main" component={MainFlow} options={{ headerShown: false }} />
            <RootStack.Screen name="Matching" component={MatchingScreen} options={{ headerShown: false }} />
            <RootStack.Screen name="Chat" component={ChatScreen} options={{ headerShown: false }} />
            <RootStack.Screen name="PostChat" component={PostChatScreen} options={{ title: '' }} />
            <RootStack.Screen name="UserProfile" component={UserProfileScreen} options={{ title: '' }} />
            <RootStack.Screen name="Paywall" component={PaywallScreen} options={{ title: 'Plus' }} />
          </>
        )}
      </RootStack.Navigator>
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
