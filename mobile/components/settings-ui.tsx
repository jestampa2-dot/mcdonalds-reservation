import { MaterialCommunityIcons } from '@expo/vector-icons';
import { ReactNode, useMemo } from 'react';
import { Modal, Pressable, ScrollView, StyleSheet, Switch, Text, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

import { HeaderIconButton } from '@/components/customer-ui';
import { palette } from '@/constants/palette';
import { AppThemeColors, useAppTheme } from '@/lib/theme';

export function SettingsRow({
  icon,
  title,
  subtitle,
  value,
  onPress,
  switchValue,
  onSwitchChange,
  danger = false,
  disabled = false,
}: {
  icon: keyof typeof MaterialCommunityIcons.glyphMap;
  title: string;
  subtitle?: string;
  value?: string;
  onPress?: () => void;
  switchValue?: boolean;
  onSwitchChange?: (value: boolean) => void;
  danger?: boolean;
  disabled?: boolean;
}) {
  const { colors, fontScale } = useAppTheme();
  const styles = useMemo(() => createStyles(colors, fontScale), [colors, fontScale]);
  const isSwitch = typeof switchValue === 'boolean' && onSwitchChange;

  const body = (
    <View style={[styles.row, disabled ? styles.disabled : null]}>
      <View style={[styles.iconWrap, danger ? styles.dangerIconWrap : null]}>
        <MaterialCommunityIcons name={icon} size={21} color={danger ? colors.brandText : colors.text} />
      </View>
      <View style={styles.copy}>
        <Text style={[styles.title, danger ? styles.dangerText : null]}>{title}</Text>
        {subtitle ? <Text style={styles.subtitle}>{subtitle}</Text> : null}
      </View>
      {value ? <Text style={styles.value}>{value}</Text> : null}
      {isSwitch ? (
        <Switch
          accessibilityLabel={title}
          value={switchValue}
          onValueChange={onSwitchChange}
          disabled={disabled}
          trackColor={{ false: colors.line, true: palette.brandRed }}
          thumbColor="#FFFFFF"
        />
      ) : onPress ? (
        <MaterialCommunityIcons name="chevron-right" size={22} color={colors.textSubtle} />
      ) : null}
    </View>
  );

  if (!onPress || isSwitch) {
    return body;
  }

  return (
    <Pressable
      accessibilityRole="button"
      accessibilityLabel={title}
      disabled={disabled}
      onPress={onPress}
      style={({ pressed }) => (pressed ? styles.pressed : null)}>
      {body}
    </Pressable>
  );
}

export function SettingsDivider() {
  const { colors } = useAppTheme();
  return <View style={[stylesStatic.divider, { backgroundColor: colors.line }]} />;
}

export function SettingsModal({
  visible,
  title,
  children,
  onClose,
}: {
  visible: boolean;
  title: string;
  children: ReactNode;
  onClose: () => void;
}) {
  const { colors, fontScale } = useAppTheme();
  const styles = useMemo(() => createStyles(colors, fontScale), [colors, fontScale]);

  return (
    <Modal visible={visible} animationType="slide" onRequestClose={onClose}>
      <SafeAreaView style={[styles.modalPage, { backgroundColor: colors.canvas }]}>
        <View style={styles.modalHeader}>
          <Text style={styles.modalTitle}>{title}</Text>
          <HeaderIconButton icon="close" onPress={onClose} accessibilityLabel={`Close ${title}`} />
        </View>
        <ScrollView contentContainerStyle={styles.modalBody}>{children}</ScrollView>
      </SafeAreaView>
    </Modal>
  );
}

const stylesStatic = StyleSheet.create({
  divider: {
    height: 1,
    marginLeft: 54,
  },
});

const createStyles = (colors: AppThemeColors, fontScale: number) => StyleSheet.create({
  row: {
    minHeight: 64,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    paddingVertical: 10,
  },
  iconWrap: {
    width: 42,
    height: 42,
    borderRadius: 21,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: colors.surfaceMuted,
  },
  dangerIconWrap: {
    backgroundColor: colors.dangerSurface,
  },
  copy: {
    flex: 1,
    gap: 3,
  },
  title: {
    color: colors.text,
    fontSize: 15 * fontScale,
    fontWeight: '800',
  },
  subtitle: {
    color: colors.textMuted,
    fontSize: 12 * fontScale,
    lineHeight: 17 * fontScale,
  },
  value: {
    maxWidth: 108,
    color: colors.textMuted,
    fontSize: 12 * fontScale,
    fontWeight: '700',
    textAlign: 'right',
  },
  dangerText: {
    color: colors.brandText,
  },
  disabled: {
    opacity: 0.55,
  },
  pressed: {
    opacity: 0.72,
  },
  modalPage: {
    flex: 1,
  },
  modalHeader: {
    minHeight: 74,
    paddingHorizontal: 18,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: 12,
    backgroundColor: palette.brandYellow,
  },
  modalTitle: {
    flex: 1,
    color: palette.ink,
    fontSize: 22 * fontScale,
    fontWeight: '900',
  },
  modalBody: {
    padding: 18,
    paddingBottom: 36,
    gap: 14,
  },
});
