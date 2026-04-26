<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\ReservationController;
use App\Models\BookingPackage;
use App\Models\BookingSetting;
use App\Models\Branch;
use App\Models\BranchInventoryItem;
use App\Models\EventType;
use App\Models\Reservation;
use App\Models\RoomOption;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class MobileOperationsController extends ReservationController
{
    public function mobileOperations(Request $request): JsonResponse
    {
        $role = (string) $request->user()->role;
        $payload = ['role' => $role];

        if (in_array($role, ['admin', 'manager'], true)) {
            $payload['admin'] = $this->adminOperationsPayload($request);
        }

        if (in_array($role, ['admin', 'manager', 'staff'], true)) {
            $payload['staff'] = $this->staffOperationsPayload($request);
        }

        return response()->json($payload);
    }

    public function mobileAvailabilityDay(Request $request, string $branchCode, string $date): JsonResponse
    {
        $this->authorizeRoles($request, ['admin', 'manager']);

        return response()->json(
            $this->availabilityDayPayload($this->catalog(), $branchCode, $date)
        );
    }

    public function mobileUpdateBookingStatus(Request $request, Reservation $reservation): JsonResponse
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

        $message = match ($validated['status']) {
            'confirmed' => 'Ba-da-ba-ba-ba. The customer reservation is successful and officially confirmed.',
            'completed' => 'Event marked as done and moved to history.',
            default => 'Booking status updated.',
        };

        return response()->json([
            'message' => $message,
            'reservation' => $this->serializeReservation($reservation->fresh(['user', 'assignedStaff'])),
        ]);
    }

    public function mobileAssignCrew(Request $request, Reservation $reservation): JsonResponse
    {
        $this->authorizeRoles($request, ['admin', 'manager']);

        $validated = $request->validate([
            'assigned_staff_id' => ['nullable', Rule::exists('users', 'id')],
        ]);

        $reservation->update([
            'assigned_staff_id' => $validated['assigned_staff_id'] ?? null,
        ]);

        return response()->json([
            'message' => 'Crew assignment updated.',
            'reservation' => $this->serializeReservation($reservation->fresh(['user', 'assignedStaff'])),
        ]);
    }

    public function mobileStoreAdminUser(Request $request): JsonResponse
    {
        $this->authorizeRoles($request, ['admin']);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:30'],
            'role' => ['required', Rule::in(['customer', 'staff', 'manager', 'admin'])],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?: null,
            'role' => $validated['role'],
            'password' => Hash::make($validated['password']),
        ]);

        return response()->json([
            'message' => 'Account created.',
            'user' => $this->serializeAdminUser($user),
        ], 201);
    }

    public function mobileUpdateAdminUser(Request $request, User $user): JsonResponse
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

        return response()->json([
            'message' => 'Account updated.',
            'user' => $this->serializeAdminUser($user->fresh()),
        ]);
    }

    public function mobileDestroyAdminUser(Request $request, User $user): JsonResponse
    {
        $this->authorizeRoles($request, ['admin']);

        if ($request->user()->is($user)) {
            return response()->json([
                'message' => 'You cannot delete your own account.',
            ], 422);
        }

        if ($user->role === 'admin' && User::query()->where('role', 'admin')->count() <= 1) {
            return response()->json([
                'message' => 'At least one admin account must remain in the system.',
            ], 422);
        }

        $user->delete();

        return response()->json([
            'message' => 'Account deleted.',
        ]);
    }

    public function mobileStoreBranch(Request $request): JsonResponse
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

        return response()->json([
            'message' => 'New branch added.',
            'branch' => $this->serializeBranch($branch->fresh()),
        ], 201);
    }

    public function mobileUpdateBranch(Request $request, Branch $branch): JsonResponse
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

        return response()->json([
            'message' => 'Branch updated.',
            'branch' => $this->serializeBranch($branch->fresh()),
        ]);
    }

    public function mobileDestroyBranch(Request $request, Branch $branch): JsonResponse
    {
        $this->authorizeRoles($request, ['admin', 'manager']);

        $branch->delete();

        return response()->json([
            'message' => 'Branch deleted.',
        ]);
    }

    public function mobileStoreInventoryItem(Request $request, Branch $branch): JsonResponse
    {
        $this->authorizeRoles($request, ['admin', 'manager']);

        $validated = $request->validate([
            'item' => ['required', 'string', 'max:255'],
            'stock' => ['required', 'integer', 'min:0'],
            'threshold' => ['required', 'integer', 'min:0'],
        ]);

        $nextSortOrder = (int) ($branch->inventoryItems()->max('sort_order') ?? -1) + 1;

        $item = BranchInventoryItem::create([
            'branch_id' => $branch->id,
            'item' => $validated['item'],
            'stock' => $validated['stock'],
            'threshold' => $validated['threshold'],
            'sort_order' => $nextSortOrder,
        ]);

        return response()->json([
            'message' => 'Inventory item added.',
            'inventory_item' => $item,
        ], 201);
    }

    public function mobileUpdateInventoryItem(Request $request, BranchInventoryItem $inventoryItem): JsonResponse
    {
        $this->authorizeRoles($request, ['admin', 'manager']);

        $validated = $request->validate([
            'item' => ['required', 'string', 'max:255'],
            'stock' => ['required', 'integer', 'min:0'],
            'threshold' => ['required', 'integer', 'min:0'],
        ]);

        $inventoryItem->update($validated);

        return response()->json([
            'message' => 'Inventory item updated.',
            'inventory_item' => $inventoryItem->fresh(),
        ]);
    }

    public function mobileUpdateEventType(Request $request, EventType $eventType): JsonResponse
    {
        $this->authorizeRoles($request, ['admin', 'manager']);

        $validated = $request->validate([
            'label' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:1000'],
            'icon' => ['nullable', 'string', 'max:100'],
            'is_active' => ['required', 'boolean'],
        ]);

        $eventType->update($validated);

        return response()->json([
            'message' => 'Event type updated.',
        ]);
    }

    public function mobileUpdatePackage(Request $request, BookingPackage $package): JsonResponse
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

        return response()->json([
            'message' => 'Package updated.',
        ]);
    }

    public function mobileStoreRoomOption(Request $request): JsonResponse
    {
        $this->authorizeRoles($request, ['admin', 'manager']);

        $validated = $request->validate([
            'label' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:1000'],
            'preferred_event_type' => ['nullable', Rule::in(array_keys($this->catalog()['eventTypes']))],
        ]);

        $roomOption = RoomOption::create([
            'code' => Str::slug($validated['label']),
            'label' => $validated['label'],
            'description' => $validated['description'],
            'preferred_event_type' => $validated['preferred_event_type'] ?? null,
            'sort_order' => ((int) RoomOption::query()->max('sort_order')) + 1,
            'is_active' => true,
        ]);

        return response()->json([
            'message' => 'Room option added.',
            'room_option' => $roomOption,
        ], 201);
    }

    public function mobileUpdateRoomOption(Request $request, RoomOption $roomOption): JsonResponse
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

        return response()->json([
            'message' => 'Room option updated.',
        ]);
    }

    public function mobileUpdateBookingSettings(Request $request): JsonResponse
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

        return response()->json([
            'message' => 'Booking hours updated.',
        ]);
    }

    public function mobileUpdateServiceStatus(Request $request, Reservation $reservation): JsonResponse
    {
        $this->authorizeRoles($request, ['admin', 'manager', 'staff']);

        $validated = $request->validate([
            'service_status' => ['required', Rule::in(['available', 'cleaning', 'in_progress'])],
        ]);

        $reservation->update([
            'service_status' => $validated['service_status'],
        ]);

        return response()->json([
            'message' => 'Floor status updated.',
            'reservation' => $this->serializeReservation($reservation->fresh(['user', 'assignedStaff'])),
        ]);
    }

    public function mobileUpdateServiceAdjustments(Request $request, Reservation $reservation): JsonResponse
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
            return response()->json([
                'message' => 'Only confirmed or active events can be edited on the service floor.',
            ], 422);
        }

        if (! $this->isDurationWindowValid($reservation->event_time, (int) $validated['duration_hours'])) {
            return response()->json([
                'message' => 'The updated duration must stay within the '.$this->bookingWindowLabel().' booking window.',
            ], 422);
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

        return response()->json([
            'message' => 'Live event updates saved.',
            'reservation' => $this->serializeReservation($reservation->fresh(['user', 'assignedStaff'])),
        ]);
    }

    public function mobileCheckIn(Request $request): JsonResponse
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
            return response()->json([
                'message' => 'No reservation matched that code.',
            ], 404);
        }

        if (! in_array($reservation->status, ['confirmed', 'rescheduled'], true)) {
            return response()->json([
                'message' => 'This booking must be confirmed by admin before staff can check it in.',
            ], 422);
        }

        $reservation->update([
            'status' => 'checked_in',
            'checked_in_at' => now(),
            'checked_in_by' => $request->user()->name,
            'service_status' => 'in_progress',
        ]);

        return response()->json([
            'message' => 'Guest checked in successfully.',
            'reservation' => $this->serializeReservation($reservation->fresh(['user', 'assignedStaff'])),
        ]);
    }

    protected function adminOperationsPayload(Request $request): array
    {
        $catalog = $this->catalog();
        $allBookings = $this->adminReservations(['user', 'assignedStaff'], null);
        $pendingBookings = $this->adminReservations(['user', 'assignedStaff'], ['pending_review']);
        $confirmedBookings = $this->adminReservations(['user', 'assignedStaff'], ['confirmed', 'rescheduled', 'checked_in']);
        $availability = $this->availabilityPayload($catalog, 366);
        $branchCodes = collect($availability['branches'] ?? [])->pluck('code')->filter()->values();
        $initialBranch = (string) ($branchCodes->first() ?? '');

        return [
            'dashboard' => [
                'stats' => $this->adminStats($this->adminStatsBookings()),
                'notifications' => $this->upcomingEventNotifications($allBookings),
                'history' => $this->eventHistory($allBookings),
                'branchSummaries' => collect(array_values($catalog['branches']))->map(fn ($branch) => [
                    'code' => $branch['code'],
                    'name' => $branch['name'],
                    'city' => $branch['city'],
                ])->values(),
            ],
            'bookings' => [
                'stats' => $this->adminStats($this->adminStatsBookings()),
                'groupedBookings' => $this->groupedBookings($catalog, $pendingBookings),
                'staffUsers' => $this->adminStaffUsers(),
                'menuBundles' => $catalog['menuBundles'],
                'addOns' => $catalog['addOns'],
                'durationOptions' => range(1, 16),
            ],
            'confirmedEvents' => [
                'stats' => $this->adminStats($this->adminStatsBookings()),
                'confirmedEvents' => $confirmedBookings->map(fn (Reservation $reservation) => $this->serializeReservation($reservation))->values(),
                'staffUsers' => $this->adminStaffUsers(),
                'menuBundles' => $catalog['menuBundles'],
                'addOns' => $catalog['addOns'],
                'durationOptions' => range(1, 16),
            ],
            'availability' => [
                'availability' => $availability,
                'initialBranch' => $initialBranch,
                'initialMonth' => now()->format('Y-m'),
            ],
            'branches' => [
                'branches' => $this->adminBranchList(),
            ],
            'accounts' => [
                'users' => $this->adminUsers(),
                'canManageAccounts' => $request->user()->role === 'admin',
            ],
            'catalog' => [
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
            ],
            'reports' => [
                'pricing' => $catalog['pricing'],
                'report' => $this->analyticsReport($allBookings),
                'inventory' => $this->inventorySnapshot($catalog, $allBookings),
                'staffAssignments' => $this->staffAssignments($catalog, $allBookings),
            ],
            'timeline' => [
                'notifications' => $this->upcomingEventNotifications($allBookings),
                'history' => $this->eventHistory($allBookings),
                'cancelledEvents' => $this->cancelledEvents($allBookings),
            ],
        ];
    }

    protected function staffOperationsPayload(Request $request): array
    {
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

        return [
            'prepList' => $this->prepList($catalog, $todayBookings),
            'todayBookings' => $todayBookings->map(fn (Reservation $reservation) => $this->serializeReservation($reservation))->values(),
            'notifications' => $this->upcomingEventNotifications($relevantBookings, 6),
            'history' => $this->eventHistory($relevantBookings, 8),
            'statusOptions' => ['available', 'cleaning', 'in_progress'],
            'menuBundles' => $catalog['menuBundles'],
            'addOns' => $catalog['addOns'],
            'durationOptions' => range(1, 16),
        ];
    }

    protected function serializeAdminUser(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'role' => $user->role,
            'created_at' => optional($user->created_at)->format('M j, Y g:i A'),
        ];
    }

    protected function serializeBranch(Branch $branch): array
    {
        return [
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
        ];
    }
}
