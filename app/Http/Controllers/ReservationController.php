<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class ReservationController extends Controller
{
    public function home(): InertiaResponse
    {
        $catalog = $this->catalog();
        $reservationCount = Schema::hasTable('reservations') ? Reservation::count() : 0;

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
            'availability' => $this->availabilityPayload($catalog, 14),
            'defaults' => [
                'event_date' => now()->addDays(3)->toDateString(),
                'event_time' => $catalog['slotOptions'][2],
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
            'guests' => ['required', 'integer', 'min:2', 'max:60'],
            'package_code' => ['required', 'string'],
            'menu_bundles' => ['array'],
            'menu_bundles.*' => ['string'],
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

        if (! $this->isSlotAvailable($validated['branch_code'], $validated['event_date'], $validated['event_time'])) {
            return back()->withErrors([
                'event_time' => 'That time slot is already full for the selected branch.',
            ])->withInput();
        }

        $menuBundles = collect($catalog['menuBundles'])
            ->whereIn('code', $validated['menu_bundles'] ?? [])
            ->values()
            ->all();

        $addOns = collect($catalog['addOns'])
            ->whereIn('code', $validated['add_ons'] ?? [])
            ->values()
            ->all();

        $proofPath = $request->file('payment_proof')->store('payment-proofs', 'public');
        $bookingReference = strtoupper('MCR-'.Str::random(8));
        $receipt = $this->buildReceipt(
            $validated['event_type'],
            $validated['event_date'],
            $package,
            $menuBundles,
            $addOns
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
            'food_package' => collect($menuBundles)->pluck('name')->implode(', '),
            'beverage_package' => 'Included in selected bundles',
            'event_materials' => collect($addOns)->pluck('name')->implode(', '),
            'branch' => $branch['name'],
            'branch_code' => $branch['code'],
            'event_date' => $validated['event_date'],
            'event_time' => $validated['event_time'],
            'menu_bundles' => Arr::pluck($menuBundles, 'code'),
            'add_ons' => Arr::pluck($addOns, 'code'),
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
        $bookings = Reservation::query()
            ->where('user_id', $request->user()->id)
            ->orderBy('event_date')
            ->orderBy('event_time')
            ->get();

        return Inertia::render('Dashboard', [
            'bookings' => $bookings->map(fn (Reservation $reservation) => $this->serializeReservation($reservation))->values(),
            'stats' => [
                ['label' => 'Upcoming', 'value' => $bookings->whereIn('status', ['pending_review', 'confirmed', 'rescheduled', 'checked_in'])->count()],
                ['label' => 'Confirmed spend', 'value' => '$'.number_format($bookings->whereIn('status', ['confirmed', 'checked_in'])->sum('total_amount'), 2)],
                ['label' => 'Pending approvals', 'value' => $bookings->where('status', 'pending_review')->count()],
            ],
        ]);
    }

    public function adminDashboard(Request $request): InertiaResponse
    {
        $this->authorizeRoles($request, ['admin', 'manager']);

        $catalog = $this->catalog();
        $bookings = Reservation::query()->orderBy('event_date')->orderBy('event_time')->get();

        return Inertia::render('Admin/Dashboard', [
            'stats' => $this->adminStats($bookings),
            'calendar' => $bookings->map(fn (Reservation $reservation) => $this->serializeReservation($reservation))->values(),
            'groupedBookings' => $this->groupedBookings($catalog, $bookings),
            'inventory' => $this->inventorySnapshot($catalog, $bookings),
            'staffAssignments' => $this->staffAssignments($catalog, $bookings),
            'availability' => $this->availabilityPayload($catalog, 14),
            'pricing' => $catalog['pricing'],
            'report' => $this->analyticsReport($bookings),
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
        ]);
    }

    public function staffDashboard(Request $request): InertiaResponse
    {
        $this->authorizeRoles($request, ['admin', 'manager', 'staff']);

        $catalog = $this->catalog();
        $today = now()->toDateString();
        $bookings = Reservation::query()
            ->whereDate('event_date', $today)
            ->orderBy('event_time')
            ->get();

        return Inertia::render('Staff/Dashboard', [
            'prepList' => $this->prepList($catalog, $bookings),
            'todayBookings' => $bookings->map(fn (Reservation $reservation) => $this->serializeReservation($reservation))->values(),
            'statusOptions' => ['available', 'cleaning', 'in_progress'],
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

        if (! $this->isSlotAvailable($reservation->branch_code, $validated['event_date'], $validated['event_time'], $reservation->id)) {
            return back()->withErrors([
                'event_time' => 'That new slot is already full.',
            ]);
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

        $reservation->update(['status' => $validated['status']]);

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

        Branch::create([
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

        return response()->json($this->availabilityPayload($this->catalog(), 14));
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
        return [
            'eventTypes' => config('booking.event_types'),
            'branches' => $this->branchCatalog(),
            'packages' => config('booking.packages'),
            'menuBundles' => config('booking.menu_bundles'),
            'addOns' => config('booking.add_ons'),
            'slotOptions' => config('booking.slot_options'),
            'pricing' => config('booking.pricing'),
        ];
    }

    protected function branchCatalog(): array
    {
        if (! Schema::hasTable('branches')) {
            return config('booking.branches');
        }

        $branches = Branch::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        if ($branches->isEmpty()) {
            return config('booking.branches');
        }

        return $branches->mapWithKeys(fn (Branch $branch) => [
            $branch->code => [
                'code' => $branch->code,
                'name' => $branch->name,
                'city' => $branch->city,
                'supports' => $branch->supports ?? [],
                'concurrent_limit' => $branch->concurrent_limit,
                'max_guests' => $branch->max_guests,
                'map_url' => $branch->map_url,
                'inventory' => $branch->inventory ?? [],
                'hosts' => $branch->hosts ?? [],
            ],
        ])->all();
    }

    protected function bookedSlots(): array
    {
        return Reservation::query()
            ->whereIn('status', ['pending_review', 'confirmed', 'rescheduled', 'checked_in'])
            ->get(['branch_code', 'event_date', 'event_time'])
            ->map(fn (Reservation $reservation) => [
                'branch_code' => $reservation->branch_code,
                'event_date' => $reservation->event_date->toDateString(),
                'event_time' => substr($reservation->event_time, 0, 5),
            ])
            ->values()
            ->all();
    }

    protected function availabilityPayload(array $catalog, int $days): array
    {
        $days = max(7, min($days, 30));
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
                        $remaining = max(($branch['concurrent_limit'] ?? 1) - $booked, 0);

                        return [
                            'time' => $time,
                            'booked' => $booked,
                            'remaining' => $remaining,
                            'full' => $remaining === 0,
                        ];
                    })->values();

                    $availableSlots = $slots->where('full', false)->count();

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

    protected function isSlotAvailable(string $branchCode, string $date, string $time, ?int $ignoreReservationId = null): bool
    {
        $count = Reservation::query()
            ->where('branch_code', $branchCode)
            ->whereDate('event_date', $date)
            ->where('event_time', $time)
            ->whereIn('status', ['pending_review', 'confirmed', 'rescheduled', 'checked_in'])
            ->when($ignoreReservationId, fn ($query) => $query->whereKeyNot($ignoreReservationId))
            ->count();

        return $count < ($this->catalog()['branches'][$branchCode]['concurrent_limit'] ?? 1);
    }

    protected function calculatePrice(string $eventDate, float $basePrice, array $menuBundles, array $addOns): float
    {
        return $this->buildReceipt('unknown', $eventDate, ['price' => $basePrice, 'name' => 'Base Package'], $menuBundles, $addOns)['total_raw'];
    }

    protected function buildReceipt(string $eventType, string $eventDate, array $package, array $menuBundles, array $addOns): array
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

        $lineItems = collect([
            [
                'label' => $package['name'],
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
            ->merge(collect($addOns)->map(fn ($item) => [
                'label' => $item['name'],
                'type' => 'add_on',
                'amount_raw' => (float) $item['price'],
                'amount' => number_format((float) $item['price'], 2),
            ]))
            ->values();

        $bundleTotal = collect($menuBundles)->sum('price');
        $addOnTotal = collect($addOns)->sum('price');
        $subtotal = (float) $package['price'] + $bundleTotal + $addOnTotal;
        $total = round($subtotal * $multiplier, 2);

        return [
            'event_type' => $eventType,
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
        $package = collect($catalog['packages'][$reservation->reservation_type] ?? [])->firstWhere('code', $reservation->package_code)
            ?? ['name' => $reservation->package_name, 'price' => (float) $reservation->total_amount];
        $menuBundles = collect($catalog['menuBundles'])->whereIn('code', $reservation->menu_bundles ?? [])->values()->all();
        $addOns = collect($catalog['addOns'])->whereIn('code', $reservation->add_ons ?? [])->values()->all();
        $receipt = $this->buildReceipt(
            $reservation->reservation_type,
            $reservation->event_date?->toDateString() ?? now()->toDateString(),
            $package,
            $menuBundles,
            $addOns
        );

        return [
            'id' => $reservation->id,
            'booking_reference' => $reservation->booking_reference,
            'event_type' => $reservation->reservation_type,
            'package_name' => $reservation->package_name,
            'branch' => $reservation->branch,
            'branch_code' => $reservation->branch_code,
            'event_date' => $reservation->event_date?->toDateString(),
            'event_time' => substr($reservation->event_time, 0, 5),
            'guests' => $reservation->guests,
            'status' => $reservation->status,
            'service_status' => $reservation->service_status,
            'notes' => $reservation->notes,
            'menu_bundles' => $reservation->menu_bundles ?? [],
            'add_ons' => $reservation->add_ons ?? [],
            'total_amount' => number_format((float) $reservation->total_amount, 2),
            'receipt' => $receipt,
            'assigned_staff_id' => $reservation->assigned_staff_id,
            'assigned_staff_name' => $reservation->assignedStaff?->name,
            'check_in_code' => $reservation->check_in_code,
            'checked_in_at' => $reservation->checked_in_at?->toDateTimeString(),
            'checked_in_by' => $reservation->checked_in_by,
            'pass_url' => route('reservations.pass', $reservation),
            'payment_proof_url' => route('reservations.payment-proof', $reservation),
        ];
    }

    protected function adminStats($bookings): array
    {
        $confirmed = $bookings->whereIn('status', ['confirmed', 'checked_in', 'completed']);

        return [
            ['label' => 'Revenue pipeline', 'value' => '$'.number_format($bookings->sum('total_amount'), 2)],
            ['label' => 'Confirmed events', 'value' => $confirmed->count()],
            ['label' => 'Peak booking hour', 'value' => $bookings->groupBy(fn ($booking) => substr($booking->event_time, 0, 5))->map->count()->sortDesc()->keys()->first() ?: '14:30'],
            ['label' => 'Weekend uplift', 'value' => '+15%'],
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
                $hosts = $catalog['branches'][$reservation->branch_code]['hosts'] ?? ['Floor Team'];
                $assigned = $reservation->assignedStaff?->name;

                return [
                    'booking_reference' => $reservation->booking_reference,
                    'branch' => $reservation->branch,
                    'slot' => $reservation->event_date?->format('M d').', '.substr($reservation->event_time, 0, 5),
                    'host' => $assigned ?: $hosts[$index % count($hosts)],
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
            $bundleLabels = collect($catalog['menuBundles'])
                ->whereIn('code', $reservation->menu_bundles ?? [])
                ->pluck('prep_label')
                ->values()
                ->all();

            return [
                'booking_reference' => $reservation->booking_reference,
                'time' => substr($reservation->event_time, 0, 5),
                'branch' => $reservation->branch,
                'package_name' => $reservation->package_name,
                'items' => $bundleLabels ?: ['Welcome tray and seating prep'],
                'guest_name' => $reservation->name,
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
        $eventTime = substr($reservation->event_time, 0, 5);

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
}
