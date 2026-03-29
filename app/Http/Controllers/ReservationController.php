<?php

namespace App\Http\Controllers;

use App\Models\AddOn;
use App\Models\BookingPackage;
use App\Models\Branch;
use App\Models\EventType;
use App\Models\MenuCategory;
use App\Models\MenuBundle;
use App\Models\MenuItem;
use App\Models\MenuItemOption;
use App\Models\PricingSetting;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Throwable;

class ReservationController extends Controller
{
    protected ?bool $databaseAvailable = null;

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
            'availability' => $this->availabilityPayload($catalog, 366),
            'defaults' => [
                'event_date' => now()->addDays(3)->toDateString(),
                'event_time' => '08:00',
                'duration_hours' => 4,
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $catalog = $this->catalog();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:50'],
            'event_type' => ['required', Rule::in(array_keys($catalog['eventTypes']))],
            'branch_code' => ['required', Rule::in(array_keys($catalog['branches']))],
            'event_date' => ['required', 'date', 'after_or_equal:today'],
            'event_time' => ['required', Rule::in($catalog['slotOptions'])],
            'duration_hours' => ['required', 'integer', 'min:4', 'max:8'],
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
                'event_time' => 'Reservations must fit within the morning booking window of 7:00 AM to 12:00 PM.',
            ])->with('error', 'Reservations are only available from 7:00 AM to 12:00 PM.')->withInput();
        }

        if (! $this->isSlotAvailable($validated['branch_code'], $validated['event_date'], $validated['event_time'], (int) $validated['duration_hours'])) {
            return back()->withErrors([
                'event_time' => 'The chosen date and time are unavailable or already reserved.',
            ])->with('error', 'The chosen date and time are unavailable or already reserved. Please choose another morning slot.')->withInput();
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
            'user_id' => $request->user()->id,
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'],
            'booking_reference' => $bookingReference,
            'reservation_type' => $validated['event_type'],
            'package_name' => $package['name'],
            'package_code' => $package['code'],
            'room_choice' => $validated['event_type'] === 'business' ? 'McCafe meeting zone' : 'Celebration area',
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

        $data = $this->adminPageData();

        $catalog = $this->catalog();

        return Inertia::render('Admin/Dashboard', [
            'stats' => $data['stats'],
            'notifications' => $data['notifications'],
            'history' => $data['history'],
            'branchSummaries' => collect($data['branches'])->map(fn ($branch) => [
                'code' => $branch['code'],
                'name' => $branch['name'],
                'city' => $branch['city'],
            ])->values(),
        ]);
    }

    public function adminBookings(Request $request): InertiaResponse
    {
        $this->authorizeRoles($request, ['admin', 'manager']);

        $data = $this->adminPageData();

        return Inertia::render('Admin/Bookings', [
            'stats' => $data['stats'],
            'groupedBookings' => $data['groupedBookings'],
            'staffUsers' => $data['staffUsers'],
            'menuBundles' => $data['menuBundles'],
            'addOns' => $data['addOns'],
            'durationOptions' => $data['durationOptions'],
        ]);
    }

    public function adminConfirmedEvents(Request $request): InertiaResponse
    {
        $this->authorizeRoles($request, ['admin', 'manager']);

        $data = $this->adminPageData();

        return Inertia::render('Admin/ConfirmedEvents', [
            'stats' => $data['stats'],
            'confirmedEvents' => $data['confirmedEvents'],
            'staffUsers' => $data['staffUsers'],
            'menuBundles' => $data['menuBundles'],
            'addOns' => $data['addOns'],
            'durationOptions' => $data['durationOptions'],
        ]);
    }

    public function adminAvailability(Request $request): InertiaResponse
    {
        $this->authorizeRoles($request, ['admin', 'manager']);

        $data = $this->adminPageData();

        return Inertia::render('Admin/Availability', [
            'availability' => $data['availability'],
        ]);
    }

    public function adminBranches(Request $request): InertiaResponse
    {
        $this->authorizeRoles($request, ['admin', 'manager']);

        $data = $this->adminPageData();

        return Inertia::render('Admin/Branches', [
            'branches' => $data['branches'],
        ]);
    }

    public function adminAccounts(Request $request): InertiaResponse
    {
        $this->authorizeRoles($request, ['admin', 'manager']);

        $data = $this->adminPageData();

        return Inertia::render('Admin/Accounts', [
            'users' => $data['users'],
        ]);
    }

    public function adminCatalog(Request $request): InertiaResponse
    {
        $this->authorizeRoles($request, ['admin', 'manager']);

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
        ]);
    }

    public function adminReports(Request $request): InertiaResponse
    {
        $this->authorizeRoles($request, ['admin', 'manager']);

        $data = $this->adminPageData();

        return Inertia::render('Admin/Reports', [
            'pricing' => $data['pricing'],
            'report' => $data['report'],
            'inventory' => $data['inventory'],
            'staffAssignments' => $data['staffAssignments'],
        ]);
    }

    public function adminTimeline(Request $request): InertiaResponse
    {
        $this->authorizeRoles($request, ['admin', 'manager']);

        $data = $this->adminPageData();

        return Inertia::render('Admin/Timeline', [
            'notifications' => $data['notifications'],
            'history' => $data['history'],
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
            'durationOptions' => range(4, 8),
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
                'event_time' => 'Reservations must fit within the morning booking window of 7:00 AM to 12:00 PM.',
            ])->with('error', 'Reservations are only available from 7:00 AM to 12:00 PM.');
        }

        if (! $this->isSlotAvailable($reservation->branch_code, $validated['event_date'], $validated['event_time'], $durationHours, $reservation->id)) {
            return back()->withErrors([
                'event_time' => 'The chosen date and time are unavailable or already reserved.',
            ])->with('error', 'The chosen date and time are unavailable or already reserved. Please choose another morning slot.');
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
            'duration_hours' => ['required', 'integer', 'min:4', 'max:8'],
            'extra_menu_bundles' => ['array'],
            'extra_menu_bundles.*' => ['string'],
            'extra_add_ons' => ['array'],
            'extra_add_ons.*' => ['string'],
        ]);

        if (! in_array($reservation->status, ['confirmed', 'rescheduled', 'checked_in'], true)) {
            return back()->with('error', 'Only confirmed or active events can be edited on the service floor.');
        }

        if (! $this->isDurationWindowValid($reservation->event_time, (int) $validated['duration_hours'])) {
            return back()->with('error', 'The updated duration must stay within the 7:00 AM to 12:00 PM booking window.');
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
                return $databaseCatalog;
            }
        }

        return [
            'eventTypes' => config('booking.event_types'),
            'branches' => $this->branchCatalog(),
            'packages' => config('booking.packages'),
            'menuBundles' => config('booking.menu_bundles'),
            'addOns' => config('booking.add_ons'),
            'menuCategories' => [],
            'slotOptions' => $this->slotOptions(),
            'pricing' => config('booking.pricing'),
        ];
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
        return $this->hasTableSafely('event_types')
            && $this->hasTableSafely('booking_packages')
            && $this->hasTableSafely('menu_bundles')
            && $this->hasTableSafely('add_ons')
            && $this->runDatabaseCheck(fn () => EventType::query()->exists(), false);
    }

    protected function slotOptions(): array
    {
        return array_map(
            fn ($hour) => str_pad((string) $hour, 2, '0', STR_PAD_LEFT).':00',
            range(7, 12)
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
                        ->filter(fn ($slot) => $this->isStartSlotOpen($slot['time'], 4, $slots))
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

        $durationHours = max(4, min(8, $durationHours));
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
            'customer_name' => $reservation->name,
            'customer_email' => $reservation->email,
            'customer_phone' => $reservation->phone,
            'event_type' => $reservation->reservation_type,
            'package_name' => $reservation->package_name,
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
        $bookings = Reservation::query()->orderBy('event_date')->orderBy('event_time')->get();

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
            'inventory' => $this->inventorySnapshot($catalog, $bookings),
            'staffAssignments' => $this->staffAssignments($catalog, $bookings),
            'availability' => $this->availabilityPayload($catalog, 366),
            'pricing' => $catalog['pricing'],
            'report' => $this->analyticsReport($bookings),
            'menuBundles' => $catalog['menuBundles'],
            'addOns' => $catalog['addOns'],
            'durationOptions' => range(4, 8),
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
        return collect($catalog['branches'])->map(function ($branch) use ($bookings) {
            $upcomingCount = $bookings
                ->where('branch_code', $branch['code'])
                ->whereIn('status', ['pending_review', 'confirmed', 'rescheduled'])
                ->count();

            return [
                'branch' => $branch['name'],
                'alerts' => collect($branch['inventory'])->map(function ($item) use ($upcomingCount) {
                    $projected = max($item['stock'] - ($upcomingCount * 3), 0);

                    return [
                        'item' => $item['item'],
                        'stock' => $item['stock'],
                        'projected' => $projected,
                        'threshold' => $item['threshold'],
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
        $today = now()->startOfDay();

        return $bookings
            ->filter(function (Reservation $reservation) use ($today) {
                if (! $reservation->event_date) {
                    return false;
                }

                return $reservation->event_date->copy()->startOfDay()->greaterThanOrEqualTo($today)
                    && in_array($reservation->status, ['pending_review', 'confirmed', 'rescheduled', 'checked_in'], true);
            })
            ->sortBy(fn (Reservation $reservation) => sprintf(
                '%s %s',
                $reservation->event_date?->toDateString() ?? '9999-12-31',
                $reservation->event_time ?? '23:59:59'
            ))
            ->take($limit)
            ->values()
            ->map(fn (Reservation $reservation) => [
                'id' => $reservation->id,
                'booking_reference' => $reservation->booking_reference,
                'package_name' => $reservation->package_name,
                'branch' => $reservation->branch,
                'event_type' => ucfirst($reservation->reservation_type),
                'event_date' => $reservation->event_date?->format('M d, Y'),
                'event_time' => $this->formatTimeRange(substr($reservation->event_time, 0, 5), (int) ($reservation->duration_hours ?? 4)),
                'status' => $reservation->status,
                'assigned_staff_name' => $reservation->assignedStaff?->name,
                'message' => $this->notificationMessage($reservation),
            ])
            ->all();
    }

    protected function eventHistory($bookings, int $limit = 10): array
    {
        $today = now()->startOfDay();

        return $bookings
            ->filter(function (Reservation $reservation) use ($today) {
                if (! $reservation->event_date) {
                    return false;
                }

                return $reservation->event_date->copy()->startOfDay()->lessThan($today)
                    || in_array($reservation->status, ['completed', 'cancelled'], true);
            })
            ->sortByDesc(fn (Reservation $reservation) => sprintf(
                '%s %s',
                $reservation->event_date?->toDateString() ?? '0000-00-00',
                $reservation->event_time ?? '00:00:00'
            ))
            ->take($limit)
            ->values()
            ->map(fn (Reservation $reservation) => [
                'id' => $reservation->id,
                'booking_reference' => $reservation->booking_reference,
                'package_name' => $reservation->package_name,
                'branch' => $reservation->branch,
                'event_type' => ucfirst($reservation->reservation_type),
                'event_date' => $reservation->event_date?->format('M d, Y'),
                'event_time' => $this->formatTimeRange(substr($reservation->event_time, 0, 5), (int) ($reservation->duration_hours ?? 4)),
                'status' => $reservation->status,
                'service_status' => $reservation->service_status,
                'assigned_staff_name' => $reservation->assignedStaff?->name,
                'checked_in_by' => $reservation->checked_in_by,
            ])
            ->all();
    }

    protected function notificationMessage(Reservation $reservation): string
    {
        if ($reservation->status === 'pending_review') {
            return 'Needs approval before the guest arrives.';
        }

        if ($reservation->status === 'rescheduled') {
            return 'Recently moved to a new schedule and should be re-checked.';
        }

        if ($reservation->status === 'checked_in') {
            return 'Guest is already on site and service is active.';
        }

        return 'Confirmed event coming up soon.';
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
        $startMinutes = ((int) substr($time, 0, 2) * 60) + (int) substr($time, 3, 2);
        $openingMinutes = 7 * 60;
        $closingMinutes = 12 * 60;
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
