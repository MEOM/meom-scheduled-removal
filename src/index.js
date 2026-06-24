/* eslint camelcase: "off" */

import { __ } from '@wordpress/i18n';
import { registerPlugin } from '@wordpress/plugins';
import {
    PluginDocumentSettingPanel,
    store as editorStore,
} from '@wordpress/editor';
import { DateTimePicker, Button } from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { useEntityProp } from '@wordpress/core-data';
import { dateI18n } from '@wordpress/date';

const META_KEY = 'meom_scheduled_removal';

/**
 * Document sidebar panel exposing the scheduled-removal date/time.
 *
 * @return {JSX.Element|null} The sidebar panel or null if not supported.
 */
function MeomScheduledRemovalPanel() {
    const { postType, postId } = useSelect( ( select ) => {
        return {
            postType: select( editorStore ).getCurrentPostType(),
            postId: select( editorStore ).getCurrentPostId(),
        };
    }, [] );

    const [ meta, setMeta ] = useEntityProp(
        'postType',
        postType,
        'meta',
        postId
    );

    // Only render on supported post types (passed from PHP).
    const allowed = window.meomScheduledRemoval?.postTypes || [];
    if ( ! allowed.includes( postType ) ) {
        return null;
    }

    const value = meta?.[ META_KEY ] || '';

    return (
        <PluginDocumentSettingPanel
            name="meom-scheduled-removal"
            title={ __( 'Scheduled removal', 'meom-scheduled-removal' ) }
        >
            <p>
                { value
                    ? __( 'Will be set to draft:', 'meom-scheduled-removal' ) +
                      ' ' +
                      dateI18n( 'j.n.Y H:i', value )
                    : __( 'No removal scheduled.', 'meom-scheduled-removal' ) }
            </p>

            <DateTimePicker
                currentDate={ value || null }
                onChange={ ( newDate ) =>
                    setMeta( { ...meta, [ META_KEY ]: newDate } )
                }
                is12Hour={ false }
                startOfWeek={ 1 }
            />

            <div style={ { marginTop: '1rem' } } />

            <Button
                variant="secondary"
                onClick={ () => setMeta( { ...meta, [ META_KEY ]: '' } ) }
                disabled={ ! value }
            >
                { __( 'Clear', 'meom-scheduled-removal' ) }
            </Button>
        </PluginDocumentSettingPanel>
    );
}

registerPlugin( 'meom-scheduled-removal', {
    render: MeomScheduledRemovalPanel,
} );
