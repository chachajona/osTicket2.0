import { useTranslation } from 'react-i18next';
import { SUPPORTED_LANGUAGES, type SupportedLanguage } from '../i18n';

interface LanguageSwitcherProps {
    className?: string;
}

export default function LanguageSwitcher({ className = '' }: LanguageSwitcherProps) {
    const { t, i18n } = useTranslation();

    const changeLanguage = (lang: SupportedLanguage) => {
        i18n.changeLanguage(lang);
    };

    return (
        <div className={`relative inline-flex items-center gap-1 ${className}`}>
            <span className="sr-only">{t('language_switcher.select_language')}</span>
            <div className="flex items-center rounded-md border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                {SUPPORTED_LANGUAGES.map((lang, index) => (
                    <button
                        key={lang.code}
                        onClick={() => changeLanguage(lang.code)}
                        aria-pressed={i18n.language === lang.code || i18n.language.startsWith(lang.code)}
                        className={[
                            'px-3 py-1.5 text-sm font-medium transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-1',
                            index === 0 ? 'rounded-l-md' : '',
                            index === SUPPORTED_LANGUAGES.length - 1 ? 'rounded-r-md' : '',
                            index > 0 ? 'border-l border-gray-200 dark:border-gray-700' : '',
                            (i18n.language === lang.code || i18n.language.startsWith(lang.code))
                                ? 'bg-blue-600 text-white'
                                : 'text-gray-700 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-gray-700',
                        ]
                            .filter(Boolean)
                            .join(' ')}
                    >
                        {lang.nativeName}
                    </button>
                ))}
            </div>
        </div>
    );
}
