import AsyncStorage from '@react-native-async-storage/async-storage';
import { ReactNode, createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react';
import { Appearance, ColorSchemeName } from 'react-native';

import { palette } from '@/constants/palette';

export type ThemeMode = 'light' | 'dark';
export type ThemePreference = ThemeMode | 'system';
export type FontSizePreference = 'standard' | 'large';

export type AppThemeColors = {
  background: string;
  canvas: string;
  surface: string;
  surfaceAlt: string;
  surfaceMuted: string;
  text: string;
  textMuted: string;
  textSubtle: string;
  border: string;
  line: string;
  input: string;
  inputPlaceholder: string;
  iconSurface: string;
  tabBar: string;
  tabActive: string;
  tabInactive: string;
  cardCream: string;
  cardYellow: string;
  cardPink: string;
  cardGreen: string;
  warningSurface: string;
  dangerSurface: string;
  dangerBorder: string;
  brandText: string;
  shadow: string;
};

const lightColors: AppThemeColors = {
  background: palette.background,
  canvas: palette.canvas,
  surface: '#FFFFFF',
  surfaceAlt: '#FFF7E4',
  surfaceMuted: '#F6F2ED',
  text: palette.ink,
  textMuted: '#6A5647',
  textSubtle: '#7A6B60',
  border: palette.border,
  line: palette.line,
  input: '#FAF8F4',
  inputPlaceholder: '#8C7E72',
  iconSurface: 'rgba(255, 255, 255, 0.52)',
  tabBar: '#FFF7EE',
  tabActive: palette.tabActive,
  tabInactive: '#7D6755',
  cardCream: '#FFF7E4',
  cardYellow: '#FFE689',
  cardPink: '#FBE0DF',
  cardGreen: '#E0F6D9',
  warningSurface: '#FFF4C7',
  dangerSurface: '#FDE9E7',
  dangerBorder: '#F2B5AA',
  brandText: palette.brandRed,
  shadow: 'rgba(35, 22, 11, 0.12)',
};

const darkColors: AppThemeColors = {
  background: '#111315',
  canvas: '#17191C',
  surface: '#222529',
  surfaceAlt: '#2B2E32',
  surfaceMuted: '#30343A',
  text: '#FFF8EF',
  textMuted: '#D0C5BB',
  textSubtle: '#B7ADA4',
  border: '#494D52',
  line: '#3E4247',
  input: '#2A2D31',
  inputPlaceholder: '#AAA198',
  iconSurface: 'rgba(255, 255, 255, 0.22)',
  tabBar: '#181A1D',
  tabActive: '#5A2C28',
  tabInactive: '#B8AEA5',
  cardCream: '#302E28',
  cardYellow: '#3A321E',
  cardPink: '#3A2829',
  cardGreen: '#26372B',
  warningSurface: '#3A321E',
  dangerSurface: '#3C2828',
  dangerBorder: '#7A4844',
  brandText: '#FF8A80',
  shadow: 'rgba(0, 0, 0, 0.4)',
};

const storageKey = 'mcdonalds-reservations:theme-mode';
const fontSizeStorageKey = 'mcdonalds-reservations:font-size';

type ThemeContextValue = {
  mode: ThemeMode;
  preference: ThemePreference;
  isDark: boolean;
  colors: AppThemeColors;
  fontSize: FontSizePreference;
  fontScale: number;
  setMode: (mode: ThemePreference) => void;
  setFontSize: (fontSize: FontSizePreference) => void;
  toggleTheme: () => void;
};

const ThemeContext = createContext<ThemeContextValue | null>(null);

export function ThemeProvider({ children }: { children: ReactNode }) {
  const [preference, setPreference] = useState<ThemePreference>('system');
  const [fontSize, setFontSizeState] = useState<FontSizePreference>('standard');
  const [systemMode, setSystemMode] = useState<ThemeMode>(() => (Appearance.getColorScheme() === 'dark' ? 'dark' : 'light'));
  const mode: ThemeMode = preference === 'system' ? systemMode : preference;

  useEffect(() => {
    let active = true;

    void AsyncStorage.multiGet([storageKey, fontSizeStorageKey])
      .then((entries) => {
        if (!active) {
          return;
        }

        const savedMode = entries[0]?.[1];
        const savedFontSize = entries[1]?.[1];

        if (savedMode === 'light' || savedMode === 'dark' || savedMode === 'system') {
          setPreference(savedMode);
        }

        if (savedFontSize === 'standard' || savedFontSize === 'large') {
          setFontSizeState(savedFontSize);
        }
      })
      .catch(() => undefined);

    return () => {
      active = false;
    };
  }, []);

  useEffect(() => {
    const subscription = Appearance.addChangeListener(({ colorScheme }: { colorScheme: ColorSchemeName }) => {
      setSystemMode(colorScheme === 'dark' ? 'dark' : 'light');
    });

    return () => subscription.remove();
  }, []);

  useEffect(() => {
    const setColorScheme = Appearance.setColorScheme as (colorScheme: ColorSchemeName | null) => void;
    setColorScheme(preference === 'system' ? null : preference);
  }, [preference]);

  const setMode = useCallback((nextMode: ThemePreference) => {
    setPreference(nextMode);
    void AsyncStorage.setItem(storageKey, nextMode).catch(() => undefined);
  }, []);

  const setFontSize = useCallback((nextFontSize: FontSizePreference) => {
    setFontSizeState(nextFontSize);
    void AsyncStorage.setItem(fontSizeStorageKey, nextFontSize).catch(() => undefined);
  }, []);

  const toggleTheme = useCallback(() => {
    setPreference((currentPreference) => {
      const currentMode = currentPreference === 'system' ? systemMode : currentPreference;
      const nextMode = currentMode === 'dark' ? 'light' : 'dark';
      void AsyncStorage.setItem(storageKey, nextMode).catch(() => undefined);
      return nextMode;
    });
  }, [systemMode]);

  const value = useMemo<ThemeContextValue>(
    () => ({
      mode,
      preference,
      isDark: mode === 'dark',
      colors: mode === 'dark' ? darkColors : lightColors,
      fontSize,
      fontScale: fontSize === 'large' ? 1.15 : 1,
      setMode,
      setFontSize,
      toggleTheme,
    }),
    [fontSize, mode, preference, setFontSize, setMode, toggleTheme],
  );

  return <ThemeContext.Provider value={value}>{children}</ThemeContext.Provider>;
}

export function useAppTheme() {
  const context = useContext(ThemeContext);

  if (!context) {
    throw new Error('useAppTheme must be used inside ThemeProvider.');
  }

  return context;
}
