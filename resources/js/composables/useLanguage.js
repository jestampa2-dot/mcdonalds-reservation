import { computed, ref } from 'vue'

const storageKey = 'mcdonalds-reservations:language'
const savedLanguage = typeof window !== 'undefined' ? window.localStorage.getItem(storageKey) : null
const language = ref(savedLanguage === 'fil' ? 'fil' : 'en')

const messages = {
  en: {
    home: 'Home',
    login: 'Login',
    register: 'Register',
    bookEvent: 'Book Event',
    dashboard: 'My Dashboard',
    account: 'My Account',
    staffView: 'Staff View',
    signIn: 'Sign in',
    logout: 'Logout',
    browseAndBook: 'Browse and book.',
    customerWorkspace: 'Bookings, payments, and event details.',
    language: 'Language',
    scheduleStep: '2. Date, time, and branch',
    scheduleLocation: 'Set the schedule and location.',
    scheduleHelp: 'Choose the servicing branch, room, exact schedule, and event venue.',
    branch: 'Branch',
    roomRental: 'Room rental',
    venueQuestion: 'Where will the event be held?',
    selectedBranchVenue: "At the selected McDonald's branch",
    customVenue: 'At another venue or customer location',
    venueBranchHelp: 'The selected branch will still manage and serve this booking.',
    eventVenue: 'Event venue',
    venueName: 'Venue or place name',
    venueAddress: 'Street / building / landmark',
    barangay: 'Barangay',
    municipality: 'Municipality / City',
  },
  fil: {
    home: 'Home',
    login: 'Mag-login',
    register: 'Gumawa ng account',
    bookEvent: 'Mag-book ng Event',
    dashboard: 'Aking Dashboard',
    account: 'Aking Account',
    staffView: 'Staff View',
    signIn: 'Mag-sign in',
    logout: 'Mag-logout',
    browseAndBook: 'Tumingin at mag-book.',
    customerWorkspace: 'Mga booking, bayad, at detalye ng event.',
    language: 'Wika',
    scheduleStep: '2. Petsa, oras, at branch',
    scheduleLocation: 'Itakda ang iskedyul at lokasyon.',
    scheduleHelp: 'Piliin ang branch na magseserbisyo, kuwarto, iskedyul, at lugar ng event.',
    branch: 'Branch',
    roomRental: 'Renta ng kuwarto',
    venueQuestion: 'Saan gaganapin ang event?',
    selectedBranchVenue: "Sa napiling McDonald's branch",
    customVenue: 'Sa ibang venue o lokasyon ng customer',
    venueBranchHelp: 'Ang napiling branch pa rin ang mamamahala at magseserbisyo sa booking.',
    eventVenue: 'Lugar ng event',
    venueName: 'Pangalan ng venue o lugar',
    venueAddress: 'Kalsada / gusali / palatandaan',
    barangay: 'Barangay',
    municipality: 'Munisipalidad / Lungsod',
  },
}

export function useLanguage() {
  const setLanguage = (nextLanguage) => {
    language.value = nextLanguage === 'fil' ? 'fil' : 'en'
    document.documentElement.lang = language.value === 'fil' ? 'fil' : 'en'
    window.localStorage.setItem(storageKey, language.value)
  }

  const t = (key) => messages[language.value]?.[key] ?? messages.en[key] ?? key

  return {
    language: computed(() => language.value),
    setLanguage,
    t,
  }
}
