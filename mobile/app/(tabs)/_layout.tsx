import { FontAwesome5, MaterialCommunityIcons } from '@expo/vector-icons';
import { Tabs } from 'expo-router';

import { palette } from '@/constants/palette';

export default function TabLayout() {
  return (
    <Tabs
      screenOptions={{
        headerShown: false,
        tabBarActiveTintColor: palette.ink,
        tabBarInactiveTintColor: '#7D6755',
        tabBarActiveBackgroundColor: palette.tabActive,
        tabBarStyle: {
          backgroundColor: '#FFF7EE',
          borderTopColor: '#E7DED1',
          height: 74,
          paddingBottom: 10,
          paddingTop: 8,
        },
        tabBarLabelStyle: {
          fontSize: 11,
          fontWeight: '700',
        },
        tabBarItemStyle: {
          borderRadius: 12,
          marginHorizontal: 4,
          marginVertical: 6,
        },
      }}>
      <Tabs.Screen
        name="index"
        options={{
          title: 'Home',
          tabBarIcon: ({ color, size }) => <FontAwesome5 size={size - 1} name="home" color={color} />,
        }}
      />
      <Tabs.Screen
        name="booking"
        options={{
          title: 'Book',
          tabBarIcon: ({ color, size }) => <MaterialCommunityIcons size={size + 1} name="calendar-check-outline" color={color} />,
        }}
      />
      <Tabs.Screen
        name="operations"
        options={{
          href: null,
        }}
      />
      <Tabs.Screen
        name="dashboard"
        options={{
          title: 'Dashboard',
          tabBarIcon: ({ color, size }) => <MaterialCommunityIcons size={size + 1} name="view-dashboard-outline" color={color} />,
        }}
      />
      <Tabs.Screen
        name="account"
        options={{
          title: 'Account',
          tabBarIcon: ({ color, size }) => <MaterialCommunityIcons size={size + 1} name="account-circle-outline" color={color} />,
        }}
      />
    </Tabs>
  );
}
