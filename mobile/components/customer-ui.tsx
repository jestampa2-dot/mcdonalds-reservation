import { MaterialCommunityIcons } from '@expo/vector-icons';
import { ReactNode, useMemo } from 'react';
import {
  ActivityIndicator,
  Image,
  Pressable,
  ScrollView,
  StyleProp,
  StyleSheet,
  Text,
  TextInput,
  TextInputProps,
  View,
  ViewStyle,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

import { palette } from '@/constants/palette';
import { AppThemeColors, useAppTheme } from '@/lib/theme';

type CustomerButtonTone = 'primary' | 'secondary' | 'ghost' | 'danger';
const brandLogoCircle = require('../assets/images/brand-logo-circle.png');

export function CustomerPage({
  children,
  scroll = true,
  contentContainerStyle,
  refreshControl,
}: {
  children: ReactNode;
  scroll?: boolean;
  contentContainerStyle?: StyleProp<ViewStyle>;
  refreshControl?: any;
}) {
  const styles = useCustomerStyles();

  if (!scroll) {
    return (
      <SafeAreaView style={styles.page} edges={['top', 'left', 'right']}>
        <View style={[styles.pageContent, contentContainerStyle]}>{children}</View>
      </SafeAreaView>
    );
  }

  return (
    <SafeAreaView style={styles.page} edges={['top', 'left', 'right']}>
      <ScrollView
        contentInsetAdjustmentBehavior="automatic"
        refreshControl={refreshControl}
        contentContainerStyle={[styles.pageContent, contentContainerStyle]}>
        {children}
      </ScrollView>
    </SafeAreaView>
  );
}

export function CustomerHeader({
  eyebrow,
  title,
  subtitle,
  leftSlot,
  rightSlot,
  centered = false,
}: {
  eyebrow?: string;
  title: string;
  subtitle?: string;
  leftSlot?: ReactNode;
  rightSlot?: ReactNode;
  centered?: boolean;
}) {
  const styles = useCustomerStyles();

  return (
    <View style={styles.header}>
      <View style={styles.headerBar}>
        <View style={styles.headerSide}>{leftSlot ?? <ThemeToggleButton />}</View>
        <View style={[styles.headerCopy, centered ? styles.headerCopyCentered : null]}>
          {eyebrow ? <Text style={styles.headerEyebrow}>{eyebrow}</Text> : null}
          <Text style={[styles.headerTitle, centered ? styles.headerTitleCentered : null]}>{title}</Text>
          {subtitle ? <Text style={[styles.headerSubtitle, centered ? styles.headerSubtitleCentered : null]}>{subtitle}</Text> : null}
        </View>
        <View style={[styles.headerSide, styles.headerSideRight]}>{rightSlot}</View>
      </View>
    </View>
  );
}

export function SheetSurface({
  children,
  style,
}: {
  children: ReactNode;
  style?: StyleProp<ViewStyle>;
}) {
  const styles = useCustomerStyles();
  return <View style={[styles.sheetSurface, style]}>{children}</View>;
}

export function CustomerCard({
  children,
  style,
  tone = 'white',
}: {
  children: ReactNode;
  style?: StyleProp<ViewStyle>;
  tone?: 'white' | 'cream' | 'yellow' | 'pink' | 'green';
}) {
  const styles = useCustomerStyles();
  const toneStyle =
    tone === 'cream'
      ? styles.cardCream
      : tone === 'yellow'
        ? styles.cardYellow
        : tone === 'pink'
          ? styles.cardPink
          : tone === 'green'
            ? styles.cardGreen
            : styles.cardWhite;

  return <View style={[styles.cardBase, toneStyle, style]}>{children}</View>;
}

export function SectionEyebrow({ children }: { children: ReactNode }) {
  const styles = useCustomerStyles();
  return <Text style={styles.sectionEyebrow}>{children}</Text>;
}

export function SectionTitle({ children }: { children: ReactNode }) {
  const styles = useCustomerStyles();
  return <Text style={styles.sectionTitle}>{children}</Text>;
}

export function CustomerButton({
  label,
  onPress,
  tone = 'primary',
  loading = false,
  disabled = false,
  icon,
  compact = false,
}: {
  label: string;
  onPress: () => void;
  tone?: CustomerButtonTone;
  loading?: boolean;
  disabled?: boolean;
  icon?: keyof typeof MaterialCommunityIcons.glyphMap;
  compact?: boolean;
}) {
  const styles = useCustomerStyles();
  const { colors } = useAppTheme();
  const buttonStyle =
    tone === 'secondary'
      ? styles.buttonSecondary
      : tone === 'ghost'
        ? styles.buttonGhost
        : tone === 'danger'
          ? styles.buttonDanger
          : styles.buttonPrimary;

  const textStyle =
    tone === 'ghost' ? styles.buttonGhostText : tone === 'secondary' ? styles.buttonSecondaryText : styles.buttonPrimaryText;

  return (
    <Pressable
      disabled={disabled || loading}
      onPress={onPress}
      style={({ pressed }) => [
        styles.buttonBase,
        compact ? styles.buttonCompact : null,
        buttonStyle,
        pressed && !disabled ? styles.buttonPressed : null,
        disabled ? styles.buttonDisabled : null,
      ]}>
      {loading ? (
        <ActivityIndicator color={tone === 'secondary' ? colors.brandText : '#FFFFFF'} />
      ) : (
        <View style={styles.buttonContent}>
          {icon ? <MaterialCommunityIcons name={icon} size={compact ? 15 : 16} color={tone === 'secondary' ? colors.brandText : tone === 'ghost' ? colors.text : '#FFFFFF'} /> : null}
          <Text style={textStyle}>{label}</Text>
        </View>
      )}
    </Pressable>
  );
}

export function HeaderIconButton({
  icon,
  onPress,
  accessibilityLabel,
}: {
  icon: keyof typeof MaterialCommunityIcons.glyphMap;
  onPress: () => void;
  accessibilityLabel?: string;
}) {
  const styles = useCustomerStyles();

  return (
    <Pressable
      accessibilityRole="button"
      accessibilityLabel={accessibilityLabel}
      onPress={onPress}
      style={({ pressed }) => [styles.headerIconButton, pressed ? styles.buttonPressed : null]}>
      <MaterialCommunityIcons name={icon} size={22} color={palette.ink} />
    </Pressable>
  );
}

export function ThemeToggleButton() {
  const { isDark, toggleTheme } = useAppTheme();

  return (
    <HeaderIconButton
      icon={isDark ? 'weather-sunny' : 'moon-waning-crescent'}
      onPress={toggleTheme}
      accessibilityLabel={isDark ? 'Switch to light mode' : 'Switch to dark mode'}
    />
  );
}

export function CustomerChip({
  label,
  active = false,
  onPress,
  tone = 'yellow',
}: {
  label: string;
  active?: boolean;
  onPress?: () => void;
  tone?: 'yellow' | 'pink' | 'green' | 'neutral';
}) {
  const styles = useCustomerStyles();
  const toneStyle =
    tone === 'pink' ? styles.chipPink : tone === 'green' ? styles.chipGreen : tone === 'neutral' ? styles.chipNeutral : styles.chipYellow;
  const activeStyle =
    tone === 'pink'
      ? styles.chipPinkActive
      : tone === 'green'
        ? styles.chipGreenActive
        : tone === 'neutral'
          ? styles.chipNeutralActive
          : styles.chipYellowActive;

  const body = (
    <View style={[styles.chip, toneStyle, active ? activeStyle : null]}>
      <Text style={[styles.chipText, active ? styles.chipTextActive : null]}>{label}</Text>
    </View>
  );

  if (!onPress) {
    return body;
  }

  return <Pressable onPress={onPress}>{body}</Pressable>;
}

export function CustomerField({
  label,
  value,
  onChangeText,
  placeholder,
  secureTextEntry = false,
  multiline = false,
  keyboardType,
  autoCapitalize = 'sentences',
  autoCorrect,
  autoComplete,
  textContentType,
  autoFocus = false,
  returnKeyType,
  onSubmitEditing,
}: {
  label: string;
  value: string;
  onChangeText: (value: string) => void;
  placeholder?: string;
  secureTextEntry?: boolean;
  multiline?: boolean;
  keyboardType?: 'default' | 'email-address' | 'numeric' | 'phone-pad';
  autoCapitalize?: TextInputProps['autoCapitalize'];
  autoCorrect?: boolean;
  autoComplete?: TextInputProps['autoComplete'];
  textContentType?: TextInputProps['textContentType'];
  autoFocus?: boolean;
  returnKeyType?: TextInputProps['returnKeyType'];
  onSubmitEditing?: TextInputProps['onSubmitEditing'];
}) {
  const styles = useCustomerStyles();
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
        autoCapitalize={autoCapitalize}
        autoCorrect={autoCorrect}
        autoComplete={autoComplete}
        textContentType={textContentType}
        autoFocus={autoFocus}
        returnKeyType={returnKeyType}
        onSubmitEditing={onSubmitEditing}
        style={[styles.fieldInput, multiline ? styles.fieldInputMultiline : null]}
      />
    </View>
  );
}

export function McLogo({ size = 56 }: { size?: number }) {
  return (
    <Image
      source={brandLogoCircle}
      style={{ width: size, height: size }}
      resizeMode="contain"
    />
  );
}

export function AvatarBadge({
  label,
  imageUrl,
  size = 48,
}: {
  label: string;
  imageUrl?: string | null;
  size?: number;
}) {
  const styles = useCustomerStyles();
  return (
    <View style={[styles.avatarWrap, { width: size, height: size, borderRadius: size / 2 }]}>
      {imageUrl ? (
        <Image source={{ uri: imageUrl }} style={{ width: size, height: size, borderRadius: size / 2 }} resizeMode="cover" />
      ) : (
        <Text style={[styles.avatarText, { fontSize: size * 0.38 }]}>{label}</Text>
      )}
    </View>
  );
}

export function MetricTile({
  icon,
  label,
  value,
}: {
  icon: keyof typeof MaterialCommunityIcons.glyphMap;
  label: string;
  value: string | number;
}) {
  const styles = useCustomerStyles();
  const { colors } = useAppTheme();

  return (
    <CustomerCard style={styles.metricTile}>
      <View style={styles.metricIconWrap}>
        <MaterialCommunityIcons name={icon} size={18} color={colors.text} />
      </View>
      <Text style={styles.metricLabel}>{label}</Text>
      <Text style={styles.metricValue}>{value}</Text>
    </CustomerCard>
  );
}

function useCustomerStyles() {
  const { colors, fontScale } = useAppTheme();
  return useMemo(() => createStyles(colors, fontScale), [colors, fontScale]);
}

const createStyles = (colors: AppThemeColors, fontScale: number) => StyleSheet.create({
  page: {
    flex: 1,
    backgroundColor: colors.background,
  },
  pageContent: {
    paddingBottom: 32,
  },
  header: {
    backgroundColor: palette.brandYellow,
    paddingHorizontal: 20,
    paddingTop: 6,
    paddingBottom: 12,
  },
  headerBar: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    gap: 12,
  },
  headerSide: {
    minWidth: 48,
    alignItems: 'flex-start',
    justifyContent: 'center',
    paddingTop: 4,
  },
  headerSideRight: {
    alignItems: 'flex-end',
  },
  headerCopy: {
    flex: 1,
    gap: 4,
    paddingTop: 4,
  },
  headerCopyCentered: {
    alignItems: 'center',
    justifyContent: 'center',
    paddingTop: 8,
  },
  headerEyebrow: {
    color: '#8B5B00',
    fontSize: 11 * fontScale,
    fontWeight: '800',
    letterSpacing: 1.1,
    textTransform: 'uppercase',
  },
  headerTitle: {
    color: palette.ink,
    fontSize: 34 * fontScale,
    fontWeight: '900',
    lineHeight: 38 * fontScale,
  },
  headerTitleCentered: {
    fontSize: 24 * fontScale,
    lineHeight: 28 * fontScale,
    textAlign: 'center',
  },
  headerSubtitle: {
    color: '#6A4F1B',
    fontSize: 14 * fontScale,
    lineHeight: 20 * fontScale,
  },
  headerSubtitleCentered: {
    textAlign: 'center',
  },
  sheetSurface: {
    marginTop: 12,
    backgroundColor: colors.canvas,
    borderTopLeftRadius: 32,
    borderTopRightRadius: 32,
    paddingHorizontal: 16,
    paddingTop: 18,
    paddingBottom: 24,
    gap: 16,
    minHeight: 540,
  },
  cardBase: {
    borderRadius: 24,
    padding: 16,
    gap: 12,
    boxShadow: `0px 12px 30px ${colors.shadow}`,
  },
  cardWhite: {
    backgroundColor: colors.surface,
  },
  cardCream: {
    backgroundColor: colors.cardCream,
  },
  cardYellow: {
    backgroundColor: colors.cardYellow,
  },
  cardPink: {
    backgroundColor: colors.cardPink,
  },
  cardGreen: {
    backgroundColor: colors.cardGreen,
  },
  sectionEyebrow: {
    color: colors.brandText,
    fontSize: 11 * fontScale,
    fontWeight: '800',
    letterSpacing: 1,
    textTransform: 'uppercase',
  },
  sectionTitle: {
    color: colors.text,
    fontSize: 18 * fontScale,
    fontWeight: '900',
    lineHeight: 22 * fontScale,
  },
  buttonBase: {
    minHeight: 52,
    borderRadius: 14,
    paddingHorizontal: 18,
    alignItems: 'center',
    justifyContent: 'center',
  },
  buttonCompact: {
    minHeight: 40,
    paddingHorizontal: 14,
  },
  buttonPrimary: {
    backgroundColor: palette.brandRed,
  },
  buttonSecondary: {
    backgroundColor: colors.cardYellow,
    borderWidth: 1,
    borderColor: colors.border,
  },
  buttonGhost: {
    backgroundColor: colors.surface,
    borderWidth: 1,
    borderColor: colors.line,
  },
  buttonDanger: {
    backgroundColor: '#FF4D4D',
  },
  buttonPrimaryText: {
    color: '#FFFFFF',
    fontWeight: '800',
    fontSize: 15 * fontScale,
  },
  buttonSecondaryText: {
    color: colors.brandText,
    fontWeight: '800',
    fontSize: 15 * fontScale,
  },
  buttonGhostText: {
    color: colors.text,
    fontWeight: '800',
    fontSize: 15 * fontScale,
  },
  buttonContent: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
  },
  buttonPressed: {
    opacity: 0.92,
    transform: [{ scale: 0.99 }],
  },
  buttonDisabled: {
    opacity: 0.5,
  },
  headerIconButton: {
    width: 42,
    height: 42,
    borderRadius: 21,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: 'rgba(255, 255, 255, 0.55)',
  },
  chip: {
    borderRadius: 999,
    paddingHorizontal: 14,
    paddingVertical: 7,
    borderWidth: 1,
  },
  chipYellow: {
    backgroundColor: colors.cardYellow,
    borderColor: colors.border,
  },
  chipPink: {
    backgroundColor: colors.cardPink,
    borderColor: colors.dangerBorder,
  },
  chipGreen: {
    backgroundColor: colors.cardGreen,
    borderColor: colors.border,
  },
  chipNeutral: {
    backgroundColor: colors.surfaceMuted,
    borderColor: colors.line,
  },
  chipYellowActive: {
    backgroundColor: palette.brandRed,
    borderColor: palette.brandRed,
  },
  chipPinkActive: {
    backgroundColor: '#FF7A7A',
    borderColor: '#FF7A7A',
  },
  chipGreenActive: {
    backgroundColor: palette.softGreenStrong,
    borderColor: palette.softGreenStrong,
  },
  chipNeutralActive: {
    backgroundColor: colors.text,
    borderColor: colors.text,
  },
  chipText: {
    color: colors.text,
    fontSize: 12 * fontScale,
    fontWeight: '700',
  },
  chipTextActive: {
    color: '#FFFFFF',
  },
  field: {
    gap: 6,
  },
  fieldLabel: {
    color: colors.textMuted,
    fontSize: 11 * fontScale,
    fontWeight: '800',
    textTransform: 'uppercase',
    letterSpacing: 0.8,
  },
  fieldInput: {
    minHeight: 46,
    borderRadius: 12,
    borderWidth: 1,
    borderColor: colors.line,
    backgroundColor: colors.input,
    paddingHorizontal: 14,
    color: colors.text,
    fontSize: 14 * fontScale,
  },
  fieldInputMultiline: {
    minHeight: 96,
    paddingTop: 12,
    textAlignVertical: 'top',
  },
  avatarWrap: {
    backgroundColor: colors.surfaceMuted,
    alignItems: 'center',
    justifyContent: 'center',
    overflow: 'hidden',
  },
  avatarText: {
    color: colors.text,
    fontWeight: '900',
  },
  metricTile: {
    flex: 1,
    minWidth: 0,
    alignItems: 'center',
    justifyContent: 'center',
    paddingVertical: 18,
  },
  metricIconWrap: {
    width: 34,
    height: 34,
    borderRadius: 17,
    backgroundColor: colors.surfaceMuted,
    alignItems: 'center',
    justifyContent: 'center',
  },
  metricLabel: {
    color: colors.textMuted,
    fontSize: 12 * fontScale,
    fontWeight: '600',
    textAlign: 'center',
  },
  metricValue: {
    color: colors.text,
    fontSize: 28 * fontScale,
    fontWeight: '900',
    textAlign: 'center',
  },
});
