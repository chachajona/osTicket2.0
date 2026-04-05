import { useTranslation } from 'react-i18next';
import LanguageSwitcher from '../components/LanguageSwitcher';

export default function Welcome() {
    const { t } = useTranslation();

    return (
        <div className="flex min-h-screen flex-col items-center justify-center gap-6">
            <h1 className="text-3xl font-bold">osTicket 2.0</h1>
            <p className="text-lg text-gray-600">{t('common.welcome')}</p>
            <LanguageSwitcher />
        </div>
    );
}
