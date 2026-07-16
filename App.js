import { StatusBar } from 'expo-status-bar';
import { SafeAreaProvider } from 'react-native-safe-area-context';
import { AuthProvider } from './src/store/AuthContext';
import { InterestsProvider } from './src/store/InterestsContext';
import { COLORS } from './src/config';
import AppNavigator from './src/navigation/AppNavigator';

export default function App() {
  return (
    <SafeAreaProvider>
      <StatusBar style="light" />
      <AuthProvider>
        <InterestsProvider>
          <AppNavigator />
        </InterestsProvider>
      </AuthProvider>
    </SafeAreaProvider>
  );
}
