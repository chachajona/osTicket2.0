import { useForm, usePage } from '@inertiajs/react';
import type { ReactElement, ReactNode } from 'react';

import { Button } from '@/components/ui/button';
import {
    Field,
    FieldDescription,
    FieldGroup,
    FieldLabel,
    FieldLegend,
    FieldSet,
} from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import DashboardLayout from '@/layouts/DashboardLayout';

const THEME_OPTIONS = [
    { value: 'system', label: 'Match system' },
    { value: 'light', label: 'Light' },
    { value: 'dark', label: 'Dark' },
] as const;

const NOTIFICATION_CHANNELS = [
    { id: 'desktop', label: 'Desktop alerts', description: 'Toast notifications inside the agent panel.' },
    { id: 'email', label: 'Email digests', description: 'Receive summary emails for new assignments.' },
    { id: 'sound', label: 'Sound effects', description: 'Audible cue when a new ticket arrives.' },
] as const;

type ThemeValue = (typeof THEME_OPTIONS)[number]['value'];
type NotificationChannel = (typeof NOTIFICATION_CHANNELS)[number]['id'];
type NotificationPreferences = Record<NotificationChannel, boolean>;

interface StaffPreferences {
    theme: ThemeValue;
    language: string | null;
    timezone: string | null;
    notifications: NotificationPreferences;
}

interface PreferencesPageProps {
    preferences: StaffPreferences;
}

interface SharedProps extends Record<string, unknown> {
    status?: string;
}

type FormSubmitHandler = NonNullable<React.ComponentProps<'form'>['onSubmit']>;

export default function PreferencesIndex({ preferences }: PreferencesPageProps) {
    const { props } = usePage<SharedProps>();
    const form = useForm({
        theme: preferences.theme,
        language: preferences.language ?? '',
        timezone: preferences.timezone ?? '',
        notifications: {
            desktop: preferences.notifications?.desktop ?? true,
            email: preferences.notifications?.email ?? false,
            sound: preferences.notifications?.sound ?? false,
        },
    });

    const save: FormSubmitHandler = (event) => {
        event.preventDefault();
        form.patch('/scp/preferences', { preserveScroll: true });
    };

    function toggleChannel(channel: NotificationChannel, enabled: boolean) {
        form.setData('notifications', {
            ...form.data.notifications,
            [channel]: enabled,
        });
    }

    return (
        <div className="mx-auto max-w-3xl">
            {props.status && (
                <div className="mb-6 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
                    {props.status}
                </div>
            )}

            <form
                onSubmit={save}
                className="rounded-[18px] border border-[#E2E8F0] bg-white p-6 shadow-sm shadow-[#0F172A]/[0.03] xl:p-8"
            >
                <FieldGroup>
                    <Field>
                        <FieldLabel htmlFor="preferences-theme">Theme</FieldLabel>
                        <Select
                            value={form.data.theme}
                            onValueChange={(value) => value && form.setData('theme', value as ThemeValue)}
                        >
                            <SelectTrigger id="preferences-theme" className="w-full">
                                <SelectValue placeholder="Select a theme" />
                            </SelectTrigger>
                            <SelectContent>
                                {THEME_OPTIONS.map(({ value, label }) => (
                                    <SelectItem key={value} value={value}>
                                        {label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <FieldDescription>Controls the appearance for your agent workspace.</FieldDescription>
                    </Field>

                    <Field>
                        <FieldLabel htmlFor="preferences-language">Language</FieldLabel>
                        <Input
                            id="preferences-language"
                            value={form.data.language}
                            placeholder="en"
                            onChange={(event) => form.setData('language', event.target.value)}
                        />
                        <FieldDescription>IETF language tag, e.g. <span className="font-mono">en</span> or <span className="font-mono">vi-VN</span>.</FieldDescription>
                    </Field>

                    <Field>
                        <FieldLabel htmlFor="preferences-timezone">Timezone</FieldLabel>
                        <Input
                            id="preferences-timezone"
                            value={form.data.timezone}
                            placeholder="America/New_York"
                            onChange={(event) => form.setData('timezone', event.target.value)}
                        />
                        <FieldDescription>IANA zone name. Leave empty to inherit from the system.</FieldDescription>
                    </Field>

                    <FieldSet>
                        <FieldLegend variant="label">Notifications</FieldLegend>
                        <FieldDescription>Choose how you want to be alerted to new ticket activity.</FieldDescription>
                        <div className="grid gap-3">
                            {NOTIFICATION_CHANNELS.map(({ id, label, description }) => (
                                <Label
                                    key={id}
                                    htmlFor={`preferences-notify-${id}`}
                                    className="flex items-start gap-3 rounded-md border border-[#E2E8F0] bg-[#F8FAFC] px-4 py-3 cursor-pointer hover:border-[#CBD5E1]"
                                >
                                    <input
                                        id={`preferences-notify-${id}`}
                                        type="checkbox"
                                        checked={form.data.notifications[id]}
                                        onChange={(event) => toggleChannel(id, event.target.checked)}
                                        className="mt-1 h-4 w-4 rounded border-[#CBD5E1] text-[#5B619D] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#5B619D]"
                                    />
                                    <span className="flex flex-col gap-1">
                                        <span className="text-sm font-medium text-[#0F172A]">{label}</span>
                                        <span className="text-xs text-[#64748B]">{description}</span>
                                    </span>
                                </Label>
                            ))}
                        </div>
                    </FieldSet>
                </FieldGroup>

                <div className="mt-8 flex justify-end gap-3">
                    <Button type="submit" disabled={form.processing}>
                        {form.processing ? 'Saving…' : 'Save preferences'}
                    </Button>
                </div>
            </form>
        </div>
    );
}

type PreferencesPageComponent = typeof PreferencesIndex & {
    layout?: (page: ReactElement) => ReactNode;
};

(PreferencesIndex as PreferencesPageComponent).layout = (page: ReactElement) => (
    <DashboardLayout
        title="Preferences"
        subtitle="Personalize how the agent workspace looks and notifies you."
        eyebrow="Account"
        activeNav="preferences"
        headerActions={null}
    >
        {page}
    </DashboardLayout>
);
