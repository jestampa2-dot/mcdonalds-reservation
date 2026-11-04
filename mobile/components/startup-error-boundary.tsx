import AsyncStorage from '@react-native-async-storage/async-storage';
import { Component, ErrorInfo, PropsWithChildren } from 'react';
import { Pressable, StyleSheet, Text, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

type StartupErrorBoundaryState = {
  hasError: boolean;
  resetting: boolean;
  resetKey: number;
};

const recoverableStorageKeys = [
  'mcdonalds-reservations:theme-mode',
  'mcdonalds-reservations:font-size',
  'mcdonalds-reservations:app-preferences',
  'mcdonalds-reservations:notified-invitations',
];

export class StartupErrorBoundary extends Component<PropsWithChildren, StartupErrorBoundaryState> {
  state: StartupErrorBoundaryState = {
    hasError: false,
    resetting: false,
    resetKey: 0,
  };

  static getDerivedStateFromError(): Partial<StartupErrorBoundaryState> {
    return { hasError: true };
  }

  componentDidCatch(_error: Error, _info: ErrorInfo) {
    // The recovery UI is intentionally self-contained so it can render even
    // when a provider or the first route fails during startup.
  }

  private resetLocalSettings = async () => {
    this.setState({ resetting: true });

    try {
      await AsyncStorage.multiRemove(recoverableStorageKeys);
    } finally {
      this.setState((current) => ({
        hasError: false,
        resetting: false,
        resetKey: current.resetKey + 1,
      }));
    }
  };

  render() {
    if (!this.state.hasError) {
      return <View key={this.state.resetKey} style={styles.app}>{this.props.children}</View>;
    }

    return (
      <SafeAreaView style={styles.page}>
        <View style={styles.logo}>
          <Text style={styles.logoText}>M</Text>
        </View>
        <Text style={styles.title}>The app could not finish starting</Text>
        <Text style={styles.body}>
          A saved appearance, notification, or security setting may be incompatible with this phone. Resetting these local settings will not delete your reservations or account.
        </Text>
        <Pressable
          accessibilityRole="button"
          disabled={this.state.resetting}
          onPress={() => void this.resetLocalSettings()}
          style={({ pressed }) => [styles.button, pressed && styles.buttonPressed]}>
          <Text style={styles.buttonText}>{this.state.resetting ? 'Resetting…' : 'Reset local settings'}</Text>
        </Pressable>
      </SafeAreaView>
    );
  }
}

const styles = StyleSheet.create({
  app: {
    flex: 1,
  },
  page: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    gap: 18,
    paddingHorizontal: 28,
    backgroundColor: '#FFFFFF',
  },
  logo: {
    width: 82,
    height: 82,
    borderRadius: 41,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: '#DA291C',
  },
  logoText: {
    color: '#FFBC0D',
    fontSize: 48,
    fontWeight: '900',
  },
  title: {
    color: '#201812',
    fontSize: 24,
    fontWeight: '900',
    textAlign: 'center',
  },
  body: {
    maxWidth: 360,
    color: '#6A5647',
    fontSize: 15,
    lineHeight: 22,
    textAlign: 'center',
  },
  button: {
    minHeight: 52,
    minWidth: 220,
    alignItems: 'center',
    justifyContent: 'center',
    borderRadius: 16,
    paddingHorizontal: 22,
    backgroundColor: '#DA291C',
  },
  buttonPressed: {
    opacity: 0.82,
  },
  buttonText: {
    color: '#FFFFFF',
    fontSize: 16,
    fontWeight: '800',
  },
});
