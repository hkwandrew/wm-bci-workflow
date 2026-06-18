import {
	BaseControl,
	Button,
	Card,
	CardBody,
	CardHeader,
	ColorPalette,
	FormTokenField,
	Notice,
	Panel,
	PanelBody,
	TextControl,
	TextareaControl,
} from '@wordpress/components';
import { Fragment, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

export function createInitialState( boot ) {
	const values = boot.values || {};

	return {
		formId: values.formId || '',
		approvalFieldId: values.approvalFieldId || '',
		notificationName: values.notificationName || '',
		approvalNotificationRecipients: values.approvalNotificationRecipients || '',
		autoApprovedUserLabels: getInitialSelectedUserLabels( boot.users || [], values.autoApprovedUserIds || [] ),
		calendarPageSlug: values.calendarPageSlug || '',
		calendarFeedName: values.calendarFeedName || '',
		googleSyncUrl: values.googleSyncUrl || '',
		googleSyncSecret: values.googleSyncSecret || '',
		calendarEventColors: { ...( values.calendarEventColors || {} ) },
		fieldMap: { ...( values.fieldMap || {} ) },
	};
}

export function getInitialSelectedUserLabels( users, ids ) {
	const labelsById = new Map(
		users.map( ( user ) => [ Number( user.id ), user.label ] )
	);

	return ids
		.map( ( id ) => labelsById.get( Number( id ) ) )
		.filter( Boolean );
}

export function filterValidUserLabels( labels, users ) {
	const validLabels = new Set( users.map( ( user ) => user.label ) );
	const seen = new Set();

	return labels.filter( ( label ) => {
		if ( ! validLabels.has( label ) || seen.has( label ) ) {
			return false;
		}

		seen.add( label );
		return true;
	} );
}

export function selectedUserIdsFromLabels( labels, users ) {
	const idsByLabel = new Map(
		users.map( ( user ) => [ user.label, String( user.id ) ] )
	);

	return labels
		.map( ( label ) => idsByLabel.get( label ) )
		.filter( Boolean );
}

export function serializeCalendarColors( colors ) {
	return Object.entries( colors ).reduce( ( output, [ type, color ] ) => {
		if ( color ) {
			output[ type ] = color;
		}

		return output;
	}, {} );
}

export function buildHiddenInputDescriptors( state, boot ) {
	const optionName = boot.optionName;
	const fieldMapFields = boot.fieldMapFields || [];
	const descriptors = arrayDescriptors(
		[
			{ name: scalarName( optionName, 'form_id' ), value: state.formId || '' },
			{ name: scalarName( optionName, 'approval_field_id' ), value: state.approvalFieldId || '' },
			{ name: scalarName( optionName, 'notification_name' ), value: state.notificationName || '' },
			{ name: scalarName( optionName, 'approval_notification_recipients' ), value: state.approvalNotificationRecipients || '' },
			{ name: scalarName( optionName, 'auto_approved_user_ids_present' ), value: '1' },
			{ name: scalarName( optionName, 'calendar_page_slug' ), value: state.calendarPageSlug || '' },
			{ name: scalarName( optionName, 'calendar_feed_name' ), value: state.calendarFeedName || '' },
			{ name: scalarName( optionName, 'google_sync_url' ), value: state.googleSyncUrl || '' },
			{ name: scalarName( optionName, 'google_sync_secret' ), value: state.googleSyncSecret || '' },
			{ name: scalarName( optionName, 'calendar_event_colors_present' ), value: '1' },
		]
	);

	selectedUserIdsFromLabels( state.autoApprovedUserLabels, boot.users || [] ).forEach( ( userId ) => {
		descriptors.push( {
			name: `${ scalarName( optionName, 'auto_approved_user_ids' ) }[]`,
			value: userId,
		} );
	} );

	Object.entries( serializeCalendarColors( state.calendarEventColors ) ).forEach( ( [ type, color ] ) => {
		descriptors.push( {
			name: `${ scalarName( optionName, 'calendar_event_colors' ) }[${ type }]`,
			value: color,
		} );
	} );

	fieldMapFields.forEach( ( field ) => {
		descriptors.push( {
			name: `${ scalarName( optionName, 'field_map' ) }[${ field.key }]`,
			value: state.fieldMap[ field.key ] || '',
		} );
	} );

	return descriptors;
}

function scalarName( optionName, key ) {
	return `${ optionName }[${ key }]`;
}

function arrayDescriptors( descriptors ) {
	return descriptors.filter( ( descriptor ) => undefined !== descriptor.value && null !== descriptor.value );
}

function HiddenInputs( { descriptors } ) {
	return (
		<div className="wm-bci-settings-app__hidden-inputs" aria-hidden="true">
			{ descriptors.map( ( descriptor, index ) => (
				<input
					key={ `${ descriptor.name }-${ index }` }
					type="hidden"
					name={ descriptor.name }
					value={ descriptor.value }
				/>
			) ) }
		</div>
	);
}

function SectionCard( { title, description, children } ) {
	return (
		<Card className="wm-bci-settings-app__card" size="medium">
			<CardHeader>
				<div className="wm-bci-settings-app__section-header">
					<h2>{ title }</h2>
					{ description ? <p>{ description }</p> : null }
				</div>
			</CardHeader>
			<CardBody>{ children }</CardBody>
		</Card>
	);
}

function ColorRow( { choice, palette, value, onChange } ) {
	return (
		<div className="wm-bci-settings-app__color-row">
			<div className="wm-bci-settings-app__color-copy">
				<strong>{ choice.label }</strong>
				{ choice.label !== choice.value ? (
					<span>{ choice.value }</span>
				) : null }
			</div>
			<div className="wm-bci-settings-app__color-picker">
				<ColorPalette
					colors={ palette }
					value={ value || undefined }
					onChange={ ( nextColor ) => onChange( nextColor || '' ) }
					disableCustomColors
					clearable={ false }
				/>
				<Button
					variant="tertiary"
					onClick={ () => onChange( '' ) }
				>
					{ __( 'Use default', 'wm-bci-workflow' ) }
				</Button>
			</div>
		</div>
	);
}

export function SettingsApp( { boot } ) {
	const [ state, setState ] = useState( () => createInitialState( boot ) );
	const hiddenInputs = buildHiddenInputDescriptors( state, boot );
	const users = boot.users || [];
	const opportunityTypeChoices = boot.opportunityTypeChoices || [];

	const setValue = ( key, value ) => {
		setState( ( current ) => ( {
			...current,
			[ key ]: value,
		} ) );
	};

	const setFieldMapValue = ( key, value ) => {
		setState( ( current ) => ( {
			...current,
			fieldMap: {
				...current.fieldMap,
				[ key ]: value,
			},
		} ) );
	};

	const setCalendarColor = ( choiceValue, nextColor ) => {
		setState( ( current ) => ( {
			...current,
			calendarEventColors: {
				...current.calendarEventColors,
				[ choiceValue ]: nextColor,
			},
		} ) );
	};

	return (
		<div className="wm-bci-settings-app">
			<HiddenInputs descriptors={ hiddenInputs } />

			<div className="wm-bci-settings-app__grid">
				<SectionCard
					title={ __( 'Form Configuration', 'wm-bci-workflow' ) }
					description={ __( 'Manage the approval, publishing, and sync settings that power the BCI community opportunities workflow.', 'wm-bci-workflow' ) }
				>
					<div className="wm-bci-settings-app__group">
						<h3>{ __( 'Workflow Setup', 'wm-bci-workflow' ) }</h3>
						<TextControl
							label={ __( 'Form ID', 'wm-bci-workflow' ) }
							type="number"
							value={ state.formId }
							__next40pxDefaultSize
							__nextHasNoMarginBottom
							onChange={ ( value ) => setValue( 'formId', value ) }
						/>
						<TextControl
							label={ __( 'Approval Field ID', 'wm-bci-workflow' ) }
							value={ state.approvalFieldId }
							__next40pxDefaultSize
							__nextHasNoMarginBottom
							onChange={ ( value ) => setValue( 'approvalFieldId', value ) }
						/>
						<TextControl
							label={ __( 'Notification Name', 'wm-bci-workflow' ) }
							value={ state.notificationName }
							__next40pxDefaultSize
							__nextHasNoMarginBottom
							onChange={ ( value ) => setValue( 'notificationName', value ) }
						/>
					</div>

					<div className="wm-bci-settings-app__group">
						<h3>{ __( 'Approvals', 'wm-bci-workflow' ) }</h3>
						<TextareaControl
							label={ __( 'Approval Notification Recipients', 'wm-bci-workflow' ) }
							help={ __( 'Enter one or more email addresses separated by commas or new lines. When provided, this list overrides the Gravity Forms Send To setting for the approval notification. Leave blank to use the Gravity Forms setting.', 'wm-bci-workflow' ) }
							value={ state.approvalNotificationRecipients }
							__nextHasNoMarginBottom
							onChange={ ( value ) => setValue( 'approvalNotificationRecipients', value ) }
						/>

						<BaseControl
							label={ __( 'Auto-Approved Submitters', 'wm-bci-workflow' ) }
							help={ __( 'Select logged-in WordPress users whose future BCI submissions should be approved automatically. This only applies when Gravity Forms saves the entry created_by user.', 'wm-bci-workflow' ) }
							__nextHasNoMarginBottom
						>
							{ users.length > 0 ? (
								<FormTokenField
									value={ state.autoApprovedUserLabels }
									suggestions={ users.map( ( user ) => user.label ) }
									__next40pxDefaultSize
									__nextHasNoMarginBottom
									onChange={ ( tokens ) => {
										setValue(
											'autoApprovedUserLabels',
											filterValidUserLabels( tokens, users )
										);
									} }
								/>
							) : (
								<Notice status="info" isDismissible={ false }>
									{ __( 'No WordPress users are available to allowlist.', 'wm-bci-workflow' ) }
								</Notice>
							) }
						</BaseControl>
					</div>

					<div className="wm-bci-settings-app__group">
						<h3>{ __( 'Publishing', 'wm-bci-workflow' ) }</h3>
						<TextControl
							label={ __( 'Calendar Page Slug', 'wm-bci-workflow' ) }
							value={ state.calendarPageSlug }
							__next40pxDefaultSize
							__nextHasNoMarginBottom
							onChange={ ( value ) => setValue( 'calendarPageSlug', value ) }
						/>
						<TextControl
							label={ __( 'Calendar Feed Name', 'wm-bci-workflow' ) }
							value={ state.calendarFeedName }
							__next40pxDefaultSize
							__nextHasNoMarginBottom
							onChange={ ( value ) => setValue( 'calendarFeedName', value ) }
						/>
					</div>
				</SectionCard>

				<SectionCard
					title={ __( 'Calendar Event Colors', 'wm-bci-workflow' ) }
					description={ __( 'Choose event colors for each opportunity type. Leave a row unselected to use the default GravityCalendar feed color.', 'wm-bci-workflow' ) }
				>
					{ opportunityTypeChoices.length > 0 ? (
						<div className="wm-bci-settings-app__color-rows">
							{ opportunityTypeChoices.map( ( choice ) => (
								<ColorRow
									key={ choice.value }
									choice={ choice }
									palette={ boot.calendarPalette || [] }
									value={ state.calendarEventColors[ choice.value ] || '' }
									onChange={ ( nextColor ) => setCalendarColor( choice.value, nextColor ) }
								/>
							) ) }
						</div>
					) : (
						<Notice status="warning" isDismissible={ false }>
							{ __( 'Opportunity type choices could not be loaded from Gravity Forms. Verify the Form ID and opportunity_type field mapping; existing saved colors will be preserved until this field can be loaded again.', 'wm-bci-workflow' ) }
						</Notice>
					) }
				</SectionCard>

				<SectionCard
					title={ __( 'Google Sheets Sync', 'wm-bci-workflow' ) }
					description={ __( 'Configure the Google Apps Script sync endpoint. These can also be set as constants in wp-config.php.', 'wm-bci-workflow' ) }
				>
					<TextControl
						label={ __( 'Sync Endpoint URL', 'wm-bci-workflow' ) }
						type="url"
						value={ state.googleSyncUrl }
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						onChange={ ( value ) => setValue( 'googleSyncUrl', value ) }
					/>
					<TextControl
						label={ __( 'Shared Secret', 'wm-bci-workflow' ) }
						type="password"
						help={
							boot.values?.hasGoogleSyncSecret
								? __( 'A secret is configured. Leave blank to keep the current value.', 'wm-bci-workflow' )
								: undefined
						}
						value={ state.googleSyncSecret }
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						onChange={ ( value ) => setValue( 'googleSyncSecret', value ) }
					/>
				</SectionCard>

				<Panel className="wm-bci-settings-app__panel">
					<PanelBody
						title={ __( 'Field Mapping', 'wm-bci-workflow' ) }
						initialOpen={ false }
					>
						<p className="wm-bci-settings-app__panel-copy">
							{ __( 'Only change these IDs when the underlying Gravity Forms form changes.', 'wm-bci-workflow' ) }
						</p>
						<div className="wm-bci-settings-app__mapping-grid">
							{ ( boot.fieldMapFields || [] ).map( ( field ) => (
								<TextControl
									key={ field.key }
									label={ field.label }
									value={ state.fieldMap[ field.key ] || '' }
									__next40pxDefaultSize
									__nextHasNoMarginBottom
									onChange={ ( value ) => setFieldMapValue( field.key, value ) }
								/>
							) ) }
						</div>
					</PanelBody>
				</Panel>
			</div>

			<div className="wm-bci-settings-app__actions">
				<Button variant="primary" type="submit">
					{ __( 'Save Changes', 'wm-bci-workflow' ) }
				</Button>
			</div>
		</div>
	);
}
