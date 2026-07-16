import { StatusBar } from 'expo-status-bar';
import { Alert, StyleSheet, View } from 'react-native';
import WelcomeScreen from './src/screens/WelcomeScreen';
import { colors } from './src/theme';

export default function App() {
  const handleStart = () => {
    Alert.alert('Hazır', 'Sohbet ekranı sıradaki adımda bağlanacak.');
  };

  return (
    <View style={styles.root}>
      <StatusBar style="light" />
      <WelcomeScreen onStart={handleStart} />
    </View>
  );
}

const styles = StyleSheet.create({
  root: {
    flex: 1,
    backgroundColor: colors.background,
  },
});
