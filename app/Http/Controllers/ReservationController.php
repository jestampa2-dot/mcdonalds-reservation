<?php

namespace App\Http\Controllers;

use App\Models\AddOn;
use App\Models\BookingPackage;
use App\Models\BookingSetting;
use App\Models\Branch;
use App\Models\BranchHost;
use App\Models\BranchInventoryItem;
use App\Models\EventType;
use App\Models\MenuCategory;
use App\Models\MenuBundle;
use App\Models\MenuItem;
use App\Models\MenuItemOption;
use App\Models\PricingSetting;
use App\Models\Reservation;
use App\Models\RoomOption;
use App\Models\User;
use App\Support\MenuCatalogSynchronizer;
use App\Support\Timeline\ReservationTimelineBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Throwable;

class ReservationController extends Controller
{
    protected ?bool $databaseAvailable = null;
    protected ?array $catalogCache = null;
    protected bool $databaseDefaultsEnsured = false;

    protected function reservationTimelineBuilder(): ReservationTimelineBuilder
    {
        return app(ReservationTimelineBuilder::class);
    }

    public function home(): InertiaResponse
    {
        $catalog = $this->catalog();
        $reservationCount = $this->hasTableSafely('reservations')
            ? $this->runDatabaseCheck(fn () => Reservation::count(), 0)
            : 0;

        return Inertia::render('Home', [
            'eventTypes' => array_values($catalog['eventTypes']),
            'branches' => array_values($catalog['branches']),
            'featuredPackages' => collect($catalog['packages'])->flatten(1)->take(3)->values(),
            'stats' => [
                ['label' => 'Live bookings', 'value' => $reservationCount ?: 24],
                ['label' => 'Party-ready branches', 'value' => collect($catalog['branches'])->filter(fn ($branch) => $branch['supports']['birthday'])->count()],
                ['label' => 'Average response time', 'value' => '15 min'],
            ],
        ]);
    }

    public function create(): InertiaResponse
    {
        $catalog = $this->catalog();

        return Inertia::render('Reservations/Create', [
            'catalog' => $catalog,
            'roomChoices' => $catalog['roomChoices'],
            'availability' => $this->availabilityPayload($catalog, 366),
            'defaults' => [
                'event_date' => now()->addDays(3)->toDateString(),
                'event_time' => '10:00',
                'duration_hours' => (int) ($catalog['bookingWindow']['default_duration_hours'] ?? 4),
                'room_choice' => $this->defaultRoomChoice('birthday'),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $catalog = $this->catalog();
        $customer = $request->user();

        $validated = $request->validate([
            'event_type' => ['required', Rule::in(array_keys($catalog['eventTypes']))],
            'branch_code' => ['required', Rule::in(array_keys($catalog['branches']))],
            'event_date' => ['required', 'date', 'after_or_equal:today'],
            'event_time' => ['required', Rule::in($catalog['slotOptions'])],
            'event_end_time' => ['nullable', 'date_format:H:i'],
            'duration_hours' => ['required', 'integer', 'min:1'],
            'room_choice' => ['required', Rule::in(collect($catalog['roomChoices'])->pluck('code')->all())],
            'guests' => ['required', 'integer', 'min:2', 'max:60'],
            'package_code' => ['required', 'string'],
            'menu_bundles' => ['array'],
            'menu_bundles.*' => ['string'],
            'manual_menu_items' => ['array'],
            'manual_menu_items.*.option_code' => ['required', 'string'],
            'manual_menu_items.*.quantity' => ['required', 'integer', 'min:1', 'max:99'],
            'add_ons' => ['array'],
            'add_ons.*' => ['string'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'payment_proof' => ['required', 'image', 'max:4096'],
        ]);

        $branch = $catalog['branches'][$validated['branch_code']];

        if (! empty($validated['event_end_time'])) {
            $selectedDuration = $this->durationBetweenTimes($validated['event_time'], $validated['event_end_time']);

            if (! is_int($selectedDuration) || $selectedDuration < 1) {
                return back()->withErrors([
                    'event_end_time' => 'Choose an end time that comes after the selected start time.',
                ])->withInput();
            }

            $validated['duration_hours'] = $selectedDuration;
        }

        if (! ($branch['supports'][$validated['event_type']] ?? false)) {
            return back()->withErrors([
                'branch_code' => 'The selected branch does not support that event type.',
            ])->withInput();
        }

        $package = collect($catalog['packages'][$validated['event_type']])->firstWhere('code', $validated['package_code']);

        if (! $package) {
            return back()->withErrors([
                'package_code' => 'Please choose a valid package.',
            ])->withInput();
        }

        if (! $this->isDurationWindowValid($validated['event_time'], (int) $validated['duration_hours'])) {
            return back()->withErrors([
                'event_time' => 'Reservations must fit within the event booking window of '.$this->bookingWindowLabel().'.',
            ])->with('error', 'Choose a start and end time that stays between '.$this->bookingWindowLabel().'.')->withInput();
        }

        if (! $this->isSlotAvailable($validated['branch_code'], $validated['event_date'], $validated['event_time'], (int) $validated['duration_hours'])) {
            return back()->withErrors([
                'event_time' => 'The chosen date and time are unavailable or already reserved.',
            ])->with('error', 'The chosen date and time are unavailable or already reserved. Please choose another schedule.')->withInput();
        }

        $menuBundles = collect($catalog['menuBundles'])
            ->whereIn('code', $validated['menu_bundles'] ?? [])
            ->values()
            ->all();

        $addOns = collect($catalog['addOns'])
            ->whereIn('code', $validated['add_ons'] ?? [])
            ->values()
            ->all();
        $manualMenuItems = $this->resolveManualMenuSelections($catalog, $validated['manual_menu_items'] ?? []);

        $proofPath = $request->file('payment_proof')->store('payment-proofs', 'public');
        $bookingReference = strtoupper('MCR-'.Str::random(8));
        $receipt = $this->buildReceipt(
            $validated['event_type'],
            $validated['event_date'],
            $package,
            $menuBundles,
            $manualMenuItems,
            $addOns,
            (int) $validated['duration_hours']
        );

        Reservation::create([
            'user_id' => $customer->id,
            'name' => $customer->name,
            'email' => $customer->email,
            'phone' => $customer->phone ?: 'Not provided',
            'booking_reference' => $bookingReference,
            'reservation_type' => $validated['event_type'],
            'package_name' => $package['name'],
            'package_code' => $package['code'],
            'room_choice' => collect($catalog['roomChoices'])->firstWhere('code', $validated['room_choice'])['label'] ?? $validated['room_choice'],
            'food_package' => $this->foodPackageSummary($menuBundles, $manualMenuItems),
            'beverage_package' => $this->beveragePackageSummary($menuBundles, $manualMenuItems),
            'event_materials' => collect($addOns)->pluck('name')->implode(', '),
            'branch' => $branch['name'],
            'branch_code' => $branch['code'],
            'event_date' => $validated['event_date'],
            'event_time' => $validated['event_time'],
            'duration_hours' => (int) $validated['duration_hours'],
            'menu_bundles' => Arr::pluck($menuBundles, 'code'),
            'add_ons' => Arr::pluck($addOns, 'code'),
            'manual_menu_items' => $manualMenuItems,
            'service_adjustments' => [
                'extra_menu_bundles' => [],
                'extra_add_ons' => [],
                'extra_manual_menu_items' => [],
            ],
            'payment_proof_path' => $proofPath,
            'guests' => $validated['guests'],
            'total_amount' => $receipt['total_raw'],
            'check_in_code' => substr(hash('sha256', $bookingReference), 0, 10),
            'notes' => $validated['notes'] ?? null,
            'status' => 'pending_review',
            'service_status' => 'available',
        ]);

        return redirect()->route('dashboard')->with('success', 'Reservation submitted. Your payment proof is queued for review.');
    }

    public function dashboard(Request $request): InertiaResponse
    {
        $catalog = $this->catalog();

        $bookings = Reservation::query()
            ->where('user_id', $request->user()->id)
            ->orderBy('event_date')
            ->orderBy('event_time')
            ->get();

        return Inertia::render('Dashboard', [
            'bookings' => $bookings->map(fn (Reservation $reservation) => $this->serializeReservation($reservation))->values(),
            'slotOptions' => $catalog['slotOptions'],
            'stats' => [
                ['label' => 'Upcoming', 'value' => $bookings->whereIn('status', ['pending_review', 'confirmed', 'rescheduled', 'checked_in'])->count()],
                ['label' => 'Confirmed spend', 'value' => $this->currencySymbol().number_format($bookings->whereIn('status', ['confirmed', 'checked_in'])->sum('total_amount'), 2)],
                ['label' => 'Pending approvals', 'value' => $bookings->where('status', 'pending_review')->count()],
            ],
        ]);
    }

    public function adminDashboard(Request $request): InertiaResponse
    {
        $this->authorizeRoles($request, ['admin', 'manager']);
        $catalog = $this->catalog();
        $bookings = $this->adminReservations(['assignedStaff:id,name']);
        $statsBookings = $this->adminStatsBookings();

        return Inertia::render('Admin/Dashboard', [
            'stats' => $this->adminStats($statsBookings),
            'notifications' => $this->upcomingEventNotifications($bookings),
            'history' => $this->eventHistory($bookings),
            'branchSummaries' => collect(array_values($catalog['branches']))->map(fn ($branch) => [
                'code' => $branch['code'],
                'name' => $branch['name'],
                'city' => $branch['city'],
            ])->values(),
        ]);
    }

    public function adminBookings(Request $request): InertiaResponse
    {
        $this->authorizeRoles($request, ['admin', 'manager']);
        $catalog = $this->catalog();
        $bookings = $this->adminReservations(['user', 'assignedStaff'], ['pending_review']);

        return Inertia::render('Admin/Bookings', [
            'stats' => $this->adminStats($this->adminStatsBookings()),
            'groupedBookings' => $this->groupedBookings($catalog, $bookings),
            'staffUsers' => $this->adminStaffUsers(),
            'menuBundles' => $catalog['menuBundles'],
            'addOns' => $catalog['addOns'],
            'durationOptions' => range(1, 16),
        ]);
    }

    public function adminConfirmedEvents(Request $request): InertiaResponse
    {
        $this->authorizeRoles($request, ['admin', 'manager']);
        $catalog = $this->catalog();
        $bookings = $this->adminReservations(['user', 'assignedStaff'], ['confirmed', 'rescheduled', 'checked_in']);

        return Inertia::render('Admin/ConfirmedEvents', [
            'stats' => $this->adminStats($this->adminStatsBookings()),
            'confirmedEvents' => $bookings->map(fn (Reservation $reservation) => $this->serializeReservation($reservation))->values(),
            'staffUsers' => $this->adminStaffUsers(),
            'menuBundles' => $catalog['menuBundles'],
            'addOns' => $catalog['addOns'],
            'durationOptions' => range(1, 16),
        ]);
    }

    public function adminAvailability(Request $request): InertiaResponse
    {
        $this->authorizeRoles($request, ['admin', 'manager']);
        $catalog = $this->catalog();
        $availability = $this->availabilityPayload($catalog, 366);
        $branchCodes = collect($availability['branches'] ?? [])->pluck('code')->filter()->values();
        $initialBranch = $request->string('branch')->toString();
        $initialMonth = $request->string('month')->toString();

        if (! $branchCodes->contains($initialBranch)) {
            $initialBranch = (string) ($branchCodes->first() ?? '');
        }

        if (! preg_match('/^\d{4}-\d{2}$/', $initialMonth)) {
            $initialMonth = now()->format('Y-m');
        }

        return Inertia::render('Admin/Availability', [
            'availability' => $availability,
            'initialBranch' => $initialBranch,
            'initialMonth' => $initialMonth,
        ]);
    }

    public function adminAvailabilityDay(Request $request, string $branchCode, string $date): InertiaResponse
    {
        $this->authorizeRoles($request, ['admin', 'manager']);

        $day = $this->availabilityDayPayload($this->catalog(), $branchCode, $date);

        return Inertia::render('Admin/AvailabilityDay', [
            'dayAvailability' => $day,
            'returnTo' => [
                'branch' => $request->string('branch')->toString() ?: $branchCode,
                'month' => preg_match('/^\d{4}-\d{2}$/', $request->string('month')->toString())
                    ? $request->string('month')->toString()
                    : substr($date, 0, 7),
            ],
        ]);
    }

    public function adminBranches(Request $request): InertiaResponse
    {
        $this->authorizeRoles($request, ['admin', 'manager']);

        return Inertia::render('Admin/Branches', [
            'branches' => $this->adminBranchList(),
        ]);
    }

    public function adminAccounts(Request $request): InertiaResponse
    {
        $this->authorizeRoles($request, ['admin', 'manager']);

        return Inertia::render('Admin/Accounts', [
            'users' => $this->adminUsers(),
            'canManageAccounts' => $request->user()->role === 'admin',
        ]);
    }

    public function adminCatalog(Request $request): InertiaResponse
    {
        $this->authorizeRoles($request, ['admin', 'manager']);
        $this->ensureDatabaseBackedDefaults();

        return Inertia::render('Admin/Catalog', [
            'eventTypes' => $this->runDatabaseCheck(
                fn () => EventType::query()
                    ->with(['packages' => fn ($query) => $query->orderBy('sort_order')->orderBy('name')])
                    ->orderBy('sort_order')
                    ->orderBy('label')
                    ->get()
                    ->map(fn (EventType $eventType) => [
                        'id' => $eventType->id,
                        'code' => $eventType->code,
                        'label' => $eventType->label,
                        'description' => $eventType->description,
                        'icon' => $eventType->icon,
                        'is_active' => $eventType->is_active,
                        'packages' => $eventType->packages->map(fn (BookingPackage $package) => [
                            'id' => $package->id,
                            'code' => $package->code,
                            'name' => $package->name,
                            'price' => (float) $package->price,
                            'guest_range' => $package->guest_range,
                            'features' => $package->features ?? [],
                            'is_active' => $package->is_active,
                        ])->values(),
                    ])->values(),
                collect()
            ),
            'roomOptions' => $this->roomChoices(true),
            'bookingSettings' => $this->bookingWindowSettings(),
        ]);
    }

    public function adminReports(Request $request): InertiaResponse
    {
        $this->authorizeRoles($request, ['admin', 'manager']);
        $catalog = $this->catalog();
        $bookings = $this->adminReservations(['assignedStaff:id,name']);

        return Inertia::render('Admin/Reports', [
            'pricing' => $catalog['pricing'],
            'report' => $this->analyticsReport($bookings),
            'inventory' => $this->inventorySnapshot($catalog, $bookings),
            'staffAssignments' => $this->staffAssignments($catalog, $bookings),
        ]);
    }

    public function adminTimeline(Request $request): InertiaResponse
    {
        $this->authorizeRoles($request, ['admin', 'manager']);
        $bookings = $this->adminReservations(['assignedStaff:id,name']);

        return Inertia::render('Admin/Timeline', [
            'notifications' => $this->upcomingEventNotifications($bookings),
            'history' => $this->eventHistory($bookings),
            'cancelledEvents' => $this->cancelledEvents($bookings),
        ]);
    }

    public function adminNotificationBar(Request $request): JsonResponse
    {
        $this->authorizeRoles($request, ['admin', 'manager']);

        return response()->json($this->pendingReservationAlerts());
    }

    public function staffDashboard(Request $request): InertiaResponse
    {
        $this->authorizeRoles($request, ['admin', 'manager', 'staff']);

        $catalog = $this->catalog();
        $today = now()->toDateString();
        $todayBookings = Reservation::query()
            ->when($request->user()->role === 'staff', function ($query) use ($request) {
                $query->where(function ($inner) use ($request) {
                    $inner->where('assigned_staff_id', $request->user()->id)
                        ->orWhereNull('assigned_staff_id');
                });
            })
            ->whereDate('event_date', $today)
            ->whereIn('status', ['confirmed', 'rescheduled', 'checked_in'])
            ->orderBy('event_time')
            ->get();
        $relevantBookings = Reservation::query()
            ->when($request->user()->role === 'staff', function ($query) use ($request) {
                $query->where(function ($inner) use ($request) {
                    $inner->where('assigned_staff_id', $request->user()->id)
                        ->orWhereNull('assigned_staff_id');
                });
            })
            ->whereIn('status', ['confirmed', 'rescheduled', 'checked_in', 'completed', 'cancelled'])
            ->orderBy('event_date')
            ->orderBy('event_time')
            ->get();

        return Inertia::render('Staff/Dashboard', [
            'prepList' => $this->prepList($catalog, $todayBookings),
            'todayBookings' => $todayBookings->map(fn (Reservation $reservation) => $this->serializeReservation($reservation))->values(),
            'notifications' => $this->upcomingEventNotifications($relevantBookings, 6),
            'history' => $this->eventHistory($relevantBookings, 8),
            'statusOptions' => ['available', 'cleaning', 'in_progress'],
            'menuBundles' => $catalog['menuBundles'],
            'addOns' => $catalog['addOns'],
            'durationOptions' => range(1, 16),
        ]);
    }

    public function cancel(Request $request, Reservation $reservation): RedirectResponse
    {
        $this->authorizeReservationAccess($request, $reservation);

        if ($reservation->checked_in_at) {
            return back()->with('error', 'Checked-in bookings cannot be cancelled.');
        }

        $reservation->update([
            'status' => 'cancelled',
            'service_status' => 'available',
        ]);

        return back()->with('success', 'Booking cancelled.');
    }

    public function reschedule(Request $request, Reservation $reservation): RedirectResponse
    {
        $this->authorizeReservationAccess($request, $reservation);

        $validated = $request->validate([
            'event_date' => ['required', 'date', 'after_or_equal:today'],
            'event_time' => ['required', Rule::in($this->catalog()['slotOptions'])],
        ]);

        $durationHours = (int) ($reservation->duration_hours ?? 4);

        if (! $this->isDurationWindowValid($validated['event_time'], $durationHours)) {
            return back()->withErrors([
                'event_time' => 'Reservations must fit within the event booking window of '.$this->bookingWindowLabel().'.',
            ])->with('error', 'Choose a start and end time that stays between '.$this->bookingWindowLabel().'.');
        }

        if (! $this->isSlotAvailable($reservation->branch_code, $validated['event_date'], $validated['event_time'], $durationHours, $reservation->id)) {
            return back()->withErrors([
                'event_time' => 'The chosen date and time are unavailable or already reserved.',
            ])->with('error', 'The chosen date and time are unavailable or already reserved. Please choose another schedule.');
        }

        $reservation->update([
            'event_date' => $validated['event_date'],
            'event_time' => $validated['event_time'],
            'status' => 'rescheduled',
            'checked_in_at' => null,
            'checked_in_by' => null,
            'service_status' => 'available',
        ]);

        return back()->with('success', 'Booking rescheduled.');
    }

    public function updateBookingStatus(Request $request, Reservation $reservation): RedirectResponse
    {
        $this->authorizeRoles($request, ['admin', 'manager']);

        $validated = $request->validate([
            'status' => ['required', Rule::in(['pending_review', 'confirmed', 'checked_in', 'completed', 'cancelled'])],
        ]);

        $updates = ['status' => $validated['status']];

        if (in_array($validated['status'], ['completed', 'cancelled'], true)) {
            $updates['service_status'] = 'available';
        }

        $reservation->update($updates);

        if ($validated['status'] === 'confirmed') {
            return back()->with('success', 'Ba-da-ba-ba-ba. The customer reservation is successful and officially confirmed.');
        }

        if ($validated['status'] === 'completed') {
            return back()->with('success', 'Event marked as done and moved to history.');
        }

        return back()->with('success', 'Booking status updated.');
    }

    public function assignCrew(Request $request, Reservation $reservation): RedirectResponse
    {
        $this->authorizeRoles($request, ['admin', 'manager']);

        $validated = $request->validate([
            'assigned_staff_id' => ['nullable', Rule::exists('users', 'id')],
        ]);

        $reservation->update([
            'assigned_staff_id' => $validated['assigned_staff_id'] ?? null,
        ]);

        return back()->with('success', 'Crew assignment updated.');
    }

    public function updateUserRole(Request $request, User $user): RedirectResponse
    {
        $this->authorizeRoles($request, ['admin']);

        $validated = $request->validate([
            'role' => ['required', Rule::in(['customer', 'staff', 'manager', 'admin'])],
        ]);

        $user->update([
            'role' => $validated['role'],
        ]);

        return back()->with('success', 'Account role updated.');
    }

    public function storeAdminUser(Request $request): RedirectResponse
    {
        $this->authorizeRoles($request, ['admin']);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:30'],
            'role' => ['required', Rule::in(['customer', 'staff', 'manager', 'admin'])],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?: null,
            'role' => $validated['role'],
            'password' => Hash::make($validated['password']),
        ]);

        return back()->with('success', 'Account created.');
    }

    public function updateAdminUser(Request $request, User $user): RedirectResponse
    {
        $this->authorizeRoles($request, ['admin']);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:30'],
            'role' => ['required', Rule::in(['customer', 'staff', 'manager', 'admin'])],
            'password' => ['nullable', 'confirmed', Password::defaults()],
        ]);

        $updates = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?: null,
            'role' => $validated['role'],
        ];

        if ($user->email !== $validated['email']) {
            $updates['email_verified_at'] = null;
        }

        if (! empty($validated['password'])) {
            $updates['password'] = Hash::make($validated['password']);
        }

        $user->update($updates);

        return back()->with('success', 'Account updated.');
    }

    public function destroyAdminUser(Request $request, User $user): RedirectResponse
    {
        $this->authorizeRoles($request, ['admin']);

        if ($request->user()->is($user)) {
            return back()->with('error', 'You cannot delete your own account.');
        }

        if ($user->role === 'admin' && User::query()->where('role', 'admin')->count() <= 1) {
            return back()->with('error', 'At least one admin account must remain in the system.');
        }

        $user->delete();

        return back()->with('success', 'Account deleted.');
    }

    public function updateEventType(Request $request, EventType $eventType): RedirectResponse
    {
        $this->authorizeRoles($request, ['admin', 'manager']);

        $validated = $request->validate([
            'label' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:1000'],
            'icon' => ['nullable', 'string', 'max:100'],
            'is_active' => ['required', 'boolean'],
        ]);

        $eventType->update($validated);

        return back()->with('success', 'Event type updated.');
    }

    public function updatePackage(Request $request, BookingPackage $package): RedirectResponse
    {
        $this->authorizeRoles($request, ['admin', 'manager']);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'price' => ['required', 'numeric', 'min:0'],
            'guest_range' => ['nullable', 'string', 'max:255'],
            'features' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['required', 'boolean'],
        ]);

        $package->update([
            'name' => $validated['name'],
            'price' => $validated['price'],
            'guest_range' => $validated['guest_range'] ?? null,
            'features' => collect(preg_split('/\r\n|\r|\n/', $validated['features'] ?? ''))
                ->map(fn ($item) => trim((string) $item))
                ->filter()
                ->values()
                ->all(),
            'is_active' => $validated['is_active'],
        ]);

        return back()->with('success', 'Package updated.');
    }

    public function storeRoomOption(Request $request): RedirectResponse
    {
        $this->authorizeRoles($request, ['admin', 'manager']);

        $validated = $request->validate([
            'label' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:1000'],
            'preferred_event_type' => ['nullable', Rule::in(array_keys($this->catalog()['eventTypes']))],
        ]);

        RoomOption::create([
            'code' => Str::slug($validated['label']),
            'label' => $validated['label'],
            'description' => $validated['description'],
            'preferred_event_type' => $validated['preferred_event_type'] ?? null,
            'sort_order' => ((int) RoomOption::query()->max('sort_order')) + 1,
            'is_active' => true,
        ]);

        return back()->with('success', 'Room option added.');
    }

    public function updateRoomOption(Request $request, RoomOption $roomOption): RedirectResponse
    {
        $this->authorizeRoles($request, ['admin', 'manager']);

        $validated = $request->validate([
            'label' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:1000'],
            'preferred_event_type' => ['nullable', Rule::in(array_keys($this->catalog()['eventTypes']))],
            'is_active' => ['required', 'boolean'],
        ]);

        $roomOption->update([
            'label' => $validated['label'],
            'description' => $validated['description'],
            'preferred_event_type' => $validated['preferred_event_type'] ?? null,
            'is_active' => $validated['is_active'],
        ]);

        return back()->with('success', 'Room option updated.');
    }

    public function updateBookingSettings(Request $request): RedirectResponse
    {
        $this->authorizeRoles($request, ['admin', 'manager']);

        $validated = $request->validate([
            'opening_hour' => ['required', 'integer', 'min:0', 'max:22'],
            'closing_hour' => ['required', 'integer', 'min:1', 'max:23', 'gt:opening_hour'],
            'default_duration_hours' => ['required', 'integer', 'min:1', 'max:16'],
        ]);

        BookingSetting::updateOrCreate(
            ['id' => 1],
            [
                'opening_hour' => $validated['opening_hour'],
                'closing_hour' => $validated['closing_hour'],
                'default_duration_hours' => $validated['default_duration_hours'],
                'is_active' => true,
            ]
        );

        return back()->with('success', 'Booking hours updated.');
    }

    public function storeBranch(Request $request): RedirectResponse
    {
        $this->authorizeRoles($request, ['admin', 'manager']);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:255', 'unique:branches,code'],
            'map_url' => ['nullable', 'url'],
            'concurrent_limit' => ['required', 'integer', 'min:1', 'max:10'],
            'max_guests' => ['required', 'integer', 'min:4', 'max:200'],
            'supports' => ['required', 'array'],
            'supports.*' => ['string', Rule::in(array_keys($this->catalog()['eventTypes']))],
        ]);

        $supports = collect(array_keys($this->catalog()['eventTypes']))
            ->mapWithKeys(fn ($type) => [$type => in_array($type, $validated['supports'], true)])
            ->all();

        $branch = Branch::create([
            'name' => $validated['name'],
            'city' => $validated['city'],
            'code' => $validated['code'],
            'map_url' => $validated['map_url'] ?? null,
            'concurrent_limit' => $validated['concurrent_limit'],
            'max_guests' => $validated['max_guests'],
            'supports' => $supports,
            'inventory' => [],
            'hosts' => [],
            'is_active' => true,
        ]);

        if (Schema::hasTable('branch_event_type') && Schema::hasTable('event_types')) {
            $branch->supportedEventTypes()->sync(
                EventType::query()
                    ->whereIn('code', $validated['supports'])
                    ->pluck('id')
                    ->all()
            );
        }

        return back()->with('success', 'New branch added.');
    }

    public function updateBranch(Request $request, Branch $branch): RedirectResponse
    {
        $this->authorizeRoles($request, ['admin', 'manager']);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:255'],
            'map_url' => ['nullable', 'url'],
            'concurrent_limit' => ['required', 'integer', 'min:1', 'max:10'],
            'max_guests' => ['required', 'integer', 'min:4', 'max:200'],
            'supports' => ['required', 'array', 'min:1'],
            'supports.*' => ['string', Rule::in(array_keys($this->catalog()['eventTypes']))],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $supports = collect(array_keys($this->catalog()['eventTypes']))
            ->mapWithKeys(fn ($type) => [$type => in_array($type, $validated['supports'], true)])
            ->all();

        $branch->update([
            'name' => $validated['name'],
            'city' => $validated['city'],
            'map_url' => $validated['map_url'] ?? null,
            'concurrent_limit' => $validated['concurrent_limit'],
            'max_guests' => $validated['max_guests'],
            'supports' => $supports,
            'is_active' => (bool) ($validated['is_active'] ?? true),
        ]);

        if (Schema::hasTable('branch_event_type') && Schema::hasTable('event_types')) {
            $branch->supportedEventTypes()->sync(
                EventType::query()
                    ->whereIn('code', $validated['supports'])
                    ->pluck('id')
                    ->all()
            );
        }

        return back()->with('success', 'Branch updated.');
    }

    public function destroyBranch(Request $request, Branch $branch): RedirectResponse
    {
        $this->authorizeRoles($request, ['admin', 'manager']);

        $branch->delete();

        return back()->with('success', 'Branch deleted.');
    }

    public function storeInventoryItem(Request $request, Branch $branch): RedirectResponse
    {
        $this->authorizeRoles($request, ['admin', 'manager']);

        $validated = $request->validate([
            'item' => ['required', 'string', 'max:255'],
            'stock' => ['required', 'integer', 'min:0'],
            'threshold' => ['required', 'integer', 'min:0'],
        ]);

        $nextSortOrder = (int) ($branch->inventoryItems()->max('sort_order') ?? -1) + 1;

        BranchInventoryItem::create([
            'branch_id' => $branch->id,
            'item' => $validated['item'],
            'stock' => $validated['stock'],
            'threshold' => $validated['threshold'],
            'sort_order' => $nextSortOrder,
        ]);

        return back()->with('success', 'Inventory item added.');
    }

    public function updateInventoryItem(Request $request, BranchInventoryItem $inventoryItem): RedirectResponse
    {
        $this->authorizeRoles($request, ['admin', 'manager']);

        $validated = $request->validate([
            'item' => ['required', 'string', 'max:255'],
            'stock' => ['required', 'integer', 'min:0'],
            'threshold' => ['required', 'integer', 'min:0'],
        ]);

        $inventoryItem->update($validated);

        return back()->with('success', 'Inventory item updated.');
    }

    public function updateServiceStatus(Request $request, Reservation $reservation): RedirectResponse
    {
        $this->authorizeRoles($request, ['admin', 'manager', 'staff']);

        $validated = $request->validate([
            'service_status' => ['required', Rule::in(['available', 'cleaning', 'in_progress'])],
        ]);

        $reservation->update([
            'service_status' => $validated['service_status'],
        ]);

        return back()->with('success', 'Floor status updated.');
    }

    public function updateServiceAdjustments(Request $request, Reservation $reservation): RedirectResponse
    {
        $this->authorizeRoles($request, ['admin', 'manager', 'staff']);

        $validated = $request->validate([
            'duration_hours' => ['required', 'integer', 'min:1'],
            'extra_menu_bundles' => ['array'],
            'extra_menu_bundles.*' => ['string'],
            'extra_add_ons' => ['array'],
            'extra_add_ons.*' => ['string'],
        ]);

        if (! in_array($reservation->status, ['confirmed', 'rescheduled', 'checked_in'], true)) {
            return back()->with('error', 'Only confirmed or active events can be edited on the service floor.');
        }

        if (! $this->isDurationWindowValid($reservation->event_time, (int) $validated['duration_hours'])) {
            return back()->with('error', 'The updated duration must stay within the '.$this->bookingWindowLabel().' booking window.');
        }

        $catalog = $this->catalog();
        $currentServiceAdjustments = $reservation->service_adjustments ?? [];
        $package = collect($catalog['packages'][$reservation->reservation_type] ?? [])->firstWhere('code', $reservation->package_code)
            ?? ['name' => $reservation->package_name, 'price' => (float) $reservation->total_amount];
        $menuBundles = collect($catalog['menuBundles'])->whereIn('code', $reservation->menu_bundles ?? [])->values()->all();
        $manualMenuItems = collect($reservation->manual_menu_items ?? [])
            ->map(fn ($item) => $this->normalizeManualMenuSnapshot($item))
            ->filter()
            ->values()
            ->all();
        $addOns = collect($catalog['addOns'])->whereIn('code', $reservation->add_ons ?? [])->values()->all();
        $serviceAdjustments = [
            'extra_menu_bundles' => collect($validated['extra_menu_bundles'] ?? [])
                ->filter(fn ($code) => collect($catalog['menuBundles'])->pluck('code')->contains($code))
                ->values()
                ->all(),
            'extra_add_ons' => collect($validated['extra_add_ons'] ?? [])
                ->filter(fn ($code) => collect($catalog['addOns'])->pluck('code')->contains($code))
                ->values()
                ->all(),
            'extra_manual_menu_items' => $currentServiceAdjustments['extra_manual_menu_items'] ?? [],
        ];

        $receipt = $this->buildReceipt(
            $reservation->reservation_type,
            $reservation->event_date?->toDateString() ?? now()->toDateString(),
            $package,
            $menuBundles,
            $manualMenuItems,
            $addOns,
            (int) $validated['duration_hours'],
            $serviceAdjustments
        );

        $reservation->update([
            'duration_hours' => (int) $validated['duration_hours'],
            'service_adjustments' => $serviceAdjustments,
            'total_amount' => $receipt['total_raw'],
        ]);

        return back()->with('success', 'Live event updates saved.');
    }

    public function checkIn(Request $request): RedirectResponse
    {
        $this->authorizeRoles($request, ['admin', 'manager', 'staff']);

        $validated = $request->validate([
            'code' => ['required', 'string'],
        ]);

        $reservation = Reservation::query()
            ->where('booking_reference', strtoupper($validated['code']))
            ->orWhere('check_in_code', strtolower($validated['code']))
            ->first();

        if (! $reservation) {
            return back()->with('error', 'No reservation matched that code.');
        }

        if (! in_array($reservation->status, ['confirmed', 'rescheduled'], true)) {
            return back()->with('error', 'This booking must be confirmed by admin before staff can check it in.');
        }

        $reservation->update([
            'status' => 'checked_in',
            'checked_in_at' => now(),
            'checked_in_by' => $request->user()->name,
            'service_status' => 'in_progress',
        ]);

        return back()->with('success', 'Guest checked in successfully.');
    }

    public function availability(Request $request): JsonResponse
    {
        $this->authorizeRoles($request, ['customer', 'staff', 'manager', 'admin']);

        return response()->json($this->availabilityPayload($this->catalog(), 366));
    }

    public function paymentProof(Request $request, Reservation $reservation)
    {
        $this->authorizeReservationAccess($request, $reservation, true);

        abort_unless($reservation->payment_proof_path && Storage::disk('public')->exists($reservation->payment_proof_path), 404);

        $filePath = storage_path('app/public/'.ltrim($reservation->payment_proof_path, '/\\'));

        return response()->download(
            $filePath,
            $reservation->booking_reference.'-payment-proof.'.pathinfo($reservation->payment_proof_path, PATHINFO_EXTENSION)
        );
    }

    public function pass(Request $request, Reservation $reservation): Response
    {
        $this->authorizeReservationAccess($request, $reservation, true);

        return response($this->buildPassSvg($reservation), 200, [
            'Content-Type' => 'image/svg+xml',
            'Content-Disposition' => 'attachment; filename="'.$reservation->booking_reference.'-pass.svg"',
        ]);
    }

    protected function catalog(): array
    {
        if ($this->catalogCache !== null) {
            return $this->catalogCache;
        }

        $this->ensureDatabaseBackedDefaults();

        if ($this->dbCatalogAvailable()) {
            $databaseCatalog = $this->runDatabaseCheck(function () {
                $eventTypes = EventType::query()
                    ->where('is_active', true)
                    ->orderBy('sort_order')
                    ->get();
                $eventTypeCodes = $eventTypes->pluck('code')->all();
                $pricing = PricingSetting::query()
                    ->where('is_active', true)
                    ->latest('id')
                    ->first();

                return [
                    'eventTypes' => $eventTypes->mapWithKeys(fn (EventType $eventType) => [
                        $eventType->code => [
                            'label' => $eventType->label,
                            'description' => $eventType->description,
                            'icon' => $eventType->icon,
                        ],
                    ])->all(),
                    'branches' => $this->branchCatalog($eventTypeCodes),
                    'packages' => BookingPackage::query()
                        ->with('eventType:id,code')
                        ->where('is_active', true)
                        ->orderBy('sort_order')
                        ->get()
                        ->groupBy(fn (BookingPackage $package) => $package->eventType?->code)
                        ->map(fn ($items) => $items->map(fn (BookingPackage $package) => [
                            'code' => $package->code,
                            'name' => $package->name,
                            'price' => (float) $package->price,
                            'guest_range' => $package->guest_range,
                            'features' => $package->features ?? [],
                        ])->values()->all())
                        ->all(),
                    'menuBundles' => MenuBundle::query()
                        ->where('is_active', true)
                        ->orderBy('sort_order')
                        ->get()
                        ->map(fn (MenuBundle $bundle) => [
                            'code' => $bundle->code,
                            'name' => $bundle->name,
                            'price' => (float) $bundle->price,
                            'prep_label' => $bundle->prep_label,
                        ])->values()->all(),
                    'addOns' => AddOn::query()
                        ->where('is_active', true)
                        ->orderBy('sort_order')
                        ->get()
                        ->map(fn (AddOn $addOn) => [
                            'code' => $addOn->code,
                            'name' => $addOn->name,
                            'price' => (float) $addOn->price,
                        ])->values()->all(),
                    'menuCategories' => $this->menuCategoryCatalog(),
                    'roomChoices' => $this->roomChoices(),
                    'bookingWindow' => $this->bookingWindowSettings(),
                    'slotOptions' => $this->slotOptions(),
                    'pricing' => [
                        'weekend_multiplier' => (float) ($pricing?->weekend_multiplier ?? 1.15),
                        'holiday_multiplier' => (float) ($pricing?->holiday_multiplier ?? 1.25),
                        'extension_hourly_rate' => (float) ($pricing?->extension_hourly_rate ?? 450),
                        'holidays' => $pricing?->holidays ?? [],
                    ],
                ];
            }, null);

            if ($databaseCatalog) {
                return $this->catalogCache = $databaseCatalog;
            }
        }

        return $this->catalogCache = [
            'eventTypes' => config('booking.event_types'),
            'branches' => $this->branchCatalog(),
            'packages' => config('booking.packages'),
            'menuBundles' => config('booking.menu_bundles'),
            'addOns' => config('booking.add_ons'),
            'menuCategories' => [],
            'roomChoices' => $this->roomChoices(),
            'bookingWindow' => $this->bookingWindowSettings(),
            'slotOptions' => $this->slotOptions(),
            'pricing' => config('booking.pricing'),
        ];
    }

    protected function adminReservations(array $with = [], ?array $statuses = null)
    {
        return Reservation::query()
            ->with($with)
            ->when($statuses, fn ($query) => $query->whereIn('status', $statuses))
            ->orderBy('event_date')
            ->orderBy('event_time')
            ->get();
    }

    protected function adminStatsBookings()
    {
        return Reservation::query()
            ->get(['id', 'status', 'event_time', 'total_amount']);
    }

    protected function adminUsers()
    {
        return User::query()
            ->orderByRaw("CASE role WHEN 'admin' THEN 1 WHEN 'manager' THEN 2 WHEN 'staff' THEN 3 ELSE 4 END")
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'phone', 'role', 'created_at'])
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'role' => $user->role,
                'created_at' => optional($user->created_at)->format('M j, Y g:i A'),
            ]);
    }

    protected function adminBranchList(): array
    {
        $this->ensureDatabaseBackedDefaults();

        if ($this->hasTableSafely('branches')) {
            $branches = $this->runDatabaseCheck(
                fn () => Branch::query()->orderBy('name')->get(),
                collect()
            );

            if ($branches->isNotEmpty()) {
                return $branches->map(fn (Branch $branch) => [
                    'id' => $branch->id,
                    'code' => $branch->code,
                    'name' => $branch->name,
                    'city' => $branch->city,
                    'map_url' => $branch->map_url,
                    'concurrent_limit' => $branch->concurrent_limit,
                    'max_guests' => $branch->max_guests,
                    'supports' => collect($branch->supports ?? [])
                        ->filter(fn ($enabled) => $enabled)
                        ->keys()
                        ->values()
                        ->all(),
                    'is_active' => (bool) $branch->is_active,
                ])->values()->all();
            }
        }

        return collect($this->catalog()['branches'])->values()->map(fn ($branch) => [
            'id' => null,
            'code' => $branch['code'],
            'name' => $branch['name'],
            'city' => $branch['city'],
            'map_url' => $branch['map_url'] ?? null,
            'concurrent_limit' => $branch['concurrent_limit'] ?? 1,
            'max_guests' => $branch['max_guests'] ?? 40,
            'supports' => collect($branch['supports'] ?? [])
                ->filter(fn ($enabled) => $enabled)
                ->keys()
                ->values()
                ->all(),
            'is_active' => true,
        ])->all();
    }

    protected function adminStaffUsers()
    {
        return User::query()
            ->whereIn('role', ['staff', 'manager'])
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'role'])
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'role' => $user->role,
            ]);
    }

    protected function menuCategoryCatalog(): array
    {
        if (! $this->hasTableSafely('menu_categories') || ! $this->hasTableSafely('menu_items') || ! $this->hasTableSafely('menu_item_options')) {
            return [];
        }

        $categories = $this->runDatabaseCheck(fn () => MenuCategory::query()
            ->where('is_active', true)
            ->with([
                'items' => fn ($query) => $query->where('is_active', true)->orderBy('sort_order'),
                'items.options' => fn ($query) => $query->where('is_active', true)->orderBy('sort_order'),
            ])
            ->orderBy('sort_order')
            ->get(), collect());

        return $categories->map(fn (MenuCategory $category) => [
            'code' => $category->code,
            'name' => $category->name,
            'icon' => $category->icon,
            'description' => $category->description,
            'items' => $category->items->map(fn (MenuItem $item) => [
                'code' => $item->code,
                'name' => $item->name,
                'description' => $item->description,
                'badge' => $item->badge,
                'artwork' => $item->artwork,
                'options' => $item->options->map(fn (MenuItemOption $option) => [
                    'code' => $option->code,
                    'label' => $option->label,
                    'price' => (float) $option->price,
                    'prep_label' => $option->prep_label,
                ])->values()->all(),
            ])->values()->all(),
        ])->values()->all();
    }

    protected function branchCatalog(array $eventTypeCodes = []): array
    {
        $this->ensureDatabaseBackedDefaults();

        if (! $this->hasTableSafely('branches')) {
            return config('booking.branches');
        }

        $branches = $this->runDatabaseCheck(fn () => Branch::query()
            ->where('is_active', true)
            ->with([
                'supportedEventTypes:id,code',
                'inventoryItems:id,branch_id,item,stock,threshold,sort_order',
                'hostsList:id,branch_id,name,sort_order',
            ])
            ->orderBy('name')
            ->get(), collect());

        if ($branches->isEmpty()) {
            return config('booking.branches');
        }

        return $branches->mapWithKeys(fn (Branch $branch) => [
            $branch->code => [
                'code' => $branch->code,
                'name' => $branch->name,
                'city' => $branch->city,
                'supports' => ! empty($eventTypeCodes)
                    ? (
                        $branch->supportedEventTypes->isNotEmpty()
                            ? collect($eventTypeCodes)->mapWithKeys(fn ($code) => [$code => $branch->supportedEventTypes->contains('code', $code)])->all()
                            : ($branch->supports ?? [])
                    )
                    : ($branch->supports ?? []),
                'concurrent_limit' => $branch->concurrent_limit,
                'max_guests' => $branch->max_guests,
                'map_url' => $branch->map_url,
                'inventory' => $branch->inventoryItems->isNotEmpty()
                    ? $branch->inventoryItems->sortBy('sort_order')->map(fn ($item) => [
                        'item' => $item->item,
                        'stock' => $item->stock,
                        'threshold' => $item->threshold,
                    ])->values()->all()
                    : ($branch->inventory ?? []),
                'hosts' => $branch->hostsList->isNotEmpty()
                    ? $branch->hostsList->sortBy('sort_order')->pluck('name')->values()->all()
                    : ($branch->hosts ?? []),
            ],
        ])->all();
    }

    protected function dbCatalogAvailable(): bool
    {
        $this->ensureDatabaseBackedDefaults();

        return $this->hasTableSafely('event_types')
            && $this->hasTableSafely('booking_packages')
            && $this->hasTableSafely('menu_bundles')
            && $this->hasTableSafely('add_ons')
            && $this->runDatabaseCheck(fn () => EventType::query()->exists(), false);
    }

    protected function bookingWindowSettings(): array
    {
        $this->ensureDatabaseBackedDefaults();

        if ($this->hasTableSafely('booking_settings')) {
            $setting = $this->runDatabaseCheck(
                fn () => BookingSetting::query()->where('is_active', true)->latest('id')->first(),
                null
            );

            if ($setting) {
                return [
                    'opening_hour' => (int) $setting->opening_hour,
                    'closing_hour' => (int) $setting->closing_hour,
                    'default_duration_hours' => (int) $setting->default_duration_hours,
                ];
            }
        }

        return [
            'opening_hour' => 7,
            'closing_hour' => 23,
            'default_duration_hours' => 4,
        ];
    }

    protected function slotOptions(): array
    {
        $settings = $this->bookingWindowSettings();

        return array_map(
            fn ($hour) => str_pad((string) $hour, 2, '0', STR_PAD_LEFT).':00',
            range($settings['opening_hour'], max($settings['opening_hour'], $settings['closing_hour'] - 1))
        );
    }

    protected function bookedSlots(): array
    {
        return $this->runDatabaseCheck(fn () => Reservation::query()
            ->whereIn('status', ['pending_review', 'confirmed', 'rescheduled', 'checked_in'])
            ->get(['branch_code', 'event_date', 'event_time', 'duration_hours'])
            ->flatMap(function (Reservation $reservation) {
                $hours = range(0, max(((int) ($reservation->duration_hours ?? 4)) - 1, 0));

                return collect($hours)->map(function (int $offset) use ($reservation) {
                    return [
                        'branch_code' => $reservation->branch_code,
                        'event_date' => $reservation->event_date->toDateString(),
                        'event_time' => $this->addHoursToTime(substr($reservation->event_time, 0, 5), $offset),
                    ];
                });
            })
            ->values()
            ->all(), []);
    }

    protected function databaseAvailable(): bool
    {
        if ($this->databaseAvailable !== null) {
            return $this->databaseAvailable;
        }

        try {
            DB::connection()->getPdo();

            return $this->databaseAvailable = true;
        } catch (Throwable) {
            return $this->databaseAvailable = false;
        }
    }

    protected function hasTableSafely(string $table): bool
    {
        return $this->runDatabaseCheck(fn () => Schema::hasTable($table), false);
    }

    protected function runDatabaseCheck(callable $callback, mixed $fallback)
    {
        if (! $this->databaseAvailable()) {
            return $fallback;
        }

        try {
            return $callback();
        } catch (Throwable) {
            return $fallback;
        }
    }

    protected function availabilityPayload(array $catalog, int $days): array
    {
        $days = max(28, min($days, 366));
        $bookedSlots = collect($this->bookedSlots())->groupBy(fn ($slot) => $slot['branch_code'].'|'.$slot['event_date'].'|'.$slot['event_time']);

        return [
            'generated_at' => now()->toDateTimeString(),
            'slotOptions' => $catalog['slotOptions'],
            'branches' => collect($catalog['branches'])->map(function ($branch) use ($catalog, $days, $bookedSlots) {
                $dates = collect(range(0, $days - 1))->map(function ($offset) use ($branch, $catalog, $bookedSlots) {
                    $date = now()->addDays($offset)->toDateString();
                    $slots = collect($catalog['slotOptions'])->map(function ($time) use ($branch, $date, $bookedSlots) {
                        $key = $branch['code'].'|'.$date.'|'.$time;
                        $booked = $bookedSlots->get($key, collect())->count();
                        $remaining = $booked > 0 ? 0 : 1;

                        return [
                            'time' => $time,
                            'label' => $this->formatTimeLabel($time),
                            'booked' => $booked,
                            'remaining' => $remaining,
                            'full' => $remaining === 0,
                        ];
                    })->values();

                    $availableSlots = $slots
                        ->filter(fn ($slot) => collect(range(1, 16))->contains(
                            fn ($duration) => $this->isStartSlotOpen($slot['time'], $duration, $slots)
                        ))
                        ->count();

                    return [
                        'date' => $date,
                        'status' => $availableSlots === 0 ? 'full' : ($availableSlots <= 2 ? 'limited' : 'available'),
                        'available_slots' => $availableSlots,
                        'slots' => $slots,
                    ];
                })->values();

                return [
                    'code' => $branch['code'],
                    'name' => $branch['name'],
                    'city' => $branch['city'],
                    'supports' => $branch['supports'],
                    'dates' => $dates,
                ];
            })->values(),
        ];
    }

    protected function availabilityDayPayload(array $catalog, string $branchCode, string $date): array
    {
        abort_unless(preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1, 404);

        $branch = collect($catalog['branches'])->firstWhere('code', $branchCode);
        abort_unless($branch, 404);

        try {
            $day = Carbon::createFromFormat('Y-m-d', $date, config('app.timezone'))->startOfDay();
        } catch (Throwable) {
            abort(404);
        }

        $reservations = Reservation::query()
            ->where('branch_code', $branchCode)
            ->whereDate('event_date', $day->toDateString())
            ->whereIn('status', ['pending_review', 'confirmed', 'rescheduled', 'checked_in'])
            ->orderBy('event_time')
            ->get();

        $roomChoices = collect($this->roomChoices())
            ->map(fn (array $room) => [
                'code' => $room['code'],
                'label' => $room['label'],
                'description' => $room['description'],
            ])
            ->values();

        $roomLabels = $roomChoices
            ->pluck('label')
            ->merge($reservations->pluck('room_choice'))
            ->filter()
            ->unique()
            ->values();

        if ($roomLabels->isEmpty()) {
            $roomLabels = collect(['Main event floor']);
        }

        $roomCards = $roomLabels->map(function (string $label) use ($roomChoices) {
            $match = $roomChoices->firstWhere('label', $label);

            return [
                'code' => $match['code'] ?? Str::slug($label),
                'label' => $label,
                'description' => $match['description'] ?? 'Room tracked from reservation history.',
            ];
        })->values();

        $maxConcurrent = max((int) ($branch['concurrent_limit'] ?? 1), 1);

        $timeSlots = collect($catalog['slotOptions'])->map(function (string $time) use ($reservations, $roomLabels, $maxConcurrent) {
            $activeReservations = $reservations
                ->filter(fn (Reservation $reservation) => $this->timeRangesOverlap(
                    substr($reservation->event_time, 0, 5),
                    (int) ($reservation->duration_hours ?? 4),
                    $time,
                    1
                ))
                ->values();

            $occupiedRooms = $roomLabels
                ->filter(fn (string $label) => $activeReservations->contains(
                    fn (Reservation $reservation) => ($reservation->room_choice ?: 'Main event floor') === $label
                ))
                ->values();

            $remainingCapacity = max($maxConcurrent - $activeReservations->count(), 0);
            $availableRooms = $remainingCapacity > 0
                ? $roomLabels->reject(fn (string $label) => $occupiedRooms->contains($label))->values()
                : collect();

            return [
                'time' => $time,
                'label' => $this->formatTimeLabel($time),
                'range_label' => $this->formatTimeRange($time, 1),
                'status' => $remainingCapacity === 0
                    ? 'full'
                    : ($availableRooms->count() <= 1 ? 'limited' : 'available'),
                'available_rooms' => $availableRooms->values()->all(),
                'occupied_rooms' => $occupiedRooms->values()->all(),
                'remaining_capacity' => $remainingCapacity,
                'active_events' => $activeReservations->map(fn (Reservation $reservation) => [
                    'booking_reference' => $reservation->booking_reference,
                    'customer_name' => $reservation->customer_name,
                    'room_choice' => $reservation->room_choice ?: 'Main event floor',
                    'status' => $reservation->status,
                    'event_time' => $this->formatTimeRange(
                        substr($reservation->event_time, 0, 5),
                        (int) ($reservation->duration_hours ?? 4)
                    ),
                ])->values()->all(),
            ];
        })->values();

        return [
            'branch' => [
                'code' => $branch['code'],
                'name' => $branch['name'],
                'city' => $branch['city'],
                'concurrent_limit' => $maxConcurrent,
            ],
            'date' => $day->toDateString(),
            'formatted_date' => $day->translatedFormat('F j, Y'),
            'weekday' => $day->translatedFormat('l'),
            'rooms' => $roomCards->map(function (array $room) use ($reservations) {
                $roomBookings = $reservations
                    ->filter(fn (Reservation $reservation) => ($reservation->room_choice ?: 'Main event floor') === $room['label'])
                    ->values();

                return [
                    'code' => $room['code'],
                    'label' => $room['label'],
                    'description' => $room['description'],
                    'bookings_count' => $roomBookings->count(),
                    'schedule' => $roomBookings
                        ->map(fn (Reservation $reservation) => $this->formatTimeRange(
                            substr($reservation->event_time, 0, 5),
                            (int) ($reservation->duration_hours ?? 4)
                        ))
                        ->values()
                        ->all(),
                ];
            })->values()->all(),
            'open_slots' => $timeSlots->where('status', '!=', 'full')->count(),
            'time_slots' => $timeSlots->all(),
            'bookings' => $reservations
                ->map(fn (Reservation $reservation) => $this->serializeReservation($reservation))
                ->values()
                ->all(),
        ];
    }

    protected function isSlotAvailable(string $branchCode, string $date, string $time, int $durationHours, ?int $ignoreReservationId = null): bool
    {
        return Reservation::query()
            ->where('branch_code', $branchCode)
            ->whereDate('event_date', $date)
            ->whereIn('status', ['pending_review', 'confirmed', 'rescheduled', 'checked_in'])
            ->when($ignoreReservationId, fn ($query) => $query->whereKeyNot($ignoreReservationId))
            ->get(['event_time', 'duration_hours'])
            ->filter(fn (Reservation $reservation) => $this->timeRangesOverlap(
                substr($reservation->event_time, 0, 5),
                (int) ($reservation->duration_hours ?? 4),
                $time,
                $durationHours
            ))
            ->isEmpty();
    }

    protected function calculatePrice(string $eventDate, float $basePrice, array $menuBundles, array $addOns): float
    {
        return $this->buildReceipt('unknown', $eventDate, ['price' => $basePrice, 'name' => 'Base Package'], $menuBundles, [], $addOns)['total_raw'];
    }

    protected function buildReceipt(
        string $eventType,
        string $eventDate,
        array $package,
        array $menuBundles,
        array $manualMenuItems,
        array $addOns,
        int $durationHours = 4,
        array $serviceAdjustments = []
    ): array
    {
        $catalog = $this->catalog();
        $date = Carbon::parse($eventDate);
        $multiplier = 1;
        $multiplierLabel = 'Standard rate';

        if (in_array($date->toDateString(), $catalog['pricing']['holidays'], true)) {
            $multiplier = $catalog['pricing']['holiday_multiplier'];
            $multiplierLabel = 'Holiday rate';
        } elseif ($date->isWeekend()) {
            $multiplier = $catalog['pricing']['weekend_multiplier'];
            $multiplierLabel = 'Weekend rate';
        }

        $durationHours = max(1, $durationHours);
        $includedHours = 4;
        $extensionHours = max($durationHours - $includedHours, 0);
        $extensionHourlyRate = (float) ($catalog['pricing']['extension_hourly_rate'] ?? 450);
        $extraMenuBundles = collect($catalog['menuBundles'])
            ->whereIn('code', $serviceAdjustments['extra_menu_bundles'] ?? [])
            ->values()
            ->all();
        $extraAddOns = collect($catalog['addOns'])
            ->whereIn('code', $serviceAdjustments['extra_add_ons'] ?? [])
            ->values()
            ->all();
        $extraManualMenuItems = collect($serviceAdjustments['extra_manual_menu_items'] ?? [])
            ->map(fn ($item) => $this->normalizeManualMenuSnapshot($item))
            ->filter()
            ->values()
            ->all();

        $lineItems = collect([
            [
                'label' => $package['name'].' (includes '.$includedHours.' hours)',
                'type' => 'package',
                'amount_raw' => (float) $package['price'],
                'amount' => number_format((float) $package['price'], 2),
            ],
        ])
            ->merge(collect($menuBundles)->map(fn ($item) => [
                'label' => $item['name'],
                'type' => 'bundle',
                'amount_raw' => (float) $item['price'],
                'amount' => number_format((float) $item['price'], 2),
            ]))
            ->merge(collect($manualMenuItems)->map(fn ($item) => [
                'label' => $item['quantity'].' x '.$item['item_name'].' ('.$item['option_label'].')',
                'type' => 'manual_menu',
                'amount_raw' => (float) $item['line_total'],
                'amount' => number_format((float) $item['line_total'], 2),
            ]))
            ->merge(collect($addOns)->map(fn ($item) => [
                'label' => $item['name'],
                'type' => 'add_on',
                'amount_raw' => (float) $item['price'],
                'amount' => number_format((float) $item['price'], 2),
            ]))
            ->when($extensionHours > 0, fn ($items) => $items->push([
                'label' => 'Extended event time ('.$extensionHours.' hour'.($extensionHours > 1 ? 's' : '').')',
                'type' => 'duration_extension',
                'amount_raw' => $extensionHours * $extensionHourlyRate,
                'amount' => number_format($extensionHours * $extensionHourlyRate, 2),
            ]))
            ->merge(collect($extraMenuBundles)->map(fn ($item) => [
                'label' => 'Extra food: '.$item['name'],
                'type' => 'service_bundle',
                'amount_raw' => (float) $item['price'],
                'amount' => number_format((float) $item['price'], 2),
            ]))
            ->merge(collect($extraAddOns)->map(fn ($item) => [
                'label' => 'Extra service: '.$item['name'],
                'type' => 'service_add_on',
                'amount_raw' => (float) $item['price'],
                'amount' => number_format((float) $item['price'], 2),
            ]))
            ->merge(collect($extraManualMenuItems)->map(fn ($item) => [
                'label' => 'Extra food: '.$item['quantity'].' x '.$item['item_name'].' ('.$item['option_label'].')',
                'type' => 'service_manual_menu',
                'amount_raw' => (float) $item['line_total'],
                'amount' => number_format((float) $item['line_total'], 2),
            ]))
            ->values();

        $bundleTotal = collect($menuBundles)->sum('price');
        $manualMenuTotal = collect($manualMenuItems)->sum('line_total');
        $addOnTotal = collect($addOns)->sum('price');
        $adjustmentBundleTotal = collect($extraMenuBundles)->sum('price');
        $adjustmentAddOnTotal = collect($extraAddOns)->sum('price');
        $adjustmentManualMenuTotal = collect($extraManualMenuItems)->sum('line_total');
        $durationExtensionTotal = $extensionHours * $extensionHourlyRate;
        $subtotal = (float) $package['price'] + $bundleTotal + $manualMenuTotal + $addOnTotal + $adjustmentBundleTotal + $adjustmentAddOnTotal + $adjustmentManualMenuTotal + $durationExtensionTotal;
        $total = round($subtotal * $multiplier, 2);

        return [
            'event_type' => $eventType,
            'duration_hours' => $durationHours,
            'included_hours' => $includedHours,
            'extension_hours' => $extensionHours,
            'extension_hourly_rate' => number_format($extensionHourlyRate, 2),
            'line_items' => $lineItems->map(fn ($item) => Arr::except($item, ['amount_raw']))->all(),
            'subtotal' => number_format($subtotal, 2),
            'subtotal_raw' => $subtotal,
            'pricing_rule' => $multiplierLabel,
            'multiplier' => $multiplier,
            'adjustment' => number_format($total - $subtotal, 2),
            'adjustment_raw' => $total - $subtotal,
            'total' => number_format($total, 2),
            'total_raw' => $total,
        ];
    }

    protected function serializeReservation(Reservation $reservation): array
    {
        $catalog = $this->catalog();
        $customer = $reservation->relationLoaded('user') ? $reservation->user : null;
        $customerAddress = collect([
            $customer?->address_line,
            $customer?->city,
            $customer?->province,
            $customer?->postal_code,
        ])->filter()->implode(', ');
        $serviceAdjustments = $reservation->service_adjustments ?? ['extra_menu_bundles' => [], 'extra_add_ons' => [], 'extra_manual_menu_items' => []];
        $package = collect($catalog['packages'][$reservation->reservation_type] ?? [])->firstWhere('code', $reservation->package_code)
            ?? ['name' => $reservation->package_name, 'price' => (float) $reservation->total_amount];
        $menuBundles = collect($catalog['menuBundles'])->whereIn('code', $reservation->menu_bundles ?? [])->values()->all();
        $manualMenuItems = collect($reservation->manual_menu_items ?? [])
            ->map(fn ($item) => $this->normalizeManualMenuSnapshot($item))
            ->filter()
            ->values()
            ->all();
        $addOns = collect($catalog['addOns'])->whereIn('code', $reservation->add_ons ?? [])->values()->all();
        $receipt = $this->buildReceipt(
            $reservation->reservation_type,
            $reservation->event_date?->toDateString() ?? now()->toDateString(),
            $package,
            $menuBundles,
            $manualMenuItems,
            $addOns,
            (int) ($reservation->duration_hours ?? 4),
            $serviceAdjustments
        );

        return [
            'id' => $reservation->id,
            'booking_reference' => $reservation->booking_reference,
            'customer_name' => $customer?->name ?: $reservation->name,
            'customer_email' => $customer?->email ?: $reservation->email,
            'customer_phone' => $customer?->phone ?: $reservation->phone,
            'customer_profile' => [
                'gender' => $customer?->gender,
                'birth_date' => $customer?->birth_date?->toDateString(),
                'birth_date_label' => $customer?->birth_date?->format('M d, Y'),
                'address_line' => $customer?->address_line,
                'city' => $customer?->city,
                'province' => $customer?->province,
                'postal_code' => $customer?->postal_code,
                'full_address' => $customerAddress,
            ],
            'event_type' => $reservation->reservation_type,
            'package_name' => $reservation->package_name,
            'room_choice' => $reservation->room_choice,
            'branch' => $reservation->branch,
            'branch_code' => $reservation->branch_code,
            'event_date' => $reservation->event_date?->toDateString(),
            'event_start_time' => substr($reservation->event_time, 0, 5),
            'event_start_label' => $this->formatTimeLabel(substr($reservation->event_time, 0, 5)),
            'event_end_time' => $this->endTime($reservation->event_time, (int) ($reservation->duration_hours ?? 4)),
            'event_end_label' => $this->formatTimeLabel($this->endTime($reservation->event_time, (int) ($reservation->duration_hours ?? 4))),
            'event_time' => $this->formatTimeRange(substr($reservation->event_time, 0, 5), (int) ($reservation->duration_hours ?? 4)),
            'duration_hours' => (int) ($reservation->duration_hours ?? 4),
            'guests' => $reservation->guests,
            'status' => $reservation->status,
            'service_status' => $reservation->service_status,
            'notes' => $reservation->notes,
            'menu_bundles' => $reservation->menu_bundles ?? [],
            'add_ons' => $reservation->add_ons ?? [],
            'manual_menu_items' => $manualMenuItems,
            'service_adjustments' => $serviceAdjustments,
            'total_amount' => (float) $reservation->total_amount,
            'receipt' => $receipt,
            'assigned_staff_id' => $reservation->assigned_staff_id,
            'assigned_staff_name' => $reservation->assignedStaff?->name,
            'check_in_code' => $reservation->check_in_code,
            'checked_in_at' => $reservation->checked_in_at?->toDateTimeString(),
            'checked_in_by' => $reservation->checked_in_by,
            'pass_url' => route('reservations.pass', $reservation),
            'payment_proof_url' => route('reservations.payment-proof', $reservation),
            'payment_proof_preview_url' => $reservation->payment_proof_path ? asset('storage/'.$reservation->payment_proof_path) : null,
        ];
    }

    protected function manualMenuOptionIndex(array $catalog): array
    {
        return collect($catalog['menuCategories'] ?? [])
            ->flatMap(function (array $category) {
                return collect($category['items'] ?? [])->flatMap(function (array $item) use ($category) {
                    return collect($item['options'] ?? [])->mapWithKeys(fn (array $option) => [
                        $option['code'] => [
                            'option_code' => $option['code'],
                            'item_code' => $item['code'],
                            'item_name' => $item['name'],
                            'option_label' => $option['label'],
                            'unit_price' => (float) $option['price'],
                            'prep_label' => $option['prep_label'] ?? $item['name'].' '.$option['label'],
                            'category_code' => $category['code'],
                            'category_name' => $category['name'],
                        ],
                    ]);
                });
            })
            ->all();
    }

    protected function resolveManualMenuSelections(array $catalog, array $requestedItems): array
    {
        $optionIndex = $this->manualMenuOptionIndex($catalog);

        return collect($requestedItems)
            ->groupBy('option_code')
            ->map(function ($items, $optionCode) use ($optionIndex) {
                if (! isset($optionIndex[$optionCode])) {
                    return null;
                }

                $quantity = (int) $items->sum(fn ($item) => (int) ($item['quantity'] ?? 0));

                if ($quantity < 1) {
                    return null;
                }

                $option = $optionIndex[$optionCode];

                return $this->normalizeManualMenuSnapshot(array_merge(
                    $option,
                    ['quantity' => min($quantity, 99)]
                ));
            })
            ->filter()
            ->values()
            ->all();
    }

    protected function normalizeManualMenuSnapshot(array $item): ?array
    {
        $quantity = max(1, min((int) ($item['quantity'] ?? 1), 99));
        $itemName = $item['item_name'] ?? $item['name'] ?? null;
        $optionLabel = $item['option_label'] ?? $item['label'] ?? null;
        $unitPrice = (float) ($item['unit_price'] ?? $item['price'] ?? 0);
        $itemCode = $item['item_code'] ?? null;
        $optionCode = $item['option_code'] ?? null;

        if (! $itemName || ! $optionLabel || ! $itemCode || ! $optionCode) {
            return null;
        }

        $lineTotal = round((float) ($item['line_total'] ?? ($unitPrice * $quantity)), 2);

        return [
            'option_code' => $optionCode,
            'item_code' => $itemCode,
            'item_name' => $itemName,
            'option_label' => $optionLabel,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'line_total' => $lineTotal,
            'prep_label' => $item['prep_label'] ?? ($quantity.' x '.$itemName.' '.$optionLabel),
            'category_code' => $item['category_code'] ?? null,
            'category_name' => $item['category_name'] ?? null,
        ];
    }

    protected function foodPackageSummary(array $menuBundles, array $manualMenuItems): string
    {
        $bundleNames = collect($menuBundles)->pluck('name');
        $manualItems = collect($manualMenuItems)
            ->map(fn ($item) => $item['quantity'].' x '.$item['item_name'].' ('.$item['option_label'].')');

        return $bundleNames
            ->merge($manualItems)
            ->filter()
            ->take(6)
            ->implode(', ') ?: 'Package inclusions only';
    }

    protected function beveragePackageSummary(array $menuBundles, array $manualMenuItems): string
    {
        $drinkKeywords = ['mcfloat', 'coke', 'sprite', 'royal', 'juice', 'tea', 'drink'];

        $bundleDrinks = collect($menuBundles)
            ->pluck('name')
            ->filter(fn ($name) => Str::contains(Str::lower($name), $drinkKeywords));

        $manualDrinks = collect($manualMenuItems)
            ->filter(function (array $item) use ($drinkKeywords) {
                $category = Str::lower((string) ($item['category_code'] ?? ''));
                $name = Str::lower($item['item_name']);

                return in_array($category, ['mcfloat', 'desserts-drinks'], true)
                    || Str::contains($name, $drinkKeywords);
            })
            ->map(fn ($item) => $item['quantity'].' x '.$item['item_name'].' ('.$item['option_label'].')');

        return $bundleDrinks
            ->merge($manualDrinks)
            ->filter()
            ->take(5)
            ->implode(', ') ?: 'Custom drink items can be added in the menu board';
    }

    protected function roomChoices(bool $includeInactive = false): array
    {
        $this->ensureDatabaseBackedDefaults();

        if ($this->hasTableSafely('room_options')) {
            $roomOptions = $this->runDatabaseCheck(
                fn () => RoomOption::query()
                    ->when(! $includeInactive, fn ($query) => $query->where('is_active', true))
                    ->orderBy('sort_order')
                    ->orderBy('label')
                    ->get(),
                collect()
            );

            if ($roomOptions->isNotEmpty()) {
                return $roomOptions->map(fn (RoomOption $roomOption) => [
                    'id' => $roomOption->id,
                    'code' => $roomOption->code,
                    'label' => $roomOption->label,
                    'description' => $roomOption->description,
                    'preferred_event_type' => $roomOption->preferred_event_type,
                    'is_active' => $roomOption->is_active,
                ])->values()->all();
            }
        }

        return $this->defaultRoomChoices();
    }

    protected function ensureDatabaseBackedDefaults(): void
    {
        if ($this->databaseDefaultsEnsured || ! $this->databaseAvailable()) {
            return;
        }

        $this->databaseDefaultsEnsured = true;

        $this->runDatabaseCheck(function () {
            $eventTypeModels = collect();
            $bookingConfig = config('booking');

            if ($this->hasTableSafely('event_types')) {
                $eventTypeModels = collect($bookingConfig['event_types'] ?? [])->mapWithKeys(function (array $eventType, string $code) {
                    $model = EventType::query()->firstOrCreate(
                        ['code' => $code],
                        [
                            'label' => $eventType['label'],
                            'description' => $eventType['description'],
                            'icon' => $eventType['icon'] ?? null,
                            'sort_order' => 0,
                            'is_active' => true,
                        ]
                    );

                    if ($model->sort_order === null) {
                        $model->update(['sort_order' => 0]);
                    }

                    return [$code => $model];
                });
            }

            if ($this->hasTableSafely('booking_packages') && $eventTypeModels->isNotEmpty()) {
                collect($bookingConfig['packages'] ?? [])->each(function (array $items, string $eventTypeCode) use ($eventTypeModels) {
                    collect($items)->each(function (array $package, int $index) use ($eventTypeModels, $eventTypeCode) {
                        BookingPackage::query()->firstOrCreate(
                            ['code' => $package['code']],
                            [
                                'event_type_id' => $eventTypeModels[$eventTypeCode]->id,
                                'name' => $package['name'],
                                'price' => $package['price'],
                                'guest_range' => $package['guest_range'] ?? null,
                                'features' => $package['features'] ?? [],
                                'sort_order' => $index,
                                'is_active' => true,
                            ]
                        );
                    });
                });
            }

            if ($this->hasTableSafely('menu_bundles')) {
                collect($bookingConfig['menu_bundles'] ?? [])->each(function (array $bundle, int $index) {
                    MenuBundle::query()->firstOrCreate(
                        ['code' => $bundle['code']],
                        [
                            'name' => $bundle['name'],
                            'price' => $bundle['price'],
                            'prep_label' => $bundle['prep_label'] ?? null,
                            'sort_order' => $index,
                            'is_active' => true,
                        ]
                    );
                });
            }

            if ($this->hasTableSafely('add_ons')) {
                collect($bookingConfig['add_ons'] ?? [])->each(function (array $addOn, int $index) {
                    AddOn::query()->firstOrCreate(
                        ['code' => $addOn['code']],
                        [
                            'name' => $addOn['name'],
                            'price' => $addOn['price'],
                            'sort_order' => $index,
                            'is_active' => true,
                        ]
                    );
                });
            }

            if ($this->hasTableSafely('pricing_settings') && ! PricingSetting::query()->where('is_active', true)->exists()) {
                $pricing = $bookingConfig['pricing'] ?? [];

                PricingSetting::query()->create([
                    'weekend_multiplier' => $pricing['weekend_multiplier'] ?? 1.15,
                    'holiday_multiplier' => $pricing['holiday_multiplier'] ?? 1.25,
                    'extension_hourly_rate' => $pricing['extension_hourly_rate'] ?? 450,
                    'holidays' => $pricing['holidays'] ?? [],
                    'is_active' => true,
                ]);
            }

            if ($this->hasTableSafely('booking_settings') && ! BookingSetting::query()->where('is_active', true)->exists()) {
                BookingSetting::query()->create([
                    'opening_hour' => 7,
                    'closing_hour' => 23,
                    'default_duration_hours' => 4,
                    'is_active' => true,
                ]);
            }

            if ($this->hasTableSafely('room_options')) {
                collect($this->defaultRoomChoices())->each(function (array $roomOption, int $index) {
                    RoomOption::query()->firstOrCreate(
                        ['code' => $roomOption['code']],
                        [
                            'label' => $roomOption['label'],
                            'description' => $roomOption['description'],
                            'preferred_event_type' => $roomOption['preferred_event_type'],
                            'sort_order' => $index,
                            'is_active' => true,
                        ]
                    );
                });
            }

            if ($this->hasTableSafely('branches')) {
                collect($bookingConfig['branches'] ?? [])->each(function (array $branch) use ($eventTypeModels) {
                    $branchModel = Branch::query()->firstOrCreate(
                        ['code' => $branch['code']],
                        [
                            'name' => $branch['name'],
                            'city' => $branch['city'],
                            'supports' => $branch['supports'] ?? [],
                            'concurrent_limit' => $branch['concurrent_limit'] ?? 1,
                            'max_guests' => $branch['max_guests'] ?? 40,
                            'map_url' => $branch['map_url'] ?? null,
                            'inventory' => $branch['inventory'] ?? [],
                            'hosts' => $branch['hosts'] ?? [],
                            'is_active' => true,
                        ]
                    );

                    if ($this->hasTableSafely('branch_event_type') && $eventTypeModels->isNotEmpty() && ! $branchModel->supportedEventTypes()->exists()) {
                        $branchModel->supportedEventTypes()->sync(
                            $eventTypeModels
                                ->filter(fn ($eventTypeModel, $code) => $branch['supports'][$code] ?? false)
                                ->pluck('id')
                                ->all()
                        );
                    }

                    if ($this->hasTableSafely('branch_inventory_items') && ! $branchModel->inventoryItems()->exists()) {
                        collect($branch['inventory'] ?? [])->each(function (array $item, int $index) use ($branchModel) {
                            BranchInventoryItem::query()->firstOrCreate(
                                [
                                    'branch_id' => $branchModel->id,
                                    'item' => $item['item'],
                                ],
                                [
                                    'stock' => $item['stock'] ?? 0,
                                    'threshold' => $item['threshold'] ?? 0,
                                    'sort_order' => $index,
                                ]
                            );
                        });
                    }

                    if ($this->hasTableSafely('branch_hosts') && ! $branchModel->hostsList()->exists()) {
                        collect($branch['hosts'] ?? [])->each(function (string $host, int $index) use ($branchModel) {
                            BranchHost::query()->firstOrCreate(
                                [
                                    'branch_id' => $branchModel->id,
                                    'name' => $host,
                                ],
                                [
                                    'sort_order' => $index,
                                ]
                            );
                        });
                    }
                });
            }

            if (
                $this->hasTableSafely('menu_categories')
                && $this->hasTableSafely('menu_items')
                && $this->hasTableSafely('menu_item_options')
            ) {
                $menuCatalogSynchronizer = app(MenuCatalogSynchronizer::class);

                if (! $menuCatalogSynchronizer->isSeeded()) {
                    $menuCatalogSynchronizer->sync();
                }
            }

            return true;
        }, false);
    }

    protected function defaultRoomChoices(): array
    {
        return [
            [
                'code' => 'birthday-party-room',
                'label' => 'Birthday Party Room',
                'description' => 'A decorated party room for birthday celebrations and family events.',
                'preferred_event_type' => 'birthday',
                'is_active' => true,
            ],
            [
                'code' => 'function-room',
                'label' => 'Function Room',
                'description' => 'A flexible function room for meetings, gatherings, and reserved events.',
                'preferred_event_type' => 'business',
                'is_active' => true,
            ],
            [
                'code' => 'whole-mcdonalds-room',
                'label' => 'Whole McDonald\'s Room',
                'description' => 'A full-space rental setup for bigger private events and store takeovers.',
                'preferred_event_type' => 'table',
                'is_active' => true,
            ],
        ];
    }

    protected function defaultRoomChoice(string $eventType): string
    {
        $roomOptions = collect($this->roomChoices());

        return $roomOptions->firstWhere('preferred_event_type', $eventType)['code']
            ?? $roomOptions->first()['code']
            ?? 'birthday-party-room';
    }

    protected function bookingWindowLabel(): string
    {
        $settings = $this->bookingWindowSettings();

        return $this->formatTimeLabel(str_pad((string) $settings['opening_hour'], 2, '0', STR_PAD_LEFT).':00')
            .' to '
            .$this->formatTimeLabel(str_pad((string) $settings['closing_hour'], 2, '0', STR_PAD_LEFT).':00');
    }

    protected function adminStats($bookings): array
    {
        $confirmed = $bookings->whereIn('status', ['confirmed', 'checked_in', 'completed']);

        return [
            ['label' => 'Revenue pipeline', 'value' => $this->currencySymbol().number_format($bookings->sum('total_amount'), 2)],
            ['label' => 'Confirmed events', 'value' => $confirmed->count()],
            ['label' => 'Peak booking hour', 'value' => $this->formatTimeLabel($bookings->groupBy(fn ($booking) => substr($booking->event_time, 0, 5))->map->count()->sortDesc()->keys()->first() ?: '14:00')],
            ['label' => 'Weekend uplift', 'value' => '+15%'],
        ];
    }

    protected function currencySymbol(): string
    {
        return "\u{20B1}";
    }

    protected function adminPageData(): array
    {
        $catalog = $this->catalog();
        $bookings = Reservation::query()
            ->with(['user', 'assignedStaff'])
            ->orderBy('event_date')
            ->orderBy('event_time')
            ->get();

        return [
            'stats' => $this->adminStats($bookings),
            'calendar' => $bookings->map(fn (Reservation $reservation) => $this->serializeReservation($reservation))->values(),
            'groupedBookings' => $this->groupedBookings($catalog, $bookings->where('status', 'pending_review')->values()),
            'confirmedEvents' => $bookings
                ->whereIn('status', ['confirmed', 'rescheduled', 'checked_in'])
                ->values()
                ->map(fn (Reservation $reservation) => $this->serializeReservation($reservation)),
            'notifications' => $this->upcomingEventNotifications($bookings),
            'history' => $this->eventHistory($bookings),
            'cancelledEvents' => $this->cancelledEvents($bookings),
            'inventory' => $this->inventorySnapshot($catalog, $bookings),
            'staffAssignments' => $this->staffAssignments($catalog, $bookings),
            'availability' => $this->availabilityPayload($catalog, 366),
            'pricing' => $catalog['pricing'],
            'report' => $this->analyticsReport($bookings),
            'menuBundles' => $catalog['menuBundles'],
            'addOns' => $catalog['addOns'],
            'durationOptions' => range(1, 16),
            'users' => User::query()
                ->orderByRaw("CASE role WHEN 'admin' THEN 1 WHEN 'manager' THEN 2 WHEN 'staff' THEN 3 ELSE 4 END")
                ->orderBy('name')
                ->get(['id', 'name', 'email', 'role'])
                ->map(fn (User $user) => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                ]),
            'staffUsers' => User::query()
                ->whereIn('role', ['staff', 'manager'])
                ->orderBy('name')
                ->get(['id', 'name', 'email', 'role'])
                ->map(fn (User $user) => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'role' => $user->role,
                ]),
            'branches' => array_values($catalog['branches']),
        ];
    }

    protected function pendingReservationAlerts(int $limit = 4): array
    {
        $pending = Reservation::query()
            ->where('status', 'pending_review')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();

        return [
            'count' => Reservation::query()->where('status', 'pending_review')->count(),
            'items' => $pending->map(fn (Reservation $reservation) => [
                'id' => $reservation->id,
                'booking_reference' => $reservation->booking_reference,
                'customer_name' => $reservation->name,
                'branch' => $reservation->branch,
                'event_date' => $reservation->event_date?->format('M d, Y'),
                'event_time' => $this->formatTimeRange(substr($reservation->event_time, 0, 5), (int) ($reservation->duration_hours ?? 4)),
                'message' => 'New reservation waiting for admin review.',
            ])->values()->all(),
        ];
    }

    protected function inventorySnapshot(array $catalog, $bookings): array
    {
        if ($this->hasTableSafely('branches') && $this->hasTableSafely('branch_inventory_items')) {
            $branches = $this->runDatabaseCheck(fn () => Branch::query()
                ->where('is_active', true)
                ->with(['inventoryItems' => fn ($query) => $query->orderBy('sort_order')->orderBy('item')])
                ->orderBy('name')
                ->get(), collect());

            if ($branches->isNotEmpty()) {
                return $branches->map(function (Branch $branch) use ($bookings) {
                    $upcomingCount = $bookings
                        ->where('branch_code', $branch->code)
                        ->whereIn('status', ['pending_review', 'confirmed', 'rescheduled'])
                        ->count();

                    return [
                        'branch' => $branch->name,
                        'branch_id' => $branch->id,
                        'branch_code' => $branch->code,
                        'alerts' => $branch->inventoryItems->map(function (BranchInventoryItem $item) use ($upcomingCount) {
                            $projected = max($item->stock - ($upcomingCount * 3), 0);

                            return [
                                'id' => $item->id,
                                'item' => $item->item,
                                'stock' => $item->stock,
                                'projected' => $projected,
                                'threshold' => $item->threshold,
                                'sort_order' => $item->sort_order,
                                'low' => $projected <= $item->threshold,
                            ];
                        })->values(),
                    ];
                })->values()->all();
            }
        }

        return collect($catalog['branches'])->map(function ($branch) use ($bookings) {
            $upcomingCount = $bookings
                ->where('branch_code', $branch['code'])
                ->whereIn('status', ['pending_review', 'confirmed', 'rescheduled'])
                ->count();

            return [
                'branch' => $branch['name'],
                'branch_id' => null,
                'branch_code' => $branch['code'],
                'alerts' => collect($branch['inventory'])->map(function ($item) use ($upcomingCount) {
                    $projected = max($item['stock'] - ($upcomingCount * 3), 0);

                    return [
                        'id' => null,
                        'item' => $item['item'],
                        'stock' => $item['stock'],
                        'projected' => $projected,
                        'threshold' => $item['threshold'],
                        'sort_order' => 0,
                        'low' => $projected <= $item['threshold'],
                    ];
                })->values(),
            ];
        })->values()->all();
    }

    protected function staffAssignments(array $catalog, $bookings): array
    {
        return $bookings
            ->whereIn('status', ['pending_review', 'confirmed', 'rescheduled'])
            ->values()
            ->map(function (Reservation $reservation, int $index) use ($catalog) {
                $hosts = collect($catalog['branches'][$reservation->branch_code]['hosts'] ?? [])
                    ->filter(fn ($host) => filled($host))
                    ->values()
                    ->all();
                $assigned = $reservation->assignedStaff?->name;
                $fallbackHost = $hosts !== []
                    ? $hosts[$index % count($hosts)]
                    : 'Floor Team';

                return [
                    'booking_reference' => $reservation->booking_reference,
                    'branch' => $reservation->branch,
                    'slot' => $reservation->event_date?->format('M d').', '.$this->formatTimeRange(substr($reservation->event_time, 0, 5), (int) ($reservation->duration_hours ?? 4)),
                    'host' => $assigned ?: $fallbackHost,
                    'event_type' => $reservation->reservation_type,
                ];
            })
            ->all();
    }

    protected function groupedBookings(array $catalog, $bookings): array
    {
        return collect($catalog['branches'])->map(function ($branch) use ($catalog, $bookings) {
            $branchBookings = $bookings->where('branch_code', $branch['code'])->values();

            return [
                'branch' => $branch['name'],
                'branch_code' => $branch['code'],
                'city' => $branch['city'],
                'types' => collect($catalog['eventTypes'])->map(function ($eventType, $key) use ($branchBookings) {
                    return [
                        'type' => $key,
                        'label' => $eventType['label'],
                        'bookings' => $branchBookings
                            ->where('reservation_type', $key)
                            ->map(fn (Reservation $reservation) => $this->serializeReservation($reservation))
                            ->values(),
                    ];
                })->values(),
            ];
        })->values()->all();
    }

    protected function upcomingEventNotifications($bookings, int $limit = 8): array
    {
        return $this->reservationTimelineBuilder()->notifications($bookings, $limit);
    }

    protected function eventHistory($bookings, int $limit = 10): array
    {
        return $this->reservationTimelineBuilder()->history($bookings, $limit);
    }

    protected function cancelledEvents($bookings, int $limit = 10): array
    {
        return $this->reservationTimelineBuilder()->cancelled($bookings, $limit);
    }

    protected function analyticsReport($bookings): array
    {
        return [
            'top_event_types' => $bookings->groupBy('reservation_type')->map->count()->sortDesc()->map(fn ($count, $type) => [
                'type' => ucfirst($type),
                'count' => $count,
            ])->values()->all(),
            'branch_mix' => $bookings->groupBy('branch')->map->count()->sortDesc()->map(fn ($count, $branch) => [
                'branch' => $branch,
                'count' => $count,
            ])->values()->all(),
        ];
    }

    protected function prepList(array $catalog, $bookings): array
    {
        return $bookings->map(function (Reservation $reservation) use ($catalog) {
            $serviceAdjustments = $reservation->service_adjustments ?? ['extra_menu_bundles' => [], 'extra_add_ons' => [], 'extra_manual_menu_items' => []];
            $bundleLabels = collect($catalog['menuBundles'])
                ->whereIn('code', $reservation->menu_bundles ?? [])
                ->pluck('prep_label')
                ->values()
                ->all();
            $extraBundleLabels = collect($catalog['menuBundles'])
                ->whereIn('code', $serviceAdjustments['extra_menu_bundles'] ?? [])
                ->pluck('prep_label')
                ->map(fn ($label) => 'Extra order: '.$label)
                ->values()
                ->all();
            $materialItems = collect($catalog['addOns'])
                ->whereIn('code', array_merge($reservation->add_ons ?? [], $serviceAdjustments['extra_add_ons'] ?? []))
                ->pluck('name')
                ->map(fn ($label) => 'Materials/service: '.$label)
                ->values()
                ->all();
            $manualMenuLabels = collect($reservation->manual_menu_items ?? [])
                ->map(fn ($item) => $this->normalizeManualMenuSnapshot($item))
                ->filter()
                ->map(fn ($item) => 'Manual order: '.$item['quantity'].' x '.$item['item_name'].' ('.$item['option_label'].')')
                ->values()
                ->all();
            $extraManualMenuLabels = collect($serviceAdjustments['extra_manual_menu_items'] ?? [])
                ->map(fn ($item) => $this->normalizeManualMenuSnapshot($item))
                ->filter()
                ->map(fn ($item) => 'Extra order: '.$item['quantity'].' x '.$item['item_name'].' ('.$item['option_label'].')')
                ->values()
                ->all();
            $prepAt = Carbon::parse(($reservation->event_date?->toDateString() ?? now()->toDateString()).' '.substr($reservation->event_time, 0, 5))->subHour();

            return [
                'booking_reference' => $reservation->booking_reference,
                'time' => $this->formatTimeRange(substr($reservation->event_time, 0, 5), (int) ($reservation->duration_hours ?? 4)),
                'branch' => $reservation->branch,
                'package_name' => $reservation->package_name,
                'items' => array_values(array_filter(array_merge($bundleLabels ?: ['Welcome tray and seating prep'], $manualMenuLabels, $extraBundleLabels, $extraManualMenuLabels, $materialItems))),
                'guest_name' => $reservation->name,
                'prep_deadline' => $prepAt->format('M d, Y h:i A'),
                'reminder' => 'Prepare all meals, products, and event materials at least 1 hour before the event starts.',
            ];
        })->values()->all();
    }

    protected function authorizeRoles(Request $request, array $roles): void
    {
        abort_unless($request->user() && in_array($request->user()->role, $roles, true), 403);
    }

    protected function authorizeReservationAccess(Request $request, Reservation $reservation, bool $allowOperations = false): void
    {
        if ($request->user()?->id === $reservation->user_id) {
            return;
        }

        if ($allowOperations && in_array($request->user()?->role, ['admin', 'manager', 'staff'], true)) {
            return;
        }

        abort(403);
    }

    protected function buildPassSvg(Reservation $reservation): string
    {
        $size = 21;
        $cell = 8;
        $hash = hash('sha256', $reservation->booking_reference.$reservation->check_in_code);
        $bits = str_split(base_convert(substr($hash, 0, 16), 16, 2));
        $cursor = 0;
        $squares = [];

        for ($row = 0; $row < $size; $row++) {
            for ($col = 0; $col < $size; $col++) {
                $isFinder = ($row < 5 && $col < 5) || ($row < 5 && $col > 15) || ($row > 15 && $col < 5);
                $filled = $isFinder || (($bits[$cursor % max(count($bits), 1)] ?? '0') === '1');

                if ($filled) {
                    $x = 40 + ($col * $cell);
                    $y = 40 + ($row * $cell);
                    $squares[] = '<rect x="'.$x.'" y="'.$y.'" width="'.$cell.'" height="'.$cell.'" rx="1" fill="#1f1f1f" />';
                }

                $cursor++;
            }
        }

        $squareMarkup = implode('', $squares);
        $eventDate = $reservation->event_date?->format('M d, Y');
        $eventTime = $this->formatTimeRange(substr($reservation->event_time, 0, 5), (int) ($reservation->duration_hours ?? 4));

        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="420" height="280" viewBox="0 0 420 280" fill="none">
  <rect width="420" height="280" rx="28" fill="#FFC72C"/>
  <rect x="18" y="18" width="384" height="244" rx="22" fill="#FFF7E6"/>
  <rect x="18" y="18" width="384" height="54" rx="22" fill="#DA291C"/>
  <text x="36" y="52" fill="#FFF7E6" font-size="24" font-family="Arial, sans-serif" font-weight="700">McDonald's Event Check-In Pass</text>
  <g>
    <rect x="34" y="34" width="184" height="184" rx="18" fill="#FFFFFF"/>
    {$this->finderPatterns()}
    {$this->finderPatterns(168, 40)}
    {$this->finderPatterns(40, 168)}
    {$squareMarkup}
  </g>
  <text x="244" y="106" fill="#1F1F1F" font-size="18" font-family="Arial, sans-serif" font-weight="700">{$reservation->booking_reference}</text>
  <text x="244" y="136" fill="#4A4A4A" font-size="14" font-family="Arial, sans-serif">Check-in code: {$reservation->check_in_code}</text>
  <text x="244" y="164" fill="#4A4A4A" font-size="14" font-family="Arial, sans-serif">{$reservation->branch}</text>
  <text x="244" y="192" fill="#4A4A4A" font-size="14" font-family="Arial, sans-serif">{$eventDate} at {$eventTime}</text>
  <text x="244" y="220" fill="#4A4A4A" font-size="14" font-family="Arial, sans-serif">Present this pass at the counter or staff scanner.</text>
</svg>
SVG;
    }

    protected function finderPatterns(int $x = 40, int $y = 40): string
    {
        return '<rect x="'.$x.'" y="'.$y.'" width="40" height="40" rx="6" fill="#1F1F1F" /><rect x="'.($x + 8).'" y="'.($y + 8).'" width="24" height="24" rx="4" fill="#FFF7E6" /><rect x="'.($x + 14).'" y="'.($y + 14).'" width="12" height="12" rx="2" fill="#1F1F1F" />';
    }

    protected function formatTimeLabel(string $time): string
    {
        return Carbon::createFromFormat('H:i', substr($time, 0, 5), config('app.timezone'))->format('g:i A');
    }

    protected function addHoursToTime(string $time, int $hours): string
    {
        return Carbon::createFromFormat('H:i', substr($time, 0, 5), config('app.timezone'))
            ->addHours($hours)
            ->format('H:i');
    }

    protected function endTime(string $time, int $durationHours): string
    {
        return $this->addHoursToTime($time, $durationHours);
    }

    protected function formatTimeRange(string $time, int $durationHours): string
    {
        return $this->formatTimeLabel($time).' to '.$this->formatTimeLabel($this->endTime($time, $durationHours));
    }

    protected function isDurationWindowValid(string $time, int $durationHours): bool
    {
        $settings = $this->bookingWindowSettings();
        $startMinutes = ((int) substr($time, 0, 2) * 60) + (int) substr($time, 3, 2);
        $openingMinutes = $settings['opening_hour'] * 60;
        $closingMinutes = $settings['closing_hour'] * 60;
        $endMinutes = $startMinutes + ($durationHours * 60);

        return $startMinutes >= $openingMinutes && $endMinutes <= $closingMinutes;
    }

    protected function timeRangesOverlap(string $existingStart, int $existingDuration, string $requestedStart, int $requestedDuration): bool
    {
        $existingStartMinutes = ((int) substr($existingStart, 0, 2) * 60) + (int) substr($existingStart, 3, 2);
        $existingEndMinutes = $existingStartMinutes + ($existingDuration * 60);
        $requestedStartMinutes = ((int) substr($requestedStart, 0, 2) * 60) + (int) substr($requestedStart, 3, 2);
        $requestedEndMinutes = $requestedStartMinutes + ($requestedDuration * 60);

        return $requestedStartMinutes < $existingEndMinutes && $existingStartMinutes < $requestedEndMinutes;
    }

    protected function durationBetweenTimes(string $startTime, string $endTime): ?int
    {
        $startMinutes = ((int) substr($startTime, 0, 2) * 60) + (int) substr($startTime, 3, 2);
        $endMinutes = ((int) substr($endTime, 0, 2) * 60) + (int) substr($endTime, 3, 2);
        $difference = $endMinutes - $startMinutes;

        if ($difference <= 0 || $difference % 60 !== 0) {
            return null;
        }

        return (int) ($difference / 60);
    }

    protected function isStartSlotOpen(string $time, int $durationHours, $slots): bool
    {
        if (! $this->isDurationWindowValid($time, $durationHours)) {
            return false;
        }

        $slotLookup = collect($slots)->keyBy('time');

        foreach (range(0, $durationHours - 1) as $offset) {
            $slot = $slotLookup->get($this->addHoursToTime($time, $offset));

            if (! $slot || $slot['full']) {
                return false;
            }
        }

        return true;
    }
}
