export function formatCurrency(value: number) {
  return new Intl.NumberFormat('en-PH', {
    style: 'currency',
    currency: 'PHP',
  }).format(value);
}

type DateFormat = 'mdy' | 'dmy' | 'ymd';
type TimeFormat = '12-hour' | '24-hour';
type AppLanguage = 'en' | 'fil';

function localeFor(language: AppLanguage) {
  return language === 'fil' ? 'fil-PH' : 'en-PH';
}

export function formatCompactDate(date: string, language: AppLanguage = 'en') {
  return new Date(`${date}T12:00:00`).toLocaleDateString(localeFor(language), {
    month: 'short',
    day: 'numeric',
  });
}

export function formatLongDate(date: string, dateFormat: DateFormat = 'mdy', language: AppLanguage = 'en') {
  if (dateFormat === 'ymd') {
    return date.slice(0, 10);
  }

  const dateValue = new Date(`${date}T12:00:00`);
  const month = dateValue.toLocaleDateString(localeFor(language), { month: 'long' });
  const day = dateValue.getDate();
  const year = dateValue.getFullYear();

  return dateFormat === 'dmy' ? `${day} ${month} ${year}` : `${month} ${day}, ${year}`;
}

export function formatMonthLabel(month: string, language: AppLanguage = 'en') {
  return new Date(`${month}-01T12:00:00`).toLocaleDateString(localeFor(language), {
    month: 'long',
    year: 'numeric',
  });
}

export function formatTimeLabel(time: string, timeFormat: TimeFormat = '12-hour') {
  const [hours, minutes] = time.split(':').map(Number);

  if (timeFormat === '24-hour') {
    return `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}`;
  }

  const meridian = hours >= 12 ? 'PM' : 'AM';
  const hour12 = hours % 12 || 12;

  return `${hour12}:${String(minutes).padStart(2, '0')} ${meridian}`;
}

export function addHoursToTime(time: string, hoursToAdd: number) {
  const [hours, minutes] = time.split(':').map(Number);
  const totalMinutes = hours * 60 + minutes + hoursToAdd * 60;
  const nextHours = Math.floor(totalMinutes / 60) % 24;
  const nextMinutes = totalMinutes % 60;

  return `${String(nextHours).padStart(2, '0')}:${String(nextMinutes).padStart(2, '0')}`;
}

export function formatStatusLabel(status: string) {
  return status
    .split('_')
    .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
    .join(' ');
}

export function getInitials(name: string) {
  return name
    .split(' ')
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0]?.toUpperCase() ?? '')
    .join('');
}
