export type MobileUser = {
  id: number;
  name: string;
  email: string;
  phone: string;
  role: string;
  birth_date: string | null;
  gender: string | null;
  address_line: string | null;
  city: string | null;
  province: string | null;
  postal_code: string | null;
  full_address: string;
};

export type EventType = {
  label: string;
  description: string;
  icon: string;
};

export type Branch = {
  code: string;
  name: string;
  city: string;
  supports: Record<string, boolean>;
  concurrent_limit: number;
  max_guests: number;
  map_url?: string | null;
};

export type PackageOption = {
  code: string;
  name: string;
  price: number;
  guest_range: string;
  features: string[];
};

export type MenuBundle = {
  code: string;
  name: string;
  price: number;
  prep_label?: string | null;
};

export type AddOn = {
  code: string;
  name: string;
  price: number;
};

export type ManualMenuOption = {
  code: string;
  label: string;
  price: number;
  prep_label?: string | null;
};

export type ManualMenuItem = {
  code: string;
  name: string;
  description?: string | null;
  badge?: string | null;
  artwork?: string | null;
  options: ManualMenuOption[];
};

export type ManualMenuCategory = {
  code: string;
  name: string;
  icon?: string | null;
  description?: string | null;
  items: ManualMenuItem[];
};

export type RoomChoice = {
  code: string;
  label: string;
  description: string;
  preferred_event_type?: string;
};

export type AvailabilitySlot = {
  time: string;
  label: string;
  booked: number;
  remaining: number;
  full: boolean;
};

export type AvailabilityDate = {
  date: string;
  status: 'full' | 'limited' | 'available';
  available_slots: number;
  slots: AvailabilitySlot[];
};

export type AvailabilityBranch = {
  code: string;
  name: string;
  city: string;
  supports: Record<string, boolean>;
  dates: AvailabilityDate[];
};

export type AvailabilityPayload = {
  generated_at: string;
  slotOptions: string[];
  branches: AvailabilityBranch[];
};

export type BookingCatalog = {
  eventTypes: Record<string, EventType>;
  branches: Record<string, Branch>;
  packages: Record<string, PackageOption[]>;
  menuBundles: MenuBundle[];
  addOns: AddOn[];
  menuCategories: ManualMenuCategory[];
  roomChoices: RoomChoice[];
  bookingWindow: {
    opening_hour: number;
    closing_hour: number;
    default_duration_hours: number;
  };
  slotOptions: string[];
  pricing: {
    weekend_multiplier: number;
    holiday_multiplier: number;
    extension_hourly_rate: number;
    holidays: string[];
  };
};

export type BookingOptionsPayload = {
  catalog: BookingCatalog;
  roomChoices: RoomChoice[];
  availability: AvailabilityPayload;
  defaults: {
    event_date: string;
    event_time: string;
    duration_hours: number;
    room_choice: string;
  };
};

export type HomePayload = {
  eventTypes: EventType[];
  branches: Branch[];
  featuredPackages: PackageOption[];
  stats: Array<{ label: string; value: string | number }>;
};

export type ReceiptLine = {
  label: string;
  type: string;
  amount: string;
};

export type ReservationRecord = {
  id: number;
  booking_reference: string;
  customer_name: string;
  customer_email: string;
  customer_phone: string;
  event_type: string;
  package_name: string;
  room_choice: string;
  branch: string;
  branch_code: string;
  event_date: string;
  event_start_time: string;
  event_start_label: string;
  event_end_time: string;
  event_end_label: string;
  event_time: string;
  duration_hours: number;
  guests: number;
  status: string;
  service_status: string;
  notes?: string | null;
  total_amount: number;
  receipt: {
    total: string;
    total_raw: number;
    subtotal: string;
    pricing_rule: string;
    line_items: ReceiptLine[];
  };
  pass_url?: string | null;
  payment_proof_preview_url?: string | null;
};

export type DashboardPayload = {
  bookings: ReservationRecord[];
  slotOptions: string[];
  stats: Array<{ label: string; value: string | number }>;
};
