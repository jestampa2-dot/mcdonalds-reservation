import { LinearGradient } from 'expo-linear-gradient';
import { ReactNode } from 'react';
import {
  ActivityIndicator,
  Pressable,
  ScrollView,
  StyleProp,
  StyleSheet,
  Text,
  TextInput,
  View,
  ViewStyle,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

import { palette, spacing } from '@/constants/palette';

export function AppScreen({
  title,
  eyebrow,
  subtitle,
  children,
  rightSlot,
  scroll = true,
}: {
  title: string;
  eyebrow: string;
  subtitle: string;
  children: ReactNode;
  rightSlot?: ReactNode;
  scroll?: boolean;
}) {
  const content = (
    <View style={styles.canvas}>
      <View style={styles.redBlob} />
      <View style={styles.yellowBlob} />
      <LinearGradient colors={[palette.brandRed, '#F15A29']} style={styles.heroCard}>
        <View style={{ flex: 1 }}>
          <Text style={styles.heroEyebrow}>{eyebrow}</Text>
          <Text style={styles.heroTitle}>{title}</Text>
          <Text style={styles.heroSubtitle}>{subtitle}</Text>
        </View>
        {rightSlot ? <View style={styles.heroAction}>{rightSlot}</View> : null}
      </LinearGradient>
      {children}
    </View>
  );

  return (
    <SafeAreaView style={styles.safeArea} edges={['top', 'left', 'right']}>
      {scroll ? <ScrollView contentContainerStyle={styles.scrollContent}>{content}</ScrollView> : <View style={styles.canvasFill}>{content}</View>}
    </SafeAreaView>
  );
}

export function Panel({
  children,
  style,
}: {
  children: ReactNode;
  style?: StyleProp<ViewStyle>;
}) {
  return <View style={[styles.panel, style]}>{children}</View>;
}

export function SectionHeading({ label, title }: { label: string; title: string }) {
  return (
    <View style={{ gap: 6 }}>
      <Text style={styles.sectionLabel}>{label}</Text>
      <Text style={styles.sectionTitle}>{title}</Text>
    </View>
  );
}

export function AppButton({
  label,
  onPress,
  tone = 'primary',
  disabled = false,
  loading = false,
}: {
  label: string;
  onPress: () => void;
  tone?: 'primary' | 'secondary' | 'ghost';
  disabled?: boolean;
  loading?: boolean;
}) {
  const style =
    tone === 'secondary'
      ? styles.buttonSecondary
      : tone === 'ghost'
        ? styles.buttonGhost
        : styles.buttonPrimary;

  const textStyle =
    tone === 'ghost' ? styles.buttonGhostText : tone === 'secondary' ? styles.buttonSecondaryText : styles.buttonPrimaryText;

  return (
    <Pressable
      disabled={disabled || loading}
      onPress={onPress}
      style={({ pressed }) => [
        styles.buttonBase,
        style,
        pressed && !disabled ? styles.buttonPressed : null,
        disabled ? styles.buttonDisabled : null,
      ]}>
      {loading ? <ActivityIndicator color={tone === 'primary' ? palette.surfaceStrong : palette.brandRed} /> : <Text style={textStyle}>{label}</Text>}
    </Pressable>
  );
}

export function Tag({
  label,
  active = false,
  onPress,
}: {
  label: string;
  active?: boolean;
  onPress?: () => void;
}) {
  const inner = (
    <View style={[styles.tag, active ? styles.tagActive : null]}>
      <Text style={[styles.tagText, active ? styles.tagTextActive : null]}>{label}</Text>
    </View>
  );

  if (!onPress) {
    return inner;
  }

  return <Pressable onPress={onPress}>{inner}</Pressable>;
}

export function Field({
  label,
  value,
  onChangeText,
  placeholder,
  secureTextEntry = false,
  multiline = false,
  keyboardType,
}: {
  label: string;
  value: string;
  onChangeText: (value: string) => void;
  placeholder?: string;
  secureTextEntry?: boolean;
  multiline?: boolean;
  keyboardType?: 'default' | 'email-address' | 'numeric' | 'phone-pad';
}) {
  return (
    <View style={styles.field}>
      <Text style={styles.fieldLabel}>{label}</Text>
      <TextInput
        value={value}
        onChangeText={onChangeText}
        placeholder={placeholder}
        placeholderTextColor="#9B8879"
        secureTextEntry={secureTextEntry}
        multiline={multiline}
        keyboardType={keyboardType}
        style={[styles.input, multiline ? styles.inputMultiline : null]}
      />
    </View>
  );
}

const styles = StyleSheet.create({
  safeArea: {
    flex: 1,
    backgroundColor: palette.background,
  },
  scrollContent: {
    paddingBottom: 32,
  },
  canvas: {
    paddingHorizontal: spacing.lg,
    gap: spacing.lg,
  },
  canvasFill: {
    flex: 1,
  },
  redBlob: {
    position: 'absolute',
    top: -32,
    right: -24,
    width: 180,
    height: 180,
    backgroundColor: '#FFD966',
    borderBottomLeftRadius: 160,
    opacity: 0.45,
  },
  yellowBlob: {
    position: 'absolute',
    top: 120,
    left: -48,
    width: 120,
    height: 120,
    backgroundColor: '#FFE095',
    borderRadius: 120,
    opacity: 0.45,
  },
  heroCard: {
    marginTop: spacing.md,
    borderRadius: 28,
    padding: spacing.lg,
    minHeight: 188,
    justifyContent: 'space-between',
    shadowColor: palette.shadow,
    shadowOpacity: 1,
    shadowRadius: 20,
    shadowOffset: { width: 0, height: 12 },
    elevation: 8,
  },
  heroEyebrow: {
    color: '#FFD966',
    fontSize: 12,
    fontWeight: '800',
    letterSpacing: 1.4,
    textTransform: 'uppercase',
    marginBottom: spacing.sm,
  },
  heroTitle: {
    color: palette.surfaceStrong,
    fontSize: 30,
    fontWeight: '900',
    lineHeight: 34,
  },
  heroSubtitle: {
    color: '#FFE9DD',
    fontSize: 15,
    lineHeight: 22,
    marginTop: spacing.sm,
    maxWidth: 300,
  },
  heroAction: {
    marginTop: spacing.lg,
    alignSelf: 'flex-start',
  },
  panel: {
    backgroundColor: palette.surfaceStrong,
    borderRadius: 24,
    padding: spacing.lg,
    borderWidth: 1,
    borderColor: palette.border,
    gap: spacing.md,
  },
  sectionLabel: {
    fontSize: 12,
    textTransform: 'uppercase',
    letterSpacing: 1.3,
    fontWeight: '800',
    color: palette.brandRed,
  },
  sectionTitle: {
    fontSize: 24,
    fontWeight: '900',
    color: palette.ink,
  },
  buttonBase: {
    minHeight: 50,
    borderRadius: 16,
    paddingHorizontal: 18,
    alignItems: 'center',
    justifyContent: 'center',
  },
  buttonPrimary: {
    backgroundColor: palette.ink,
  },
  buttonSecondary: {
    backgroundColor: palette.brandYellow,
  },
  buttonGhost: {
    backgroundColor: 'transparent',
    borderWidth: 1,
    borderColor: palette.border,
  },
  buttonPrimaryText: {
    color: palette.surfaceStrong,
    fontWeight: '800',
    fontSize: 15,
  },
  buttonSecondaryText: {
    color: palette.ink,
    fontWeight: '800',
    fontSize: 15,
  },
  buttonGhostText: {
    color: palette.brandRed,
    fontWeight: '800',
    fontSize: 15,
  },
  buttonPressed: {
    opacity: 0.9,
    transform: [{ scale: 0.99 }],
  },
  buttonDisabled: {
    opacity: 0.5,
  },
  tag: {
    alignSelf: 'flex-start',
    borderRadius: 999,
    backgroundColor: '#FFF3D1',
    paddingHorizontal: 14,
    paddingVertical: 8,
  },
  tagActive: {
    backgroundColor: palette.brandRed,
  },
  tagText: {
    color: palette.ink,
    fontWeight: '700',
    fontSize: 13,
  },
  tagTextActive: {
    color: palette.surfaceStrong,
  },
  field: {
    gap: 8,
  },
  fieldLabel: {
    fontSize: 13,
    fontWeight: '800',
    color: palette.ink,
  },
  input: {
    minHeight: 50,
    borderRadius: 16,
    borderWidth: 1,
    borderColor: palette.border,
    backgroundColor: '#FFF9EE',
    paddingHorizontal: 16,
    color: palette.ink,
    fontSize: 15,
  },
  inputMultiline: {
    minHeight: 108,
    paddingTop: 14,
    textAlignVertical: 'top',
  },
});
