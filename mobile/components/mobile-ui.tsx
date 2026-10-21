import { LinearGradient } from 'expo-linear-gradient';
import { ReactNode, useMemo } from 'react';
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
import { ThemeToggleButton } from '@/components/customer-ui';
import { AppThemeColors, useAppTheme } from '@/lib/theme';

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
  const styles = useMobileStyles();
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
        <View style={styles.heroActionRow}>
          <ThemeToggleButton />
          {rightSlot ? <View style={styles.heroAction}>{rightSlot}</View> : null}
        </View>
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
  const styles = useMobileStyles();
  return <View style={[styles.panel, style]}>{children}</View>;
}

export function SectionHeading({ label, title }: { label: string; title: string }) {
  const styles = useMobileStyles();
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
  const styles = useMobileStyles();
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
  const styles = useMobileStyles();
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
  const styles = useMobileStyles();
  const { colors } = useAppTheme();

  return (
    <View style={styles.field}>
      <Text style={styles.fieldLabel}>{label}</Text>
      <TextInput
        value={value}
        onChangeText={onChangeText}
        placeholder={placeholder}
        placeholderTextColor={colors.inputPlaceholder}
        secureTextEntry={secureTextEntry}
        multiline={multiline}
        keyboardType={keyboardType}
        style={[styles.input, multiline ? styles.inputMultiline : null]}
      />
    </View>
  );
}

function useMobileStyles() {
  const { colors, fontScale } = useAppTheme();
  return useMemo(() => createStyles(colors, fontScale), [colors, fontScale]);
}

const createStyles = (colors: AppThemeColors, fontScale: number) => StyleSheet.create({
  safeArea: {
    flex: 1,
    backgroundColor: colors.background,
  },
  scrollContent: {
    paddingBottom: 32,
  },
  canvas: {
    paddingHorizontal: spacing.lg,
    gap: spacing.lg,
    backgroundColor: colors.background,
  },
  canvasFill: {
    flex: 1,
    backgroundColor: colors.background,
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
    fontSize: 12 * fontScale,
    fontWeight: '800',
    letterSpacing: 1.4,
    textTransform: 'uppercase',
    marginBottom: spacing.sm,
  },
  heroTitle: {
    color: palette.surfaceStrong,
    fontSize: 30 * fontScale,
    fontWeight: '900',
    lineHeight: 34 * fontScale,
  },
  heroSubtitle: {
    color: '#FFE9DD',
    fontSize: 15 * fontScale,
    lineHeight: 22 * fontScale,
    marginTop: spacing.sm,
    maxWidth: 300,
  },
  heroAction: {
    alignSelf: 'flex-start',
  },
  heroActionRow: {
    marginTop: spacing.lg,
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.sm,
  },
  panel: {
    backgroundColor: colors.surface,
    borderRadius: 24,
    padding: spacing.lg,
    borderWidth: 1,
    borderColor: colors.border,
    gap: spacing.md,
  },
  sectionLabel: {
    fontSize: 12 * fontScale,
    textTransform: 'uppercase',
    letterSpacing: 1.3,
    fontWeight: '800',
    color: colors.brandText,
  },
  sectionTitle: {
    fontSize: 24 * fontScale,
    fontWeight: '900',
    color: colors.text,
  },
  buttonBase: {
    minHeight: 50,
    borderRadius: 16,
    paddingHorizontal: 18,
    alignItems: 'center',
    justifyContent: 'center',
  },
  buttonPrimary: {
    backgroundColor: palette.brandRed,
  },
  buttonSecondary: {
    backgroundColor: palette.brandYellow,
  },
  buttonGhost: {
    backgroundColor: 'transparent',
    borderWidth: 1,
    borderColor: colors.border,
  },
  buttonPrimaryText: {
    color: palette.surfaceStrong,
    fontWeight: '800',
    fontSize: 15 * fontScale,
  },
  buttonSecondaryText: {
    color: palette.ink,
    fontWeight: '800',
    fontSize: 15 * fontScale,
  },
  buttonGhostText: {
    color: colors.brandText,
    fontWeight: '800',
    fontSize: 15 * fontScale,
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
    backgroundColor: colors.cardYellow,
    paddingHorizontal: 14,
    paddingVertical: 8,
  },
  tagActive: {
    backgroundColor: palette.brandRed,
  },
  tagText: {
    color: colors.text,
    fontWeight: '700',
    fontSize: 13 * fontScale,
  },
  tagTextActive: {
    color: palette.surfaceStrong,
  },
  field: {
    gap: 8,
  },
  fieldLabel: {
    fontSize: 13 * fontScale,
    fontWeight: '800',
    color: colors.text,
  },
  input: {
    minHeight: 50,
    borderRadius: 16,
    borderWidth: 1,
    borderColor: colors.border,
    backgroundColor: colors.input,
    paddingHorizontal: 16,
    color: colors.text,
    fontSize: 15 * fontScale,
  },
  inputMultiline: {
    minHeight: 108,
    paddingTop: 14,
    textAlignVertical: 'top',
  },
});
