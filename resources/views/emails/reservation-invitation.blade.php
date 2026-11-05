@component('mail::message')
# You're invited to a McDonald's event

Hello {{ $invitation->invitee_name }},

{{ $invitation->inviter?->name ?? 'A McDonald\'s Reservations customer' }} invited you to an upcoming event.

@component('mail::panel')
**Event:** {{ $invitation->reservation?->package_name ?: $invitation->reservation?->reservation_type }}<br>
**Branch:** {{ $invitation->reservation?->branch }}<br>
**Date:** {{ $invitation->reservation?->event_date?->format('d M Y') }}<br>
**Time:** {{ $invitation->reservation?->event_time ? \Carbon\Carbon::parse($invitation->reservation->event_time)->format('g:i A') : '' }}<br>
**Booking reference:** {{ $invitation->reservation?->booking_reference }}
@endcomponent

Sign in or create your customer account using this email address to accept or decline the invitation.

@component('mail::button', ['url' => $dashboardUrl, 'color' => 'red'])
View invitation and RSVP
@endcomponent

If you were not expecting this invitation, you can ignore this email.

Thanks,<br>
{{ config('app.name') }}
@endcomponent
