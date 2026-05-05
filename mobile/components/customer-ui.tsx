import { MaterialCommunityIcons } from '@expo/vector-icons';
import { ReactNode } from 'react';
import {
  ActivityIndicator,
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

type CustomerButtonTone = 'primary' | 'secondary' | 'ghost' | 'danger';

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
  return (
    <View style={styles.header}>
      <View style={styles.headerBar}>
        <View style={styles.headerSide}>{leftSlot}</View>
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
  return <Text style={styles.sectionEyebrow}>{children}</Text>;
}

export function SectionTitle({ children }: { children: ReactNode }) {
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
        <ActivityIndicator color={tone === 'secondary' ? palette.brandRed : '#FFFFFF'} />
      ) : (
        <View style={styles.buttonContent}>
          {icon ? <MaterialCommunityIcons name={icon} size={compact ? 15 : 16} color={tone === 'secondary' ? palette.brandRed : tone === 'ghost' ? palette.ink : '#FFFFFF'} /> : null}
          <Text style={textStyle}>{label}</Text>
        </View>
      )}
    </Pressable>
  );
}

export function HeaderIconButton({
  icon,
  onPress,
}: {
  icon: keyof typeof MaterialCommunityIcons.glyphMap;
  onPress: () => void;
}) {
  return (
    <Pressable onPress={onPress} style={({ pressed }) => [styles.headerIconButton, pressed ? styles.buttonPressed : null]}>
      <MaterialCommunityIcons name={icon} size={22} color={palette.ink} />
    </Pressable>
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
  return (
    <View style={styles.field}>
      <Text style={styles.fieldLabel}>{label}</Text>
      <TextInput
        value={value}
        onChangeText={onChangeText}
        placeholder={placeholder}
        placeholderTextColor="#A19488"
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
    <View style={[styles.logoWrap, { width: size, height: size, borderRadius: size / 2 }]}>
      <Text style={[styles.logoText, { fontSize: size * 0.54 }]}>M</Text>
    </View>
  );
}

export function AvatarBadge({
  label,
  size = 48,
}: {
  label: string;
  size?: number;
}) {
  return (
    <View style={[styles.avatarWrap, { width: size, height: size, borderRadius: size / 2 }]}>
      <Text style={[styles.avatarText, { fontSize: size * 0.38 }]}>{label}</Text>
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
  return (
    <CustomerCard style={styles.metricTile}>
      <View style={styles.metricIconWrap}>
        <MaterialCommunityIcons name={icon} size={18} color={palette.ink} />
      </View>
      <Text style={styles.metricLabel}>{label}</Text>
      <Text style={styles.metricValue}>{value}</Text>
    </CustomerCard>
  );
}

const styles = StyleSheet.create({
  page: {
    flex: 1,
    backgroundColor: palette.brandYellow,
  },
  pageContent: {
    paddingBottom: 32,
  },
  header: {
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
    fontSize: 11,
    fontWeight: '800',
    letterSpacing: 1.1,
    textTransform: 'uppercase',
  },
  headerTitle: {
    color: palette.ink,
    fontSize: 34,
    fontWeight: '900',
    lineHeight: 38,
  },
  headerTitleCentered: {
    fontSize: 24,
    lineHeight: 28,
    textAlign: 'center',
  },
  headerSubtitle: {
    color: '#6A4F1B',
    fontSize: 14,
    lineHeight: 20,
  },
  headerSubtitleCentered: {
    textAlign: 'center',
  },
  sheetSurface: {
    marginTop: 12,
    backgroundColor: palette.surfaceStrong,
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
    boxShadow: '0px 12px 30px rgba(35, 22, 11, 0.08)',
  },
  cardWhite: {
    backgroundColor: '#FFFFFF',
  },
  cardCream: {
    backgroundColor: '#FFF7E4',
  },
  cardYellow: {
    backgroundColor: '#FFE689',
  },
  cardPink: {
    backgroundColor: '#FBE0DF',
  },
  cardGreen: {
    backgroundColor: '#E0F6D9',
  },
  sectionEyebrow: {
    color: '#A36B00',
    fontSize: 11,
    fontWeight: '800',
    letterSpacing: 1,
    textTransform: 'uppercase',
  },
  sectionTitle: {
    color: palette.ink,
    fontSize: 18,
    fontWeight: '900',
    lineHeight: 22,
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
    backgroundColor: '#FFF3CE',
    borderWidth: 1,
    borderColor: '#F2D58A',
  },
  buttonGhost: {
    backgroundColor: '#FFFFFF',
    borderWidth: 1,
    borderColor: palette.line,
  },
  buttonDanger: {
    backgroundColor: '#FF4D4D',
  },
  buttonPrimaryText: {
    color: '#FFFFFF',
    fontWeight: '800',
    fontSize: 15,
  },
  buttonSecondaryText: {
    color: palette.brandRed,
    fontWeight: '800',
    fontSize: 15,
  },
  buttonGhostText: {
    color: palette.ink,
    fontWeight: '800',
    fontSize: 15,
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
    backgroundColor: 'rgba(255, 255, 255, 0.45)',
  },
  chip: {
    borderRadius: 999,
    paddingHorizontal: 14,
    paddingVertical: 7,
    borderWidth: 1,
  },
  chipYellow: {
    backgroundColor: '#FFF4C9',
    borderColor: '#F4D98D',
  },
  chipPink: {
    backgroundColor: '#FBE1E0',
    borderColor: '#F2B6B1',
  },
  chipGreen: {
    backgroundColor: '#D7F3D0',
    borderColor: '#A9E19D',
  },
  chipNeutral: {
    backgroundColor: '#F6F2ED',
    borderColor: '#E3DBD2',
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
    backgroundColor: palette.ink,
    borderColor: palette.ink,
  },
  chipText: {
    color: palette.ink,
    fontSize: 12,
    fontWeight: '700',
  },
  chipTextActive: {
    color: '#FFFFFF',
  },
  field: {
    gap: 6,
  },
  fieldLabel: {
    color: '#7A604B',
    fontSize: 11,
    fontWeight: '800',
    textTransform: 'uppercase',
    letterSpacing: 0.8,
  },
  fieldInput: {
    minHeight: 46,
    borderRadius: 12,
    borderWidth: 1,
    borderColor: palette.line,
    backgroundColor: '#FAF8F4',
    paddingHorizontal: 14,
    color: palette.ink,
    fontSize: 14,
  },
  fieldInputMultiline: {
    minHeight: 96,
    paddingTop: 12,
    textAlignVertical: 'top',
  },
  logoWrap: {
    backgroundColor: palette.brandRed,
    alignItems: 'center',
    justifyContent: 'center',
  },
  logoText: {
    color: palette.brandYellow,
    fontWeight: '900',
    marginTop: -2,
  },
  avatarWrap: {
    backgroundColor: '#FFF6EF',
    alignItems: 'center',
    justifyContent: 'center',
  },
  avatarText: {
    color: palette.ink,
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
    backgroundColor: '#F2EEE8',
    alignItems: 'center',
    justifyContent: 'center',
  },
  metricLabel: {
    color: '#776251',
    fontSize: 12,
    fontWeight: '600',
    textAlign: 'center',
  },
  metricValue: {
    color: palette.ink,
    fontSize: 28,
    fontWeight: '900',
    textAlign: 'center',
  },
});
