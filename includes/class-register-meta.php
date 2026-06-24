<?php
/**
 * Register the scheduled-removal post meta.
 *
 * @package Meom\Scheduled_Removal
 */

namespace Meom\Scheduled_Removal;

/**
 * Registers the removal date meta on all supported post types.
 */
class Register_Meta {

    /**
     * Hook meta registration into init.
     */
    public function __construct() {
        add_action( 'init', array( $this, 'register' ), 20 );
    }

    /**
     * Register the removal date meta for each supported post type.
     *
     * @return void
     */
    public function register(): void {
        foreach ( get_supported_post_types() as $post_type ) {
            register_post_meta(
                $post_type,
                META_KEY,
                array(
                    'type'              => 'string',
                    'description'       => __( 'Date and time at which the post is automatically set to draft.', 'meom-scheduled-removal' ),
                    'single'            => true,
                    'show_in_rest'      => true,
                    'sanitize_callback' => __NAMESPACE__ . '\\sanitize_removal_date',
                    'auth_callback'     => function ( $allowed, $meta_key, $post_id ) {
                        return current_user_can( 'edit_post', $post_id );
                    },
                )
            );
        }
    }
}
