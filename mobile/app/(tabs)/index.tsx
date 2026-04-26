import { router } from 'expo-router';
import { useEffect, useState } from 'react';
import { Linking, RefreshControl, ScrollView, StyleSheet, Text, useWindowDimensions, View } from 'react-native';

import { AppButton, AppScreen, Panel, SectionHeading, Tag } from '@/components/mobile-ui';
import { palette } from '@/constants/palette';
import { ApiError, fetchHome, getApiBaseUrl } from '@/lib/api';
import { useAuth } from '@/lib/auth';
import type { HomePayload } from '@/lib/types';

export default function HomeScreen() {
  const { user, signOut } = useAuth();
  const [payload, setPayload] = useState<HomePayload | null>(null);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [errorMessage, setErrorMessage] = useState('');
  const { width } = useWindowDimensions();
  const isWide = width >= 760;

  async function loadHome(nextRefreshing = false) {
    try {
      if (nextRefreshing) {
        setRefreshing(true);
      } else {
        setLoading(true);
      }

      setErrorMessage('');
      const response = await fetchHome();
      setPayload(response);
    } catch (error) {
      setErrorMessage(error instanceof ApiError ? error.message : 'Unable to load live branch and package data right now.');
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }

  useEffect(() => {
    void loadHome();
  }, []);

  return (
    <AppScreen
      eyebrow="McDonald's mobile"
      title="Book party-ready branches faster"
      subtitle={`Expo app talking to ${getApiBaseUrl()}. Browse packages, reserve a slot, then track approval from your dashboard.`}
      scroll={false}
      rightSlot={
        user ? (
          <AppButton label="Sign out" onPress={() => void signOut()} tone="secondary" />
        ) : (
          <AppButton label="Sign in" onPress={() => router.push('/login')} tone="secondary" />
        )
      }>
      <ScrollView
        refreshControl={<RefreshControl refreshing={refreshing} onRefresh={() => void loadHome(true)} tintColor={palette.brandRed} />}
        contentContainerStyle={{ gap: 20, paddingBottom: 32 }}>
        {errorMessage ? (
          <Panel style={styles.errorPanel}>
            <SectionHeading label="Connection issue" title="The mobile app cannot reach Laravel yet" />
            <Text style={styles.errorText}>{errorMessage}</Text>
            <AppButton label="Try again" onPress={() => void loadHome()} tone="secondary" />
          </Panel>
        ) : null}

        <Panel>
          <SectionHeading label="Quick actions" title={user ? `Welcome, ${user.name.split(' ')[0]}` : 'Customer flow'} />
          <View style={styles.buttonRow}>
            <AppButton label="Start booking" onPress={() => router.push('/(tabs)/booking')} />
            <AppButton label={user ? 'Open dashboard' : 'Create account'} onPress={() => router.push(user ? '/(tabs)/dashboard' : '/register')} tone="ghost" />
          </View>
        </Panel>

        {loading && !payload ? (
          <Panel>
            <Text style={styles.loadingText}>Loading live branch and package data...</Text>
          </Panel>
        ) : payload ? (
          <>
            <View style={[styles.statGrid, isWide ? styles.statGridWide : null]}>
              {payload.stats.map((stat) => (
                <Panel key={stat.label} style={styles.statCard}>
                  <Text style={styles.statLabel}>{stat.label}</Text>
                  <Text style={styles.statValue}>{stat.value}</Text>
                </Panel>
              ))}
            </View>

            <Panel>
              <SectionHeading label="Event types" title="Choose the kind of celebration" />
              <View style={styles.stack}>
                {payload.eventTypes.map((eventType) => (
                  <View key={eventType.label} style={styles.eventCard}>
                    <Text style={styles.eventIcon}>{eventType.icon}</Text>
                    <View style={{ flex: 1, gap: 6 }}>
                      <Text style={styles.eventTitle}>{eventType.label}</Text>
                      <Text style={styles.eventDescription}>{eventType.description}</Text>
                    </View>
                  </View>
                ))}
              </View>
            </Panel>

            <Panel>
              <SectionHeading label="Branches" title="Find a party-ready branch" />
              <View style={styles.stack}>
                {payload.branches.map((branch) => (
                  <View key={branch.code} style={styles.branchCard}>
                    <View style={styles.branchHeader}>
                      <View style={{ flex: 1 }}>
                        <Text style={styles.branchName}>{branch.name}</Text>
                        <Text style={styles.branchCity}>{branch.city}</Text>
                      </View>
                      <Tag label={`Up to ${branch.max_guests} guests`} />
                    </View>
                    <View style={styles.tagWrap}>
                      {Object.entries(branch.supports).map(([key, supported]) => (
                        <Tag key={key} label={key.replace('_', ' ')} active={supported} />
                      ))}
                    </View>
                    {branch.map_url ? (
                      <AppButton label="Open in Maps" onPress={() => void Linking.openURL(branch.map_url!)} tone="ghost" />
                    ) : null}
                  </View>
                ))}
              </View>
            </Panel>

            <Panel>
              <SectionHeading label="Packages" title="Featured crowd favorites" />
              <View style={styles.stack}>
                {payload.featuredPackages.map((item) => (
                  <View key={item.code} style={styles.packageCard}>
                    <View style={styles.packageHeader}>
                      <Text style={styles.packageRange}>{item.guest_range}</Text>
                      <Text style={styles.packagePrice}>
                        {new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(item.price)}
                      </Text>
                    </View>
                    <Text style={styles.packageName}>{item.name}</Text>
                    <View style={styles.featureList}>
                      {item.features.map((feature) => (
                        <Text key={feature} style={styles.featureItem}>
                          • {feature}
                        </Text>
                      ))}
                    </View>
                  </View>
                ))}
              </View>
            </Panel>
          </>
        ) : null}
      </ScrollView>
    </AppScreen>
  );
}

const styles = StyleSheet.create({
  buttonRow: {
    gap: 12,
  },
  loadingText: {
    color: palette.inkMuted,
    fontSize: 15,
  },
  errorPanel: {
    borderColor: '#F2B5AA',
    backgroundColor: '#FFF4F2',
  },
  errorText: {
    color: palette.brandRedDark,
    lineHeight: 20,
  },
  statGrid: {
    gap: 12,
  },
  statGridWide: {
    flexDirection: 'row',
    flexWrap: 'wrap',
  },
  statCard: {
    flex: 1,
    minWidth: 180,
  },
  statLabel: {
    color: palette.inkMuted,
    fontSize: 13,
    fontWeight: '700',
  },
  statValue: {
    color: palette.ink,
    fontSize: 28,
    fontWeight: '900',
  },
  stack: {
    gap: 14,
  },
  eventCard: {
    flexDirection: 'row',
    gap: 14,
    padding: 16,
    borderRadius: 20,
    backgroundColor: '#FFF6DE',
  },
  eventIcon: {
    fontSize: 24,
  },
  eventTitle: {
    fontSize: 18,
    fontWeight: '900',
    color: palette.ink,
  },
  eventDescription: {
    color: palette.inkMuted,
    lineHeight: 20,
  },
  branchCard: {
    gap: 14,
    padding: 16,
    borderRadius: 20,
    backgroundColor: '#FFF8EA',
  },
  branchHeader: {
    flexDirection: 'row',
    gap: 12,
    alignItems: 'flex-start',
  },
  branchName: {
    fontSize: 18,
    fontWeight: '900',
    color: palette.ink,
  },
  branchCity: {
    color: palette.inkMuted,
    marginTop: 4,
  },
  tagWrap: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 8,
  },
  packageCard: {
    gap: 12,
    padding: 16,
    borderRadius: 20,
    backgroundColor: '#FFF6DE',
  },
  packageHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    gap: 12,
    alignItems: 'center',
  },
  packageRange: {
    color: palette.inkMuted,
    fontWeight: '700',
    textTransform: 'uppercase',
    fontSize: 12,
    letterSpacing: 1.2,
  },
  packagePrice: {
    color: palette.brandRed,
    fontSize: 20,
    fontWeight: '900',
  },
  packageName: {
    color: palette.ink,
    fontSize: 22,
    fontWeight: '900',
  },
  featureList: {
    gap: 6,
  },
  featureItem: {
    color: palette.inkMuted,
    lineHeight: 20,
  },
});
