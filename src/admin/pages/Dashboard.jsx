/**
 * Tableau de bord : navigation principale à 6 onglets (voir PROJECT.md §6 —
 * « Ne pas multiplier les pages ou sous-pages »).
 */
import { TabPanel } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import Overview from './dashboard/Overview';
import CookiesPage from './dashboard/CookiesPage';
import ServicesPage from './dashboard/ServicesPage';
import BannerPage from './dashboard/BannerPage';
import PolicyPage from './dashboard/PolicyPage';
import SettingsPage from './dashboard/SettingsPage';

const TABS = [
	{
		name: 'overview',
		title: __( 'Vue d’ensemble', 'nook' ),
		Component: Overview,
	},
	{ name: 'cookies', title: __( 'Cookies', 'nook' ), Component: CookiesPage },
	{
		name: 'services',
		title: __( 'Services', 'nook' ),
		Component: ServicesPage,
	},
	{ name: 'banner', title: __( 'Bannière', 'nook' ), Component: BannerPage },
	{ name: 'policy', title: __( 'Politique', 'nook' ), Component: PolicyPage },
	{
		name: 'settings',
		title: __( 'Paramètres', 'nook' ),
		Component: SettingsPage,
	},
];

export default function Dashboard() {
	return (
		<div className="nook-dashboard">
			<h1 className="nook-dashboard__title">{ __( 'Nook', 'nook' ) }</h1>
			<TabPanel tabs={ TABS } className="nook-dashboard__tabs">
				{ ( tab ) => {
					const Component = tab.Component;
					return <Component />;
				} }
			</TabPanel>
		</div>
	);
}
