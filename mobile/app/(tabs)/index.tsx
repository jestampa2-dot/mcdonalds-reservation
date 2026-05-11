import { MaterialCommunityIcons } from '@expo/vector-icons';
import { router, useFocusEffect } from 'expo-router';
import { useCallback, useEffect, useRef, useState } from 'react';
import { Linking, RefreshControl, StyleSheet, Text, View, useWindowDimensions } from 'react-native';

import {
  CustomerButton,
  CustomerCard,
  CustomerChip,
  CustomerHeader,
  CustomerPage,
  McLogo,
  SectionEyebrow,
  SectionTitle,
} from '@/components/customer-ui';
import { palette } from '@/constants/palette';
import { ApiError, fetchHome } from '@/lib/api';
import { useAuth } from '@/lib/auth';
import { readCacheEnvelope, writeCache } from '@/lib/cache';
import { formatCurrency } from '@/lib/formatters';
import type { HomePayload } from '@/lib/types';

const homeCacheKey = 'mobile-cache:home';
const homeCacheTtlMs = 1000 * 60 * 30;
const homeRefreshIntervalMs = 1000 * 60 * 5;

function ShowcaseTile({
  icon,
  title,
  detail,
  tone,
}: {
  icon: keyof typeof MaterialCommunityIcons.glyphMap;
  title: string;
  detail: string;
  tone: 'cream' | 'yellow' | 'pink' | 'green';
}) {
  return (
    <CustomerCard tone={tone} style={styles.showcaseTile}>
      <View style={styles.showcaseIcon}>
        <MaterialCommunityIcons name={icon} size={22} color={palette.ink} />
      </View>
      <Text style={styles.showcaseTitle}>{title}</Text>
      <Text style={styles.showcaseDetail}>{detail}</Text>
    </CustomerCard>
  );
}

export default function HomeScreen() {
  const { user, signOut } = useAuth();
  const [payload, setPayload] = useState<HomePayload | null>(null);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [errorMessage, setErrorMessage] = useState('');
  const hasPayloadRef = useRef(false);
  const lastLoadedAtRef = useRef(0);
  const { width } = useWindowDimensions();
  const isWide = width >= 760;

  const loadHome = useCallback(async (nextRefreshing = false) => {
    try {
      if (nextRefreshing) {
        setRefreshing(true);
      } else if (!hasPayloadRef.current) {
        setLoading(true);
      }

      setErrorMessage('');
      const response = await fetchHome();
      hasPayloadRef.current = true;
      lastLoadedAtRef.current = Date.now();
      setPayload(response);
      await writeCache(homeCacheKey, response);
    } catch (error) {
      setErrorMessage(
        error instanceof ApiError ? error.message : 'Unable to load the customer app right now.',
      );
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, []);

  useEffect(() => {
    let active = true;

    void (async () => {
      const cachedPayload = await readCacheEnvelope<HomePayload>(homeCacheKey, homeCacheTtlMs);

      if (active && cachedPayload) {
        hasPayloadRef.current = true;
        lastLoadedAtRef.current = cachedPayload.savedAt;
        setPayload(cachedPayload.data);
        setLoading(false);
      }
    })();

    return () => {
      active = false;
    };
  }, []);

  useFocusEffect(
    useCallback(() => {
      if (Date.now() - lastLoadedAtRef.current > homeRefreshIntervalMs) {
        void loadHome();
      }
    }, [loadHome]),
  );

  const featuredBranch = payload?.branches[0];
  const firstName = user?.name.split(' ')[0] ?? null;

  return (
    <CustomerPage
      refreshControl={<RefreshControl refreshing={refreshing} onRefresh={() => void loadHome(true)} tintColor={palette.brandRed} />}
      contentContainerStyle={styles.pageContent}>
      <CustomerHeader
        title={firstName ? `Welcome, ${firstName}!` : 'Welcome!'}
        subtitle="Choose a branch, time, and package in one flow."
        rightSlot={<McLogo />}
      />

      {errorMessage ? (
        <CustomerCard tone="pink">
          <SectionEyebrow>Connection issue</SectionEyebrow>
          <SectionTitle>Home data is unavailable</SectionTitle>
          <Text style={styles.helperText}>{errorMessage}</Text>
          <CustomerButton label="Try again" onPress={() => void loadHome()} tone="ghost" />
        </CustomerCard>
      ) : null}

      <View style={styles.showcaseGrid}>
        <ShowcaseTile icon="silverware-fork-knife" title="Party meals" detail="Crowd favorites for birthdays and business events." tone="cream" />
        <ShowcaseTile icon="food-drumstick" title="Chicken sets" detail="Mix trays, bundles, and add-ons in one booking." tone="yellow" />
        <ShowcaseTile icon="cup-outline" title="Drinks and dessert" detail="Complete your receipt before you confirm." tone="pink" />
        <ShowcaseTile icon="database" title="Admin-connected" detail="Bookings update live when your admin confirms them." tone="green" />
      </View>

      <CustomerCard tone="yellow" style={styles.heroCard}>
        <SectionEyebrow>Customer mobile</SectionEyebrow>
        <Text style={styles.heroTitle}>Book McDonald&apos;s Event Faster!</Text>
        <Text style={styles.heroSubtitle}>Choose a branch, time, package, and payment proof in one customer flow connected to your admin and database.</Text>
        <View style={styles.heroActions}>
          <CustomerButton label="Book now" onPress={() => router.push('/(tabs)/booking')} />
          <CustomerButton
            label={user ? 'Open dashboard' : 'Sign in'}
            onPress={() => router.push(user ? '/(tabs)/dashboard' : '/login')}
            tone="secondary"
          />
          <CustomerButton
            label={user ? 'Sign out' : 'Create account'}
            onPress={() => void (user ? signOut() : router.push('/register'))}
            tone="ghost"
          />
        </View>
      </CustomerCard>

      {loading && !payload ? (
        <CustomerCard>
          <Text style={styles.helperText}>Loading live branches, packages, and customer stats...</Text>
        </CustomerCard>
      ) : null}

      {payload ? (
        <>
          <View style={[styles.statsRow, isWide ? styles.statsRowWide : null]}>
            {payload.stats.map((stat) => (
              <CustomerCard key={stat.label} style={styles.statCard}>
                <Text style={styles.statLabel}>{stat.label}</Text>
                <Text style={styles.statValue}>{stat.value}</Text>
              </CustomerCard>
            ))}
          </View>

          {featuredBranch ? (
            <CustomerCard>
              <SectionEyebrow>Choose a branch</SectionEyebrow>
              <View style={styles.branchHeader}>
                <View style={{ flex: 1, gap: 4 }}>
                  <SectionTitle>{featuredBranch.name}</SectionTitle>
                  <Text style={styles.branchMeta}>{featuredBranch.city}</Text>
                  <Text style={styles.branchGuests}>Up to {featuredBranch.max_guests} guests</Text>
                </View>
                <View style={styles.branchArrow}>
                  <MaterialCommunityIcons name="chevron-right" size={26} color="#D0A2A2" />
                </View>
              </View>
              <View style={styles.chipRow}>
                {Object.entries(featuredBranch.supports)
                  .filter(([, supported]) => supported)
                  .map(([type]) => (
                    <CustomerChip
                      key={type}
                      label={type.charAt(0).toUpperCase() + type.slice(1)}
                      tone={type === 'business' ? 'pink' : 'green'}
                    />
                  ))}
              </View>
              {featuredBranch.map_url ? (
                <CustomerButton
                  label="Open in Maps"
                  onPress={() => void Linking.openURL(featuredBranch.map_url!)}
                  tone="ghost"
                  icon="map-marker-outline"
                  compact
                />
              ) : null}
            </CustomerCard>
          ) : null}

          <CustomerCard>
            <SectionEyebrow>Event types</SectionEyebrow>
            <SectionTitle>What are you booking today?</SectionTitle>
            <View style={styles.eventGrid}>
              {payload.eventTypes.map((eventType) => (
                <View key={eventType.label} style={styles.eventCard}>
                  <Text style={styles.eventIcon}>{eventType.icon}</Text>
                  <Text style={styles.eventLabel}>{eventType.label}</Text>
                  <Text style={styles.eventDescription}>{eventType.description}</Text>
                </View>
              ))}
            </View>
          </CustomerCard>

          <View>
            <SectionTitle>Featured packages</SectionTitle>
            <View style={styles.packageGrid}>
              {payload.featuredPackages.map((item) => (
                <CustomerCard key={item.code} style={[styles.packageCard, isWide ? styles.packageCardWide : null]}>
                  <Text style={styles.packageRange}>{item.guest_range}</Text>
                  <Text style={styles.packageName}>{item.name}</Text>
                  <Text style={styles.packagePrice}>{formatCurrency(item.price)}</Text>
                  <View style={styles.featureList}>
                    {item.features.slice(0, 3).map((feature) => (
                      <Text key={feature} style={styles.featureItem}>
                        - {feature}
                      </Text>
                    ))}
                  </View>
                  <CustomerButton label="Avail now" onPress={() => router.push('/(tabs)/booking')} compact />
                </CustomerCard>
              ))}
            </View>
          </View>
        </>
      ) : null}
    </CustomerPage>
  );
}

const styles = StyleSheet.create({
  pageContent: {
    paddingHorizontal: 18,
    gap: 16,
  },
  helperText: {
    color: '#5C4A3A',
    lineHeight: 20,
    fontSize: 14,
  },
  showcaseGrid: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 12,
  },
  showcaseTile: {
    width: '47%',
    minHeight: 146,
    justifyContent: 'space-between',
  },
  showcaseIcon: {
    width: 40,
    height: 40,
    borderRadius: 20,
    backgroundColor: 'rgba(255, 255, 255, 0.65)',
    alignItems: 'center',
    justifyContent: 'center',
  },
  showcaseTitle: {
    color: palette.ink,
    fontSize: 18,
    fontWeight: '900',
  },
  showcaseDetail: {
    color: '#5C4A3A',
    lineHeight: 18,
    fontSize: 13,
  },
  heroCard: {
    gap: 10,
  },
  heroTitle: {
    color: palette.ink,
    fontSize: 30,
    fontWeight: '900',
    lineHeight: 34,
  },
  heroSubtitle: {
    color: '#5C4A3A',
    lineHeight: 21,
    fontSize: 15,
  },
  heroActions: {
    gap: 10,
    marginTop: 4,
  },
  statsRow: {
    gap: 12,
  },
  statsRowWide: {
    flexDirection: 'row',
  },
  statCard: {
    flex: 1,
  },
  statLabel: {
    color: '#7A604B',
    fontSize: 12,
    fontWeight: '700',
  },
  statValue: {
    color: palette.ink,
    fontSize: 28,
    fontWeight: '900',
  },
  branchHeader: {
    flexDirection: 'row',
    gap: 12,
    alignItems: 'center',
  },
  branchMeta: {
    color: '#6C6258',
    fontSize: 15,
  },
  branchGuests: {
    alignSelf: 'flex-start',
    color: '#8B6A07',
    backgroundColor: '#FFF2C8',
    paddingHorizontal: 10,
    paddingVertical: 6,
    borderRadius: 999,
    overflow: 'hidden',
    fontWeight: '700',
    fontSize: 12,
  },
  branchArrow: {
    width: 38,
    height: 38,
    borderRadius: 19,
    alignItems: 'center',
    justifyContent: 'center',
  },
  chipRow: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 8,
  },
  eventGrid: {
    gap: 12,
  },
  eventCard: {
    borderRadius: 18,
    backgroundColor: '#FFF7E7',
    padding: 14,
    gap: 6,
  },
  eventIcon: {
    fontSize: 18,
  },
  eventLabel: {
    color: palette.ink,
    fontSize: 17,
    fontWeight: '900',
  },
  eventDescription: {
    color: '#6C6258',
    lineHeight: 19,
    fontSize: 13,
  },
  packageGrid: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 12,
    marginTop: 12,
  },
  packageCard: {
    width: '47%',
    gap: 8,
  },
  packageCardWide: {
    width: '31%',
  },
  packageRange: {
    color: '#8B7C6D',
    fontSize: 11,
    fontWeight: '700',
    textTransform: 'uppercase',
  },
  packageName: {
    color: palette.ink,
    fontSize: 22,
    fontWeight: '900',
    lineHeight: 26,
  },
  packagePrice: {
    color: palette.brandRed,
    fontSize: 22,
    fontWeight: '900',
  },
  featureList: {
    gap: 4,
    minHeight: 58,
  },
  featureItem: {
    color: '#6C6258',
    fontSize: 13,
    lineHeight: 17,
  },
});
