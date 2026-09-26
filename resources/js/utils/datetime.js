const DEFAULT_TIMEZONE = 'Asia/Kolkata';

export const timezoneFor = (user) => user?.household?.timezone || DEFAULT_TIMEZONE;

export const isDateOnly = (value) => /^\d{4}-\d{2}-\d{2}$/.test(String(value || '').slice(0, 10));

export const formatDate = (value, timezone = DEFAULT_TIMEZONE) => {
    if (!value) return '—';
    const date = isDateOnly(value) ? new Date(`${String(value).slice(0, 10)}T12:00:00`) : new Date(value);
    if (Number.isNaN(date.getTime())) return '—';
    return new Intl.DateTimeFormat('en-IN', { day: '2-digit', month: 'short', year: 'numeric', timeZone: timezone }).format(date);
};

export const formatDateTime = (value, timezone = DEFAULT_TIMEZONE) => {
    if (!value) return '—';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '—';
    return new Intl.DateTimeFormat('en-IN', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit', timeZone: timezone }).format(date);
};

export const formatColumn = (column, value, timezone = DEFAULT_TIMEZONE) => {
    if (!value) return '—';
    if (isDateOnly(value) || /(^|_)(date|expiry|start|end)$/i.test(column) || ['due_date', 'period_start', 'period_end', 'warranty_expiry', 'payment_date', 'expiry_date', 'reminder_date', 'date_of_birth', 'visited_at'].includes(column)) return formatDate(value, timezone);
    if (/(^|_)(at|time)$|_datetime$|created_at|updated_at|completed_at|last_used_at/i.test(column)) return formatDateTime(value, timezone);
    return value;
};

export const toDateInput = (value, timezone = DEFAULT_TIMEZONE) => {
    if (!value) return '';
    if (isDateOnly(value)) return String(value).slice(0, 10);
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return String(value).slice(0, 10);
    const parts = new Intl.DateTimeFormat('en-CA', { timeZone: timezone, year: 'numeric', month: '2-digit', day: '2-digit' }).formatToParts(date).reduce((out, part) => ({ ...out, [part.type]: part.value }), {});
    return `${parts.year}-${parts.month}-${parts.day}`;
};

export const toDateTimeInput = (value, timezone = DEFAULT_TIMEZONE) => {
    if (!value) return '';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return String(value).slice(0, 16);
    const parts = new Intl.DateTimeFormat('en-CA', { timeZone: timezone, year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit', hour12: false }).formatToParts(date).reduce((out, part) => ({ ...out, [part.type]: part.value }), {});
    return `${parts.year}-${parts.month}-${parts.day}T${parts.hour === '24' ? '00' : parts.hour}:${parts.minute}`;
};
