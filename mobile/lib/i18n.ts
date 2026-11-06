import type { AppLanguage } from '@/lib/preferences';

const messages: Record<AppLanguage, Record<string, string>> = {
  en: {
    schedule: 'Schedule',
    scheduleHint: 'Set the branch, venue, room, date, and time.',
    chooseBranch: 'Choose a branch',
    eventVenue: 'Event venue',
    venueQuestion: 'Where will the event be held?',
    locationType: 'Location type',
    selectedBranch: 'Selected branch',
    anotherVenue: 'Another venue',
    venueHelp: 'The selected branch will manage and serve the booking even when you choose another venue.',
    venueName: 'Venue or place name',
    venueAddress: 'Street / building / landmark',
    barangay: 'Barangay',
    municipality: 'Municipality / City',
    reservationSchedule: 'Reservation schedule',
    pickDateTime: 'Pick your date and time',
    refresh: 'Refresh now',
    language: 'Language',
  },
  fil: {
    schedule: 'Iskedyul',
    scheduleHint: 'Itakda ang branch, venue, kuwarto, petsa, at oras.',
    chooseBranch: 'Pumili ng branch',
    eventVenue: 'Lugar ng event',
    venueQuestion: 'Saan gaganapin ang event?',
    locationType: 'Uri ng lokasyon',
    selectedBranch: 'Napiling branch',
    anotherVenue: 'Ibang venue',
    venueHelp: 'Ang napiling branch ang mamamahala at magseserbisyo kahit ibang venue ang piliin.',
    venueName: 'Pangalan ng venue o lugar',
    venueAddress: 'Kalsada / gusali / palatandaan',
    barangay: 'Barangay',
    municipality: 'Munisipalidad / Lungsod',
    reservationSchedule: 'Iskedyul ng reservation',
    pickDateTime: 'Piliin ang petsa at oras',
    refresh: 'I-refresh',
    language: 'Wika',
  },
};

export function translate(language: AppLanguage, key: string) {
  return messages[language]?.[key] ?? messages.en[key] ?? key;
}
