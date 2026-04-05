import i18n from 'i18next';
import { initReactI18next } from 'react-i18next';
import LanguageDetector from 'i18next-browser-languagedetector';

import enTranslations from '../../../lang/en.json';
import viTranslations from '../../../lang/vi.json';

export const SUPPORTED_LANGUAGES = [
    { code: 'en', name: 'English', nativeName: 'English' },
    { code: 'vi', name: 'Vietnamese', nativeName: 'Tiếng Việt' },
] as const;

export type SupportedLanguage = (typeof SUPPORTED_LANGUAGES)[number]['code'];

i18n
    .use(LanguageDetector)
    .use(initReactI18next)
    .init({
        resources: {
            en: {
                translation: enTranslations,
            },
            vi: {
                translation: viTranslations,
            },
        },
        fallbackLng: 'en',
        supportedLngs: ['en', 'vi'],
        interpolation: {
            escapeValue: false,
        },
        detection: {
            order: ['cookie', 'localStorage', 'navigator', 'htmlTag'],
            caches: ['cookie', 'localStorage'],
        },
        debug: import.meta.env.DEV,
    });

export default i18n;
