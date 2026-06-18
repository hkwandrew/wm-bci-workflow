import { fireEvent, render, screen } from '@testing-library/react';

import {
	buildHiddenInputDescriptors,
	filterValidUserLabels,
	selectedUserIdsFromLabels,
	serializeCalendarColors,
	SettingsApp,
} from './settings-app';

const boot = {
	optionName: 'wm_bci_workflow',
	values: {
		formId: '4',
		approvalFieldId: '22',
		notificationName: 'Admin Notification',
		approvalNotificationRecipients: 'first@example.com',
		autoApprovedUserIds: [ 18 ],
		calendarPageSlug: 'bci-resources',
		calendarFeedName: 'BCI Community Opportunity Submission',
		googleSyncUrl: 'https://example.com/sync',
		googleSyncSecret: '',
		hasGoogleSyncSecret: true,
		calendarEventColors: {
			Event: '#004966',
		},
		fieldMap: {
			opportunity_type: '1',
			title: '4',
		},
	},
	fieldMapFields: [
		{ key: 'opportunity_type', label: 'Opportunity Type', value: '1' },
		{ key: 'title', label: 'Title', value: '4' },
	],
	calendarPalette: [
		{ color: '#004966', name: 'Dark Blue' },
		{ color: '#d9a242', name: 'Gold' },
	],
	opportunityTypeChoices: [
		{ value: 'Event', label: 'Event' },
		{ value: 'Resources', label: 'Resources' },
	],
	users: [
		{ id: 12, label: 'Avery Smith (asmith, avery@example.com)' },
		{ id: 18, label: 'Casey Jones (cjones, casey@example.com)' },
	],
};

describe( 'SettingsApp', () => {
	it( 'renders the major settings sections', () => {
		render( <SettingsApp boot={ boot } /> );

		expect( screen.getByText( 'Form Configuration' ) ).toBeTruthy();
		expect( screen.getByText( 'Calendar Event Colors' ) ).toBeTruthy();
		expect( screen.getByText( 'Google Sheets Sync' ) ).toBeTruthy();
		expect( screen.getByText( 'Field Mapping' ) ).toBeTruthy();
		expect( screen.getByRole( 'button', { name: 'Save Changes' } ) ).toBeTruthy();
	} );

	it( 'mirrors form state to hidden inputs and updates scalar values', () => {
		const { container } = render( <SettingsApp boot={ boot } /> );

		expect(
			container.querySelector( 'input[type="hidden"][name="wm_bci_workflow[form_id]"]' ).value
		).toBe( '4' );
		expect(
			container.querySelector( 'input[type="hidden"][name="wm_bci_workflow[auto_approved_user_ids][]"]' ).value
		).toBe( '18' );

		fireEvent.change( screen.getByLabelText( 'Form ID' ), {
			target: { value: '9' },
		} );

		expect(
			container.querySelector( 'input[type="hidden"][name="wm_bci_workflow[form_id]"]' ).value
		).toBe( '9' );
	} );

	it( 'filters token selections to known users only', () => {
		expect(
			filterValidUserLabels(
				[
					'Avery Smith (asmith, avery@example.com)',
					'Unknown User',
					'Avery Smith (asmith, avery@example.com)',
				],
				boot.users
			)
		).toEqual( [ 'Avery Smith (asmith, avery@example.com)' ] );

		expect(
			selectedUserIdsFromLabels(
				[ 'Casey Jones (cjones, casey@example.com)' ],
				boot.users
			)
		).toEqual( [ '18' ] );
	} );

	it( 'serializes calendar colors and hidden descriptors correctly', () => {
		expect(
			serializeCalendarColors( {
				Event: '#004966',
				Resources: '',
			} )
		).toEqual( {
			Event: '#004966',
		} );

		expect(
			buildHiddenInputDescriptors(
				{
					formId: '4',
					approvalFieldId: '22',
					notificationName: 'Admin Notification',
					approvalNotificationRecipients: '',
					autoApprovedUserLabels: [ 'Casey Jones (cjones, casey@example.com)' ],
					calendarPageSlug: 'bci-resources',
					calendarFeedName: 'BCI Community Opportunity Submission',
					googleSyncUrl: '',
					googleSyncSecret: '',
					calendarEventColors: { Event: '#004966', Resources: '' },
					fieldMap: { opportunity_type: '1', title: '4' },
				},
				boot
			)
		).toEqual(
			expect.arrayContaining( [
				expect.objectContaining( {
					name: 'wm_bci_workflow[auto_approved_user_ids][]',
					value: '18',
				} ),
				expect.objectContaining( {
					name: 'wm_bci_workflow[calendar_event_colors][Event]',
					value: '#004966',
				} ),
				expect.objectContaining( {
					name: 'wm_bci_workflow[field_map][title]',
					value: '4',
				} ),
			] )
		);
	} );
} );
