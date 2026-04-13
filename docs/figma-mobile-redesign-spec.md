# McDonald's Reservation Mobile Figma Spec

## Goal

Turn the current Laravel + Vue reservation system into a mobile-first Figma product design with responsive behavior and end-to-end clickable prototyping.

## Source Screens Found In The App

Customer-facing:

- Home landing and entry points
- Login and registration
- Booking wizard
- Manual food and drinks board
- Booking receipt and payment proof upload
- Customer dashboard
- Booking reschedule and cancellation
- Profile and account settings

Operations-facing:

- Staff dashboard
- Check-in scanner
- Live floor management
- Admin hub
- Pending bookings review
- Confirmed events
- Availability calendar
- Branches, catalog, reports, timeline, accounts

## Product Direction

### Primary experience

Build the core mobile app around the customer journey:

1. Discover booking options
2. Sign in or create account
3. Build reservation
4. Upload payment proof
5. Confirm booking
6. Track booking
7. Reschedule, cancel, or download QR pass

### Secondary experience

Keep staff and admin as companion responsive workspaces:

- Staff should work well on mobile and small tablet
- Admin should be optimized for tablet first, with phone-safe fallback views

## Figma File Structure

Create this Figma structure:

1. `00 Cover`
2. `01 Foundations`
3. `02 Components`
4. `03 Customer Mobile`
5. `04 Staff Mobile`
6. `05 Admin Tablet`
7. `06 Responsive Rules`
8. `07 Prototype Flows`

## Foundations

### Color system

Use the existing product colors as the base:

- Primary red: `#DA291C`
- Deep red: `#9F1914`
- Gold: `#FFC72C`
- Deep gold: `#D89A00`
- Cream background: `#FFF7E6`
- Charcoal text: `#1F1F1F`
- Ink text: `#241714`
- Night surface: `#150906`

### Surfaces

- Soft cream page backgrounds for customer screens
- White glass cards for content modules
- Dark red-to-brown gradient surfaces for premium or system-critical areas
- Gold accents for availability, highlights, and alerts

### Type direction

Preserve the current brand contrast:

- Heavy display style for headlines
- Clean readable sans-serif for body copy
- Strong uppercase labels for status, tags, and filters

### Spacing and radius

- Base spacing scale: `4, 8, 12, 16, 20, 24, 32`
- Card radius: `24`
- Input radius: `18`
- Button radius: `999`

## Core Components

Build these first as reusable Figma components with variants:

- App bar
- Bottom navigation
- Top segmented tabs
- Primary button
- Secondary button
- Ghost button
- Icon button
- Text input
- Select field
- Date picker field
- File upload field
- Step card
- Branch card
- Room choice card
- Package card
- Menu bundle row
- Manual menu item stepper row
- Add-on row
- Receipt line item
- Status badge
- Metric card
- Booking card
- Timeline alert card
- Calendar day tile
- Time slot chip
- QR pass card
- Empty state
- Confirmation modal

## Customer Mobile Screens

Design the customer app at `390x844` first, then create responsive variants.

### 1. Splash / entry

- Brand mark
- Short value proposition
- `Book Event` primary CTA
- `Sign In` secondary CTA

### 2. Authentication

- Sign in
- Register
- Forgot password

Prototype:

- Sign in success -> Dashboard
- Register success -> Booking flow or Dashboard

### 3. Home

- Hero summary
- Event type highlights
- Branch cards
- Featured packages
- Quick CTA to start reservation

Prototype:

- Event type tap -> Booking wizard with preset event type
- Branch tap -> Branch detail sheet or booking prefill

### 4. Booking wizard: Step 1

- Event type selection
- Guest count
- Duration summary

Prototype:

- Continue -> Step 2 only when required values exist

### 5. Booking wizard: Step 2

- Branch selection
- Room rental selection
- Schedule planner
- Calendar
- Start time and end time picker
- Availability summary

Prototype:

- Date tap -> Time selection state
- Invalid slots disabled
- Refresh action updates availability state

### 6. Booking wizard: Step 3

- Package selection
- Bundle add-ons

Prototype:

- Package change updates total in sticky footer

### 7. Booking wizard: Step 4

- Manual food and drinks board
- Category tabs
- Item cards
- Quantity controls
- Running totals

Prototype:

- Add item -> tray summary updates
- Category switch -> filtered menu list

### 8. Booking wizard: Step 5

- Extra add-ons
- Payment proof uploader
- Notes field
- Receipt preview

Prototype:

- Upload state
- Validation error state
- Submit -> Confirmation screen

### 9. Booking confirmation

- Reservation submitted state
- Booking summary
- Status badge: `pending review`
- CTA to open dashboard

### 10. Dashboard

- Upcoming bookings
- Spend summary
- Pending approvals
- Booking cards

Prototype:

- Tap booking -> Booking detail
- Refresh -> simulated loading state

### 11. Booking detail

- Booking reference
- Package and branch
- Date and time
- Guest count
- Notes
- Payment proof CTA
- Download QR pass CTA
- Reschedule sheet
- Cancel action

Prototype:

- Download pass -> QR pass screen
- Reschedule -> date/time selector -> save

### 12. QR pass

- Booking code
- QR visual
- Venue summary
- Event date and time

### 13. Profile

- Personal information
- Verification state
- Location
- Update profile
- Update password
- Delete account

## Staff Mobile Screens

Design staff at `390x844` and `768x1024`.

### 1. Staff dashboard

- History summary
- Check-in scanner card
- Upcoming events
- Prep list

### 2. Check-in flow

- Input for booking code or check-in code
- Success state
- Error state
- Pending admin confirmation state

### 3. Live floor management

- Active event cards
- Floor status selector
- Service adjustments
- Extra food
- Extra services

Prototype:

- Update status
- Save event edits

## Admin Responsive Screens

Design admin primarily at `834x1194`, with a compressed phone-safe fallback.

### 1. Admin hub

- Quick links
- Stats
- Alerts
- Branch summaries

### 2. Pending bookings review

- Search
- Grouped bookings by branch and event type
- Status update
- Crew assignment
- Customer details
- Payment proof preview
- Service adjustments

### 3. Availability calendar

- Branch switcher
- Month navigation
- Day status tiles
- Daily detail drill-down

### 4. Confirmed events

- Event list
- Status and service state
- Crew assignment

### 5. Catalog / branches / reports / timeline / accounts

- Use a consistent admin shell and list-detail pattern

## Responsive Rules

### Customer

- `390`: base mobile design
- `430`: expanded mobile spacing
- `768`: two-column tablet booking wizard
- `1440`: desktop adaptation using the existing sidebar concept

### Staff

- Mobile shows stacked cards and bottom nav
- Tablet introduces split views for alerts and live floor updates

### Admin

- Phone uses cards and drill-in screens instead of dense tables
- Tablet uses two-pane layouts
- Desktop can keep the current sidebar shell pattern

### Navigation transformation

- Desktop sidebar becomes bottom navigation on phone
- Secondary actions move into sheets, overflow menus, or segmented tabs

## Prototype Flows To Build In Figma

### Flow A: New customer booking

Home -> Sign in/Register -> Booking Step 1 -> Step 2 -> Step 3 -> Step 4 -> Step 5 -> Confirmation -> Dashboard -> Booking Detail -> QR Pass

### Flow B: Customer reschedule

Dashboard -> Booking Detail -> Reschedule Sheet -> Updated Booking Detail

### Flow C: Customer cancel

Dashboard -> Booking Detail -> Cancel Confirmation -> Dashboard Empty/Updated State

### Flow D: Staff check-in

Staff Dashboard -> Check-in -> Success State -> Live Floor Management

### Flow E: Admin review

Admin Hub -> Pending Bookings -> Booking Review -> Confirm Booking -> Confirmed Events

### Flow F: Admin availability inspection

Admin Hub -> Availability Calendar -> Day View -> Reservation Detail

## Figma Build Notes

- Use Auto Layout for every major container
- Build mobile components with variants for default, hover, active, disabled, success, warning, and error states where relevant
- Use component properties for labels, icons, and status text
- Keep sticky CTA footers on booking steps
- Use interactive components for segmented controls, status badges, and time slots
- Use overlays for reschedule, cancel, and upload assistance flows

## What To Prototype

Prototype at least these interactions:

- Event type selection
- Branch selection
- Calendar date selection
- Time slot selection
- Package switching
- Manual menu quantity changes
- Payment proof upload state
- Reservation submission
- Booking detail drill-in
- QR pass open
- Reschedule flow
- Cancel flow
- Staff check-in
- Staff service status updates
- Admin booking approval
- Admin availability drill-down

## Build Priority

### Phase 1

- Foundations
- Components
- Customer mobile flow

### Phase 2

- Staff mobile flow

### Phase 3

- Admin tablet flow

## Code References

These source files define the real product behavior and should stay aligned with the Figma version:

- `resources/js/pages/Home.vue`
- `resources/js/pages/Auth/Login.vue`
- `resources/js/pages/Reservations/Create.vue`
- `resources/js/pages/Dashboard.vue`
- `resources/js/pages/Profile/Edit.vue`
- `resources/js/pages/Staff/Dashboard.vue`
- `resources/js/pages/Admin/Dashboard.vue`
- `resources/js/pages/Admin/Bookings.vue`
- `resources/js/pages/Admin/Availability.vue`
- `resources/js/Components/AppShell.vue`
- `resources/css/app.css`
