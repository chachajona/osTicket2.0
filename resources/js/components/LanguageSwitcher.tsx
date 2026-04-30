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
            <div className="flex items-center rounded-[4px] border border-[#E2E8F0] bg-white shadow-sm shadow-[#0F172A]/[0.03]">
                {SUPPORTED_LANGUAGES.map((lang, index) => (
                    <button
                        key={lang.code}
                        onClick={() => changeLanguage(lang.code)}
                        aria-pressed={i18n.language === lang.code || i18n.language.startsWith(lang.code)}
                        className={[
                            'px-3 py-1.5 text-xs font-medium transition-colors focus:outline-none focus:ring-2 focus:ring-[#C4A5F3]/40 focus:ring-offset-1',
                            index === 0 ? 'rounded-l-[4px]' : '',
                            index === SUPPORTED_LANGUAGES.length - 1 ? 'rounded-r-[4px]' : '',
                            index > 0 ? 'border-l border-[#E2E8F0]' : '',
                            (i18n.language === lang.code || i18n.language.startsWith(lang.code))
                                ? 'bg-[#5B619D] text-white'
                                : 'text-[#64748B] hover:bg-[#F8FAFC] hover:text-[#0F172A]',
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
