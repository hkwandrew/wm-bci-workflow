import domReady from '@wordpress/dom-ready';
import { createRoot } from '@wordpress/element';

import { SettingsApp } from './settings-app';
import './style.css';

domReady( () => {
	const container = document.getElementById( 'wm-bci-settings-admin-root' );

	if ( ! container || ! window.wmBciWorkflowAdmin ) {
		return;
	}

	const root = createRoot( container );

	root.render( <SettingsApp boot={ window.wmBciWorkflowAdmin } /> );
} );
