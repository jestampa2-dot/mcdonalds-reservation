import { Tabs } from 'expo-router';
import { FontAwesome5, MaterialCommunityIcons } from '@expo/vector-icons';

import { palette } from '@/constants/palette';

export default function TabLayout() {
  return (
    <Tabs
      screenOptions={{
        headerShown: false,
        tabBarActiveTintColor: palette.brandRed,
        tabBarInactiveTintColor: '#8D6D52',
        tabBarStyle: {
          backgroundColor: '#FFF9EE',
          borderTopColor: palette.border,
          height: 78,
          paddingBottom: 10,
          paddingTop: 10,
        },
        tabBarLabelStyle: {
          fontSize: 12,
          fontWeight: '700',
        },
      }}>
      <Tabs.Screen
        name="index"
        options={{
          title: 'Home',
          tabBarIcon: ({ color, size }) => <FontAwesome5 size={size - 2} name="store" color={color} />,
        }}
      />
      <Tabs.Screen
        name="booking"
        options={{
          title: 'Booking',
          tabBarIcon: ({ color, size }) => <MaterialCommunityIcons size={size + 2} name="calendar-check" color={color} />,
        }}
      />
      <Tabs.Screen
        name="operations"
        options={{
          title: 'Ops',
          tabBarIcon: ({ color, size }) => <MaterialCommunityIcons size={size + 2} name="briefcase-variant" color={color} />,
        }}
      />
      <Tabs.Screen
        name="dashboard"
        options={{
          title: 'Dashboard',
          tabBarIcon: ({ color, size }) => <MaterialCommunityIcons size={size + 2} name="view-dashboard" color={color} />,
        }}
      />
      <Tabs.Screen
        name="account"
        options={{
          title: 'Account',
          tabBarIcon: ({ color, size }) => <MaterialCommunityIcons size={size + 2} name="account-circle" color={color} />,
        }}
      />
    </Tabs>
  );
}
